#!/usr/bin/env python3
"""Re-check every sourced claim in database/editorial/places.json against its cited page.

Why this exists
---------------
The slow part of publishing editorial place content is not writing it, it is proving the numbers.
Doing that by hand costs a quarter of an hour per attraction and, worse, it decays silently: a
museum raises its admission in the spring and the page keeps quoting last year's price with the
word "verified" next to it.

So every entry in `facts_checked` carries an `assert_text`: the exact string that must still appear
on the cited page. This script fetches each source once and looks for it. That turns fact-checking
from a manual reading task into a re-runnable check, and it is the same check whether there are two
attractions or two hundred.

What a PASS means precisely: the cited page still contains the asserted string. It does NOT mean a
human agreed the fact follows from the page. That judgement happens once, when the entry is
written; this script defends it from drift afterwards.

Matching is deliberately forgiving about presentation and strict about content: HTML tags are
stripped, entities unescaped, whitespace collapsed, non-breaking spaces normalised, and comparison
is case-insensitive. It will not paper over a changed number.

Usage
-----
    python scripts/verify_place_sources.py                      # check everything
    python scripts/verify_place_sources.py --slug rome-italy    # one destination
    python scripts/verify_place_sources.py --json report.json   # machine-readable output

Exit code is 1 if any assertion fails or any source is unreachable, so it can gate a publish.
"""
from __future__ import annotations

import argparse
import datetime
import hashlib
import html
import http.cookiejar
import json
import os
import re
import ssl
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor

# Same reason as probe_place_sources.py: a cp1252 stdout cannot encode an asserted string from a
# Japanese or Greek source, and the resulting UnicodeEncodeError would take down a whole run.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLACES_JSON = os.path.join(HERE, "database", "editorial", "places.json")

# Hosts a human has looked at and decided to keep citing even though a plain fetch can no longer
# reach them. Curated by hand, committed, and read only by this script.
#
# Deliberately NOT under database/editorial/: publish_editorial.php globs that directory and expects
# every file in it to be editorial content, so a config file there fails the publish outright.
BLOCKED_SOURCES_JSON = os.path.join(HERE, "database", "blocked_sources.json")

# Generated: when each source last verified successfully. Never edited by hand.
SOURCE_HEALTH_JSON = os.path.join(HERE, ".cache", "source_health.json")

UA = "RuinMyTrip/1.0 (+https://ruinmytrip.com; editorial fact verification)"
TIMEOUT = 30

_SCRIPT_STYLE = re.compile(r"<(script|style)\b.*?</\1>", re.I | re.S)
_TAG = re.compile(r"<[^>]+>")
_WS = re.compile(r"\s+")


def normalise(text: str) -> str:
    """Strip markup and flatten presentation so a match survives reformatting but not a rewrite."""
    text = _SCRIPT_STYLE.sub(" ", text)
    text = _TAG.sub(" ", text)
    text = html.unescape(text)
    # Prices and hours are riddled with non-breaking and thin spaces; treat them as ordinary ones.
    text = text.replace(" ", " ").replace(" ", " ").replace(" ", " ")
    return _WS.sub(" ", text).strip().lower()


_META_CHARSET = re.compile(rb"""charset=["']?\s*([a-zA-Z0-9_\-]+)""", re.I)


def decode(raw: bytes, header_charset: str | None) -> str:
    """Decode a page, getting the currency symbols right.

    This matters more than it looks. Half these sites serve windows-1252, where the euro sign is
    byte 0x80. Decoding that as latin-1 (the obvious fallback) silently turns EUR into a control
    character, so an assert_text containing a price could never match and every price assertion
    would fail for a reason that has nothing to do with the price being wrong. Order: the charset
    the server declares, then the one the document declares, then utf-8, then cp1252 (a superset of
    latin-1 for the printable range, so it is the safer of the two to guess).
    """
    candidates = [c for c in (header_charset, None) if c]
    m = _META_CHARSET.search(raw[:4096])
    if m:
        try:
            candidates.append(m.group(1).decode("ascii"))
        except UnicodeDecodeError:
            pass
    candidates += ["utf-8", "cp1252"]

    seen = set()
    for enc in candidates:
        enc = enc.lower().strip()
        if enc in seen:
            continue
        seen.add(enc)
        try:
            return raw.decode(enc)
        except (UnicodeDecodeError, LookupError):
            continue
    return raw.decode("utf-8", "replace")


