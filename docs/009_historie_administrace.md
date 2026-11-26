# Historie administrátorských zásahů – plán

## 1. Účel a stav dokumentu

**Stav: plán – zatím není implementováno.**

Cílem je vytvořit trvalou a podrobnou historii důležitých zásahů v administraci K&F. Z historie musí být možné zpětně zjistit:

- kdo operaci provedl,
- kdy ji provedl,
- jakého záznamu se týkala,
- jaký typ operace provedl,
- jaké hodnoty změnil,
- jak záznam vypadal v okamžiku smazání,
- zda šlo o práci s čekajícím návrhem, nebo s již zveřejněným záznamem.

Historie je určena pro administrátorskou kontrolu, nikoli pro běžné uživatele. Její načítání proto nemusí být součástí běžného načtení webu. Data se načtou až po otevření samostatné administrátorské stránky.

Tento dokument popisuje cílové chování. Neznamená, že je historie již dostupná nebo že se nyní mění stávající schvalování.

## 2. Rozsah zaznamenávaných entit

Historie bude vedena pro tři hlavní typy záznamů:

| Entita | Obsah |
|---|---|
| `events` | Koncerty, festivaly a ostatní akce |
| `bands` | Kapely |
| `venues` | Kluby a místa |

Každá entita bude mít vlastní soubory historie. Nebude existovat jeden společný neomezeně rostoucí soubor pro celou administraci.

Historie se bude týkat zásahů moderátorů a administrátorů, které mají vliv na návrhy nebo zveřejněné záznamy. Identita osoby provádějící operaci se vždy určuje ze skutečně přihlášeného uživatele a jeho aktuálních oprávnění na serveru. Nikdy se nepřebírá z formuláře, URL ani z klientského JavaScriptu.

## 3. Jaké operace se zaznamenají

### 3.1 Schválení návrhu

Při úspěšném schválení návrhu se zaznamená:

- kdo návrh schválil,
- datum a čas schválení,
- ID původního návrhu v `pending`,
- typ návrhu,
- název návrhu v okamžiku schválení,
- ID nově zveřejněného záznamu,
- výsledná entita,
- výsledek operace.

Záznam musí umožnit propojit původní čekající návrh s nově vytvořeným zveřejněným záznamem.

Schválení a zveřejnění jsou jedna administrátorská operace. Do historie se zapíše až potvrzené úspěšné dokončení publikačního procesu.

### 3.2 Zamítnutí návrhu

Při úspěšném zamítnutí se zaznamená:

- kdo návrh zamítl,
- datum a čas,
- ID návrhu,
- typ a název návrhu,
- případný důvod zamítnutí, pokud jej aplikace v budoucnu bude ukládat,
- výsledek operace.

Zamítnutý návrh již nemusí existovat v databázi ani v adresáři `data/pending`. Historický záznam proto musí obsahovat vlastní identifikaci návrhu a nesmí se spoléhat na jeho pozdější načtení z databáze.

### 3.3 Úprava návrhu ve schvalování

Pokud moderátor upraví čekající návrh, zaznamená se samostatná operace:

- kdo návrh upravil,
- kdy,
- ID návrhu,
- typ a název návrhu,
- seznam změněných polí,
- původní a nová hodnota každého změněného pole.

Taková operace se označí jako úprava čekajícího návrhu, nikoli jako úprava již zveřejněné entity.

Pokud bude návrh později schválen, vznikne další samostatný záznam o schválení. Historie tak zachová celý sled událostí.

### 3.4 Úprava zveřejněného záznamu

Při úpravě již zveřejněné akce, kapely nebo klubu se zaznamená:

- kdo záznam upravil,
- datum a čas,
- typ entity a její ID,
- název záznamu před úpravou a po úpravě,
- přesný seznam změněných polí,
- původní a nová hodnota.

Pokud se změní vazby, musí být zaznamenána také změna vazeb. Například u akce změna klubu nebo seznamu kapel.

