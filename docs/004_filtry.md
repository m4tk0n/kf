# Filtry ve výpisech

**Stav: implementováno v kódu, čeká na praktické ověření.**

Tento dokument popisuje rozdíl mezi trvalým nastavením filtrů v uživatelském menu a dočasnými změnami filtrů přímo ve výpisech.

## 1. Dva druhy nastavení

Aplikace rozlišuje:

- **Trvalé nastavení** – výchozí preference uživatele uložené v JSON profilu na serveru. Upravuje se v menu `user_menu_filter`.
- **Dočasné nastavení relace** – rychlé změny provedené přímo ve výpisu. Ukládá se pouze do `sessionStorage` prohlížeče a nepřepisuje JSON profil.

Dočasné nastavení má při zobrazování výpisů přednost před trvalým nastavením.

## 2. Přihlášený uživatel

**Závazný postup pro přihlášeného uživatele:**

1. Při načtení výpisu bez dočasných změn se použije trvalé nastavení z JSON profilu.
2. Rychlé změny filtrů přímo ve výpisu se ukládají pouze do `sessionStorage`. Od té chvíle přepisují JSON nastavení pouze pro zobrazování výpisů v aktuální relaci.
3. Při otevření `user_menu_filter` se formulář vždy načte výhradně z trvalého JSON profilu. Nesmí přebírat hodnoty z `sessionStorage`, z parametru `kf_filters` ani z jiného dočasného stavu rychlých filtrů.
4. Uživatel může v `user_menu_filter` změnit hodnoty a výslovně je uložit. Teprve úspěšné uložení aktualizuje JSON profil.
5. Až po úspěšném uložení se smaže dočasné nastavení rychlých filtrů aktuální identity ze `sessionStorage`.
6. Při dalším zobrazení výpisu se použije nově uložené nastavení z JSON profilu.

**Důležité:** Otevření `user_menu_filter`, jeho pouhé prohlížení, zrušení úprav ani neúspěšné uložení nesmí dočasné rychlé filtry smazat. Rychlé filtry nikdy samy o sobě nepřepisují JSON profil.

## 3. Nepřihlášený návštěvník

Nepřihlášený návštěvník může měnit filtry přímo ve výpisech stejně jako přihlášený uživatel.

- Jeho změny se ukládají pouze do `sessionStorage`.
- Nemá trvalé nastavení v JSON profilu.
- Obnovení stránky a běžná navigace zachovají jeho dočasné filtry.
- Po zahájení nové relace se použije výchozí nastavení; předchozí dočasné změny se nepřenášejí.

## 4. Chování úložiště

Pro dočasné filtry se používá `sessionStorage`, nikoli `localStorage`.

Požadavky:

- **Reload / F5:** dočasné filtry zůstávají zachované.
- **Navigace mezi výpisy:** dočasné filtry zůstávají zachované a uplatní se i v jiném katalogu podle podporovaných filtrů.
- **Otevření detailu a návrat:** dočasné filtry zůstávají zachované.
- **Nová relace:** dočasné filtry se nepoužijí; přihlášenému se znovu načte JSON profil a host dostane výchozí nastavení.
- **Žádné trvalé ukládání:** dočasné změny se nesmí ukládat do `localStorage`, cookies určených k dlouhodobému uchování ani do JSON profilu.

`sessionStorage` je vázané na kartu/prohlížečovou relaci. Běžně zanikne po zavření karty, některé prohlížeče ale mohou při obnově zavřených karet obnovit i její session data. Pokud bude požadováno striktní ukončení i při obnově karty, musí implementace doplnit samostatné ověření nové relace.

## 5. Oddělení účtů

Dočasné nastavení přihlášeného uživatele musí být bezpečně oddělené podle identity účtu. Po odhlášení nebo přepnutí účtu se nesmí dočasné filtry předchozího uživatele použít pro jiný účet. Hostovské nastavení se rovněž nesmí zaměnit s nastavením přihlášeného uživatele.

## 6. Zásady implementace

