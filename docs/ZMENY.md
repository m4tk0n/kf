# K&F – přidávání klubů: co nahrát a co doplnit

## Soubory (nahrát na server, přepsat)
- `index.php` – nový tok přidání klubu, našeptávač s městem a adresou, route `/venue/{id}`
- `src/venues.php` – **nový**, pomocné funkce pro kluby
- `templates/add.php` – formulář, náhled, potvrzení
- `templates/venue-detail.php` – **nový**, detail klubu (používá ho i náhled)
- `templates/venue.php` – **nový**, stránka detailu klubu

## 1) Jazykové soubory

### `lang/cs/common.ini` – nahradit jeden řádek a přidat nové
```ini
add-error-venue-fields = "Vyplňte název, platnou zemi, město a adresu. Ostatní textová pole nesmí překročit povolenou délku."

add-error-duplicate = "Klub na této adrese už existuje nebo čeká na schválení: {name}."
add-exists-note = "Podobný klub už v databázi je:"
add-exists-open = "Otevřít detail"
add-exists-hint = "Pokud jde o jiný klub na jiné adrese, pokračujte ve vyplňování."
add-preview-button = "Zobrazit náhled"
add-preview-title = "Náhled klubu"
add-preview-note = "Takto bude klub vypadat. Zkontrolujte údaje a odešlete ke schválení, nebo se vraťte k úpravám."
add-preview-urls = "Odkazy na fotky (zkontroluje moderátor):"
add-preview-confirm = "Odeslat ke schválení"
add-preview-edit = "Zpět k úpravám"
add-kept-images = "Již nahrané fotky"
add-remove-image = "Odebrat"
venue-map = "Mapa"
venue-open-map = "Zobrazit na mapě"
```

### `lang/en/common.ini` – nahradit jeden řádek a přidat nové
```ini
add-error-venue-fields = "Enter a name, valid country, city and address. Other text fields must stay within their length limits."

add-error-duplicate = "A club at this address already exists or is awaiting approval: {name}."
add-exists-note = "A similar club is already in the database:"
add-exists-open = "Open detail"
add-exists-hint = "If it is a different club at a different address, continue filling in the form."
add-preview-button = "Show preview"
add-preview-title = "Club preview"
add-preview-note = "This is how the club will look. Check the details and submit for approval, or go back to edit."
add-preview-urls = "Photo links (checked by a moderator):"
add-preview-confirm = "Submit for approval"
add-preview-edit = "Back to editing"
add-kept-images = "Already uploaded photos"
add-remove-image = "Remove"
venue-map = "Map"
venue-open-map = "Show on map"
```

## 2) CSS – přidat na konec `assets/css/site.css`
```css
/* Detail klubu, náhled a upozornění na existující klub */
.venue-detail { padding: 15px; background: var(--panel); border: 1px solid var(--line); border-radius: 9px; }
.venue-title { margin: 0 0 4px; font-size: 19px; }
.venue-place { margin: 0 0 12px; color: var(--muted); font-size: 12px; }
.venue-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; margin: 0 0 14px; }
.venue-gallery img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border: 1px solid var(--line); border-radius: 6px; }
.venue-gallery img:first-child { grid-column: 1 / -1; aspect-ratio: 16 / 9; }
.venue-description { margin: 0 0 14px; font-size: 13px; line-height: 1.55; overflow-wrap: anywhere; }
.venue-info { display: grid; gap: 8px; margin: 0; font-size: 13px; }
.venue-info div { display: grid; gap: 2px; }
.venue-info dt { color: var(--muted); font-size: 11px; }
.venue-info dd { margin: 0; overflow-wrap: anywhere; }
.venue-info a, .venue-exists a { color: var(--accent); font-weight: 700; }
.venue-exists { margin-top: 6px; padding: 9px 10px; border: 1px solid var(--accent); border-radius: 6px; color: var(--text); font-size: 12px; line-height: 1.6; }
.venue-exists[hidden] { display: none; }
.venue-exists small { color: var(--muted); }
.preview-urls { overflow-wrap: anywhere; margin: 12px 0 0; }
.preview-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.preview-actions button { min-height: 44px; cursor: pointer; font: inherit; }
.kept-images { margin: 0; padding: 10px; border: 1px solid var(--line); border-radius: 6px; }
.kept-images legend { padding: 0 6px; color: var(--muted); font-size: 12px; }
.kept-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
.kept-image img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 5px; }
.kept-image span { display: flex; align-items: center; gap: 6px; }
.account-form .kept-image input[type="checkbox"] { width: auto; height: auto; }
```

## 3) Schéma – slug klubu se neukládá
V `sql/001_schema.sql` bude tabulka `venues` takhle (bez `slug` a bez klíče `city_slug`):
```sql
CREATE TABLE IF NOT EXISTS `{p}venues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `city_id` (`city_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```
Klíč `city_id` nahrazuje původní `city_slug (city_id, slug)`, který city_id pokrýval. Klíč `name` zůstává, hledání `LIKE 'text%'` ho využije.

**Už nainstalovaná DB:** jednorázově ručně, po záloze (instalátor destruktivní změny nespouští).
Uložit i jako `migrations/002_venues_drop_slug.sql`, aby se v instalátoru ukázal v ručních krocích:
```sql
-- Nahradit {p} skutečným TABLE_PREFIX. Spustit jednou, po záloze.
ALTER TABLE `{p}venues`
  ADD INDEX `city_id` (`city_id`),
  DROP INDEX `city_slug`,
  DROP COLUMN `slug`;
```
Tabulka `venues` musí být upravená dřív, než se začnou schvalovat kluby, jinak by vložení bez `slug` selhalo.

## 4) Pozor: opravené číslování typů ve frontě
Dřív kód ukládal do `pending.type` klub=2 a kapelu=3, ale `config.php` (`enums.pending_type`) říká kapela=2, klub=3.
Nově se čísla berou z configu. Pokud už ve `pending` jsou řádky z testování, zkontroluj je nebo je smaž.

## Jak to funguje
1. Formulář → tlačítko **Zobrazit náhled**: ověří se pole, spočítá se duplicita (město + adresa v hotových klubech i ve frontě), fotky se uloží zmenšené do `img/tmp/{token}/`, koncept zůstane v session.
2. Náhled používá `venue-detail.php`, tedy stejnou šablonu jako skutečný detail.
3. **Zpět k úpravám** vrátí vyplněný formulář včetně již nahraných fotek (jdou odebrat zaškrtnutím).
4. **Odeslat ke schválení** zapíše řádek do `pending` a přesune fotky do `img/pending/venue/{id}/`.
5. Koncepty starší než 24 h se uklidí samy při práci s formulářem.

## Zatím neudělané
- Schvalování/zamítání v moderaci (město, `venues`, JSON, přesun fotek na `{id}_{slug}_{n}.webp`).
- Odkazy na fotky se v náhledu jen vypíšou; po schválení se z nich nic nestahuje.