Pouhé otevření formuláře bez uložení změny se jako úprava nezaznamenává.

Pokud uživatel uloží formulář bez skutečné změny hodnot, nemá vzniknout falešný záznam o úpravě. Případné technické uložení bez změny dat lze evidovat odděleně pouze tehdy, pokud k tomu bude konkrétní důvod.

### 3.5 Smazání zveřejněného záznamu

Smazání musí být zaznamenáno dříve, než se odstraní poslední dostupná data záznamu.

Historie musí obsahovat vlastní kopii základních údajů záznamu v okamžiku smazání, zejména:

- ID a typ entity,
- název,
- datum akce, pokud jde o akci,
- město a klub, pokud jsou relevantní,
- základní vazby na kapely, klub nebo město,
- další důležité identifikační údaje potřebné pro pozdější dohledání.

Záznam historie nesmí být závislý na DB řádku, JSON detailu ani obrázku, které se při smazání odstraní.

Binární obsah obrázků se do historie nekopíruje. Pokud je důležité zaznamenat změnu obrázku, uloží se informace o operaci s obrázkem a jeho identifikace, nikoli celý soubor.

### 3.6 Další administrátorské operace

Do budoucna lze zaznamenávat také:

- převzetí zámku návrhu administrátorem,
- ruční uvolnění zámku,
- další zásahy, které mění stav návrhu nebo zveřejněného záznamu.

Samotné běžné otevření detailu nebo zobrazení fronty se do historie změn zapisovat nebude. Historie nemá být provozním logem každého kliknutí.

## 4. Umístění a rozdělení souborů

Historie se bude ukládat do chráněné datové části aplikace, mimo veřejné soubory webu.

Navržená struktura:

```text
data/
└── logs/
    └── admin/
        ├── 2026/
        │   └── 11/
        │       ├── events-001.jsonl
        │       ├── bands-001.jsonl
        │       └── venues-001.jsonl
        └── 2026/
            └── 12/
                ├── events-001.jsonl
                ├── bands-001.jsonl
                └── venues-001.jsonl
```

Pravidla:

1. Rok a měsíc určují datum, kdy se operace skutečně provedla.
2. První zápis v novém měsíci automaticky vytvoří potřebné adresáře a soubor.
3. Není potřeba cron ani ruční zakládání souborů.
4. Soubory se vytvářejí pouze při skutečném zápisu historie.
5. Každý soubor obsahuje pouze jednu entitu.
6. Historie se běžným zápisem nikdy nepřepisuje ani nemaže.

### 4.1 Omezení velikosti souborů

Měsíční rozdělení samo o sobě nezaručuje, že soubor nikdy nebude velký. Proto se doplní také velikostní rotace.

Navrhovaný výchozí limit jednoho souboru je **10 MB**. Limit bude konfigurovatelný, nikoli pevně zapsaný v jednotlivých routách.

Po dosažení limitu se začne zapisovat do dalšího souboru stejné entity a měsíce:

```text
events-001.jsonl
events-002.jsonl
events-003.jsonl
```

Měsíc se tedy nemění podle velikosti souboru. Číslo části pouze rozděluje příliš velký měsíční soubor na menší části.

Tím se zabrání tomu, aby jediný soubor postupně narostl například na 100 MB. Počet souborů zůstane vzhledem k nízkému počtu administrátorských operací malý.

### 4.2 Uchovávání historie

Výchozí pravidlo:

- historie se automaticky nemaže,
- starší měsíce zůstávají dostupné,
- běžná administrátorská stránka nenačítá všechny roky najednou,
- případná archivace nebo retenční lhůta se může doplnit později jako samostatně schválená funkčnost.

Nesmí se zavést automatické mazání historie bez výslovně stanovené retenční politiky.

## 5. Formát jednoho záznamu – JSONL

Použije se formát JSONL (JSON Lines).