- JSON profil zůstává jediným zdrojem trvalých preferencí přihlášeného uživatele.
- `sessionStorage` představuje pouze dočasné přepsání pro aktuální relaci.
- Pokud pro relaci neexistuje dočasné přepsání, použije se trvalé nastavení z JSON, případně výchozí nastavení hosta.
- Uložení nastavení v menu musí dočasné přepsání odstranit, aby se nově uložené hodnoty ihned projevily.
- Dočasné nastavení musí být jednotné pro všechny relevantní výpisy a jejich součásti (výsledky, stránkování a kalendář). Nesmí se stát, že seznam používá jiné filtry než pager nebo kalendář.
- Při nedostupném `sessionStorage` musí výpis zůstat funkční; použije se trvalé nastavení nebo výchozí nastavení hosta.

## 7. Rychlé filtry

- Tlačítko **Filtry** vedle vyhledávání je dostupné přihlášeným i nepřihlášeným návštěvníkům a vede na veřejnou stránku `/quick-filters`.
- Stránka používá anglický název route `quick-filters`, ale texty UI jsou lokalizované.
- Vedle nadpisu je informační ikona s vysvětlením dočasného charakteru filtrů. Přihlášenému uživateli výslovně sděluje, že změny neovlivňují ani nepřepisují filtry uložené v jeho profilu; host dostane informaci, že výběr není trvalý.
- Při otevření z výpisu (`/?s=events`, `/?s=bands` nebo `/?s=venues`) se parametr `s` zachová v odkazu na rychlé filtry i v návratovém odkazu. Podle něj se automaticky aktivuje odpovídající záložka: Akce, Kapely nebo Kluby a místa. Výchozí sekcí je `events` pouze tehdy, když parametr `s` chybí nebo je neplatný. Stránka zobrazuje tři záložky vedle sebe (Akce, Kapely, Kluby a místa) a všechny tři sekce lze upravit bez opuštění stránky.
- Každá záložka zobrazuje pouze možnosti používané v publikovaných záznamech své sekce. Přepínání záložek nemění ani neukládá výběr.
- Výchozí hodnoty všech tří sekcí se načtou z aktuálního dočasného stavu, případně při prvním použití z trvalého nastavení uživatele / výchozího zobrazení hosta.
- Horní volba **Vybrat vše** ovládá všechny možnosti ve všech třech sekcích. Tlačítko Uložit přečte výběr ze všech tří sekcí, předá celý stav do `window.kfSetSessionFilters()` a uloží jej do `sessionStorage`. Nikdy neodesílá POST a nikdy nezapisuje do JSON profilu.
- Po uložení se návštěvník vrátí na původní výpis včetně sekce, hledání a pozice pageru. Stávající `assets/js/filters.js` následně obnoví výpis s dočasným stavem.
- Prázdný výběr v aktuální sekci znamená nezobrazit v této sekci žádné záznamy; neovlivní jiné sekce.
- Checkbox **Vybrat vše** na stránce `quick-filters` se vztahuje na všechny tři katalogy.
- Trvalá stránka `filters` (uživatelské menu) zůstává oddělená a vyžaduje přihlášení.

## 8. Technické provedení dočasných filtrů

- Klíč v `sessionStorage` je oddělený podle identity: host a konkrétní ID přihlášeného účtu mají vlastní prostor.
- Při změně identity (přihlášení, odhlášení nebo přepnutí účtu) se staré dočasné filtry odstraní.
- JavaScript posílá stav dočasných filtrů serveru pouze jako parametr `kf_filters` u částečných požadavků na výpis a kalendář. Tento parametr je transportní kopie; server přijímá pouze kladná číselná ID pro právě zobrazovanou entitu, používá je jako SQL parametry a nikam je trvale neukládá. Neprovádí kvůli tomu další dotazy na nabídku filtrů.
- Po reloadu se uložený stav znovu načte a výpis se aktualizuje částečným požadavkem. Adresa stránky zůstává bez serializovaných filtrů.
- Serverová funkce `filters_effective($kind)` vybírá dočasný stav, pokud byl předán, jinak trvalý JSON profil přihlášeného uživatele nebo výchozí stav hosta. Při načítání výpisu ověřuje dostupné hodnoty pouze pro právě zobrazovanou entitu.
- `window.kfSetSessionFilters(state)` je programové rozhraní pro budoucí ovládací prvky rychlých filtrů. Přijímá objekt s `show_all`, `none` a `sets`; server přijímá pouze kladná číselná ID a používá je jako SQL parametry.
- Formulář `user_menu_filter` načítá výhradně trvalý JSON profil; dočasný stav ze `sessionStorage` se pro jeho předvyplnění nikdy nepoužije.
- Dočasný stav aktuální identity se smaže až po potvrzeném úspěšném uložení změn v `user_menu_filter`. Při otevření, opuštění bez uložení nebo chybě ukládání zůstává zachován.
- JSON profil zůstává jediným trvalým nastavením.
- Dočasné filtry se uplatňují ve společném SQL filtru, a proto ovlivňují seznam, počty a pager; požadavky kalendáře předávají stejný stav.

