# Propojený seed K&F

Všechny záznamy v tomto balíčku jsou ve stavu `pending`. Nejde o publikovaná data.

## Soubory

- `events.json` – akce, jejich vystoupení, vazby na místa, kapely a turné
- `bands.json` – kapely včetně zpětných vazeb na akce a turné
- `venues.json` – místa včetně zpětných vazeb na akce
- `tours.json` – turné a vazby na zastávky a kapely
- `sources.json` – zdroje, na které odkazují ostatní záznamy
- `status.json` – rozsah sběru, počty a výsledek kontroly integrity

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

`status: "pending"` označuje stav záznamu v tomto přípravném seed balíčku. Samotné soubory ještě nevytvářejí řádky v databázové frontě `pending`. Aktuální schvalovací workflow aplikace umí publikovat návrhy akcí; schvalování kapel a míst vyžaduje samostatné napojení.
