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

**Stav: implementováno v kódu, čeká na praktické ověření po nasazení. Podrobná specifikace je v `docs/001_kalendar.md`.**

Pager přechází mezi **aktivními dny**, nikoli pouze mezi dny začátků akcí a nikoli přes všechny kalendářní dny.

- **Pager** zohledňuje celý interval akce `date_from–date_to`. Vícedenní festival proto vytvoří aktivní den pro každý den svého trvání včetně prvního a posledního.
- **Výpis** pro zvolený den zachovává pokračující akce a zobrazuje také následující akce, které ještě neskončily.
- **Kalendář** označuje každý den, do kterého zasahuje trvání akce.

Příklad: festival 14.–16. 2. 2028 a další akce 28. 2. 2028 vytvoří posloupnost pageru 14. 2. → 15. 2. → 16. 2. → 28. 2. Dny bez probíhající akce se přeskočí.

Pager i výpis respektují stejné hledání, oblíbené a uživatelské filtry. Podrobná pravidla jsou v `docs/001_kalendar.md`.

## 3. Přihlášení na více zařízeních

**Stav: specifikováno v samostatném dokumentu.**

Jeden účet může mít současně pouze jednu aktivní session. Přihlášení na druhém zařízení zneplatní session původního zařízení. Na původním zařízení se při dalším požadavku zobrazí modální okno s informací o odhlášení.

Úplná pravidla a ověřovací scénář jsou v `docs/003_session.md`. Tento dokument je zde uveden jako přehledová položka; podrobná specifikace se neduplikuje.

## 4. Uživatelské menu přihlášeného uživatele

**Stav: implementováno, čeká na praktické ověření.**

Tato pravidla platí vždy, když má přihlášený uživatel otevřené uživatelské menu.

### Zavření menu

Menu se zavře pouze jedním z těchto způsobů:
1. Uživatel vybere položku přímo v uživatelském menu.
2. Uživatel klikne kamkoliv mimo uživatelské menu.

Kliknutí uvnitř menu se nepovažuje za kliknutí mimo menu. Výběr položky menu provede její běžnou akci a menu se zavře.

### Kliknutí mimo menu – výhradně zavření

Kliknutí mimo otevřené menu má jediný účel: zavřít menu. Toto kliknutí nesmí vyvolat žádnou další reakci aplikace.

To platí pro **jakýkoliv prvek a jakoukoliv oblast stránky mimo menu**, nejen pro prvky bezprostředně pod menu. Zejména se nesmí:

- otevřít odkaz ani změnit stránka nebo URL,
- aktivovat tlačítko, formulář nebo jiné ovládání,
- změnit oblíbené položky, filtry, datum, výběr katalogu ani jiný stav,
- spustit žádný obslužný kód kliknutí ani jiná akce navázaná na cílový prvek,
- provést výchozí akce prohlížeče vyvolaná tímto kliknutím.

Kliknutí se musí zachytit a zrušit dříve, než je doručeno cílovému prvku nebo jeho nadřazeným prvkům. Nesmí se tedy stát, že jedno kliknutí současně zavře menu a aktivuje cokoliv na stránce.

Pokud chce uživatel po zavření menu použít prvek na stránce, musí na něj provést **nové, samostatné kliknutí**.

### Implementační pravidlo

Obsluha kliknutí mimo menu musí běžet ve fázi zachytávání události (capture). Při otevřeném menu a cíli mimo menu musí zrušit výchozí akci a zastavit další šíření události; teprve poté menu zavře. Kliknutí uvnitř menu se tímto blokováním nesmí ovlivnit.

Dotčené soubory:
- `templates/layout.php` – struktura uživatelského menu.
- `assets/js/menu.js` – zavření menu a úplné zablokování kliknutí mimo menu pro ostatní prvky stránky.

## 5. Pravidlo průběžné dokumentace

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

## 6. Přihlášení a ověření identity přes Google a Facebook

**Stav: implementováno, čeká na praktické ověření obou poskytovatelů.**

K&F nabízí pouze dva způsoby přihlášení: Google a Facebook. Samostatná registrace ani hesla K&F neexistují. K&F neodesílá žádné e-maily a neprovádí e-mailové ověřování. Uživatel se ověří přímo u poskytovatele prostřednictvím OAuth 2.0; K&F po úspěšném návratu ověří OAuth odpověď a identifikuje účet podle dvojice provider + provider_uid.

