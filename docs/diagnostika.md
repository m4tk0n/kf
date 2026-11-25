# Diagnostika (K&F Developer Tools)

## Účel a umístění

Diagnostika je vývojový panel integrovaný do běžného rozhraní K&F. Její implementace je oddělená od ostatních částí aplikace a všechny její soubory a PHP funkce jsou v adresáři `/debug/`.

Hlavní soubory:

- `debug/debug.php` – oprávnění, zapnutí/vypnutí, načítání prostředků, vykreslení panelu a serverová data.
- `debug/debug.js` – zachytávání událostí, záznamy, záložky, kontroly a ovládání panelu.
- `debug/debug.css` – vzhled panelu.
- `debug/README.md` – technický přehled funkcí a ovládání.

## Kdo smí diagnostiku používat

Přístup je záměrně omezen na **uživatelský účet s ID 2**. Nestačí role administrátora:

- účet musí být přihlášený;
- jeho ID musí být přesně `2`;
- ostatní účty, včetně případných dalších administrátorů, diagnostiku používat nemohou.

U účtu ID 2 se v uživatelském menu zobrazuje položka **Diagnostika**. Přímý přístup na `/diagnostics` i požadavek na serverové logy jsou kontrolovány na serveru. Nepovolený přístup končí odpovědí 403 a zaznamená se do bezpečnostního logu.

## Kde se diagnostika nastavuje a jak ji aktivovat

Není k tomu potřeba měnit `.env` ani konfigurační soubor.

1. Přihlásit se účtem s ID 2.
2. Otevřít uživatelské menu.
3. Zvolit **Diagnostika** (adresa `/diagnostics`).
4. Stránka ukáže aktuální stav a nabídne tlačítko **Zobrazit** nebo **Vypnout**.
5. Tlačítkem se stav přepne.

Stav se ukládá do JSON profilu uživatele pod klíčem `diagnostika`. Aktivní je pouze při skutečné booleovské hodnotě `true`. Výchozí stav pro účet bez tohoto nastavení je vypnuto.

Vypnutí diagnostiky odstraní panel z vykreslené stránky a aplikace nenačte její JavaScript ani CSS. Diagnostika se tedy nespouští jen proto, že její soubory na serveru existují.

## Jak se načítá

- `bootstrap.php` načte PHP modul `debug/debug.php`.
- `templates/layout.php` zavolá `kf_debug_enabled($user)`.
- Pouze při aktivním nastavení oprávněného účtu vloží do `<head>` CSS a JavaScript přes `kf_debug_assets()`.
- Panel se vykreslí na konci stránky přes `kf_debug_render()`.
- CSS a JS mají verzi odvozenou z `filemtime()`, takže se po změně souboru načte nová verze.
- JavaScript se záměrně načítá bez `defer`, aby mohl zachytit chyby a požadavky ostatních skriptů od jejich začátku.

Veřejné integrační funkce modulu jsou `kf_debug_enabled()`, `kf_debug_assets()` a `kf_debug_render()`.

## Co diagnostika umí

Panel má devět záložek:

| Záložka | Obsah |
|---|---|
| **Console** | Zachytává `console.log/info/warn/error/debug`, filtruje úrovně, slučuje opakované zprávy a u varování/chyb přidává zásobník volání. |
| **Network** | Sleduje `fetch` a XHR; volitelně také načítání assetů. Zobrazuje metodu, URL, stav, trvání, typ odpovědi, cache a přesměrování. Požadavky jsou vidět už během běhu; pomalé a chybné jsou zvýrazněné. Podporuje filtry Fetch/XHR, Assety, Chyby a Pomalé. |
| **Errors** | Zachycuje JavaScriptové chyby, neošetřené Promise rejection, selhání načtení zdrojů, HTTP 4xx/5xx, porušení CSP a volání `fetch(null)`. U dostupných textových chybových odpovědí zobrazí i tělo odpovědi. |
| **Events** | Zaznamenává kliknutí a atributy `data-*`, odeslání formulářů (včetně upozornění na chybějící CSRF pole), navigaci History API, `popstate`/změny hashe, změny připojení, `alert`/`confirm`, posuny layoutu a dlouhé úlohy. |
| **App** | Zobrazuje parametry URL včetně dekódovaného `return`, stav výpisu a pageru, filtr oblíbených, hvězdičky, verze JS/CSS, session/localStorage a čitelné cookies. Verze assetů pomáhají odhalit duplicity a načtení z jiného zdroje. |
| **Stránka** | Měří TTFB, FCP, LCP a CLS, DOMContentLoaded/Load, dlouhé úlohy, nejpomalejší a největší zdroje, velikost DOM, dostupnou paměť a vlastnosti zařízení (DPR, dotyk, tmavý režim, připojení). |
| **Kontroly** | Prověřuje horizontální přetečení, duplicitní ID, inline `on*` atributy, POST formuláře bez CSRF, obrázky, chybějící `alt`, malé dotykové cíle, popisky polí, počet H1, cizí skripty a velikost DOM. Kliknutí na nález zvýrazní příslušný prvek a posune k němu stránku. |
| **Server** | Ukazuje čas generování HTML, špičku paměti, limit paměti, verzi PHP, počet načtených PHP souborů, ID/roli uživatele, jazyk, prostředí, CSRF pole a stav OPcache. Načítá také posledních 80 řádků PHP error logu a 60 řádků bezpečnostního logu. |
| **⚙ Nastavení** | Umožňuje nastavit rozsah a chování záznamů, vizuální zvýraznění, umístění a velikost panelu a vytvořit report. |

