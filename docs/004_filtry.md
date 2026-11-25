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
- Z parametru `s` načte pouze jednu aktuální sekci: `events`, `bands` nebo `venues`. Zobrazuje jen možnosti používané v publikovaných záznamech této sekce.
- Uživatel mění pouze dočasný výběr této sekce. Ostatní sekce převezmou dosavadní dočasné nastavení, nebo při prvním použití aktuální trvalé nastavení uživatele / výchozí zobrazení hosta.
- Tlačítko Uložit předá celý stav do `window.kfSetSessionFilters()` a uloží jej do `sessionStorage`. Nikdy neodesílá POST a nikdy nezapisuje do JSON profilu.
- Po uložení se návštěvník vrátí na původní výpis včetně sekce, hledání a pozice pageru. Stávající `assets/js/filters.js` následně obnoví výpis s dočasným stavem.
- Prázdný výběr v aktuální sekci znamená nezobrazit v této sekci žádné záznamy; neovlivní jiné sekce.
- Checkbox **Vybrat vše** na stránce `quick-filters` se vztahuje pouze na právě otevřenou sekci, nikoli na všechny tři katalogy.
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

## Platnost hodnot a stejné chování všech sekcí

- Země a města se nabízejí pouze tehdy, pokud jsou použity ve zveřejněných záznamech příslušné sekce: Akce (`events`), Kapely (`bands`) nebo Kluby a místa (`venues`). Žánry se nabízejí pouze tehdy, pokud je používá zveřejněná kapela.
- Každá sekce má vlastní dostupné hodnoty a vlastní výběr. Načtení nebo změna filtrů jedné sekce nesmí spouštět filtrování ani měnit nastavení jiné sekce.
- **Výběr země má přednost před výběrem měst.** Pokud je vybrána alespoň jedna země, zobrazí se všechny záznamy dané sekce z vybrané země nebo zemí. Současně zaškrtnutá města výsledek nijak neomezují.
- Pokud není vybrána žádná země, ale je vybráno jedno nebo více měst, zobrazí se pouze záznamy z těchto měst.
- Pokud není vybrána žádná země ani město a není aktivní volba **Vše**, nezobrazí se žádné záznamy v dané sekci. Prázdný výběr se posuzuje samostatně pro Akce, Kapely a Kluby a místa; výběr v jedné sekci nikdy nepovolí všechny záznamy v jiné sekci. Volba **Vše** zobrazí všechny záznamy ve všech sekcích.
- Individuální checkboxy se při změně navzájem automaticky nevynulují. Checkbox **Vše** pouze odráží stav všech jednotlivých voleb; uživatel si vlastní výběr určuje přímo.
- Výpis, počty, pager a kalendář akcí používají stejné podmínky filtrování dané sekce.
