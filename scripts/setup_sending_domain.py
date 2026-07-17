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


def nc_call(command, extra=None):
    """Namecheap XML API. Returns the raw XML."""
    params = {
        "ApiUser": NC_USER, "ApiKey": NC_KEY, "UserName": NC_USER,
        "Command": command, "ClientIp": public_ip(),
    }
    params.update(extra or {})
    url = "https://api.namecheap.com/xml.response?" + urllib.parse.urlencode(params)
    with urllib.request.urlopen(url, timeout=45) as r:
        x = r.read().decode()
    status = re.search(r'ApiResponse Status="(\w+)"', x)
    if not status or status.group(1) != "OK":
        errs = re.findall(r'<Error Number="(\d+)">([^<]+)</Error>', x)
        die(f"Namecheap {command} failed: {errs or x[:400]}")
    return x


def read_zone():
    """Live host records + EmailType. EmailType=FWD is load-bearing: it generates the MX+SPF."""
    x = nc_call("namecheap.domains.dns.getHosts", {"SLD": SLD, "TLD": TLD})
    res = re.search(r"<DomainDNSGetHostsResult([^>]*)>", x)
    attrs = dict(re.findall(r'(\w+)="([^"]*)"', res.group(1))) if res else {}
    hosts = []
    for row in re.findall(r"<host\s([^/]+)/>", x, re.I):
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
    nc_call("namecheap.domains.dns.setHosts", extra)


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
    print(f"   EmailType={email_type}  (FWD is what generates the eforward MX + SPF — must be resent)")
    for h in hosts:
        print(f"     {h['Type']:6} {h['Name']:24} -> {h['Address'][:50]}")
    before = {key(h) for h in hosts}

    print("\n== 3. Merge (existing records kept verbatim)")
    merged = list(hosts)
    for r in records:
        # Resend gives FQDNs; Namecheap wants the name relative to the apex.
        name = r["name"]
        if name.endswith("." + SLD + "." + TLD):
            name = name[: -(len(SLD) + len(TLD) + 2)]
        elif name == f"{SLD}.{TLD}":
            name = "@"
        new = {"Name": name, "Type": r["type"].upper(),
               "Address": r["value"], "MXPref": str(r.get("priority") or 10), "TTL": "1799"}
        if key(new) in before:
            print(f"   = already present: {new['Type']} {new['Name']}")
            continue
        merged.append(new)
        print(f"   + ADD {new['Type']:5} {new['Name']:30} -> {new['Address'][:50]}")

    if not APPLY:
        print(f"\nDRY RUN — nothing written. {len(hosts)} existing + "
              f"{len(merged)-len(hosts)} new = {len(merged)} records.")
        print("Re-run with --apply to write.")
        return

    print("\n== 4. Namecheap: setHosts (replaces the whole zone — resending everything)")
    write_zone(merged, email_type)
    time.sleep(3)
    after_hosts, after_type = read_zone()
    after = {key(h) for h in after_hosts}
    lost = before - after
    if lost:
        die(f"records LOST by setHosts: {lost} — restore immediately")
    if after_type != email_type:
        die(f"EmailType changed {email_type} -> {after_type}; email forwarding may be broken")
    print(f"   OK: {len(after_hosts)} records, EmailType={after_type}, 0 pre-existing records lost")

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