### Nastavení panelu

V záložce ⚙ lze přepínat:

- uchovávání záznamů mezi stránkami;
- ukládání těl odpovědí;
- zobrazování síťových assetů;
- zaznamenávání kliknutí a navigace;
- automatické kontroly DOM;
- označování problémových prvků a malých dotykových cílů;
- obrysy všech prvků;
- indikátor rozměrů viewportu;
- odsazení stránky o výšku panelu.

Dále lze nastavit hranici pomalého požadavku, výšku panelu, velikost písma a jeho umístění.

Výchozí nastavení v aktuálním JavaScriptu: záznamy mezi stránkami zapnuté, těla odpovědí vypnutá, assety skryté, automatické kontroly zapnuté, zvýrazňování problémových prvků zapnuté, hranice pomalého požadavku 800 ms a panel dole.

### Ovládání panelu

- **❚❚** pozastaví/znovu spustí zaznamenávání.
- **⌫** vymaže záznamy.
- **⧉** zkopíruje report do schránky.
- **⇅** přepne panel nahoru/dolů.
- **▾** sbalí/rozbalí panel; klávesová zkratka je **Alt+D**.
- Výšku lze měnit tažením úchytu na okraji panelu.
- Řádek lze rozbalit klepnutím; označený text zůstává možné kopírovat.

Report je text určený k vložení do diagnostického chatu. Obsahuje chyby, neúspěšné požadavky, záznamy Console a Network, události, nálezy kontrol a údaje o výkonu.

## Ukládání záznamů a nastavení

- Nastavení panelu se ukládá do `localStorage` pod klíčem `kf-devtools-settings-v3`.
- Záznamy mezi stránkami se ukládají do `sessionStorage` aktuální karty pod klíčem `kf-devtools-log-v3`, pokud je zapnuté jejich uchovávání.
- Záznamy jsou omezené: v aktuální stránce nejvýše 300 Console, 300 Network, 150 Errors a 300 Events. Do úložiště mezi stránkami se ukládá nejvýše posledních 80 záznamů každého typu; síťové assety se do něj neukládají.
- Nejde o trvalé serverové ukládání diagnostických záznamů. Zavření karty odstraní její `sessionStorage`; nastavení v `localStorage` zůstává v daném prohlížeči.

## Soukromí a bezpečnost

- Citlivé názvy parametrů, například `_csrf`, `token`, `code`, `state`, `key`, `password`, `secret` a další, se při záznamu URL a strukturovaných těl požadavků nahrazují `***`.
- Hodnoty heslových polí formulářů se nezaznamenávají.
- HttpOnly cookie `KF_SESSID` JavaScript nemůže číst.
- Běžné záznamy zůstávají v paměti stránky a případně v `sessionStorage`; neposílají se automaticky na server.
- Jediný síťový požadavek diagnostiky na její serverovou část je načtení logů pro záložku Server z `GET /diagnostics/log`. Tento endpoint znovu ověřuje účet ID 2.
- **Pozor na obsah reportu:** diagnostika může zaznamenat texty z Console, URL, atributy `data-*` a při zapnutí ukládání těl také obsah textových odpovědí. Redakce známých názvů tajných polí není univerzální anonymizace. Před sdílením reportu je proto vhodné zkontrolovat, zda neobsahuje osobní nebo citlivé údaje.

## Integrace záložky Server

V aktuálním `index.php` je v poli GET rout připojeno:

```php
'diagnostics/log' => 'route_diagnostics_log',
```

Bez této routy by panel fungoval, ale záložka Server by nemohla načíst logy. Endpoint vrací JSON s časem načtení a omezeným koncem obou logů; používá hlavičku `Cache-Control: no-store`.

## Související zdrojové soubory

- `bootstrap.php`
- `index.php`
- `debug/debug.php`
- `debug/debug.js`
- `debug/debug.css`
- `templates/layout.php`
- `templates/diagnostics.php`
- `src/views.php` – položka Diagnostika v menu účtu ID 2
- `lang/cs.php` – texty stránky Diagnostika
- `debug/README.md` – podrobný technický popis záložek a ovládání