# Retried once with a pause. Several of these sites serve a page happily and then 403 the next
# request from the same address seconds later; that is rate limiting, not a missing page, and
# without a retry the verifier fails a publish for a reason that has nothing to do with the facts.
# Kept deliberately small: this is a courtesy retry, not an attempt to defeat a block.
RETRIES = 2
RETRY_PAUSE = 4.0

# Retries for a host that has actually asked us to slow down.
#
# Two attempts is right for a transient blip. It is not enough for a host that grants roughly one
# request per long window: royalcollection.org.uk verified fine when its page was checked alone, and
# failed in a full sweep, because the sibling page citing the same host had just spent the
# allowance. The batch, not the source, decided whether the fact could be checked. So a host that
# has signalled 403 or 429 gets more attempts, spaced by its widened interval.
BACKOFF_RETRIES = 4

# How long to leave a refusing host alone before the end-of-run retry pass.
RETRY_PASS_COOLDOWN = 60.0

# Requests to one host are serialised and spaced. Two places can cite the same museum, and two
# facts on one page already share a fetch, but across a batch the same domain still gets hit
# repeatedly. Firing those concurrently is what produced intermittent 403s that failed a publish
# for no content-related reason. Different hosts are still fetched in parallel, so a large batch is
# not much slower; the same host is simply never hammered.
MIN_HOST_INTERVAL = 1.5

# Some hosts want a much wider gap than that, and say so only by refusing. royalcollection.org.uk
# serves a page happily and then 403s the next request seconds later, indefinitely: it was recorded
# as a dead source and two live pages were nearly retired over it, when the site had simply set a
# rate a fixed interval could not satisfy. So the interval is per host and adaptive. A refusal
# widens that host's gap for the rest of the run, a success narrows it back gradually, and nothing
# changes for the hosts that never complain. Slowing down for a host that asks us to is the polite
# behaviour anyway; the alternative was losing the source entirely.
MAX_HOST_INTERVAL = 20.0
HOST_BACKOFF_FACTOR = 4.0

# Any real ticket or opening-hours page carries far more text than this. Anything shorter that still
# came back 200 is an interstitial, a cookie wall or a soft block.
MIN_PAGE_CHARS = 400

# A soft block does not have to be short. Some protection layers serve a full-length page whose only
# content is "turn on JavaScript" or "verify you are human". Length alone reads those as real pages,
# so the asserted string is missing and the fact is reported FAIL, which says "this price is wrong"
# about copy that is fine and sends someone off to correct an accurate number. These phrases mean we
# were not shown the page, which is BLOCKED, not FAIL.
_CHALLENGE_MARKERS = (
    "enable javascript to continue",
    "please enable javascript",
    "verify you are human",
    "are you a robot",
    "checking your browser",
    "access denied",
    "request unsuccessful",
    "attention required! | cloudflare",
)


def looks_like_challenge(text: str) -> bool:
    head = text[:4000].lower()
    return any(marker in head for marker in _CHALLENGE_MARKERS)
_host_locks: dict[str, threading.Lock] = {}
_host_last: dict[str, float] = {}
_host_interval: dict[str, float] = {}
_registry_lock = threading.Lock()


def _host_lock(host: str) -> threading.Lock:
    with _registry_lock:
        return _host_locks.setdefault(host, threading.Lock())


def _interval(host: str) -> float:
    with _registry_lock:
        return _host_interval.get(host, MIN_HOST_INTERVAL)


