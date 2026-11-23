# Chování aplikace

Tento dokument shromažďuje dohodnutá a vyřešená pravidla chování aplikace K&F. Nezakládá se samostatný dokument pro každou jednotlivou opravu nebo funkci; jednotlivá témata se zapisují do odpovídajících kapitol zde.

## Stav pravidel

U každého pravidla rozlišujeme:
- **Dohodnuto** – požadované chování je odsouhlasené.
- **Implementováno** – změna je zapracovaná v aplikaci.
- **Ověřeno** – chování bylo prakticky otestováno.

Stav se nesmí označit jako ověřený pouze na základě úpravy kódu nebo úspěšného commitu.

## 1. Návrat z detailu do výpisu

**Stav: implementováno, čeká na praktické ověření**

Pokud uživatel otevře detail položky z výpisu, musí se při návratu vrátit do stejného kontextu výpisu, ze kterého detail otevřel.

Přenáší se zejména:
- datum výpisu, na kterém byl uživatel,
- vybraný typ katalogu (akce, kapely nebo kluby),
- vyhledávací dotaz,
- aktuální stránka stránkování,
- aktivní filtr oblíbených položek,
- další parametry výpisu, které jsou součástí původního požadavku.

Datum akce z detailu nesmí při návratu přepsat datum původního výpisu. Například pokud uživatel otevře detail akce z výpisu pro 26. 11. 2026, návrat musí obnovit výpis pro 26. 11. 2026, i když samotná akce začíná 19. 12. 2026. Pokud původní výpis neměl datum výslovně nastavené (parametr `d=` je prázdný), musí se tato prázdná hodnota zachovat. Po návratu se tak znovu použije automatický výběr výchozího dne podle aktuálního data; datum začátku otevřené akce se použije pouze při přímém otevření detailu bez návratového kontextu.


### Obnovení polohy konkrétní položky ve výpisu

**Stav: implementováno, čeká na praktické ověření**

Při otevření detailu kliknutím na položku výpisu se do `sessionStorage` dočasně uloží:
- ID otevřené položky,
- její svislá poloha vůči hornímu okraji obrazovky,
- aktuální pozice stránky.

Po návratu z detailu se záznam znovu vyhledá ve výpisu a stránka se posune tak, aby byl zobrazen na stejné pozici jako před otevřením detailu. Nejde pouze o návrat na začátek výpisu ani o prosté obnovení první položky.

Uložení pozice se zachová i při přechodu z detailu na související detail. Po obnovení výpisu se jednorázový stav odstraní, aby se při dalším samostatném otevření výpisu nepoužívala zastaralá pozice.

Pokud prohlížeč úložiště neposkytne nebo se cílový záznam ve výpisu nenachází, výpis zůstane použitelný a zobrazí se běžným způsobem.

Dotčené soubory:
- `templates/list.php` – označení položek výpisu jejich ID,
- `assets/js/list.js` – uložení a obnovení polohy,
- `src/catalog.php` a `src/detail.php` – zachování návratového kontextu detailu (stávající řešení).

### Navigace mezi souvisejícími detaily

Pokud uživatel přejde z jednoho detailu na jiný související detail (například z akce na kapelu nebo klub), musí se původní návratová adresa zachovat. Návrat z kteréhokoli z těchto detailů pak vede zpět do původního výpisu, nikoli na mezilehlý detail.

### Přímé otevření detailu

Pokud uživatel otevře detail přímo a neexistuje původní výpis, použije se bezpečná výchozí návratová adresa. U detailu akce je výchozím datem datum začátku akce.

### Bezpečnost návratové adresy

Návratová adresa se skládá pouze z povolených parametrů aplikace. Nesmí umožnit přesměrování na cizí web ani přenášet libovolnou URL.

## 2. Pager a kalendář

**Stav: dohodnuto; podrobná specifikace je v `docs/001_kalendar.md`.**

Pager a kalendář používají dvě odlišná pravidla:

- **Pager** přechází mezi dny, ve kterých začínají odpovídající akce.
- **Kalendář** označuje každý den, do kterého zasahuje trvání akce, tedy u vícedenní akce všechny dny od začátku do konce včetně.

Tato pravidla se nesmí zaměňovat. Podrobná specifikace kalendáře, filtrování a pageru je vedena v `docs/001_kalendar.md`.

## 3. Přihlášení na více zařízeních

**Stav: specifikováno v samostatném dokumentu.**

Jeden účet může mít současně pouze jednu aktivní session. Přihlášení na druhém zařízení zneplatní session původního zařízení. Na původním zařízení se při dalším požadavku zobrazí modální okno s informací o odhlášení.

Úplná pravidla a ověřovací scénář jsou v `docs/003_session.md`. Tento dokument je zde uveden jako přehledová položka; podrobná specifikace se neduplikuje.

## 4. Pravidlo průběžné dokumentace

Každé chování, které společně projednáme a dohodneme, se zapíše do tohoto dokumentu nebo do již existující tematické specifikace, pokud pro danou oblast existuje.

Po vyřešení problému se doplní:
- jaké chování bylo požadováno,
- jaké chování bylo chybné (pokud šlo o opravu),
- jaké řešení bylo přijato,
- kterých částí aplikace se pravidlo týká,
- stav implementace a ověření.

Při změně dříve dohodnutého chování se aktualizuje původní pravidlo. Nesmí zůstat současně platné dvě rozporné verze.

Před úpravou aplikace je nutné zkontrolovat relevantní dokumentaci. Implementace musí odpovídat dohodnutým pravidlům; existující chování se nesmí svévolně měnit při úpravě jiné části aplikace.

## 5. Průběžné vyhledávání a minimální délka dotazu

**Stav: implementováno, čeká na praktické ověření**

Živé vyhledávání ve výpisu používá společné nastavení minimální délky dotazu určené pro autocomplete:

- Zdroj nastavení: `paging.autocomplete_min_chars` v `config.php`.
- Aktuální hodnota: `2` znaky.
- Pravidlo platí jednotně pro všechny tři katalogy: `events`, `bands` a `venues`.

Chování:

1. Při zadání méně znaků, než stanoví konfigurace, se nový vyhledávací požadavek nespouští.
2. Po dosažení minimální délky se výpis automaticky aktualizuje podle dotazu bez odeslání formuláře a bez obnovení celé stránky.
3. Při mazání znaků z aktivního dotazu se výsledky průběžně aktualizují. Jakmile dotaz klesne pod minimum, zobrazí se celý výpis bez textového filtru.
4. Pokud uživatel změní typ katalogu, přepnutí se provede i tehdy, když je dotaz kratší než minimum. Krátký dotaz se při tom nepoužije jako filtr.
5. Minimum se nesmí natvrdo duplikovat v JavaScriptu. Hodnota se předává z konfigurace aplikace, aby změna `autocomplete_min_chars` automaticky ovlivnila i živé vyhledávání.

Toto pravidlo určuje pouze okamžik spuštění hledání. Samotné živé vyhledávání a našeptávání autocomplete jsou odlišné funkce; mohou sdílet minimální délku dotazu, ale jejich výsledky a účel se nezaměňují.

Dotčené soubory:
- `config.php` – společná hodnota `paging.autocomplete_min_chars`,
- `templates/layout.php` – předání hodnoty minimální délky do vyhledávacího pole,
- `assets/js/list.js` – řízení spouštění živého vyhledávání podle této hodnoty.
