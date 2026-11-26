# Oblíbené

## Účel

Funkce **Oblíbené** umožní přihlášenému uživateli ukládat akce, místa a kapely mezi oblíbené položky. Údaje se ukládají trvale do databáze a jsou vázané na konkrétní uživatelský účet.

Funkce má dvě propojené části:

1. Označení nebo odebrání konkrétní položky jako oblíbené v jejím detailu.
2. Globální přepínač oblíbených položek v horní navigaci, který filtruje právě zobrazený seznam.

Obě části pracují se stejnými databázovými záznamy.

## Databázová struktura

Použijí se přesně **tři samostatné tabulky**, jedna pro každý typ entity:

### `fav_events`

| Sloupec | Význam |
|---|---|
| `user_id` | ID uživatele |
| `id` | ID akce z tabulky `events` |

### `fav_venues`

| Sloupec | Význam |
|---|---|
| `user_id` | ID uživatele |
| `id` | ID místa z tabulky `venues` |

### `fav_bands`

| Sloupec | Význam |
|---|---|
| `user_id` | ID uživatele |
| `id` | ID kapely z tabulky `bands` |

### Databázová pravidla

- Každá tabulka obsahuje pouze dva sloupce: `user_id` a `id`.
- Sloupec `id` je ID příslušné entity, nikoliv samostatné automaticky generované ID oblíbeného záznamu.
- Kombinace `(user_id, id)` tvoří primární klíč. Jeden uživatel tak nemůže mít stejnou položku uloženou vícekrát.
- `user_id` odkazuje na uživatele v tabulce uživatelů a při smazání uživatele se jeho oblíbené záznamy odstraní pomocí `ON DELETE CASCADE`.
- Při odebrání poslední oblíbené položky uživateli nezůstává v tabulce žádný prázdný záznam.
- Při přidání první oblíbené položky se jednoduše vloží první řádek.
- Nepřidávají se datum vytvoření, poznámky, typ entity ani jiné doplňující sloupce.
- Aplikace při ukládání ověří, že příslušná entita skutečně existuje a že uživatel smí danou operaci provést.

## Přístupová pravidla

### Nepřihlášený návštěvník

- V horní navigaci se vedle avatara nezobrazuje globální hvězdička.
- V detailu akce, místa nebo kapely se zobrazí šedá hvězdička.
- Hvězdička v detailu není aktivní a nelze na ni kliknout.
- Návštěvník nemůže oblíbené položky přidávat ani odebírat.

### Přihlášený uživatel

- V horní navigaci se bezprostředně vlevo od avatara zobrazuje globální hvězdička.
- V detailu každé podporované entity se zobrazuje aktivní ovládací hvězdička.
- Stav hvězdičky v detailu vždy odpovídá skutečnému stavu v databázi.

## Hvězdička v detailu entity

- Neoblíbená položka: šedá hvězdička.
- Oblíbená položka: žlutá vyplněná hvězdička.
- Kliknutím na šedou hvězdičku se položka uloží mezi oblíbené a hvězdička se ihned změní na žlutou.
- Kliknutím na žlutou hvězdičku se položka odebere a hvězdička se ihned změní na šedou.
- Stejné chování platí pro akce, místa i kapely.
- Po otevření detailu se stav načte z databáze, nikoliv pouze ze session nebo cookie.

## Globální filtr oblíbených položek

Globální hvězdička v horní navigaci zapíná nebo vypíná filtrování aktuálně zobrazeného seznamu.

- Neaktivní šedá hvězdička: zobrazují se všechny položky aktuální kategorie s ohledem na ostatní nastavené filtry.
- Aktivní žlutá hvězdička: zobrazují se pouze oblíbené položky aktuální kategorie.
- Filtr se vztahuje na právě zobrazenou kategorii: akce, místa nebo kapely.
- Stav globálního filtru zůstává zachován při přepínání mezi kategoriemi. Pokud je filtr aktivní u kapel a uživatel přejde na místa, zobrazí se jeho oblíbená místa.
- Vypnutí globální hvězdičky zobrazí všechny položky aktuální kategorie, stále s ohledem na ostatní filtry.
- Filtr oblíbených se kombinuje s ostatními filtry pomocí logiky AND. Ostatní filtry se při zapnutí ani vypnutí oblíbených neresetují.
- Pokud uživatel v aktuální kategorii nemá žádné oblíbené položky, zobrazí se prázdný seznam a zpráva: **„Zatím nemáte žádné oblíbené položky.“**
- Pokud uživatel odebere položku, která je právě zobrazena v seznamu filtrovaném na oblíbené, položka se ze seznamu ihned odstraní.
- Přidání nebo odebrání oblíbené položky se musí projevit bez nutnosti ručního obnovení stránky.

## Trvalost a synchronizace

- Oblíbené položky jsou vždy vázané na přihlášené `user_id`.
- Data zůstávají uložena v databázi i po odhlášení a opětovném přihlášení.
- Session ani cookie nesmí být jediným úložištěm oblíbených položek.
- Stav hvězdiček v detailech i globální filtrování vychází ze stejných databázových tabulek.
- Přidávání, odebírání a filtrování musí fungovat konzistentně pro všechny tři typy entit.
.