def _slow_down(host: str) -> None:
    with _registry_lock:
        current = _host_interval.get(host, MIN_HOST_INTERVAL)
        _host_interval[host] = min(MAX_HOST_INTERVAL, current * HOST_BACKOFF_FACTOR)


def _speed_up(host: str) -> None:
    with _registry_lock:
        current = _host_interval.get(host, MIN_HOST_INTERVAL)
        if current > MIN_HOST_INTERVAL:
            _host_interval[host] = max(MIN_HOST_INTERVAL, current / 2)


# On-disk page cache. This is the difference between a verifier that works at twenty places and one
# that works at two thousand.
#
# Without it, every run re-fetches every source, so the request load on each museum grows with the
# whole dataset and re-running the check twice in an afternoon is enough to get rate limited. That
# is not hypothetical: www.stedelijk.nl starts returning a 157-character stub after a handful of
# requests, and once it does, nothing on that host can be checked for a while.
#
# Caching successful fetches for a few hours means a re-run costs nothing, a batch only pays for
# sources it has not seen recently, and the polite request rate stays flat as the dataset grows.
# Failures are never cached: a blocked or broken source must be retried next time, or a transient
# failure would look permanent.
CACHE_DIR = os.path.join(HERE, ".cache", "place_sources")
DEFAULT_MAX_AGE = 6 * 3600

_cache_max_age = DEFAULT_MAX_AGE
_cache_enabled = True


def _cache_path(url: str) -> str:
    return os.path.join(CACHE_DIR, hashlib.sha256(url.encode("utf-8")).hexdigest() + ".html")


def _cache_read(url: str) -> str | None:
    if not _cache_enabled:
        return None
    path = _cache_path(url)
    try:
        if time.time() - os.path.getmtime(path) > _cache_max_age:
            return None
        with open(path, encoding="utf-8") as fh:
            return fh.read()
    except OSError:
        return None


def _cache_write(url: str, body: str) -> None:
    if not _cache_enabled:
        return
    try:
        os.makedirs(CACHE_DIR, exist_ok=True)
        with open(_cache_path(url), "w", encoding="utf-8") as fh:
            fh.write(body)
    except OSError:
        pass  # a cache that cannot be written is not a reason to fail a check


def fetch(url: str) -> tuple[bool, str]:
    cached = _cache_read(url)
    if cached is not None:
        return True, cached

    host = urllib.parse.urlsplit(url).netloc.lower()
    with _host_lock(host):
        gap = time.monotonic() - _host_last.get(host, 0.0)
        wait = _interval(host) - gap
        if wait > 0:
            time.sleep(wait)
        try:
            ok, body = _fetch_once(url)
        finally:
            _host_last[host] = time.monotonic()

    # Only cache a page that actually looks like a page. Caching a soft-block would freeze the
    # blocked state in for hours and hide a source that has since recovered.
    if ok and len(normalise(body)) >= MIN_PAGE_CHARS:
        _cache_write(url, body)
    return ok, body


class _PermanentRedirect(urllib.request.HTTPRedirectHandler):
    """Follow 308 Permanent Redirect.

    Python 3.10's redirect handler knows 301, 302, 303 and 307 but not 308, so a 308 surfaces as an
    HTTPError and the source reads as UNUSABLE. It is not: the site is telling us exactly where the
    page moved. Two official sources (glyptoteket.dk, perlan.is) were written off for this reason
    alone. 308 is 301 with the method preserved, and every fetch here is a GET, so the inherited
    handler does the right thing once it is wired to the code.
    """

    def http_error_308(self, req, fp, code, msg, headers):
        return self.http_error_301(req, fp, 301, msg, headers)

    https_error_308 = http_error_308


def _build_opener(cafile: str | None = None) -> urllib.request.OpenerDirector:
    handlers: list = [
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
        _PermanentRedirect(),
    ]
    if cafile:
        handlers.append(urllib.request.HTTPSHandler(context=ssl.create_default_context(cafile=cafile)))
    return urllib.request.build_opener(*handlers)


