# Přidávání kapel

## Formulář `/band/new`

Přidávání kapely používá stejný dvoukrokový postup jako akce a kluby:

1. Uživatel vyplní údaje a nahraje obrázky.
2. Zobrazí se náhled detailu kapely.
3. Pod detailem se zobrazí samostatný náhled položky ze seznamu kapel, včetně malého loga.
4. Uživatel se může vrátit k úpravám, odeslat návrh nebo rozpracování zrušit.

### Údaje

- Název kapely (povinný).
- Město a stát (povinné); používá se našeptávač měst a států stejně jako u klubů. Nové město se uloží pouze do návrhu a do tabulky `cities` se zapíše až při schválení nebo přímém zveřejnění.
- Žánr (povinný výběr z tabulky `genres`).
- Web, Facebook a Bandzone (nepovinné URL).
- Popis (nepovinný, max. 5000 znaků).

### Dva samostatné obrázky

- Malé logo se ukládá jako `img/bands/{bucket}/{id}_s.webp` a používá se ve výpisu kapel.
- Obrázek detailu se ukládá jako `img/bands/{bucket}/{id}_l.webp` a používá se na detailní stránce.
- Každý obrázek lze nahrát ze souboru nebo stáhnout z URL přes společnou bezpečnou HTTP funkci. Oba se ověří podle MIME, velikosti a rozměrů a převedou do WebP.
- Obrázky jsou nepovinné; při absenci se příslušná část nezobrazí. V rozpracovaném formuláři je lze vyměnit nebo odstranit.
- V draftu se ukládají odděleně v `data/tmp`, v pending vedle JSON návrhu jako `{id}_s.webp` a `{id}_l.webp`.

### Náhled a zveřejnění

Náhled detailu používá stejnou šablonu jako skutečný detail kapely. Bezprostředně pod ním je výpisová položka se stejnou třídou a rozložením jako řádek v katalogu. Po odeslání vznikne návrh typu band; role s oprávněním `publish_direct` kapelu zveřejní rovnou. Při schválení se znovu ověří údaje, město a duplicita.

### Uložení

Tabulka `bands` již obsahuje `city_id`, `genre_id` a příznak `logo`. Web, Facebook, Bandzone, popis a příznak velkého obrázku jsou uloženy v JSON `data/bands`. Pro malé logo se používá DB příznak `logo=1`; pro detailní obrázek JSON `image_l=true`.
