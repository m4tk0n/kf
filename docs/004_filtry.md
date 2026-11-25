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

## 7. Rozsah této specifikace

Tento dokument určuje ukládání a životnost nastavení filtrů. Neurčuje vzhled ani rozložení ovládacích prvků `user_menu_filter` nebo rychlých filtrů ve výpisu. Jejich vzhled a ovládání se řeší samostatně.

## 8. Technické provedení dočasných filtrů

- Klíč v `sessionStorage` je oddělený podle identity: host a konkrétní ID přihlášeného účtu mají vlastní prostor.
- Při změně identity (přihlášení, odhlášení nebo přepnutí účtu) se staré dočasné filtry odstraní.
- JavaScript posílá stav dočasných filtrů serveru pouze jako parametr `kf_filters` u částečných požadavků na výpis a kalendář. Tento parametr je transportní kopie; server ho validuje proti aktuálně dostupným hodnotám a nikam ho trvale neukládá.
- Po reloadu se uložený stav znovu načte a výpis se aktualizuje částečným požadavkem. Adresa stránky zůstává bez serializovaných filtrů.
- Společná serverová funkce `filters_effective()` vybírá dočasný stav, pokud byl předán, jinak trvalý JSON profil přihlášeného uživatele nebo výchozí stav hosta.
- `window.kfSetSessionFilters(state)` je programové rozhraní pro budoucí ovládací prvky rychlých filtrů. Přijímá objekt s `show_all`, `none` a `sets`; server přesto znovu ověřuje všechny ID.
- Formulář `user_menu_filter` načítá výhradně trvalý JSON profil; dočasný stav ze `sessionStorage` se pro jeho předvyplnění nikdy nepoužije.
- Dočasný stav aktuální identity se smaže až po potvrzeném úspěšném uložení změn v `user_menu_filter`. Při otevření, opuštění bez uložení nebo chybě ukládání zůstává zachován.
- JSON profil zůstává jediným trvalým nastavením.
- Dočasné filtry se uplatňují ve společném SQL filtru, a proto ovlivňují seznam, počty a pager; požadavky kalendáře předávají stejný stav.

## 9. Oddělení implementace

Filtrační logika je soustředěna do samostatných modulů:

- `src/filters.php` – načtení a uložení trvalých filtrů, validace dočasného stavu, nabídka dostupných hodnot a SQL podmínky filtrů.
- `assets/js/filters.js` – `sessionStorage`, oddělení identity, přidání filtračního stavu do požadavků, obnova po reloadu a hromadné ovládání checkboxů trvalého nastavení.

Ostatní soubory obsahují pouze napojení: `bootstrap.php`, `src/catalog.php`, `src/routes.php`, `assets/js/list.js`, `assets/js/calendar.js` a `templates/layout.php`.

Vzhled a ovládání rychlých filtrů přímo ve výpisu zatím nejsou implementovány. `window.kfSetSessionFilters(state)` je připravené rozhraní pro jejich budoucí napojení.