# One opener with a cookie jar, shared across the run.
#
# Some official ticketing platforms will not serve a page until a session cookie exists. Greece's
# Hellenic Heritage store is the example that forced this: every venue page 307s to
# /api/auth/ensure-token and, without somewhere to keep the cookie it sets, the redirect never
# resolves and the source looks permanently unreachable. With a jar it returns 200 and the prices
# are right there. That is a genuine official source recovered with ten lines and no browser.
_opener = _build_opener()

# Fallback trust store, built lazily.
#
# Several official sites (three Polish museums, tokyo's tnm.jp) fail with "unable to get local
# issuer certificate" against Python's default store on Windows, which does not carry the full
# Mozilla root set. certifi does. This is not a downgrade and emphatically NOT a verification
# bypass: certificates are still fully validated, against a different and stricter-maintained list
# of roots. A genuinely bad certificate still fails both ways.
_certifi_opener: urllib.request.OpenerDirector | None = None
_certifi_lock = threading.Lock()


def _certifi_fallback() -> urllib.request.OpenerDirector | None:
    global _certifi_opener
    with _certifi_lock:
        if _certifi_opener is None:
            try:
                import certifi
            except ImportError:
                return None
            _certifi_opener = _build_opener(certifi.where())
        return _certifi_opener


def _open(url: str, use_certifi: bool = False):
    opener = _certifi_fallback() if use_certifi else _opener
    if opener is None:
        raise ssl.SSLError("certifi is not installed")
    req = urllib.request.Request(
        url,
        headers={"User-Agent": UA, "Accept": "text/html,*/*", "Accept-Language": "en"},
    )
    return opener.open(req, timeout=TIMEOUT)


def url_variants(url: str) -> list[str]:
    """The same page, spelled the ways servers disagree about.

    A source is not unreachable because it moved from www to the bare host, gained a trailing slash,
    or redirects http to https without advertising it. Those are the same page, and treating them as
    a dead source silently drops an attraction that was perfectly citable. Tried only after the URL
    as written fails, so a working source costs nothing.
    """
    split = urllib.parse.urlsplit(url)
    out = []

    def add(scheme: str, netloc: str, path: str) -> None:
        candidate = urllib.parse.urlunsplit((scheme, netloc, path, split.query, ""))
        if candidate != url and candidate not in out:
            out.append(candidate)

    hosts = [split.netloc]
    if split.netloc.startswith("www."):
        hosts.append(split.netloc[4:])
    else:
        hosts.append("www." + split.netloc)

    paths = [split.path]
    if split.path.endswith("/"):
        paths.append(split.path.rstrip("/") or "/")
    elif split.path:
        paths.append(split.path + "/")

    for host in hosts:
        for path in paths:
            add(split.scheme, host, path)
    if split.scheme == "http":
        add("https", split.netloc, split.path)
    return out


def _fetch_once(url: str) -> tuple[bool, str]:
    ok, detail = _fetch_exact(url)
    if ok:
        return True, detail
    # Only spelling failures are worth re-spelling. A 403 is the host refusing us and a variant
    # will be refused identically; retrying it just adds load to a site that already said no.
    if detail.startswith("HTTP 4") and detail not in ("HTTP 404", "HTTP 410"):
        return False, detail
    for variant in url_variants(url):
        ok, alt = _fetch_exact(variant)
        if ok:
            return True, alt
    return False, detail


