# Propojený seed K&F

Všechny záznamy v tomto balíčku jsou ve stavu `pending`. Nejde o publikovaná data.

## Soubory

- `events.json` – akce, jejich vystoupení, vazby na místa, kapely a turné
- `bands.json` – kapely včetně zpětných vazeb na akce a turné
- `venues.json` – místa včetně zpětných vazeb na akce
- `tours.json` – turné a vazby na zastávky a kapely
- `sources.json` – zdroje, na které odkazují ostatní záznamy
- `status.json` – rozsah sběru, počty a výsledek kontroly integrity

## SQL seed do databázové fronty

Při čisté instalaci se připravené záznamy vloží do tabulky `pending` těmito soubory v `install/sql/seed/`:

- `70_seed_pending_events.sql` – 1 318 akcí (typ pending 1)
- `71_seed_pending_bands.sql` – 130 kapel (typ pending 2)
- `72_seed_pending_venues.sql` – 64 míst (typ pending 3)
- `73_seed_pending_sources.sql` – 102 zdrojů (typ pending 6)
- `74_seed_pending_tours.sql` – 17 turné (typ pending 7)

Plný původní záznam je uložen v `pending.payload`; při prvním otevření návrhu se z něj vytvoří pracovní JSON v `data/pending/`. Všechny SQL soubory vkládají návrhy jako pending pod systémovým účtem AI. Nepublikují je do katalogových tabulek. SQL soubory se spouštějí pouze při instalaci / opětovném spuštění instalátoru, nikoli běžným FTP nahráním.


## Propojení

Vazby používají stabilní textová ID, nikoli pořadí záznamů:

- akce: `venue_id`, `performances[].band_id`, `tour_ids`, `source_ids`
- kapely: `event_ids`, `tour_ids`, `sources`
- místa: `event_ids`, `source_ids`
- turné: `event_ids`, `band_ids`, `source_ids`

Neznámé vazby zůstávají prázdné nebo `null`; nevytvářejí se odhadem.

## Stav kontroly

- Všech 1 318 akcí, 130 kapel, 64 míst, 17 turné a 102 zdrojů má `status: "pending"`.
- Opraveno bylo 137 chybných hodnot `event_type` a souvisejících posunutých kódů zemí.
- Doplněny byly 2 jednoznačné vazby akcí na již evidovaná místa.
- Všechny existující odkazy na místa, kapely, zdroje a zastávky turné procházejí kontrolou integrity.
- Zbývá 1 164 akcí bez přiřazeného místa. U 45 vystoupení chybí vazba na profil kapely a u 45 také datum vystoupení. Tyto údaje jsou ponechány k ruční kontrole.

## Důležité

`status: "pending"` označuje stav záznamu v přípravném balíčku. SQL seed nyní vytváří skutečné řádky v databázové frontě a ukládá úplný zdrojový payload. Aktuální schvalovací workflow stále umí publikovat pouze běžné návrhy akcí; připravené historické záznamy kapel, míst, zdrojů a turné jsou ve frontě, ale jejich schválení do katalogových tabulek vyžaduje další rozšíření moderace. U historických akcí zůstávají textová ID a neúplné údaje zachovány v payloadu; automaticky se nepřevádějí na databázová ID.