Důležitá hranice ověření:
- Úspěšné OAuth přihlášení potvrzuje, že uživatel ovládá příslušný účet Google nebo Facebook.
- U Google se e-mail označí jako ověřený pouze při výslovném email_verified = true v odpovědi OpenID Connect. Chybějící příznak se nepovažuje za souhlas.
- Facebook Graph API při běžném /me přihlášení neposkytuje spolehlivý příznak ověření e-mailové adresy. Facebookem vrácený e-mail se proto může uložit jako kontaktní údaj, ale K&F jej neoznačí za ověřený. To nebrání přihlášení: ověřená je identita Facebook účtu, nikoli automaticky jeho e-mailová adresa.
- Přístup do K&F ani identifikace uživatele se nikdy neopírá o samotnou shodu e-mailů. Účty Google a Facebook se automaticky neslučují podle e-mailu.
- K&F neodesílá žádné e-maily. E-mail vrácený poskytovatelem je pouze kontaktní údaj v profilu; není podmínkou přihlášení ani registrace.

Bezpečnost a implementace:
- OAuth state je podepsaný a svázaný s krátkodobou HttpOnly cookie; Google používá PKCE.
- Po získání tokenu se načítá identita poskytovatele. Požadavky na Facebook Graph API používají také appsecret_proof vypočtený z access tokenu a aplikačního secretu.
- Do profilu uživatele se může uložit kontaktní e-mail vrácený poskytovatelem a avatar. E-mail ani příznak jeho ověření se nepoužívají pro přihlášení, registraci nebo oprávnění.
- Nová registrace, role, session a pravidlo jednoho aktivního zařízení zůstávají společné pro oba poskytovatele.

Konfigurace:
- Tajné údaje jsou pouze v .env: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI, FACEBOOK_APP_ID, FACEBOOK_APP_SECRET, FACEBOOK_REDIRECT_URI.
- Přesměrovací adresy OAuth musí být shodně nastavené v konzoli Google / Meta a v .env.
- Tlačítka se zobrazují pouze pro poskytovatele, který je povolený v config.php a má vyplněné potřebné klíče v .env.

Podrobná specifikace a testovací scénáře: docs/005_prihlaseni.md.

## 7. Potvrzení úspěšné akce přímo na tlačítku

**Stav: implementováno, čeká na ověření po nasazení.**

Po úspěšném odeslání formuláře nebo provedení akce se běžná informační zpráva (flash), například „Filtry uloženy“, nesmí vykreslit jako samostatný zelený prvek v obsahu stránky ani se kvůli ní nesmí do DOM vkládat nový informační `div` nebo jiný banner.

Místo toho se potvrzení zobrazí přímo na ovládacím prvku, který akci vyvolal:

1. Při kliknutí na tlačítko nebo odeslání formuláře se zapamatuje ovládací prvek, který akci spustil.
2. Pokud akce po úspěšném zpracování přesměruje nebo znovu načte stránku, identifikace ovládacího prvku se dočasně zachová přes `sessionStorage`.
3. Po návratu na stránku se na odpovídajícím tlačítku zobrazí přesný text úspěšné zprávy, který by se jinak zobrazil ve flash banneru.
4. Tlačítko se na dobu 3 sekund vizuálně zvýrazní zeleným stavem.
5. Po 3 sekundách se obnoví původní text i původní vzhled tlačítka.
6. Samostatný zelený flash banner se nevykreslí.

Platí to obecně pro běžná potvrzení úspěšných akcí, nejen pro ukládání filtrů. Text potvrzení se nepřepisuje pevně v JavaScriptu; používá se skutečná zpráva vytvořená konkrétní akcí.

Pokud nelze původní ovládací prvek bezpečně určit, nesmí se potvrzení zobrazit na nesouvisejícím tlačítku. Chybové zprávy a zvláštní bezpečnostní/modální upozornění nejsou tímto pravidlem nahrazena.

Dotčené soubory:
- `templates/layout.php` – předání textu flash zprávy klientské části bez vykreslení banneru,
- `assets/js/app.js` – zapamatování spouštěcího prvku a dočasná změna jeho textu,
- `assets/css/app.css` – zelený potvrzovací vzhled tlačítka.

## 8. Trvalé a dočasné filtry

**Stav: implementováno v kódu, čeká na praktické ověření.**

Přihlášený uživatel má trvalé výchozí nastavení filtrů uložené v JSON profilu. Dočasné změny se ukládají pouze do `sessionStorage`; reload ani běžná navigace je nemaže. Uložení nového nastavení v menu aktualizuje JSON a smaže dočasné přepsání relace. Host může filtry měnit také, ale jeho nastavení zůstává pouze v `sessionStorage`. Trvalé ukládání do `localStorage` se nepoužívá. Server ověřuje dočasně předané hodnoty a uplatňuje je společně pro seznam, pager a kalendář.

Podrobná specifikace včetně pravidel pro oddělení účtů, navigaci, reload a návaznost seznamu, pageru a kalendáře: [docs/004_filtry.md](004_filtry.md).
