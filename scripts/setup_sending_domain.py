"""
One-shot setup of the RuinMyTrip transactional sending domain (send.ruinmytrip.com).

    python scripts/setup_sending_domain.py <RESEND_FULL_ACCESS_KEY> [--apply]

Without --apply it is a DRY RUN: it creates/reads the Resend domain, prints the exact DNS
change, and touches nothing at Namecheap.

WHY A SUBDOMAIN: sending from send.ruinmytrip.com keeps the apex zone's email untouched.
ruinmytrip.com currently has EmailType=FWD, which is what makes Namecheap auto-generate the
eforward1-5 MX records and the SPF TXT. Those records are NOT in the host list — they exist
because of the EmailType flag. namecheap.domains.dns.setHosts REPLACES THE ENTIRE HOST LIST,
so this script always:
  1. reads the live zone (getHosts) and keeps every existing record verbatim,
  2. re-sends EmailType=FWD, or the forwarding MX + SPF vanish silently,
  3. reads the zone back and fails loudly if anything that existed before is gone.

Steps: create Resend domain -> read required DNS records -> merge into the live Namecheap zone
-> trigger verification -> poll -> flip MAIL_FROM on Render -> send a live probe to a
non-owner address to prove real users can actually be reached.
"""
import re
import sys
import json
import time
import urllib.parse
import urllib.request

NC_USER = "Nimadamus"
NC_KEY = "1af77f072ced4bbda7c765938fd174f1"
SLD, TLD = "ruinmytrip", "com"
SUBDOMAIN = "send.ruinmytrip.com"

RENDER_KEY = "rnd_RTlUN4ico5DAlDkjzKLfVslAcp2f"
RMT_SERVICE = "srv-d9co4n0k1i2s73cg0nfg"

APPLY = "--apply" in sys.argv
RESEND_KEY = next((a for a in sys.argv[1:] if a.startswith("re_")), None)


def die(msg):
    print(f"\nABORT: {msg}")
    sys.exit(1)


def http(url, method="GET", data=None, headers=None):
    req = urllib.request.Request(url, method=method, headers=headers or {})
    # Resend sits behind Cloudflare, which blocks the default Python-urllib user agent with
    # "error code: 1010" — an HTML edge error that looks nothing like an API response and is
    # easy to misread as a Resend failure. Always send a real UA.
    req.add_header("User-Agent", "ruinmytrip-setup/1.0")
    req.add_header("Accept", "application/json")
    body = None
    if data is not None:
        body = json.dumps(data).encode()
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, body, timeout=30) as r:
            raw = r.read().decode()
            return r.status, (json.loads(raw) if raw.strip().startswith(("{", "[")) else raw)
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        return e.code, (json.loads(raw) if raw.strip().startswith(("{", "[")) else raw)


def public_ip():
    with urllib.request.urlopen("https://api.ipify.org", timeout=15) as r:
        return r.read().decode().strip()


def nc_call(command, extra=None, method="GET"):
    """
    Namecheap XML API. POST is used for setHosts because the DKIM public key is ~218 chars and a
    long GET query string silently drops records (Namecheap returns Status=OK but writes fewer
    records than sent — observed Jul 17 2026).
    """
    params = {
        "ApiUser": NC_USER, "ApiKey": NC_KEY, "UserName": NC_USER,
        "Command": command, "ClientIp": public_ip(),
    }
    params.update(extra or {})
    if method == "POST":
        req = urllib.request.Request("https://api.namecheap.com/xml.response",
            data=urllib.parse.urlencode(params).encode(), method="POST",
            headers={"Content-Type": "application/x-www-form-urlencoded"})
        x = urllib.request.urlopen(req, timeout=45).read().decode()
    else:
        url = "https://api.namecheap.com/xml.response?" + urllib.parse.urlencode(params)
        x = urllib.request.urlopen(url, timeout=45).read().decode()
    status = re.search(r'ApiResponse Status="(\w+)"', x)
    if not status or status.group(1) != "OK":
        errs = re.findall(r'<Error Number="(\d+)">([^<]+)</Error>', x)
        die(f"Namecheap {command} failed: {errs or x[:400]}")
    return x


