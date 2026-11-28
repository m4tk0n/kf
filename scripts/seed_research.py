#!/usr/bin/env python3
"""Resumable, file-first concert research. Candidates are never promoted to verified seed data."""
from __future__ import annotations
import hashlib, json, os, pathlib, re, subprocess, sys, time, unicodedata, urllib.error, urllib.request
from datetime import datetime, timezone

ROOT = pathlib.Path(__file__).resolve().parents[1]
BASE = ROOT / "Install" / "seed_priprava"
TARGETS_FILE = BASE / "source_targets.json"
STATE_FILE = BASE / "automation_state.json"
QUEUE_FILE = BASE / "band_queue.json"
LOG_FILE = BASE / "research_log.jsonl"
CANDIDATES = BASE / "candidates"
API_URL = "https://api.openai.com/v1/responses"
MODEL = os.getenv("OPENAI_MODEL", "gpt-6.2-astra")
MAX_TASKS = max(1, min(int(os.getenv("MAX_TASKS", "2")), 8))
MAX_SECONDS = max(60, min(int(os.getenv("MAX_SECONDS", "3300")), 3300))
STARTED = time.monotonic()
YEARS = range(2000, 2028)

SCHEMA = {
 "type":"object","additionalProperties":False,
 "properties":{
  "summary":{"type":"string"},
  "events":{"type":"array","items":{"type":"object","additionalProperties":False,"properties":{
   "name":{"type":"string"},"status":{"type":"string"},"date_from":{"type":"string"},"date_to":{"type":"string"},
   "city":{"type":"string"},"country":{"type":"string"},"venue":{"type":"string"},"venue_type":{"type":"string"},
   "event_type":{"type":"string"},"bands":{"type":"array","items":{"type":"string"}},
   "tour_name":{"type":"string"},"tour_stop_order":{"type":"integer"},
   "sources":{"type":"array","items":{"type":"string"}},"notes":{"type":"string"}},
   "required":["name","status","date_from","date_to","city","country","venue","venue_type","event_type","bands","tour_name","tour_stop_order","sources","notes"]}},
  "venues":{"type":"array","items":{"type":"object","additionalProperties":False,"properties":{
   "name":{"type":"string"},"city":{"type":"string"},"country":{"type":"string"},"venue_type":{"type":"string"},
   "address":{"type":"string"},"sources":{"type":"array","items":{"type":"string"}}},
   "required":["name","city","country","venue_type","address","sources"]}},
  "bands":{"type":"array","items":{"type":"object","additionalProperties":False,"properties":{
   "name":{"type":"string"},"origin_city":{"type":"string"},"origin_country":{"type":"string"},
   "genres":{"type":"array","items":{"type":"string"}},"sources":{"type":"array","items":{"type":"string"}}},
   "required":["name","origin_city","origin_country","genres","sources"]}},
  "sources":{"type":"array","items":{"type":"object","additionalProperties":False,"properties":{
   "url":{"type":"string"},"title":{"type":"string"},"supports":{"type":"string"},"source_type":{"type":"string"}},
   "required":["url","title","supports","source_type"]}},
  "band_followups":{"type":"array","items":{"type":"string"}}
 },
 "required":["summary","events","venues","bands","sources","band_followups"]
}

def now():
 return datetime.now(timezone.utc).isoformat()

def norm(value):
 value=unicodedata.normalize("NFKD",str(value)).encode("ascii","ignore").decode().casefold()
 return re.sub(r"[^a-z0-9]+","",value)

def digest(value):
 return hashlib.sha256(value.encode("utf-8")).hexdigest()[:20]

def read_json(path, default):
 try: return json.loads(path.read_text(encoding="utf-8"))
 except (FileNotFoundError,json.JSONDecodeError): return default

def read_jsonl(path):
 if not path.exists(): return []
 rows=[]
 for line in path.read_text(encoding="utf-8").splitlines():
  try: rows.append(json.loads(line))
  except json.JSONDecodeError: continue
 return rows

