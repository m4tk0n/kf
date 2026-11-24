# DEBUG PANEL – NASTAVENÍ, NAČÍTÁNÍ A FUNKČNOST

## 1. Účel

Debug panel je diagnostický nástroj pro vývoj a ladění K&F. Zaznamenává dění v prohlížeči, aby bylo možné dohledat chyby JavaScriptu, neplatné požadavky, HTTP odpovědi, kliknutí a další události.

Jeho úkolem je chybu odhalit a poskytnout informace k opravě její skutečné příčiny v aplikačním kódu. Debug nesmí chyby potlačovat, maskovat ani měnit chování aplikace.

## 2. Zapnutí a načítání

Debug JavaScript se do HTML stránek vkládá v `index.php`:

```php
$GLOBALS['headScripts'] = '<script src="' . e(url('debug/debug.js')) . '" defer></script>';
```

Tento řádek nastavuje proměnnou `headScripts`. Ta se předává do šablony také funkcí `page()` v `src/views.php` a vkládá se v `templates/layout.php` do HTML hlavičky:

```php
<?= $headScripts ?? '' ?>
```

Atribut `defer` zajistí spuštění skriptu po parsování HTML dokumentu. Po spuštění skript vytvoří tlačítko DEBUG a při otevření panel.

### Pořadí vykonávání v index.php

Při změně umístění načítacího řádku je nutné zachovat správné pořadí:
- `kf_dispatch()` zpracovává samostatné routy (například přihlášení, profil nebo administraci).
- Přiřazení `$GLOBALS['headScripts']` následuje až po dispatchi.
- Poté se zpracovávají detaily akcí, kapel a klubů a běžný výpis.

Proto je debug dostupný na běžném výpisu a detailech vykreslených po tomto řádku. Routy, které se kompletně vykreslí a ukončí uvnitř `kf_dispatch()`, mohou skončit dříve, než je proměnná nastavena; jejich pokrytí je nutné ověřit samostatně. Částečné HTML odpovědi a JSON endpointy, které nepoužívají společnou šablonu, debug skript samostatně nenačítají. Jejich Fetch požadavky ale může zaznamenat již otevřená stránka s aktivním debugem.

## 3. Jak debug vypnout

V současné implementaci není přepínač v administraci ani konfigurační volba v `.env` nebo `config.php`.

Vypnutí se provede zakomentováním nebo odstraněním přiřazení `$GLOBALS['headScripts']` v `index.php`:

```php
// $GLOBALS['headScripts'] = '<script src="' . e(url('debug/debug.js')) . '" defer></script>';
```

Tím se skript přestane vkládat do stránek, nevytvoří se tlačítko ani panel a nezačne nové zaznamenávání. Soubory v `/debug/` se nemažou; opětovné zapnutí znamená vrátit uvedený řádek.

Vypnutí načítání nemaže dříve uložená data v `sessionStorage`. Po opětovném zapnutí mohou být ve stejné kartě znovu načtena. Historii lze vymazat tlačítkem **Vymazat** nebo odstraněním klíče `kf-debug-state` z `sessionStorage` dané karty.

## 4. Soubory a jejich odpovědnost

| Soubor | Úloha |
|---|---|
| `index.php` | Zapíná načtení debug skriptu přes `$GLOBALS['headScripts']`. |
| `src/views.php` | Funkce `page()` předává `headScripts` do společné šablony. |
| `templates/layout.php` | Vkládá předané skripty do HTML hlavičky. |
| `debug/debug.js` | Vytváří panel, sbírá a vykresluje události, filtruje je, ukládá stav, kopíruje historii a sleduje Fetch. |
| `debug/debug.css` | Vzhled, umístění, rozměry, barvy a scrollování panelu. |
| `assets/js/favorites.js` | Není součástí debug panelu. Jde o aplikační kód oblíbených položek; jeho chyba byla pomocí debug panelu odhalena a opravena. |

Soubory, které přímo tvoří debug panel, jsou v adresáři `/debug/`. Ostatní uvedené soubory zajišťují vložení skriptu do stránky nebo představují kód aplikace, který panel diagnostikuje.

## 5. Ovládání panelu

### Tlačítko DEBUG
Po načtení skriptu se vlevo dole vytvoří plovoucí tlačítko **DEBUG**. Kliknutím se otevře panel a tlačítko se skryje.

### Přesouvání
Panel lze přesouvat uchopením horní lišty. Používá Pointer Events, takže funguje myší i dotykem. Tlačítka a výběr filtru nejsou úchytem.

### Kopírovat
Tlačítko **Kopírovat** kopíruje celou uloženou historii, nikoliv pouze právě zobrazený filtr. Použije se `navigator.clipboard.writeText()`, pokud je dostupné; jinak se zobrazí textové okno s obsahem ke zkopírování.