def _fetch_exact(url: str) -> tuple[bool, str]:
    host = urllib.parse.urlsplit(url).netloc.lower()
    last = "not attempted"
    use_certifi = False
    attempt = 0
    while attempt < max(RETRIES, BACKOFF_RETRIES if _interval(host) > MIN_HOST_INTERVAL else 0):
        if attempt:
            time.sleep(max(RETRY_PAUSE, _interval(host)))
        attempt += 1
        try:
            with _open(url, use_certifi) as r:
                raw = r.read()
                charset = r.headers.get_content_charset()
                ctype = (r.headers.get_content_type() or "").lower()
            _speed_up(host)
            # A PDF menu decoded as text is not a page, it is noise that happens to contain digits
            # next to currency symbols. Two restaurant PDFs came back from a probe looking like
            # price candidates, and an assertion written against that noise would "pass" forever
            # while meaning nothing. Only markup is a source.
            if ctype and not (ctype.startswith("text/") or "html" in ctype or "xml" in ctype):
                return False, f"not a web page (Content-Type: {ctype})"
            return True, decode(raw, charset)
        except urllib.error.HTTPError as e:
            last = f"HTTP {e.code}"
            # 404 and 410 are settled answers; retrying them just wastes time.
            if e.code in (404, 410):
                break
            # 403 and 429 are both "not so fast" from hosts that will serve the page at a slower
            # rate. Widen this host's gap and try again rather than recording a dead source.
            if e.code in (403, 429):
                _slow_down(host)
            # 429 usually says how long to wait. Honouring it is the difference between a source
            # that works and one recorded as unreachable.
            if e.code == 429:
                try:
                    time.sleep(min(30.0, float(e.headers.get("Retry-After", RETRY_PAUSE))))
                except (TypeError, ValueError):
                    pass
        except urllib.error.URLError as e:
            last = f"{type(e).__name__}: {e}"
            # Retry a chain-of-trust failure against certifi's roots rather than spending the
            # retry re-proving the same missing issuer.
            if not use_certifi and "certificate verify failed" in str(e.reason):
                use_certifi = True
        except Exception as e:  # network, DNS, timeout
            last = f"{type(e).__name__}: {e}"
    return False, last


def load_blocked_hosts() -> dict[str, dict]:
    """Hosts a human decided to keep citing after they stopped answering automated fetches.

    The alternative was to fail every run forever, or to delete pages whose facts were checked when
    they were written and have not been shown to be wrong. Neither is honest. A host listed here is
    reported UNCHECKED with the date it last verified, every run, so the situation stays visible and
    can be revisited, and it does not gate a publish of unrelated content.

    Nothing here reaches a reader. No page renders a source status, and none should: a source we
    cannot re-fetch is not a reason to warn a traveler about a price nobody has shown to be wrong.
    """
    try:
        with open(BLOCKED_SOURCES_JSON, encoding="utf-8") as fh:
            data = json.load(fh)
    except FileNotFoundError:
        return {}
    return {h.lower(): meta for h, meta in (data.get("hosts") or {}).items()}


_BLOCKED_HOSTS = load_blocked_hosts()


def blocked_entry(url: str) -> dict | None:
    host = urllib.parse.urlsplit(url).netloc.lower()
    for candidate in (host, host[4:] if host.startswith("www.") else "www." + host):
        if candidate in _BLOCKED_HOSTS:
            return _BLOCKED_HOSTS[candidate]
    return None


def record_health(results: list[dict]) -> None:
    """Remember when each source last verified, so 'never worked' stays distinguishable from 'broke'.

    Without this, a source that 403s today is indistinguishable from one that was never citable, and
    the only way to tell is to remember. That is exactly the judgement that gets lost between
    batches, and getting it wrong means either deleting good pages or trusting stale ones.
    """
    today = datetime.date.today().isoformat()
    try:
        with open(SOURCE_HEALTH_JSON, encoding="utf-8") as fh:
            health = json.load(fh)
    except (FileNotFoundError, json.JSONDecodeError):
        health = {}

    for entry in results:
        url = entry.get("url")
        if not url:
            continue
        record = health.setdefault(url, {})
        record["last_seen"] = today
        record["last_status"] = entry["status"]
        if entry["status"] == "PASS":
            record["last_verified"] = today

    os.makedirs(os.path.dirname(SOURCE_HEALTH_JSON), exist_ok=True)
    tmp = SOURCE_HEALTH_JSON + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(health, fh, indent=2, ensure_ascii=False, sort_keys=True)
    os.replace(tmp, SOURCE_HEALTH_JSON)


