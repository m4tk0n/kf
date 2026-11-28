#!/usr/bin/env python3
"""Read-only structural audit for K&F seed preparation JSON files."""
from __future__ import annotations

import glob
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
errors: list[str] = []
warnings: list[str] = []


def load(path: Path):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        errors.append(f"{path.name}: invalid JSON: {exc}")
        return {}


def records(data, path):
    result = []
    for state in ("aktual", "ok", "next"):
        value = data.get(state, [])
        if not isinstance(value, list):
            errors.append(f"{path.name}: {state} must be an array")
            continue
        result.extend((state, item) for item in value if isinstance(item, dict))
    return result


bands = load(ROOT / "bands.json")
venues = load(ROOT / "venues.json")
evidence = load(ROOT / "evidence.json")
band_rows = records(bands, ROOT / "bands.json")
venue_rows = records(venues, ROOT / "venues.json")
event_files = sorted(Path(x) for x in glob.glob(str(ROOT / "event_[0-9][0-9][0-9][0-9].json")))
event_rows = []
for path in event_files:
    data = load(path)
    year = int(path.stem[-4:])
    for state, item in records(data, path):
        event_rows.append((path, year, state, item))

def unique_ids(rows, label):
    seen = {}
    for item in rows:
        ident = item.get("id")
        if ident is None:
            errors.append(f"{label}: record missing id")
        elif ident in seen:
            errors.append(f"{label}: duplicate id {ident!r} ({seen[ident]} and {item.get('name')})")
        else:
            seen[ident] = item.get("name", "?")
    return set(seen)

band_ids = unique_ids([x for _, x in band_rows], "bands")
venue_ids = unique_ids([x for _, x in venue_rows], "venues")
event_ids = set()
for path, year, state, event in event_rows:
    ident = event.get("id")
    if ident is None:
        errors.append(f"{path.name}/{state}: event missing id")
    elif ident in event_ids:
        errors.append(f"{path.name}: duplicate event id {ident!r} across year files")
    event_ids.add(ident)
    if event.get("date_from") and not str(event["date_from"]).startswith(f"{year}-"):
        errors.append(f"{path.name}: event {ident} date_from does not match file year")
    if event.get("date_from") and event.get("date_to") and event["date_to"] < event["date_from"]:
        errors.append(f"{path.name}: event {ident} date_to precedes date_from")
    if event.get("venue_id") is not None and event["venue_id"] not in venue_ids:
        errors.append(f"{path.name}: event {ident} references missing venue_id {event['venue_id']}")
    for slot in event.get("bands", []):
        if slot.get("band_id") not in band_ids:
            errors.append(f"{path.name}: event {ident} references missing band_id {slot.get('band_id')}")
    if not event.get("sources"):
        warnings.append(f"{path.name}: event {ident} has no sources")

source_ids = set()
claim_ids = set()
for src in evidence.get("sources", []):
    sid = src.get("id")
    if sid in source_ids:
        errors.append(f"evidence: duplicate source id {sid}")
    source_ids.add(sid)
for claim in evidence.get("claims", []):
    cid = claim.get("id")
    if cid in claim_ids:
        errors.append(f"evidence: duplicate claim id {cid}")
    claim_ids.add(cid)
    for sid in claim.get("source_ids", []):
        if sid not in source_ids:
            errors.append(f"evidence: claim {cid} references missing source {sid}")

# Do not assume evidence uses a particular claims key until the file defines it.
if "claims" not in evidence:
    warnings.append("evidence.json has no top-level 'claims' array; claim validation skipped")

print(f"Seed audit: {len(band_rows)} bands, {len(venue_rows)} venues, {len(event_rows)} events, {len(event_files)} year files")
print(f"Errors: {len(errors)} | Warnings: {len(warnings)}")
for msg in errors:
    print("ERROR:", msg)
for msg in warnings:
    print("WARN:", msg)
# Existing data warnings/errors are reported as an artifact; don't block archive collection.
if errors:
    Path("seed-audit-errors.txt").write_text("\n".join(errors) + "\n", encoding="utf-8")
if warnings:
    Path("seed-audit-warnings.txt").write_text("\n".join(warnings) + "\n", encoding="utf-8")