- Jeden řádek = jedna historická událost.
- Každý řádek je samostatný platný JSON objekt.
- Nový záznam se přidává na konec souboru.
- Starší řádky se při běžném zápisu nemění.
- JSON se zapisuje v UTF-8.
- Uvnitř JSON objektu mohou být vnořené objekty a pole.
- Zápis nesmí rozdělit jeden JSON záznam na více řádků.

### 5.1 Společná struktura záznamu

Každý záznam bude obsahovat minimálně:

| Pole | Význam |
|---|---|
| `schema_version` | Verze struktury záznamu pro budoucí kompatibilitu |
| `log_id` | Jedinečný identifikátor historické události |
| `occurred_at` | Datum a čas operace v ISO 8601 včetně časového posunu |
| `actor.user_id` | ID přihlášeného uživatele, který operaci provedl |
| `actor.display_name` | Jméno uživatele v okamžiku operace |
| `actor.role` | Role uživatele v okamžiku operace |
| `action` | Typ operace |
| `entity.type` | Typ entity: `event`, `band`, `venue` |
| `entity.id` | ID zveřejněného záznamu, pokud již existuje |
| `entity.name` | Název záznamu v okamžiku operace |
| `target` | Informace, zda šlo o návrh nebo zveřejněný záznam |
| `pending_id` | ID čekajícího návrhu, pokud se operace týkala návrhu |
| `result` | Výsledek operace |
| `changes` | Seznam změněných hodnot, pokud šlo o úpravu |
| `snapshot` | Zachycené údaje potřebné pro dohledání smazaného záznamu |

Některá pole budou podle typu operace nepovinná. Například při zamítnutí návrhu ještě nemusí existovat ID zveřejněné entity.

### 5.2 Příklad schválení akce

Soubor `events-001.jsonl`:

```json
{"schema_version":1,"log_id":"a8f3c1","occurred_at":"2026-11-27T00:15:32+01:00","actor":{"user_id":2,"display_name":"Admin","role":4},"action":"approve","entity":{"type":"event","id":123,"name":"Metalový koncert"},"target":"pending","pending_id":58,"result":"success"}
```

### 5.3 Příklad úpravy akce

```json
{"schema_version":1,"log_id":"a8f3c2","occurred_at":"2026-11-27T00:24:11+01:00","actor":{"user_id":3,"display_name":"Moderátor","role":3},"action":"edit","entity":{"type":"event","id":123,"name":"Metalový koncert"},"target":"published","result":"success","changes":{"venue":{"old":{"id":12,"name":"Klub A"},"new":{"id":18,"name":"Klub B"}},"description":{"old":"Původní popis","new":"Nový popis"}}}
```

### 5.4 Příklad smazání akce

```json
{"schema_version":1,"log_id":"a8f3c3","occurred_at":"2026-11-27T00:31:45+01:00","actor":{"user_id":2,"display_name":"Admin","role":4},"action":"delete","entity":{"type":"event","id":124,"name":"Rockový večer"},"target":"published","result":"success","snapshot":{"date_from":"2026-12-05","date_to":null,"city":{"id":4,"name":"Brno"},"venue":{"id":12,"name":"Klub A"},"bands":[{"id":7,"name":"Kapela X"}]}}
```

### 5.5 Příklad zamítnutí návrhu

```json
{"schema_version":1,"log_id":"a8f3c4","occurred_at":"2026-11-27T00:40:00+01:00","actor":{"user_id":3,"display_name":"Moderátor","role":3},"action":"reject","entity":{"type":"event","id":null,"name":"Návrh koncertu"},"target":"pending","pending_id":61,"result":"success"}
```

Příklady jsou ilustrativní. Konkrétní generování ID a přesný seznam polí se stanoví při implementaci.

## 6. Co přesně ukládat při úpravě

Historie úprav má být užitečná i bez přístupu ke starému JSON detailu. Nestačí proto uložit pouze text „záznam upravil administrátor“.

