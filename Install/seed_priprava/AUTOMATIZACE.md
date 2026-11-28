# Automatický výzkum koncertů (bez modelového API)

## Architektura

- seed-worker.php je chráněný PHP endpoint nasazený na serveru.
- GitHub Actions jej jednou denně zavolá přes HTTPS.
- PHP worker prohledává veřejné výsledky Bing RSS bez modelu a bez placeného vyhledávacího API.
- Výsledky ukládá přímo na server do souborů v tomto adresáři.
- Workflow následně stáhne pouze výzkumné soubory přes FTP, uloží je do GitHubu a deploy workflow je vrátí na server.

## Soubory

- band_queue.json: fronta 140 výchozích kapel; stav každé kapely a pořadí dotazu.
- automation_state.json: checkpoint posledního běhu.
- candidates/search_results.jsonl: nalezené URL, názvy a úryvky výsledků.
- candidates/search_queries.jsonl: audit provedených dotazů a chyb.
- research_progress.txt: ručně vedený stav ověřené rešerše.

## Jak worker hledá

Pro každou kapelu používá varianty přesného názvu, koncertů, live/tour/gig, festivalů a line-upů, setlistů, historie kapely, klubů a zápisu bez diakritiky. Jedno spuštění má limit 12 dotazů. Po každém dotazu se ihned uloží výsledky i pozice ve frontě. Přerušený běh proto pokračuje od další varianty.

## Co výsledky znamenají

Výsledky vyhledávače jsou pouze zdrojové stopy, nikoli potvrzené koncerty. Skript sám nevymýšlí data, line-upy ani identitu kapel a automaticky nic nepřidává do ověřených ročních events.txt, bands.txt, venues.txt nebo sources.txt.

Já následně zdroje ověřuji, rozlišuji stejnojmenné kapely, sestavuji koncerty a line-upy a zapisuji ověřené údaje do příslušných ročních souborů. Nové kapely z ověřených line-upů se přidávají do fronty.

## Bezpečnost a nastavení

- Endpoint přijímá pouze POST a vyžaduje hlavičku X-AI-Key.
- Používá existující AI_WRITE_KEY z .env; stejná hodnota musí být v GitHub Secret AI_WRITE_KEY.
- Soubory v candidates/ jsou na webu blokované pomocí .htaccess.
- Worker používá zámek, aby se běhy nepřekrývaly.
- Žádné OpenAI API, žádný modelový secret, žádné SQL a žádný zápis do databáze.

## Spuštění

Workflow .github/workflows/seed-research.yml se spouští jednou denně nebo ručně přes Actions. Pro ruční běh lze nastavit počet dotazů 1–12.

## První spuštění – chyba 403

První automatický pokus dne 28. 11. 2026 došel na server, ale PHP endpoint vrátil 403 forbidden. To znamená, že hodnota GitHub Secret AI_WRITE_KEY nebyla shodná s hodnotou AI_WRITE_KEY načtenou na serveru z .env (nebo serverová hodnota chybí). Žádné rešeršní dotazy se v tomto běhu neprovedly.

Je nutné nastavit stejný dlouhý náhodný klíč na obou místech:
- server: řádek AI_WRITE_KEY v souboru .env;
- GitHub: Settings → Secrets and variables → Actions → AI_WRITE_KEY.

Hodnotu klíče neposílej do chatu ani ji neukládej do GitHub souborů. Po synchronizaci lze workflow znovu spustit ručně.