def append_jsonl(path,item):
 path.parent.mkdir(parents=True,exist_ok=True)
 with path.open("a",encoding="utf-8") as handle:
  handle.write(json.dumps(item,ensure_ascii=False,sort_keys=True)+"\n")

def save_json(path,value):
 path.parent.mkdir(parents=True,exist_ok=True)
 temp=path.with_suffix(path.suffix+".tmp")
 temp.write_text(json.dumps(value,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
 temp.replace(path)

def seed_band_queue(queue):
 known={row.get("key") or norm(row.get("name","")) for row in queue}
 for path in sorted(BASE.glob("20[0-2][0-9]/bands.txt")):
  year=int(path.parent.name)
  for line in path.read_text(encoding="utf-8").splitlines():
   if not line.strip() or line.lstrip().startswith("#") or " | " not in line: continue
   name=line.split(" | ",1)[0].strip()
   key=norm(name)
   if name and key and key not in known:
    queue.append({"name":name,"key":key,"status":"pending","discovered_from":str(path.relative_to(ROOT)),"seed_year":year})
    known.add(key)
 return queue

def load_state():
 state=read_json(STATE_FILE,{})
 state.setdefault("target_year",2027); state.setdefault("target_index",0)
 state.setdefault("completed_tasks",0); state.setdefault("last_run",""); state.setdefault("last_task","")
 return state

def load_queue():
 queue=read_json(QUEUE_FILE,[])
 if not isinstance(queue,list): queue=[]
 for row in queue:
  if row.get("status")=="in_progress": row["status"]="pending"
 return seed_band_queue(queue)

def next_task(queue,state,targets):
 for row in queue:
  if row.get("status")=="pending":
   return {"kind":"band","name":row["name"],"key":row["key"]}
 year=int(state["target_year"]); index=int(state["target_index"])
 if year<2000 or not targets: return None
 return {"kind":"target","year":year,"target":targets[index],"index":index}

def make_prompt(task):
 if task["kind"]=="band":
  subject="KAPELA: "+task["name"]
  scope="""Prověř koncertní historii této konkrétní kapely v celém období 2000–2027.
Zahrň doložené koncerty v ČR a SR a také doložené koncerty v zahraničních KLUBECH.
Zahraniční festivaly a neklubové akce mimo ČR/SR vynech. Hledej supporty, předkapely,
společná turné a klubové koncerty; u každé akce zjisti celý doložený line-up a nové kapely."""
 else:
  t=task["target"]
  subject=f"AKCE / SÉRIE: {t.get('name','')} | typ {t.get('type','')} | území {t.get('territory','')} | žánry {t.get('genres','')}"
  scope=f"Prověř konkrétní ročník {task['year']} této akce nebo konkrétní koncerty této série. Zahrň pouze doložené akce v ČR a SR."
 return f"""
Jsi pečlivý historický rešeršér koncertů pro projekt K&F.
{subject}
{scope}

Povinný způsob hledání:
- Proveď více samostatných webových hledání, ne pouze jeden dotaz.
- Vyzkoušej přesný název, varianty bez diakritiky, alternativní zápis, přezdívky a jazykové varianty.
- Kombinuj název s výrazy koncerty, koncertní historie, tour, live, gig, festival, lineup, program,
  plakát, klub, město, rok a názvy známých spoluúčinkujících.
- Projdi oficiální archiv kapely, pořadatelů a klubů, dobové plakáty, koncertní databáze a reportáže.
- U každého nalezeného koncertu zkontroluj line-up a zařaď nové kapely do band_followups.
- Stejnojmenné kapely neslučuj bez ověření identity podle původu, členů, historie a diskografie.

Pravidla:
- Zapiš pouze události podložené konkrétními zdroji, které jsi při rešerši skutečně našel.
- Nevymýšlej datum, město, místo, line-up, původ, žánr ani URL. Neznámé údaje nech prázdné.
- Každá zastávka turné je samostatná akce. Vyplň tour_name jen pokud zdroj výslovně potvrzuje společné turné/sérii.
- Festival = vícedenní hudební akce; koncert = jednodenní hudební akce, i když název obsahuje Fest.
  Přesah po půlnoci sám o sobě nedělá z koncertu festival.
- Budoucí akce označ plánováno, zrušené zrušeno, minulé doložené uskutečněno.
- U zahraniční akce v úloze kapely smí být pouze klubový koncert; venue_type uveď jako klub.
- Každá akce musí mít alespoň jeden konkrétní zdroj URL. U zdrojů popiš, co přesně dokládají.
- Pokud najdeš jen stopu bez ověřitelného data/místa/zdroje, nevkládej ji mezi events; vysvětli ji v summary.
- Nepovažuj výsledek vyhledávače za potvrzení, pokud není dohledatelný zdroj.
Vrať pouze JSON podle schématu. Nepřidávej domněnky.
"""

def call_api(prompt):
 key=os.getenv("OPENAI_API_KEY","").strip()
 if not key: raise RuntimeError("Chybí GitHub Secret OPENAI_API_KEY.")
 body={"model":MODEL,"tools":[{"type":"web_search","search_context_size":"high"}],
       "tool_choice":"required","input":prompt,
       "text":{"format":{"type":"json_schema","name":"kf_concert_research","strict":True,"schema":SCHEMA}}}
 request=urllib.request.Request(API_URL,data=json.dumps(body).encode("utf-8"),
  headers={"Authorization":"Bearer "+key,"Content-Type":"application/json"},method="POST")
 with urllib.request.urlopen(request,timeout=1200) as response:
  payload=json.loads(response.read().decode("utf-8"))
 for item in payload.get("output",[]):
  if item.get("type")=="message":
   for part in item.get("content",[]):
    if part.get("type")=="output_text": return json.loads(part["text"])
 raise RuntimeError("API nevrátila strukturovaný výsledek.")

def valid_urls(result):
 return {s.get("url","").strip().rstrip("/") for s in result.get("sources",[])
         if s.get("url","").strip().startswith(("https://","http://"))}

def enqueue_band(name,year,discovered_from):
 name=str(name).strip()
 if not name: return
 queue=read_json(QUEUE_FILE,[])
 key=norm(name)
 if not key or any((row.get("key") or norm(row.get("name","")))==key for row in queue): return
 queue.append({"name":name,"key":key,"status":"pending","discovered_from":discovered_from,"seed_year":year})
 save_json(QUEUE_FILE,queue)

def write_candidates(task,result):
 urls=valid_urls(result); added={"events":0,"bands":0,"venues":0,"sources":0}
 for event in result.get("events",[]):
  date=event.get("date_from","")
  if not re.fullmatch(r"20\d\d-\d\d-\d\d",date): continue
  year=int(date[:4])
  if year not in YEARS or (task["kind"]=="target" and year!=task["year"]): continue
  refs=[u.rstrip("/") for u in event.get("sources",[]) if u.rstrip("/") in urls]
  if not refs: continue
  if task["kind"]=="band" and event.get("country","").casefold() not in ("česko","česká republika","czechia","czech republic","slovensko","slovakia"):
   if "klub" not in event.get("venue_type","").casefold() and "club" not in event.get("venue_type","").casefold(): continue
  record=dict(event); record["sources"]=refs
  record.update({"review_status":"needs_manual_review","researched_at":now(),"research_task":task})
  record["candidate_id"]=digest(norm(record.get("name",""))+"|"+date+"|"+norm(record.get("city",""))+"|"+norm(record.get("venue","")))
  path=CANDIDATES/str(year)/"events.jsonl"
  if record["candidate_id"] not in {r.get("candidate_id") for r in read_jsonl(path)}:
   append_jsonl(path,record); added["events"]+=1
  for band in event.get("bands",[]): enqueue_band(band,year,"line-up "+record["candidate_id"])
 for band in result.get("bands",[]):
  name=band.get("name","").strip()
  refs=[u.rstrip("/") for u in band.get("sources",[]) if u.rstrip("/") in urls]
  if not name or not refs: continue
  record=dict(band); record["sources"]=refs
  record.update({"review_status":"needs_manual_review","researched_at":now(),"candidate_id":digest(norm(name))})
  path=CANDIDATES/"bands.jsonl"
  if record["candidate_id"] not in {r.get("candidate_id") for r in read_jsonl(path)}:
   append_jsonl(path,record); added["bands"]+=1
  enqueue_band(name,task.get("year",2027),"band profile")
 for venue in result.get("venues",[]):
  refs=[u.rstrip("/") for u in venue.get("sources",[]) if u.rstrip("/") in urls]
  if not venue.get("name") or not refs: continue
  record=dict(venue); record["sources"]=refs
  record.update({"review_status":"needs_manual_review","researched_at":now(),
   "candidate_id":digest(norm(venue.get("name",""))+"|"+norm(venue.get("city",""))+"|"+norm(venue.get("country","")))})
  path=CANDIDATES/"venues.jsonl"
  if record["candidate_id"] not in {r.get("candidate_id") for r in read_jsonl(path)}:
   append_jsonl(path,record); added["venues"]+=1
 for source in result.get("sources",[]):
  url=source.get("url","").rstrip("/")
  if not url: continue
  record=dict(source); record.update({"researched_at":now(),"research_task":task})
  path=CANDIDATES/"sources.jsonl"
  if url not in {r.get("url","").rstrip("/") for r in read_jsonl(path)}:
   append_jsonl(path,record); added["sources"]+=1
 for name in result.get("band_followups",[]): enqueue_band(name,task.get("year",2027),"recursive discovery")
 return added

def main():
 if not os.getenv("OPENAI_API_KEY"): raise RuntimeError("Není nastaven OPENAI_API_KEY; výzkum nebyl spuštěn.")
 CANDIDATES.mkdir(parents=True,exist_ok=True)
 targets=read_json(TARGETS_FILE,{}).get("targets",[])
 queue=load_queue(); save_json(QUEUE_FILE,queue)
 state=load_state(); done=0
 while done<MAX_TASKS and time.monotonic()-STARTED<MAX_SECONDS:
  queue=read_json(QUEUE_FILE,[])
  task=next_task(queue,state,targets)
  if task is None:
   print("Fronta kapel i seznam ročníků jsou dokončené."); break
  if task["kind"]=="band":
   for row in queue:
    if row.get("key")==task["key"]: row["status"]="in_progress"; row["started_at"]=now()
   save_json(QUEUE_FILE,queue)
  print("Rešerše:",json.dumps(task,ensure_ascii=False))
  try:
   result=call_api(make_prompt(task))
   added=write_candidates(task,result)
   if task["kind"]=="band":
    queue=read_json(QUEUE_FILE,[])
    for row in queue:
     if row.get("key")==task["key"]:
      row["status"]="done"; row["completed_at"]=now(); row["summary"]=result.get("summary","")[:500]
    save_json(QUEUE_FILE,queue)
   else:
    state["target_index"]+=1
    if state["target_index"]>=len(targets):
     state["target_index"]=0; state["target_year"]-=1
   state["completed_tasks"]+=1; state["last_task"]=json.dumps(task,ensure_ascii=False); state["last_run"]=now()
   append_jsonl(LOG_FILE,{"timestamp":now(),"task":task,"summary":result.get("summary",""),"added":added,"status":"completed"})
   save_json(STATE_FILE,state); done+=1
   print("Uloženo:",json.dumps(added,ensure_ascii=False))
  except Exception as exc:
   append_jsonl(LOG_FILE,{"timestamp":now(),"task":task,"status":"error","error":str(exc)})
   if task["kind"]=="band":
    queue=read_json(QUEUE_FILE,[])
    for row in queue:
     if row.get("key")==task["key"]: row["status"]="pending"; row["last_error"]=str(exc)[:500]
    save_json(QUEUE_FILE,queue)
   save_json(STATE_FILE,state)
   raise
 print(f"Hotovo: {done} úloh v tomto běhu; celkem {state['completed_tasks']}.")

if __name__=="__main__":
 try: main()
 except (urllib.error.URLError,TimeoutError,RuntimeError,ValueError) as error:
  print("Výzkumný běh skončil chybou:",error,file=sys.stderr); raise