U každé skutečně změněné hodnoty se uloží:

- identifikátor pole,
- původní hodnota,
- nová hodnota.

Příklady:

| Oblast | Co zaznamenat |
|---|---|
| Název | Původní a nový název |
| Datum | Původní a nové datum |
| Čas | Původní a nový čas |
| Město | Původní a nové ID i název |
| Klub | Původní a nové ID i název |
| Kapely | Původní a nový seznam ID a názvů, včetně pořadí |
| Popis | Původní a nový text |
| Web | Původní a novou adresu |
| Typ akce | Původní a nový typ |
| Obrázek | Informace, zda byl přidán, nahrazen nebo odstraněn |

U vazeb se nesmí ukládat pouze ID bez názvu. Název se uloží také, aby historie zůstala čitelná i po pozdější změně nebo smazání související entity.

Pokud se změní více polí najednou, uloží se všechny změny v jednom záznamu historie. Jedno uložení formuláře tedy vytvoří jednu historickou událost, nikoli samostatný řádek pro každé pole.

U dlouhých textů se musí při implementaci stanovit bezpečný limit velikosti jednoho historického záznamu. Pokud by úplné staré a nové texty mohly způsobovat nepřiměřený růst logů, nesmí se bez upozornění tiše zkrátit. Je nutné zvolit a zdokumentovat pravidlo, které zachová možnost zjistit podstatu změny.

## 7. Bezpečný zápis a souběh více administrátorů

Zápis musí být bezpečný i v situaci, kdy dva moderátoři provedou operaci ve stejnou chvíli.

Požadavky:

1. Použít `flock(LOCK_EX)` pro výhradní přístup při zápisu.
2. Použít jeden společný zamykací soubor pro zapisování historie, nikoli vytvářet samostatný lock soubor pro každou událost.
3. Pod zámkem určit aktuální měsíční soubor a jeho velikost.
4. Při překročení limitu bezpečně zvolit další část souboru.
5. Zapsat celý JSON objekt jako jeden řádek zakončený znakem nového řádku.
6. Ověřit, že zápis skutečně proběhl v celé délce.
7. Zámek vždy uvolnit, včetně chybových stavů.

Při zápisu se nesmí načítat a znovu ukládat celý dosavadní soubor. Použije se připojení na konec souboru.

Historie se nesmí ukládat do MySQL. Tím se respektuje omezení databáze a dosavadní architektura K&F, ve které se souborové logy oddělují od hlavních databázových dat.

### 7.1 Okamžik zápisu

Historická událost se vztahuje pouze k operaci, která skutečně uspěla.

- Neúspěšná validace nesmí vytvořit záznam o úspěšném schválení, úpravě nebo smazání.
- Záznam se zapíše v návaznosti na úspěšné dokončení příslušné operace.
- Pokud zápis historie selže, nesmí aplikace chybu tiše ignorovat ani předstírat, že je auditní záznam uložen.
- Chyba zápisu historie musí být zaznamenána do technického/error logu a musí být dohledatelná pro administrátora.
- Protože databázová operace a souborový zápis nejsou jedna společná transakce, musí implementace výslovně ošetřit stav, kdy se změna entity dokončí, ale zápis historie selže. Taková situace se nesmí zaměnit za běžný neúspěch operace, který by uživatele vedl k opakování smazání nebo úpravy.

Před implementací se ověří, jak jednotlivé současné operace používají DB transakce a mazání souborů. Způsob zápisu historie se musí přizpůsobit jejich skutečnému pořadí, nikoli naopak.

## 8. Ochrana a přístup k souborům

Historie obsahuje interní informace o administrátorech a změnách dat. Nesmí být veřejně dostupná.

