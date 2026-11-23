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

Datum akce z detailu nesmí při návratu přepsat datum původního výpisu. Například pokud uživatel otevře detail akce z výpisu pro 26. 11. 2026, návrat musí obnovit výpis pro 26. 11. 2026, i když samotná akce začíná 19. 12. 2026.


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
