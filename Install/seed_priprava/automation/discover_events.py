#!/usr/bin/env python3
"""Source-first discovery of punk/punk-rock events in Czechia and Slovakia.

Collects traceable raw candidates only. It never writes verified seed records
or the live database. Sources and candidate excerpts remain reviewable.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "discovery_candidates.json"
SOURCES = ROOT / "discovery_sources.json"
VENUES = ROOT / "venues.json"
BANDS = ROOT / "bands.json"
HEADERS = {"User-Agent": "KF-EventDiscovery/1.0 (+https://github.com/m4tk0n/kf)"}
DATE_RE = re.compile(r"(?<!\d)(?:\d{1,2}[.\-/]\s?\d{1,2}[.\-/]\s?\d{2,4}|\d{4}-\d{2}-\d{2}|\d{1,2}\.\s*\d{1,2}\.\s*\d{4})(?!\d)")
PUNK_RE = re.compile(r"\b(punk|punk\s*[-–]?\s*rock|streetpunk|ska\s*[-–]?\s*punk|hardcore\s*[-–]?\s*punk|oi!?|crust|anarcho[- ]punk)\b", re.I)
EVENT_PATH_RE = re.compile(r"(akce|koncert|event|program|kalend|punk|festival|gig)", re.I)


def fetch(url: str) -> str:
    req = Request(url, headers=HEADERS)
    with urlopen(req, timeout=25) as response:
        raw = response.read(3_000_000)
        charset = response.headers.get_content_charset() or "utf-8"
    return raw.decode(charset, errors="replace")


def read_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


def records(data: dict):
    for state in ("aktual", "ok", "next"):
        yield from (x for x in data.get(state, []) if isinstance(x, dict))


def parse_page(html: str, url: str):
    from bs4 import BeautifulSoup
    soup = BeautifulSoup(html, "html.parser")
    for node in soup(["script", "style", "noscript", "svg", "header", "footer"]):
        node.decompose()
    lines = [" ".join(x.split()) for x in soup.get_text("\n").splitlines() if x.strip()]
    links = []
    host = urlparse(url).netloc.lower()
    for a in soup.find_all("a", href=True):
        absolute = urljoin(url, a["href"].strip())
        parsed = urlparse(absolute)
        if parsed.scheme in ("http", "https") and parsed.netloc.lower() == host:
            links.append({"label": " ".join(a.get_text(" ", strip=True).split())[:180], "url": absolute})
    return lines, links


def excerpts(lines: list[str], curated: bool):
    found = []
    for i, line in enumerate(lines):
        if not DATE_RE.search(line):
            continue
        chunk = " | ".join(lines[max(0, i - 1):min(len(lines), i + 3)])
        if PUNK_RE.search(chunk) or curated:
            found.append(chunk[:1000])
    return list(dict.fromkeys(found))


def digest(source_url: str, excerpt: str) -> str:
    return hashlib.sha256((source_url + "\n" + excerpt).encode("utf-8")).hexdigest()[:24]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--start-year", type=int, default=datetime.now().year + 2)
    parser.add_argument("--end-year", type=int, default=datetime.now().year - 2)
    parser.add_argument("--max-pages", type=int, default=250)
    args = parser.parse_args()
    if args.start_year < args.end_year:
        parser.error("start-year must be >= end-year")

    config = read_json(SOURCES)
    queue = []
    for item in config.get("sources", []):
        if item.get("url"):
            queue.append((item["url"], item.get("id", "SRC"), item.get("kind", "curated"), bool(item.get("curated_punk", False))))
    # Every known venue homepage becomes a discovery seed, not just the venue
    # associated with the currently researched band.
    venue_data = read_json(VENUES)
    for venue in records(venue_data):
        url = venue.get("url")
        if url and url.startswith(("http://", "https://")):
            queue.append((url, "VENUE-" + str(venue.get("id")), "venue", False))

    # Also scan known band gig archives when an exact Bandzone URL is supplied.
    band_data = read_json(BANDS)
    for band in records(band_data):
        base = band.get("bandzone_url")
        if base:
            for year in range(args.start_year, args.end_year - 1, -1):
                queue.append((base + ("&" if "?" in base else "?") + f"at=gig&gy={year}", "BAND-" + str(band.get("id")), "band_archive", False))

    existing = {}
    old = read_json(OUT)
    for item in old.get("candidates", []):
        if isinstance(item, dict) and item.get("id"):
            existing[item["id"]] = item

    seen_urls = set()
    scanned = []
    errors = []
    while queue and len(seen_urls) < args.max_pages:
        url, source_id, kind, curated = queue.pop(0)
        if url in seen_urls:
            continue
        seen_urls.add(url)
        try:
            lines, links = parse_page(fetch(url), url)
            rows = []
            for excerpt in excerpts(lines, curated):
                cid = digest(url, excerpt)
                row = {"id": cid, "source_id": source_id, "source_url": url,
                       "source_kind": kind, "excerpt": excerpt,
                       "review_status": "unreviewed",
                       "discovered_at": datetime.now(timezone.utc).isoformat()}
                existing[cid] = row
                rows.append(row)
            scanned.append({"url": url, "source_id": source_id, "source_kind": kind,
                            "candidate_count": len(rows), "link_count": len(links),
                            "content_sha256": hashlib.sha256("\n".join(lines).encode()).hexdigest()})
            # Follow event/calendar/pagination links, but never spider an entire site.
            for link in links:
                target = link["url"]
                parsed = urlparse(target)
                if EVENT_PATH_RE.search(parsed.path + " " + parsed.query):
                    if target not in seen_urls and len(queue) + len(seen_urls) < args.max_pages:
                        queue.append((target, source_id, kind, curated))
            print(f"OK {url}: {len(rows)} candidates")
        except Exception as exc:
            errors.append({"url": url, "error": str(exc)[:400]})
            print(f"WARN {url}: {exc}")
        time.sleep(0.35)

    payload = {
        "_purpose": "Raw, traceable punk/punk-rock event discovery candidates. Not verified and not directly importable.",
        "_generated_at": datetime.now(timezone.utc).isoformat(),
        "_scope": {"countries": ["CZ", "SK"], "start_year": args.start_year, "end_year": args.end_year},
        "_rules": {
            "review_status": "unreviewed",
            "promotion": "Verify event date, Czech/Slovak location, venue, lineup and whether it is announced/held/cancelled before adding to event_YYYY.json or database.",
            "deduplication": "Candidate IDs identify source excerpts, not events. Merge same event across sources during review.",
            "safety": "Crawler never writes to verified seed JSON or the live database."
        },
        "scan": {"pages_attempted": len(seen_urls), "pages_succeeded": len(scanned),
                 "errors": errors, "sources": scanned},
        "candidates": sorted(existing.values(), key=lambda x: (x.get("discovered_at", ""), x["id"]), reverse=True)
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Saved {len(existing)} raw candidates from {len(scanned)} pages")
    if not scanned:
        raise SystemExit("No discovery source could be fetched")


if __name__ == "__main__":
    main()