- Umístění zůstane pod chráněným adresářem `data/`.
- Přímý HTTP přístup k souborům musí být blokovaný stejně jako u ostatních interních JSON dat.
- Historické soubory se nikdy nesmějí zobrazovat přes veřejnou statickou URL.
- Čtení historie bude probíhat pouze přes serverovou routu po kontrole oprávnění.
- Stránku historie smí otevřít pouze administrátor s rolí 4.
- Moderátor může být uveden jako autor operace, ale samotná role moderátora mu automaticky nedává právo prohlížet kompletní administrátorskou historii.
- Oprávnění se kontroluje na serveru při každém požadavku, včetně načítání dalších stránek a detailu záznamu.

Do historie se nesmějí ukládat hesla, OAuth tokeny, session tokeny, CSRF tokeny, tajné klíče ani jiné autentizační údaje. Historie nemá sloužit jako náhrada bezpečnostního logu `data/security.log`.

## 9. Administrátorská stránka – „Historie“

Historie bude samostatná stránka v administraci. Nebude součástí běžného seznamu akcí, kapel ani klubů a nebude se načítat automaticky při otevření aplikace.

### 9.1 Základní výpis

Výchozí zobrazení:

- nejnovější operace nahoře,
- datum a čas,
- jméno administrátora nebo moderátora,
- typ operace,
- typ entity,
- název záznamu,
- výsledek.

Příklad:

| Datum | Uživatel | Operace | Entita | Záznam |
|---|---|---|---|---|
| 27. 11. 00:31 | Admin | Smazání | Akce | Rockový večer |
| 27. 11. 00:24 | Moderátor | Úprava | Akce | Metalový koncert |
| 27. 11. 00:15 | Admin | Schválení | Akce | Metalový koncert |

Výpis musí být kompaktní a použitelný také na mobilu.

### 9.2 Filtry

Administrátor bude moci kombinovat následující filtry:

- **Uživatel:** konkrétní administrátor nebo moderátor.
- **Operace:** schválení, zamítnutí, úprava, smazání; případně další později doplněné typy.
- **Entita:** akce, kapely, kluby a místa.
- **Datum od – do:** časové období, ve kterém operace proběhla.
- **Vyhledávání:** název záznamu nebo jeho ID.

Filtry se budou aplikovat na historii, nikoli na současné záznamy v hlavních tabulkách. Smazaný záznam proto musí být možné vyhledat stejně jako existující záznam.

### 9.3 Detail operace

Kliknutím na řádek se otevře detail historické události.

Detail zobrazí:

- datum a čas,
- kdo operaci provedl,
- jeho roli v okamžiku operace,
- typ operace,
- typ a ID entity,
- název záznamu,
- ID původního návrhu, pokud existovalo,
- výsledek,
- seznam změněných polí,
- původní a novou hodnotu,
- uložený snímek smazaného záznamu, pokud šlo o smazání.

U úprav bude přehledný rozdíl například:

| Pole | Před úpravou | Po úpravě |
|---|---|---|
| Klub | Klub A | Klub B |
| Popis | Původní popis | Nový popis |

U seznamů kapel a dalších vazeb se zobrazí přehled předchozího a nového stavu, včetně pořadí.

### 9.4 Stránkování a načítání

Historie může časem obsahovat velké množství záznamů. Proto:

- nenačítat všechny roky a měsíce současně,
- výchozí pohled otevřít na aktuálním období,
- načítat záznamy po stránkách,
- řadit od nejnovějších,
- při změně filtru začít znovu od nejnovější odpovídající položky,
- pro starší období číst pouze příslušné soubory,
- pro detail načíst pouze jeden konkrétní záznam.

Velikostní rotace souborů a stránkování mají zabránit tomu, aby běžné otevření historie vyžadovalo načtení jednoho obrovského souboru nebo celé historie.

Při implementaci se ověří efektivní čtení od konce souboru nebo práce s pozicí v souboru. Není vhodné pokaždé načíst celý měsíční soubor do paměti jen proto, aby se zobrazilo několik posledních položek.

### 9.5 Navigace v čase