def check_place(place: dict) -> dict:
    name = place.get("name", "?")
    dest = place.get("destination_slug", "?")
    facts = place.get("facts_checked", []) or []
    results = []

    # One fetch per distinct URL, not per fact: several facts usually cite the same official page.
    urls = sorted({f.get("url", "") for f in facts if f.get("url")})
    pages: dict[str, tuple[bool, str]] = {}
    if urls:
        with ThreadPoolExecutor(max_workers=min(6, len(urls))) as pool:
            for u, res in zip(urls, pool.map(fetch, urls)):
                pages[u] = res

    for f in facts:
        url = f.get("url", "")
        assert_text = f.get("assert_text", "")
        entry = {"fact": f.get("fact", ""), "url": url, "assert_text": assert_text}
        if not url or not assert_text:
            entry["status"] = "INVALID"
            entry["detail"] = "missing url or assert_text"
        else:
            ok, body = pages.get(url, (False, "not fetched"))
            text = normalise(body) if ok else ""
            known_block = blocked_entry(url)
            if not ok and known_block:
                # A known, accepted block. Say so precisely rather than reporting it as a failure
                # every run: the fact was checked when it was written and nothing suggests it moved.
                entry["status"] = "UNCHECKED"
                entry["detail"] = (
                    f"{body}; host blocked to automated fetches since "
                    f"{known_block.get('since', 'unknown')}, last verified "
                    f"{known_block.get('last_verified', 'unknown')}"
                )
            elif not ok:
                entry["status"] = "UNREACHABLE"
                entry["detail"] = body
            elif looks_like_challenge(text):
                entry["status"] = "BLOCKED"
                entry["detail"] = "page returned 200 but its content is a challenge or consent wall"
            elif len(text) < MIN_PAGE_CHARS:
                # A 200 that returns almost nothing is a challenge or interstitial page, not the
                # real one. Reporting that as FAIL would say "this fact is wrong" about copy that
                # is fine, and send someone off to "correct" an accurate price. The distinction
                # matters more the more pages there are to check.
                entry["status"] = "BLOCKED"
                entry["detail"] = f"page returned 200 but only {len(text)} chars of text, likely a block or challenge page"
            elif normalise(assert_text) in text:
                entry["status"] = "PASS"
                entry["detail"] = ""
            else:
                entry["status"] = "FAIL"
                entry["detail"] = "assert_text not present on the page"
        results.append(entry)

    return {"destination_slug": dest, "name": name, "facts": results}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", default=PLACES_JSON)
    ap.add_argument("--slug", help="only check this destination_slug")
    ap.add_argument("--name", help="only check places whose name contains this (case-insensitive)")
    ap.add_argument("--json", help="write a machine-readable report here")
    ap.add_argument("--no-cache", action="store_true",
                    help="ignore the on-disk page cache and re-fetch every source")
    ap.add_argument("--max-age", type=int, default=DEFAULT_MAX_AGE,
                    help=f"seconds a cached page stays usable (default {DEFAULT_MAX_AGE})")
    ap.add_argument("--allow-blocked", action="store_true",
                    help="do not fail on BLOCKED sources. BLOCKED means the site refused to show us "
                         "the page, not that the fact is wrong. Use only when a human has read the "
                         "source and confirmed the copy; the blocked assertions are still printed "
                         "every run so the situation stays visible.")
    ap.add_argument("--no-retry-pass", action="store_true",
                    help="skip the end-of-run second attempt at sources that would not load. The "
                         "retry pass is what recovers hosts that only serve a page after a long "
                         "quiet gap, so skipping it makes those look permanently dead.")
    ap.add_argument("--strict", action="store_true",
                    help="also fail on UNCHECKED, the accepted blocks listed in blocked_sources.json. "
                         "Use this to audit what the registry is currently excusing.")
    a = ap.parse_args()

    global _cache_enabled, _cache_max_age
    _cache_enabled = not a.no_cache
    _cache_max_age = a.max_age

    with open(a.file, encoding="utf-8") as fh:
        places = json.load(fh).get("places", [])

    if a.slug:
        places = [p for p in places if p.get("destination_slug") == a.slug]
    if a.name:
        places = [p for p in places if a.name.lower() in p.get("name", "").lower()]
    if not places:
        print("no places matched", file=sys.stderr)
        return 1

    reports = [check_place(p) for p in places]

    # Second pass, at the end, for transport failures only.
    #
    # Some hosts hand out roughly one page per long quiet period. No amount of in-request backoff
    # buys that quiet, because the sweep itself is the traffic: royalcollection.org.uk served
    # Holyroodhouse and then refused Buckingham for the rest of the run, and the bigger the dataset
    # got, the more reliably it happened. Waiting once, after everything else has finished, gives
    # the host the gap it actually wants and costs a minute on a run that already takes many.
    #
    # Only UNREACHABLE and BLOCKED are retried. A FAIL means the page loaded and the string is gone,
    # which is a content answer; re-fetching it would just be hoping for a different truth.
    retryable = {"UNREACHABLE", "BLOCKED"}
    if not a.no_retry_pass:
        stuck = [i for i, rep in enumerate(reports)
                 if any(f["status"] in retryable for f in rep["facts"])]
        if stuck:
            print(f"\n{len(stuck)} place(s) had a source that would not load. Waiting "
                  f"{RETRY_PASS_COOLDOWN:.0f}s and trying those once more.")
            time.sleep(RETRY_PASS_COOLDOWN)
            for i in stuck:
                # No cache juggling needed: failures are never cached, so this re-fetches exactly
                # the URLs that failed and reuses the cache for the facts that already passed.
                retried = check_place(places[i])
                # Keep the better answer per fact. A retry that fails again must not overwrite a
                # PASS the first pass got on a different fact of the same place.
                for old, new in zip(reports[i]["facts"], retried["facts"]):
                    if old["status"] != "PASS" and new["status"] == "PASS":
                        old.update(new)

    record_health([f for rep in reports for f in rep["facts"]])

    counts = {"PASS": 0, "FAIL": 0, "BLOCKED": 0, "UNCHECKED": 0, "UNREACHABLE": 0, "INVALID": 0}
    for rep in reports:
        print(f"\n{rep['destination_slug']} / {rep['name']}")
        for f in rep["facts"]:
            counts[f["status"]] = counts.get(f["status"], 0) + 1
            mark = "ok  " if f["status"] == "PASS" else f["status"]
            print(f"  [{mark}] {f['assert_text'][:70]}")
            if f["status"] != "PASS":
                print(f"         {f['url']}")
                print(f"         {f['detail']}")

    total = sum(counts.values())
    print(f"\n{total} assertions across {len(reports)} places: "
          + ", ".join(f"{k}={v}" for k, v in counts.items() if v))

    if a.json:
        with open(a.json, "w", encoding="utf-8") as fh:
            json.dump({"places": reports, "counts": counts}, fh, indent=2, ensure_ascii=False)
        print(f"report written to {a.json}")

    bad = counts["FAIL"] + counts["UNREACHABLE"] + counts["INVALID"]
    if not a.allow_blocked:
        bad += counts["BLOCKED"]
    elif counts["BLOCKED"]:
        print(f"\n{counts['BLOCKED']} BLOCKED assertion(s) ignored by --allow-blocked. "
              f"These are UNCHECKED, not verified.")
    if counts["UNCHECKED"]:
        print(f"\n{counts['UNCHECKED']} assertion(s) cite a host in {os.path.relpath(BLOCKED_SOURCES_JSON, HERE)}, "
              f"which no longer answers automated fetches. They were verified when written and are "
              f"UNCHECKED now, not failed. Use --strict to treat them as failures.")
        if a.strict:
            bad += counts["UNCHECKED"]
    if bad:
        print(f"\n{bad} assertion(s) did not pass. Fix the entry or correct the copy before publishing.")
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