def read_zone():
    """Live host records + EmailType. NOTE the parser: a naive [^/]+ regex breaks on the '/' in a
    DKIM base64 value and makes present records look missing — use a >-terminated match."""
    x = nc_call("namecheap.domains.dns.getHosts", {"SLD": SLD, "TLD": TLD})
    res = re.search(r"<DomainDNSGetHostsResult([^>]*)>", x)
    attrs = dict(re.findall(r'(\w+)="([^"]*)"', res.group(1))) if res else {}
    hosts = []
    for row in re.findall(r"<host\b([^>]*?)/>", x, re.I):
        d = dict(re.findall(r'(\w+)="([^"]*)"', row))
        hosts.append({
            "Name": d.get("Name", "@"), "Type": d.get("Type", "A"),
            "Address": d.get("Address", ""), "MXPref": d.get("MXPref", "10"),
            "TTL": d.get("TTL", "1799"),
        })
    return hosts, attrs.get("EmailType", "FWD")


def write_zone(hosts, email_type):
    extra = {"SLD": SLD, "TLD": TLD, "EmailType": email_type}
    for i, h in enumerate(hosts, start=1):
        extra[f"HostName{i}"] = h["Name"]
        extra[f"RecordType{i}"] = h["Type"]
        extra[f"Address{i}"] = h["Address"]
        extra[f"TTL{i}"] = h["TTL"]
        if h["Type"] == "MX":
            extra[f"MXPref{i}"] = h["MXPref"]
    nc_call("namecheap.domains.dns.setHosts", extra, method="POST")


def key(h):
    return (h["Type"].upper(), h["Name"].lower(), h["Address"].rstrip(".").lower())