### Vymazat
Tlačítko **Vymazat** smaže historii v paměti a uloží prázdný seznam. Další události se zaznamenávají znovu. Filtr, poloha a stav otevření panelu zůstávají zachovány.

### Zavřít
Tlačítko `×` panel zavře a zobrazí tlačítko DEBUG. Historie se nemaže.

## 6. Filtry

Filtr mění pouze zobrazení. Události nemaže ani nepřepisuje.

| Volba | Obsah |
|---|---|
| **Vše** | Všechny zaznamenané události. |
| **Chyby** | Typy obsahující `ERROR` a HTTP odpovědi 4xx/5xx. |
| **Síť** | Zahájení Fetch požadavku, HTTP odpovědi a chyby Fetch. |
| **Akce** | Kliknutí na odkazy/tlačítka a volání `alert()`. |
| **Systém** | Spuštění debug skriptu (`START`) a informační záznamy (`INFO`). |

Přepnutí z **Vše** na **Chyby** ostatní události pouze skryje. Po návratu na **Vše** je celá zachovaná historie znovu viditelná.

### Filtr Chyby

Zahrnuje:
- `JS ERROR` – neodchycená chyba JavaScriptu nebo chyba načítání skriptu.
- `PROMISE ERROR` – odmítnutý Promise bez obsluhy.
- `FETCH ERROR` – odmítnutý Fetch požadavek nebo diagnosticky zachycený neplatný vstup, například `fetch(null)`.
- `HTTP 4xx` a `HTTP 5xx` – klientská nebo serverová chybová HTTP odpověď.

HTTP 2xx a 3xx nejsou ve filtru Chyby. Ve filtru Síť se zobrazí.

## 7. Typy zaznamenávaných událostí

### START
Vzniká při inicializaci panelu. Obsahuje adresu otevřené stránky. Při každém načtení celé stránky vzniká nový záznam START.

### JS ERROR
Zachycuje událost `window.error`. Záznam obsahuje čas, typ a text chyby, soubor, číslo řádku a sloupce a zásobník volání, pokud jej prohlížeč poskytne.

### PROMISE ERROR
Zachycuje událost `unhandledrejection`. Obsahuje důvod odmítnutí Promise a dostupný zásobník volání.

### ALERT
Před voláním původní funkce `window.alert()` uloží zprávu a zásobník volání. Poté zavolá původní nativní alert. Upozornění tedy neruší ani nepotlačuje.

### CLICK
Zachycuje kliknutí na nejbližší prvek `a` nebo `button`. Záznam může obsahovat typ prvku, jeho ID, URL odkazu a text prvku. Posluchač běží v režimu capture, tedy před běžnými posluchači aplikace. Kliknutí na jiné prvky se tímto pravidlem nezaznamenává.

### FETCH
Vzniká při zahájení volání `window.fetch()`. Obsahuje URL nebo textovou reprezentaci předaného vstupu.

### HTTP xxx
Po přijetí odpovědi zaznamená HTTP status, URL a dobu od zahájení požadavku do přijetí odpovědi. HTTP odpověď 4xx/5xx je stále platná HTTP odpověď; aplikace musí vyhodnotit její status a obsah.

### FETCH ERROR
Zaznamená odmítnutý Fetch Promise a chybu nebo její zásobník. HTTP 4xx/5xx se zaznamenává jako `HTTP 404`, `HTTP 500` atd., nikoliv jako odmítnutí Fetch Promise.

### INFO
Informační záznam, aktuálně například potvrzení zkopírování diagnostiky.

## 8. Jak funguje sledování Fetch

Při spuštění si `debug.js` uloží původní `window.fetch` do proměnné `originalFetch` a nahradí veřejnou funkci obalovací funkcí.

Postup při požadavku:
1. Zaznamená čas zahájení.
2. Přečte vstupní argument a pokusí se získat URL.
3. Pokud je vstup `null` nebo `undefined`, zapíše `FETCH ERROR` s diagnostickým zásobníkem volání.
4. Zapíše událost `FETCH`.
5. Zavolá původní nativní `fetch()` se stejnými argumenty a stejným kontextem.
6. Po přijetí odpovědi zapíše HTTP status a dobu požadavku.
7. Při odmítnutí Promise zapíše `FETCH ERROR` a odmítnutí předá dál.

Debug nesmí měnit URL, metodu, hlavičky, tělo ani přihlašovací údaje požadavku. Nesmí chybu požadavku potlačit. Pokud původní Fetch selže, aplikace musí dostat odmítnutý Promise stejně jako bez debug panelu.

Neplatný vstup, například `fetch(null)`, se zaznamená, ale debug nesmí předstírat, že požadavek proběhl správně. O skutečném výsledku rozhoduje původní nativní Fetch.

## 9. Ukládání historie a nastavení

Panel používá `sessionStorage` pod klíčem:

`kf-debug-state`

