#!/usr/bin/env python3
"""Collect raw, traceable candidates from the configured public band archives.

This tool never promotes candidates to verified seed records. It stores source
URLs and short text excerpts for manual/AI-assisted cross-checking.
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
OUT = ROOT / "research_candidates.json"
INDEX = ROOT / "band_archive_index.json"
DATE_RE = re.compile(
    r"(?<!\d)(?:\d{1,2}[.\-/]\s?\d{1,2}[.\-/]\s?\d{2,4}|"
    r"\d{4}-\d{2}-\d{2}|\d{1,2}\.\s*\d{1,2}\.\s*\d{4})(?!\d)"
)
HEADERS = {"User-Agent": "KF-SeedResearchBot/1.0 (+https://github.com/m4tk0n/kf)"}


def fetch(url: str) -> str:
    req = Request(url, headers=HEADERS)
    with urlopen(req, timeout=35) as response:
        raw = response.read()
        charset = response.headers.get_content_charset() or "utf-8"
    return raw.decode(charset, errors="replace")


def clean_html(html: str) -> tuple[str, list[dict]]:
    try:
        from bs4 import BeautifulSoup
    except ImportError as exc:
        raise RuntimeError("Install beautifulsoup4 before running the scanner") from exc
    soup = BeautifulSoup(html, "html.parser")
    for node in soup(["script", "style", "noscript", "svg", "header", "footer"]):
        node.decompose()
    links = []
    for a in soup.find_all("a", href=True):
        label = " ".join(a.get_text(" ", strip=True).split())
        href = a["href"].strip()
        if href and not href.startswith(("mailto:", "tel:", "javascript:", "#")):
            links.append({"label": label[:240], "url": href})
    text = "\n".join(
        " ".join(line.split()) for line in soup.get_text("\n").splitlines()
        if line.strip()
    )
    return text, links


def excerpts(text: str) -> list[str]:
    lines = [x.strip() for x in text.splitlines() if x.strip()]
    found = []
    for i, line in enumerate(lines):
        if DATE_RE.search(line):
            excerpt = " | ".join(lines[max(0, i - 1):min(len(lines), i + 3)])
            if len(excerpt) > 900:
                excerpt = excerpt[:900]
            found.append(excerpt)
    # Preserve order while removing exact duplicates.
    return list(dict.fromkeys(found))


def digest(url: str, excerpt: str) -> str:
    return hashlib.sha256((url + "\n" + excerpt).encode("utf-8")).hexdigest()[:20]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--start-year", type=int, default=2026)
    parser.add_argument("--end-year", type=int, default=2000)
    args = parser.parse_args()
    if args.start_year < args.end_year:
        parser.error("start-year must be >= end-year")

    index = json.loads(INDEX.read_text(encoding="utf-8"))
    band = index.get("_scope", {}).get("band", "current band")
    fixed_sources = [
        {"id": "ARCH-001", "url": "https://thefialky.cz/koncerty-2000-2015/"},
        {"id": "ARCH-002", "url": "https://thefialky.cz/odehrane-koncerty/"},
    ]
    pages = []
    for src in fixed_sources:
        pages.append((src["url"], src["id"], None))
    for year in range(args.start_year, args.end_year - 1, -1):
        pages.append((f"https://bandzone.cz/thefialky?at=gig&gy={year}", "ARCH-003", year))

    existing = {}
    if OUT.exists():
        try:
            old = json.loads(OUT.read_text(encoding="utf-8"))
            existing = {x["id"]: x for x in old.get("candidates", [])}
        except (ValueError, KeyError, TypeError):
            existing = {}

    errors = []
    scanned = []
    for url, source_id, year in pages:
        try:
            html = fetch(url)
            text, links = clean_html(html)
            rows = []
            for excerpt in excerpts(text):
                cid = digest(url, excerpt)
                rows.append({
                    "id": cid,
                    "source_id": source_id,
                    "source_url": url,
                    "archive_year": year,
                    "excerpt": excerpt,
                    "review_status": "unreviewed",
                })
                existing[cid] = rows[-1]
            domain = urlparse(url).netloc
            relevant_links = []
            for link in links:
                absolute = urljoin(url, link["url"])
                if urlparse(absolute).netloc.endswith(domain):
                    relevant_links.append({"label": link["label"], "url": absolute})
            scanned.append({
                "url": url,
                "source_id": source_id,
                "archive_year": year,
                "fetched_at": datetime.now(timezone.utc).isoformat(),
                "excerpt_count": len(rows),
                "link_count": len(relevant_links),
                "links": relevant_links[:500],
                "content_sha256": hashlib.sha256(text.encode("utf-8")).hexdigest(),
            })
            print(f"OK {url}: {len(rows)} date excerpts, {len(relevant_links)} links")
        except Exception as exc:  # Keep processing the remaining archive years.
            errors.append({"url": url, "archive_year": year, "error": str(exc)[:500]})
            print(f"WARN {url}: {exc}")
        time.sleep(1.0)

    payload = {
        "_purpose": "Automaticky sesbirane surove nalezy z verejnych archivu. Nejde o overene akce a nesmi se automaticky importovat do event_YYYY.json.",
        "_band": band,
        "_generated_at": datetime.now(timezone.utc).isoformat(),
        "_rules": {
            "review_status": "unreviewed",
            "promotion": "Rucne/nezavisle overit datum, skutecne konani, misto a line-up. Samotny archivni zaznam neni automaticky dukazem vsech poli.",
            "deduplication": "Candidate ID je hash zdrojove URL a textoveho vyrezu; neni to ID akce.",
        },
        "scan": {
            "start_year": args.start_year,
            "end_year": args.end_year,
            "pages_attempted": len(pages),
            "pages_succeeded": len(scanned),
            "errors": errors,
            "sources": scanned,
        },
        "candidates": sorted(existing.values(), key=lambda x: (x.get("archive_year") or 0, x["id"]), reverse=True),
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Saved {len(existing)} raw candidates to {OUT}")
    if not scanned:
        raise SystemExit("No archive page could be fetched")


if __name__ == "__main__":
    main()