def main():
    if not RESEND_KEY:
        die("pass a Resend FULL-ACCESS api key (re_...) as the first argument.\n"
            "  The key stored for TrustMyRecord is send-only and returns 401 restricted_api_key\n"
            "  on /domains, so it cannot create a sending domain.")

    rh = {"Authorization": f"Bearer {RESEND_KEY}"}

    print("== 1. Resend: find or create", SUBDOMAIN)
    code, doms = http("https://api.resend.com/domains", headers=rh)
    if code == 401:
        die(f"that Resend key is not full-access: {doms}")
    existing = None
    if isinstance(doms, dict):
        for d in doms.get("data", []) or []:
            if d.get("name") == SUBDOMAIN:
                existing = d
    if existing:
        dom_id = existing["id"]
        print(f"   already exists: {dom_id} status={existing.get('status')}")
    else:
        code, created = http("https://api.resend.com/domains", "POST",
                             {"name": SUBDOMAIN, "region": "us-east-1"}, rh)
        if code >= 300:
            die(f"create failed: {created}")
        dom_id = created["id"]
        print(f"   created: {dom_id}")

    code, dom = http(f"https://api.resend.com/domains/{dom_id}", headers=rh)
    if code >= 300:
        die(f"read failed: {dom}")
    records = dom.get("records", [])
    print(f"   Resend requires {len(records)} DNS record(s):")
    for r in records:
        print(f"     {r['type']:5} {r['name']:34} {r['value'][:56]}")

    print("\n== 2. Namecheap: read the live zone")
    hosts, email_type = read_zone()
    print(f"   EmailType={email_type}")
    for h in hosts:
        print(f"     {h['Type']:6} {h['Name']:24} -> {h['Address'][:50]}")

    # Resend needs an MX (send.<domain> Return-Path). Namecheap DROPS custom MX while
    # EmailType=FWD, so we must switch to EmailType=MX. Under FWD the eforward1-5 MX and the
    # forwarding SPF are AUTO-GENERATED and absent from the host list — switching to MX would
    # lose them, so we re-author them explicitly. (Checked first: if no forwarding rules are
    # configured they are cosmetic, but we preserve them regardless so forwarding still works.)
    EFORWARD = [
        ("@", "MX", "eforward1.registrar-servers.com.", "10"),
        ("@", "MX", "eforward2.registrar-servers.com.", "10"),
        ("@", "MX", "eforward3.registrar-servers.com.", "10"),
        ("@", "MX", "eforward4.registrar-servers.com.", "15"),
        ("@", "MX", "eforward5.registrar-servers.com.", "20"),
        ("@", "TXT", "v=spf1 include:spf.efwd.registrar-servers.com ~all", None),
    ]

    print("\n== 3. Build target zone (EmailType=MX, forwarding records preserved)")
    merged = []
    seen = set()

    def add(name, typ, addr, pref):
        k = (typ.upper(), name.lower(), addr.rstrip(".").lower())
        if k in seen:
            return
        seen.add(k)
        merged.append({"Name": name, "Type": typ.upper(), "Address": addr,
                       "MXPref": pref or "10", "TTL": "1799"})

    for h in hosts:                                   # keep every existing non-MX record (A, CNAME, …)
        if h["Type"].upper() != "MX":
            add(h["Name"], h["Type"], h["Address"], h["MXPref"])
    for name, typ, addr, pref in EFORWARD:            # forwarding MX + SPF, explicit
        add(name, typ, addr, pref)
    for r in records:                                 # Resend records (names already apex-relative)
        add(r["name"], r["type"], r["value"], str(r.get("priority") or 10))

    for h in merged:
        print(f"   {h['Type']:5} {h['Name']:24} -> {h['Address'][:50]}")

    if not APPLY:
        print(f"\nDRY RUN — nothing written ({len(merged)} records). Re-run with --apply.")
        return

    print("\n== 4. Namecheap: setHosts (POST) with EmailType=MX")
    write_zone(merged, "MX")
    time.sleep(3)
    after_hosts, after_type = read_zone()
    after = {key(h) for h in after_hosts}
    # Per-record check — every intended record must actually be present (the earlier failure mode
    # was setHosts returning OK while silently dropping the MX and the long DKIM TXT).
    missing = [h for h in merged if key(h) not in after]
    if missing:
        die("records MISSING after write: " + ", ".join(f"{h['Type']} {h['Name']}" for h in missing))
    print(f"   OK: all {len(merged)} records present, EmailType={after_type}")

    print("\n== 5. Resend: trigger verification")
    http(f"https://api.resend.com/domains/{dom_id}/verify", "POST", None, rh)
    for i in range(1, 21):
        time.sleep(15)
        _, d = http(f"https://api.resend.com/domains/{dom_id}", headers=rh)
        st = d.get("status")
        print(f"   poll {i}: {st}")
        if st == "verified":
            break
    else:
        print("   not verified yet — DNS can take up to an hour. Re-run with --apply to re-poll.")
        return

    print("\n== 6. Render: point MAIL_FROM at the verified domain")
    code, _ = http(f"https://api.render.com/v1/services/{RMT_SERVICE}/env-vars/MAIL_FROM", "PUT",
                   {"value": f"RuinMyTrip <noreply@{SUBDOMAIN}>"},
                   {"Authorization": f"Bearer {RENDER_KEY}"})
    print(f"   MAIL_FROM set (HTTP {code}) — redeploy for the container to pick it up")

    print("\n== 7. Prove a NON-OWNER address can now be reached")
    code, res = http("https://api.resend.com/emails", "POST", {
        "from": f"RuinMyTrip <noreply@{SUBDOMAIN}>",
        "to": ["nj2121+realuserprobe@gmail.com"],
        "subject": "RuinMyTrip sending-domain probe",
        "html": "<p>If this arrived, verification email now reaches real users.</p>",
    }, rh)
    print(f"   HTTP {code}: {res}")
    print("\nDONE." if code < 300 else "\nSend still failing — see above.")


if __name__ == "__main__":
    main()