Ukládá:
- historii událostí,
- zvolený filtr,
- zda je panel otevřený,
- polohu panelu (`left`, `top`, `right`, `bottom`).

Historie je omezena na posledních **200 událostí**. Při vykreslení se zobrazí nejvýše posledních **100 událostí odpovídajících filtru**. Kopírování zahrnuje všech až 200 uložených událostí bez ohledu na filtr.

Použití `sessionStorage` znamená:
- stav přežije načtení další stránky ve stejné kartě,
- jednotlivé karty mají oddělené úložiště,
- data se neukládají na server ani do databáze,
- prohlížeč může data po ukončení relace/karty odstranit,
- nejde o centrální aplikační log.

Při navigaci na detail se historie načte ze sessionStorage, obnoví se filtr, otevření a poloha panelu a pokračuje se v zaznamenávání.

## 10. Vzhled a rozměry

Základní vzhled je v `debug/debug.css`:
- tlačítko DEBUG je pevně umístěné vlevo dole,
- panel je pevně umístěný vlevo dole, dokud jej uživatel nepřesune,
- šířka je maximálně 560 px a současně se přizpůsobuje šířce displeje,
- maximální výška je menší z hodnot 33 % výšky viewportu a 280 px,
- obsah má vlastní svislé posouvání,
- písmo je monospace, velikost 11 px,
- chybové řádky jsou červené,
- vysoký `z-index` udržuje panel nad obsahem stránky.

Po překreslení výpisu se obsah posouvá dolů, aby byl viditelný poslední záznam. Posun se provádí ve dvou následujících animačních snímcích, protože výška obsahu se může dopočítat až po aktualizaci DOM.

## 11. Příklad odhalené chyby v oblíbených položkách

Při otevření detailu akce se objevila chyba:

`Cannot read properties of null (reading 'url')`

Záznam ukázal, že chyba vznikla v obalovací funkci Fetch v `debug/debug.js`, kterou zavolal `assets/js/favorites.js`.

Příčinou byl selektor:

`ev.target.closest('[data-fav]')`

Atribut `data-fav` nepoužívá pouze tlačítko hvězdičky, ale také skrytý element `data-view-state`, který nese stav výpisu. Kliknutí na odkaz koncertu tak mohlo způsobit, že obsluha oblíbených položek vybrala skrytý stavový element místo tlačítka. Jeho `data-url` neexistovalo a následovalo `fetch(null)`.

Oprava v `assets/js/favorites.js`:
- selektor byl zúžen na `button[data-fav]`,
- skrytý stavový element už nemůže být považován za tlačítko oblíbených položek.

Doplňující diagnostika v `debug/debug.js`:
- neplatný vstup `null` nebo `undefined` do Fetch se zaznamená včetně zásobníku volání,
- čtení URL je bezpečné i pro nečekané typy vstupu,
- diagnostické logování nesmí zabránit zavolání původního Fetch.

Správný princip: debug chybu odhalí a umožní opravit její skutečný zdroj v aplikačním kódu. Nesmí se používat k jejímu skrytí.

## 12. Bezpečnost a omezení

- Debug je klientský nástroj. Neprovádí zápisy do databáze ani na server.
- Historie je uložena pouze v sessionStorage aktuální karty.
- Kopírované záznamy mohou obsahovat URL, text chyb a zásobníky volání. Před sdílením je nutné ověřit, že neobsahují citlivé údaje.
- Chyby uvnitř aplikačního `try/catch`, které aplikace sama zpracuje a nikam nepředá, se nemusí objevit jako JS ERROR nebo PROMISE ERROR.
- Kliknutí se zaznamenávají pouze na odkazy a tlačítka.
- Fetch wrapper sleduje `window.fetch`. Přímé načítání obrázků a požadavky jinými mechanismy nemusí být zachyceny.
- Debug nenahrazuje PHP error log, serverové logy ani bezpečnostní log.
- Při vypnutém debug skriptu se nové klientské události nezaznamenávají.

## 13. Pravidla pro další úpravy

1. Diagnostický kód zůstává v adresáři `/debug/`, pokud není nutné změnit místo jeho vložení.
2. Debug musí jít vypnout bez mazání diagnostických souborů.
3. Debug nesmí potlačovat chyby aplikace ani měnit chování aplikačních požadavků.
4. Každý nový typ události musí být přiřazen k odpovídajícímu filtru.
5. Filtry nesmí mazat ani přepisovat historii.
6. Kopírování musí zahrnovat všechny uložené události, nejen aktuální filtr.
7. Historie, filtr a poloha panelu se zachovávají při navigaci ve stejné kartě.
8. Při změně načítání skriptu ověřit běžný výpis, detail akce, detail kapely, detail klubu a samostatné stránky vykreslované přes `page()`.
9. Po úpravě ověřit, že poslední záznam zůstává viditelný a že filtry **Chyby** a **Vše** fungují bez ztráty historie.
