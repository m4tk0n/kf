#!/usr/bin/env python3
"""Resumable research worker. Writes sourced candidates only; never writes SQL or DB."""
from __future__ import annotations

import json
import os
import pathlib
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone

ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = ROOT / "Install" / "seed_priprava"
STATE = BASE / "automation_state.json"
QUEUE = BASE / "band_queue.jsonl"
CANDIDATES = BASE / "candidates"
TARGETS = BASE / "source_targets.json"
API_URL = "https://api.openai.com/v1/responses"
MODEL = os.getenv("OPENAI_MODEL", "gpt-6.2-astra")
MAX_TASKS = int(os.getenv("MAX_TASKS", "4"))
MAX_SECONDS = min(int(os.getenv("MAX_SECONDS", "3300")), 3300)
STARTED = time.monotonic()

SCHEMA = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "summary": {"type": "string"},
        "events": {"type": "array", "items": {
            "type": "object", "additionalProperties": False,
            "properties": {
                "name": {"type": "string"}, "status": {"type": "string"},
                "date_from": {"type": "string"}, "date_to": {"type": "string"},
                "city": {"type": "string"}, "country": {"type": "string"},
                "venue": {"type": "string"}, "event_type": {"type": "string"},
                "bands": {"type": "array", "items": {"type": "string"}},
                "tour_name": {"type": "string"},
                "tour_stop_order": {"type": "integer"},
                "sources": {"type": "array", "items": {"type": "string"}},
                "notes": {"type": "string"}
            },
            "required": ["name","status","date_from","date_to","city","country","venue","event_type","bands","tour_name","tour_stop_order","sources","notes"]
        }},
        "venues": {"type": "array", "items": {
            "type": "object", "additionalProperties": False,
            "properties": {
                "name":{"type":"string"},"city":{"type":"string"},
                "country":{"type":"string"},"venue_type":{"type":"string"},
                "sources":{"type":"array","items":{"type":"string"}}
            },
            "required":["name","city","country","venue_type","sources"]
        }},
        "bands": {"type": "array", "items": {
            "type": "object", "additionalProperties": False,
            "properties": {
                "name":{"type":"string"},"origin_city":{"type":"string"},
                "origin_country":{"type":"string"},"genre":{"type":"string"},
                "sources":{"type":"array","items":{"type":"string"}}
            },
            "required":["name","origin_city","origin_country","genre","sources"]
        }},
        "sources": {"type": "array", "items": {
            "type":"object","additionalProperties":False,
            "properties":{"url":{"type":"string"},"supports":{"type":"string"},"source_type":{"type":"string"}},
            "required":["url","supports","source_type"]
        }},
        "band_followups": {"type":"array","items":{"type":"string"}}
    },
    "required":["summary","events","venues","bands","sources","band_followups"]
}

def read_json(path, default):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        return default

def append_jsonl(path, item):
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as f:
        f.write(json.dumps(item, ensure_ascii=False) + "\n")

def call_research(prompt):
    key = os.environ.get("OPENAI_API_KEY", "")
    if not key:
        raise RuntimeError("Chybí GitHub Actions secret OPENAI_API_KEY.")
    body = {
        "model": MODEL,
        "tools": [{"type":"web_search","search_context_size":"high"}],
        "tool_choice": "required",
        "input": prompt,
        "text": {"format":{"type":"json_schema","name":"concert_research","strict":True,"schema":SCHEMA}}
    }
    req = urllib.request.Request(
        API_URL, data=json.dumps(body).encode("utf-8"),
        headers={"Authorization":"Bearer "+key,"Content-Type":"application/json"},
        method="POST"
    )
    with urllib.request.urlopen(req, timeout=900) as response:
        payload = json.loads(response.read().decode("utf-8"))
    for item in payload.get("output", []):
        if item.get("type") == "message":
            for part in item.get("content", []):
                if part.get("type") == "output_text":
                    return json.loads(part["text"])
    raise RuntimeError("Responses API nevrátila očekávaný JSON výstup.")

def existing_urls():
    urls=set()
    for path in BASE.glob("*/sources.txt"):
        for line in path.read_text(encoding="utf-8").splitlines():
            if " | " in line and line[:1].isdigit():
                parts=line.split(" | ")
                if len(parts)>1: urls.add(parts[1].strip().rstrip("/"))
    for path in CANDIDATES.glob("*.jsonl"):
        for line in path.read_text(encoding="utf-8").splitlines():
            try:
                row=json.loads(line)
                for source in row.get("sources",[]): urls.add(source.get("url","").rstrip("/"))
            except Exception: pass
    return urls

def build_tasks():
    targets=read_json(TARGETS,{}).get("targets",[])
    state=read_json(STATE,{"year":2027,"target_index":0,"band_cursor":0,"completed":[]})
    tasks=[]
    # Newly discovered bands get their own concert-history research.
    band_items=[]
    if QUEUE.exists():
        for line in QUEUE.read_text(encoding="utf-8").splitlines():
            try: band_items.append(json.loads(line))
            except Exception: continue
    cursor=int(state.get("band_cursor",0))
    while cursor < len(band_items) and len(tasks)<MAX_TASKS:
        band=band_items[cursor]
        tasks.append({"kind":"band","year":int(band.get("year",state.get("year",2027))),"name":band["name"]})
        cursor+=1
    state["band_cursor"]=cursor
    year=int(state.get("year",2027))
    idx=int(state.get("target_index",0))
    while year>=2000 and len(tasks)<MAX_TASKS:
        if not targets:
            break
        target=targets[idx % len(targets)]
        tasks.append({"kind":"target","year":year,"target":target})
        idx+=1
        if idx % len(targets)==0:
            year-=1
    state["year"]=year
    state["target_index"]=idx
    return tasks,state