Administrátor bude moci vybrat rok a měsíc. Pro zvolené období se načtou pouze odpovídající soubory.

Pokud v daném měsíci žádná operace neproběhla, stránka zobrazí prázdný stav. Chybějící soubor není chyba – znamená pouze, že v daném období nebyl zaznamenán žádný zásah.

## 10. Chování po smazání nebo změně souvisejících záznamů

Historie musí zůstat čitelná nezávisle na aktuálním stavu databáze.

Příklady:

- Smazaná akce musí být v historii stále dohledatelná.
- Pokud byla později smazána kapela, její název v historické změně vazeb akce musí zůstat zachovaný.
- Pokud se klub přejmenuje, starší historie musí zobrazovat původní název, který platil v okamžiku operace.
- Pokud je účet administrátora později přejmenován nebo odstraněn, historie musí zachovat jméno a ID uložené v okamžiku operace.

Z tohoto důvodu se do historických záznamů ukládají identifikační názvy a potřebné hodnoty jako kopie. Při zobrazení se nesmí spoléhat pouze na aktuální JOIN do DB.

## 11. Výkon a provoz

Historie je administrátorská funkčnost s nízkou četností čtení a zápisů.

Požadavky:

- běžný návštěvník ani běžný přihlášený uživatel nesmí historii načítat,
- zápis jednoho záznamu nesmí vyžadovat načtení celé historie,
- soubor se při zápisu pouze otevře pro připojení a uzamkne,
- čtení historie se spouští až po otevření administrátorské stránky,
- seznam používá stránkování a omezený rozsah souborů,
- detail operace se načítá samostatně,
- chybný nebo poškozený řádek nesmí způsobit pád celé stránky; chyba musí být bezpečně oznámena a dohledatelná,
- chybějící měsíční soubor je běžný stav, nikoli chyba.

Pokud se v budoucnu objem historie výrazně zvětší, lze doplnit pomocný index nebo archivaci. Základní řešení však musí fungovat bez nové databázové tabulky a bez cron úlohy.

## 12. Konfigurace

Všechna provozní nastavení budou v `config.php`, nikoli duplicitně v `.env` a nikoli natvrdo v jednotlivých souborech.

Při implementaci se posoudí například tato nastavení:

- kořenový adresář historie,
- maximální velikost jedné části souboru (navržený výchozí limit 10 MB),
- počet položek na stránku,
- případně maximální rozsah období načítaného bez dalšího filtru.

Konkrétní názvy konfiguračních klíčů se stanoví podle současné struktury `config.php`. Nesmí vzniknout druhé místo, ve kterém se stejná hodnota nastavuje.

## 13. Napojení na současné K&F

Před implementací je nutné projít aktuální kód a napojit zápis historie přímo na existující úspěšné operace.

Prověřit zejména:

- schválení návrhu a publikaci akce,
- zamítnutí návrhu,
- úpravu čekajícího návrhu přes schvalování,
- smazání vlastního čekajícího návrhu,
- případné administrátorské mazání zveřejněné akce,
- úpravy zveřejněných akcí,
- úpravy a mazání kapel,
- úpravy a mazání klubů a míst,
- změny vazeb mezi akcemi, kapelami a kluby.

Zápis historie se má volat ze společného místa, kde je jisté, že operace skutečně uspěla. Nemá se kopírovat do několika různých šablon ani do klientského JavaScriptu.

Při mazání se musí připravit potřebný snímek záznamu ještě před odstraněním DB řádku, JSON detailu a obrázků.

Při úpravě se musí porovnat skutečné hodnoty před uložením a po uložení. Nesmí se jako změna zaznamenat pouze hodnoty odeslané formulářem, které se ve skutečnosti neuložily.

## 14. Testovací scénáře

Před označením funkčnosti za hotovou se musí ověřit minimálně:

