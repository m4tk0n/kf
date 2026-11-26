# K&F Developer Tools

Debug se zapíná a vypíná na stránce **DEBUG** v uživatelském menu (`/diagnostics`). Nastavení se ukládá do JSON profilu uživatele. Stránku i panel smí používat pouze uživatel s ID 2. Vypnutý debug nenačítá žádné JS ani CSS.

## Integrace

Veřejné funkce zůstaly stejné: `kf_debug_enabled()`, `kf_debug_assets()`, `kf_debug_render()`.

Nové je volitelné: pro záložku **Server** přidej do pole `$get` v `kf_dispatch()` (`index.php`) jeden řádek:

```php
'diagnostics/log' => 'route_diagnostics_log',
```

Bez něj panel funguje, jen záložka Server ukáže „chybí route“.

`debug.js` se načítá bez `defer`, aby zachytil chyby a požadavky i z ostatních skriptů. `kf_debug_assets()` proto může zůstat v `<head>`.

## Záložky

| Záložka | Co ukazuje |
|---|---|
| **Console** | `console.*` s filtrem úrovní, slučuje opakované zprávy (×N), u warn/error zásobník volání |
| **Network** | fetch, XHR a volitelně assety. Rozbalení řádku ukáže tělo požadavku, typ, cache, přesměrování a náhled odpovědi. Rozpracované požadavky jsou vidět hned, pomalé jsou zvýrazněné. Filtry: Fetch/XHR, Assety, Chyby, Pomalé |
| **Errors** | JS chyby, Promise, nenačtené zdroje, HTTP 4xx/5xx včetně těla odpovědi, **porušení CSP**, `fetch(null)` |
| **Events** | kliknutí (včetně `data-*`), odeslání formulářů (varuje při chybějícím `_csrf`), `pushState`/`popstate`, doba přenačtení `#view`, posuny layoutu (CLS), dlouhé úkoly, `alert`/`confirm` |
| **App** | parametry URL včetně rozbaleného `return`, stav výpisu (počet položek, pager, filtr oblíbených, hvězdičky), verze JS/CSS podle `?v=filemtime` (odhalí duplicity a cizí původ), session/localStorage, čitelné cookies |
| **Stránka** | TTFB, FCP, LCP, CLS, dlouhé úkoly, DOMContentLoaded/Load, nejpomalejší a největší zdroje, velikost DOM, paměť, zařízení (DPR, dotyk, tmavý režim, připojení) |
| **Kontroly** | přetečení šířky viewportu (horizontální posun), duplicitní `id`, inline `on*` atributy (blokuje CSP), POST formuláře bez `_csrf`, nenačtené/velké obrázky, chybějící `alt`, malé dotykové prvky, pole bez popisku, více/žádné H1, cizí skripty, velikost DOM. Klik na nález prvek na stránce zvýrazní a posune se k němu |
| **Server** | čas generování HTML, špička paměti, verze PHP, počet načtených souborů, uživatel/role/jazyk; poslední řádky PHP error logu a bezpečnostního logu |
| **⚙** | přepínače, hranice „pomalého“ požadavku, výška panelu, velikost písma, umístění, reporty |

## Přepínače (⚙)

Zachovat záznamy mezi stránkami · ukládat těla odpovědí · zobrazit assety · zaznamenávat kliknutí / navigaci · automatické kontroly DOM · označit problémové prvky na stránce · označit malé dotykové prvky · obrysy všech prvků · rozměry viewportu v rohu · odsadit stránku o výšku panelu.

## Ovládání

- Záhlaví: ❚❚ pozastavit, ⌫ vymazat, ⧉ zkopírovat report, ⇅ nahoru/dolů, ▾ sbalit (`Alt+D`).
- Výšku změníš tažením úchytu na okraji panelu.
- Klepnutí na řádek ho rozbalí. Označený text se nerozbaluje, jde kopírovat.
- **Report** (⧉ nebo ⚙) je text vhodný k vložení do chatu: chyby, selhané požadavky, console, network, události, nálezy kontrol a výkon.

## Soukromí

Parametry `_csrf`, `token`, `code`, `state`, `key`, `password`, `secret` se v URL i tělech požadavků nahrazují `***`. Hesla z formulářů se nezaznamenávají. HttpOnly cookies (`KF_SESSID`) JavaScript nevidí. Záznamy žijí v paměti stránky a v `sessionStorage` karty. Nic se neposílá na server kromě čtení logů v záložce Server.

## Testovací profily

- Definice pěti testovacích profilů je v `debug/test_profiles.sql`.
- Vytvoření se nespouští při běžné instalaci ani automaticky ze složky seedů.
- V instalátoru je samostatné tlačítko **Vytvořit testovací profily**.
- Akce ověřuje, že v databázi existuje hlavní účet s ID 2, a potom profily vytvoří nebo aktualizuje bez duplicit.
- Testovací identity používají interního poskytovatele `provider = 3`; nejsou dostupné přes Google ani Facebook OAuth.
- Profil „User blokovaný“ používá stav 2. Může se přihlásit, ale aplikace mu dovolí pouze prohlížení; nemůže přidávat, upravovat, schvalovat ani měnit oblíbené položky.
- Přepínač je na `/debug/users` a je dostupný jen při skutečném přihlášení aktivního administrátora ID 2.
- Přepnutí používá krátkodobou podepsanou HttpOnly cookie `KF_DEBUG_PROFILE`; nevytváří ani nemění databázovou session testovacího účtu. Skutečná session administrátora zůstává zachována.
- Efektivní identita ovlivňuje UI, serverová oprávnění i autora nových záznamů. CSRF a kontrola čerstvého přihlášení zůstávají navázané na skutečnou session administrátora.
- Odkaz **Profily** je také v hlavičce Developer Tools. Přepnutí zpět na „Skutečný administrátor“ nebo ukončení simulace odstraní výběrovou cookie.
- Simulovaný administrátor nesmí změnit ani odstranit skutečný účet ID 2. Ostatní oprávnění odpovídají právě zvolenému profilu.