## 9. Oddělení implementace

Filtrační logika je soustředěna do samostatných modulů:

- `src/filters/profile.php` – načtení a uložení trvalých filtrů v JSON profilu.
- `src/filters/session.php` – výběr dočasných filtrů pro aktuální požadavek; nikdy neupravuje profil.
- `src/filters/options.php` – nabídka dostupných hodnot z publikovaných záznamů dané entity.
- `src/filters/catalog.php` – SQL podmínky pouze pro právě načítaný katalog.
- `assets/js/filters.js` – `sessionStorage`, oddělení identity, přidání filtračního stavu do požadavků, obnova po reloadu a hromadné ovládání checkboxů trvalého nastavení.

Ostatní soubory obsahují pouze napojení: `bootstrap.php`, `src/catalog.php`, `src/routes.php`, `assets/js/list.js`, `assets/js/calendar.js` a `templates/layout.php`.

Rychlé filtry jsou implementovány na stránce `templates/quick_filters.php` a v modulu `assets/js/quick-filters.js`. `window.kfSetSessionFilters(state)` zůstává společným rozhraním pro uložení dočasného stavu.

## Stát jako souhrnný checkbox

Stát není samostatná filtrační hodnota. Slouží výhradně jako souhrnný checkbox pro hromadné ovládání všech měst daného státu.

Pravidla jsou stejná v rychlých filtrech i v trvalém nastavení v uživatelském menu:

1. Zaškrtnutí státu zaškrtne všechna dostupná města daného státu.
2. Odškrtnutí státu odškrtne všechna jeho města.
3. Pokud uživatel odškrtne kterékoliv jedno město, souhrnný checkbox státu se odškrtne. Ostatní města zůstanou beze změny.
4. Pokud uživatel znovu zaškrtne poslední chybějící město, stát se automaticky zaškrtne.
5. Stát je zaškrtnutý právě tehdy, když jsou zaškrtnuta všechna dostupná města daného státu.
6. Do filtračního stavu, dočasného úložiště, JSON profilu ani serverových SQL podmínek se nikdy neposílá ID státu. Ukládají a filtrují se pouze ID konkrétních měst.
7. Pokud není vybráno žádné město (a v sekci kapel ani žádný žánr) a není aktivní volba **Vše**, nezobrazí se v dané sekci žádné záznamy.

Nabídka států pro souhrnné checkboxy se sestavuje z vazeb měst, které se vyskytují ve zveřejněných záznamech příslušné sekce. Samotný stát nemá vlastní filtrační dotaz ani prioritu nad výběrem měst.

Starší uložené profily, které obsahují samostatná ID států, se při načtení automaticky převedou na seznam všech dostupných měst těchto států. Původní položka států se z profilu odstraní.

Konfigurace filters.sections proto obsahuje pouze skutečné filtrační hodnoty:
- Akce: města.
- Kapely: města a žánry.
- Kluby a místa: města.

Checkbox **Vybrat vše** nadále hromadně ovládá skutečné filtrační možnosti ve všech sekcích; souhrnné checkboxy států pouze odrážejí a hromadně mění výběr měst v příslušné skupině.