| Test | Očekávaný výsledek |
|---|---|
| Schválení návrhu akce | Vznikne záznam o schválení s ID návrhu i zveřejněné akce |
| Zamítnutí návrhu | Vznikne záznam o zamítnutí, i když se pending následně smaže |
| Úprava návrhu | Zaznamená se moderátor a rozdíly hodnot |
| Úprava zveřejněné akce | Zaznamenají se jen skutečně změněná pole |
| Změna klubu u akce | Historie obsahuje starý i nový klub včetně názvů |
| Změna kapel u akce | Historie obsahuje původní a nový seznam kapel |
| Smazání akce | Historie zůstane dostupná po odstranění DB, JSON a obrázků |
| Smazání kapely | Historie zůstane dostupná a obsahuje identifikaci kapely |
| Smazání klubu | Historie zůstane dostupná a obsahuje identifikaci klubu |
| Dva souběžné zápisy | Oba JSONL záznamy zůstanou celé a samostatné |
| Dosažení limitu souboru | Založí se další část, starší soubor se nepřepíše |
| Nový měsíc | Zápis automaticky vytvoří nový měsíční adresář a soubor |
| Neúspěšná operace | Nevznikne falešný záznam o úspěšném dokončení |
| Chyba zápisu historie | Chyba se nezamlčí a je dohledatelná |
| Poškozený řádek | Ostatní záznamy lze nadále zobrazit |
| Přístup běžného uživatele | Stránka i datové soubory zůstanou nepřístupné |
| Přístup moderátora | Bez výslovného oprávnění historii neuvidí |
| Přístup administrátora | Historii může otevřít a filtrovat |
| Historie smazané entity | Vyhledání funguje i bez odpovídajícího DB záznamu |
| Stránkování | Načítá se pouze omezený počet položek |
| Filtry | Lze kombinovat uživatele, operaci, entitu, období a hledaný text |

## 15. Doporučené pořadí implementace

1. Projít současné routy a funkce pro schvalování, úpravy a mazání všech tří entit.
2. Určit přesná místa, ve kterých je potvrzeno úspěšné dokončení operace.
3. Implementovat společnou serverovou funkci pro bezpečný zápis JSONL.
4. Implementovat rozdělení podle roku, měsíce, entity a velikosti souboru.
5. Napojit historii na schválení a zamítnutí návrhů.
6. Napojit historii na úpravy návrhů a zveřejněných záznamů.
7. Napojit historii na mazání včetně uložení snímku před odstraněním dat.
8. Ověřit souběžné zápisy, rotaci, chyby a ochranu souborů.
9. Vytvořit serverovou stránku historie dostupnou pouze administrátorovi.
10. Doplnit výpis, detail, filtry, stránkování a navigaci podle období.
11. Provést všechny testovací scénáře.
12. Aktualizovat tento dokument podle skutečné implementace a výsledků praktického testování.

## 16. Co se nyní nemění

Tento plán sám o sobě nemění:

- současné přidávání akcí,
- současnou úpravu návrhu,
- současné zamykání návrhů,
- současné schvalování a zamítání,
- současné mazání,
- databázové tabulky,
- běžné uživatelské stránky a jejich načítání.

Přidání historie se bude realizovat samostatně a až po kontrole současného kódu. Současné funkční chování se musí zachovat.

## 17. Aktuální stav ověření souvisejících funkcí

Podle posledního praktického sdělení uživatele:

- přidávání návrhu funguje,
- úprava návrhu funguje,
- lock návrhu funguje,
- mazání zatím čeká na samostatný praktický test.

Mazání se nesmí označit za ověřené pouze na základě existence kódu nebo automatického testu. Před napojením historie na mazání je potřeba ověřit skutečné chování mazání na nasazeném webu a určit, které soubory a DB vazby se při něm odstraňují.

---

**Zásadní pravidlo:** Historie je samostatný, dlouhodobý auditní záznam. Nesmí se mazat společně s původní entitou, nesmí být dostupná běžným uživatelům a její zápis nesmí přepisovat starší události.
