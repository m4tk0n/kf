# Automatický výzkum koncertů

## Co automatizace dělá

Workflow .github/workflows/seed-research.yml spouští scripts/seed_research.py:
- načte výchozí kapely z ročních bands.txt a vytvoří deduplikovanou frontu;
- nejprve zpracovává jednotlivé kapely a hledá jejich koncertní historii 2000–2027;
- následně prochází výchozí festivaly a koncertní série po ročnících od 2027 zpět do roku 2000;
- vyhledává varianty názvů, koncerty, line-upy, spoluúčinkující a doložená turné;
- nové kapely přidává do fronty, takže se rešerše rekurzivně rozšiřuje;
- ukládá dílčí výsledky do samostatných souborů JSONL.

## Soubory a význam

- band_queue.json: stav fronty kapel (pending, in_progress, done).
- automation_state.json: poslední rok/ročník, index výchozího cíle a počty dokončených úloh.
- research_log.jsonl: audit každé dokončené nebo chybové úlohy.
- candidates/YYYY/events.jsonl: nalezené kandidátní akce podle roku.
- candidates/bands.jsonl: kandidátní profily kapel.
- candidates/venues.jsonl: kandidátní místa.
- candidates/sources.jsonl: URL zdrojů a co mají dokládat.

Každý kandidát má review_status=needs_manual_review. Automatizace nezapisuje automaticky do ověřených ročních events.txt, bands.txt, venues.txt ani sources.txt. Tím se zabrání tomu, aby chybný výstup modelu vypadal jako ověřený fakt. Kandidáty lze po kontrole převést do těchto textových souborů.

## Obnovení po přerušení

- Fronta a stav se ukládají do souborů v repozitáři.
- Přerušená položka in_progress se při dalším spuštění vrátí do pending.
- Kandidáti se zapisují průběžně a deduplikují podle identifikátoru.
- Workflow má concurrency, takže se dva výzkumné běhy nepřekrývají.
- Po skončení nebo chybě běhu se dostupné změny commitnou do main.

## Zapnutí

Zatím je automatické spouštění bezpečně vypnuté. V GitHubu v repozitáři m4tk0n/kf nastav:

1. Settings → Secrets and variables → Actions → New repository secret
   - název: OPENAI_API_KEY
   - hodnota: tvůj API klíč OpenAI.
2. Settings → Secrets and variables → Actions → Variables → New repository variable
   - název: SEED_RESEARCH_ENABLED
   - hodnota: true

API používá Responses API s web search nástrojem a modelem gpt-6.2-astra. Použití OpenAI API může být zpoplatněné podle aktuálního API ceníku a účtu; není součástí běžného ChatGPT tarifu. Dokud proměnná není true, workflow rešerši nespustí a API se nevolá.

Po zapnutí se workflow spouští jednou denně (výchozí limit dvě rešeršní úlohy). Ručně jej lze spustit přes Actions → K&F – automatický výzkum koncertů → Run workflow. Výchozí limit jsou dvě rešeršní úlohy na běh; lze jej zvýšit na nejvýše osm.

## Důležité limity

- Web search a jazykový model mohou přehlédnout zdroje nebo chybně vyhodnotit podobné názvy. Výstupy jsou proto kandidáti, nikoli automaticky ověřená data.
- Nelze zaručit nalezení všech koncertů ani všech variant názvu.
- U zahraničních koncertů se ukládají pouze klubové akce; zahraniční festivaly se vynechávají.
- Skript nepřistupuje k databázi, nevytváří SQL a nenasazuje nic na živý web.