def prompt_for(task, known):
    yr=task["year"]
    if task["kind"]=="band":
        subject=f"kapela {task['name']}"
        scope=("Najdi doložené koncerty této kapely v ČR a SR v daném roce. "
               "Navíc zahrň její doložené zahraniční klubové koncerty. "
               "Zahraniční festivaly mimo ČR/SR vynech. Identitu stejnojmenných kapel ověř.")
    else:
        t=task["target"]
        subject=f"{t['name']} ({t['type']}, území {t['territory']}, žánry {t['genres']})"
        scope=("Prověř konkrétní ročník nebo koncertní program této akce/série. "
               "Zahrň pouze doložené koncerty v ČR/SR. Pokud jde o klubovou sérii, "
               "hledej konkrétní klubové koncerty. Kapely mohou být odkudkoliv.")
    return f"""
Jsi rešeršér historických koncertů pro databázi K&F. Rok: {yr}. Předmět: {subject}.
{scope}
Pravidla:
- Použij web search a pracuj pouze s konkrétními zdroji, které jsi skutečně otevřel/ověřil.
- Nevymýšlej data, line-up, místo, adresu, původ kapely ani URL.
- Zapiš jen události s alespoň jedním konkrétním zdrojovým URL; preferuj oficiální archivy, pořadatele, kluby, kapely, dobové plakáty a důvěryhodné archivy.
- Stav musí být uskutečněno, plánováno nebo zrušeno; budoucí koncerty označ plánováno.
- Pokud údaj neznáš, použij prázdný řetězec. Datum používej YYYY-MM-DD, neznámý konec = stejné datum jako začátek, pokud je doloženo jednodenní konání.
- Zahraniční klubové koncerty jsou povolené pouze při průzkumu kapely. Zahraniční festivaly a neklubové akce mimo ČR/SR vynech.
- Tour: pokud zdroj výslovně dokládá, že akce je zastávkou pojmenovaného turné, vyplň tour_name a tour_stop_order, pokud je pořadí doloženo. Jinak tour_name prázdné a pořadí 0. Neslučuj jednotlivé koncerty do jedné akce; každá zastávka je samostatná akce.
- U každého zdroje uveď URL a přesně co dokládá. Neuváděj vyhledávací URL ani domnělé odkazy.
- Neopakuj známé URL: {json.dumps(sorted(known)[:250],ensure_ascii=False)}
Vrať pouze JSON podle předepsaného schématu. Pokud nic spolehlivého nenajdeš, vrať prázdná pole a vysvětli to v summary.
"""

def validate(result, year, known):
    clean=[]
    for ev in result.get("events",[]):
        try:
            if not ev.get("name") or not ev.get("sources"): continue
            if not ev.get("date_from","").startswith(str(year)): continue
            if ev.get("country") not in ("Česko","Česká republika","Slovensko","Czechia","Czech Republic","Slovakia",""):
                if ev.get("event_type","").lower() not in ("klubový koncert","club concert"): continue
            valid=[]
            for u in ev.get("sources",[]):
                if isinstance(u,str) and u.startswith("https://") and u.rstrip("/") not in known:
                    valid.append(u)
            if not valid: continue
            ev["sources"]=valid
            clean.append(ev)
        except Exception: continue
    result["events"]=clean
    result["sources"]=[s for s in result.get("sources",[]) if s.get("url","").startswith("https://")]
    return result

def main():
    CANDIDATES.mkdir(parents=True,exist_ok=True)
    tasks,state=build_tasks()
    if not tasks:
        print("Fronta je prázdná nebo dokončená.")
        return
    known=existing_urls()
    done=0
    for task in tasks:
        if done>=MAX_TASKS or time.monotonic()-STARTED>MAX_SECONDS:
            break
        year=task["year"]
        print(f"Rešerše: {task['kind']} / {year}")
        result=call_research(prompt_for(task,known))
        result=validate(result,year,known)
        record={
            "researched_at":datetime.now(timezone.utc).isoformat(),
            "task":task,"summary":result.get("summary",""),
            "events":result.get("events",[]),"venues":result.get("venues",[]),
            "bands":result.get("bands",[]),"sources":result.get("sources",[])
        }
        append_jsonl(CANDIDATES/f"{year}.jsonl",record)
        for band in result.get("band_followups",[]):
            if band.strip():
                append_jsonl(QUEUE,{"name":band.strip(),"year":year})
        for band in result.get("bands",[]):
            if band.get("name"):
                append_jsonl(QUEUE,{"name":band["name"],"year":year})
        for src in result.get("sources",[]):
            known.add(src.get("url","").rstrip("/"))
        done+=1
    state["last_run"]=datetime.now(timezone.utc).isoformat()
    state["tasks_processed"]=int(state.get("tasks_processed",0))+done
    STATE.write_text(json.dumps(state,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print(f"Dokončeno úloh: {done}; celkem: {state['tasks_processed']}")

if __name__=="__main__":
    try: main()
    except (urllib.error.URLError,TimeoutError,RuntimeError) as exc:
        print(f"Výzkumná úloha selhala: {exc}",file=sys.stderr)
        raise
