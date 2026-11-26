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

**Stav: implementováno v kódu, čeká na praktické ověření po nasazení.**

Tato pravidla platí na všech stránkách K&F vždy, když má přihlášený uživatel otevřené uživatelské menu.

### Zavření menu

Menu má přesně dva způsoby zavření:

1. **Výběr položky v uživatelském menu.** Vybraná položka provede svou běžnou akci a menu se zavře.
2. **Kliknutí mimo uživatelské menu.** Kliknutí slouží pouze k zavření menu.

### Kliknutí mimo menu

Pokud je menu otevřené a uživatel klikne kamkoliv mimo něj, jediným výsledkem je zavření menu. Kliknutí nesmí aktivovat ani vizuálně stisknout žádný prvek pod místem kliknutí, změnit jeho hodnotu, spustit jeho obsluhu, odeslat formulář ani vyvolat navigaci.

Chce-li uživatel po zavření menu použít jiný prvek stránky, musí na něj kliknout znovu, samostatnou novou interakcí.

### Implementace

Při otevření menu se do hlavičky vloží jediná průhledná překryvná vrstva. Ta pokrývá celou obrazovku, ale samotné uživatelské menu zůstává nad ní. Ostatní prvky hlavičky i obsah stránky jsou pod vrstvou, takže kliknutí nemůže dosáhnout na žádný z nich. Kliknutí na vrstvu menu zavře a vrstvu odstraní.

Vrstva se vytvoří pouze po otevření menu a odstraní se při jeho zavření. Nepřidávají se globální zachytávače událostí ani další podmínky na jednotlivé prvky.

Dotčené soubory:
- `templates/layout.php` – společná hlavička a uživatelské menu.
- `assets/js/menu.js` – vytvoření a odstranění jediné překryvné vrstvy.
- `assets/css/app.css` – vrstvení překryvu pod menu a nad ostatními prvky stránky.


## Upozornění

U přihlášeného uživatele se v hlavičce zobrazuje ikona upozornění před hvězdičkou oblíbených a avatarem. Podrobná samostatná specifikace je v `docs/006_upozorneni.md`.

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
.

## Přidávání akcí a schvalování

**Stav: implementováno; základní průchod formulářem, vložením do pending a schválením byl uživatelem prakticky ověřen.**

Podrobný popis formuláře, draftu, náhledu, obrázků, odeslání, fronty `/my`, schvalování `/review`, úklidu souborů a testovacích scénářů je v [docs/007_pridavani_akci.md](007_pridavani_akci.md).

**Pozor na nevyřešený rozpor role 2:** konfigurace definuje `role=2` jako `trusted` a aktuální kód jí přiznává přímé zveřejnění. Uživatel však při testu uvedl, že účet označený jako role 2 vytvořil návrh v pending. Tento rozdíl je zaznamenán v samostatném dokumentu a musí se ověřit na živém serveru; nesmí se považovat za potvrzené pravidlo.
