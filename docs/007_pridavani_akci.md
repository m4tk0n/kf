# Přidávání akcí a schvalování návrhů

Tento dokument popisuje přidání nové akce, dočasný náhled, odeslání návrhu, frontu ke schválení a zveřejnění akce.

## Stav a hranice ověření

**Stav: implementováno; základní průchod formulářem a schválením byl prakticky ověřen uživatelem.**

Rozlišujeme:
- **Implementováno** – chování je obsaženo v aktuálním kódu na GitHubu.
- **Ověřeno** – uživatel daný postup skutečně provedl na nasazené aplikaci.
- **Neověřeno** – postup nebyl zatím prakticky vyzkoušen.
- **Rozpor k prověření** – pozorované chování neodpovídá oprávněním definovaným v aktuálním kódu.

### Dosud provedený test

Uživatel oznámil tento úspěšný průchod:
1. Přihlásil se účtem, který označil jako **role 2**.
2. Vyplnil formulář nové akce.
3. Prošel náhledem včetně obrázku načteného z odkazu.
4. Odeslal akci a návrh se objevil ve frontě `pending`.
5. Přihlásil se účtem **role 4**.
6. Návrh schválil.
7. Akce se po schválení zveřejnila.

Uživatel potvrdil, že tento postup zatím funguje. Tento test potvrzuje průchod formulář → náhled → pending → schválení → zveřejněná akce. Neznamená automaticky, že byly ověřeny všechny role, validační chyby, limity, zamítnutí nebo mazání návrhu.

### Důležitý rozpor v testu role 2

Aktuální konfigurace v `config.php` definuje role takto:

| Hodnota `users.role` | Role | Oprávnění podle aktuálního kódu |
|---:|---|---|
| 1 | `user` | Přihlášený uživatel; může odeslat návrh do pending |
| 2 | `trusted` | Důvěryhodný uživatel; má oprávnění k přímému zveřejnění |
| 3 | `moderator` | Moderátor; může přímo zveřejňovat a schvalovat |
| 4 | `admin` | Administrátor; může přímo zveřejňovat a schvalovat |

V `src/session.php` má oprávnění `publish_direct` podmínku role alespoň `trusted`. V `src/events_add.php` proto role 2 a vyšší mají při odeslání publikovat přímo, zatímco role 1 vytváří návrh v pending.

**Pozorování uživatele (role 2 → pending) je tedy v rozporu s aktuálním nastavením a kódem na GitHubu.** Tento rozdíl se nesmí v dokumentaci zaměnit za potvrzené pravidlo. Je potřeba jej při dalším testu prověřit na serveru: ověřit skutečnou hodnotu `users.role`, zda změna role zneplatnila starou session a zda server používá stejnou verzi `config.php` a `src/session.php` jako GitHub. Do vyřešení rozporu se test role 2 eviduje jako pozorovaný výsledek, nikoli jako potvrzení zamýšleného oprávnění role `trusted`.

## 1. Přístup k přidávání akcí

Přidávání akce je dostupné přihlášeným uživatelům s oprávněním `submit`:
- role 1 (`user`) může akci navrhnout a poslat ke schválení,
- role 2 (`trusted`) a vyšší mají podle aktuálního kódu oprávnění `publish_direct` a akci zveřejňují přímo,
- role 3 (`moderator`) a role 4 (`admin`) mohou navíc pracovat se schvalovací frontou.

Oprávnění se kontrolují na serveru z aktuální role načtené z DB. Role ani oprávnění se nesmějí přebírat z formuláře, URL nebo cookie.

## 2. Formulář nové akce

Vstupní stránka je `event/new`. Formulář aktuálně obsahuje:

- název akce,
- typ akce,
- datum začátku a nepovinné datum konce,
- čas začátku,
- nepovinný klub/místo,
- město,
- až 10 kapel a nepovinné časy jejich vystoupení,
- webovou adresu,
- popis,
- obrázek nahraný ze zařízení nebo obrázek načtený z URL.

Kluby, města a kapely se vybírají z existujících záznamů v databázi. Pokud je vybrán klub a město není zadané, validace doplní město podle klubu.

Server před pokračováním kontroluje zejména:
- povinný název,
- povolený typ akce,
- platnost data a pořadí začátku a konce,
- platnost času,
- existenci vybraného klubu, města a kapel,
- bezpečnost a platnost webové adresy,
- platnost časů vystoupení kapel.

Obrázek se ověřuje podle povoleného MIME typu, velikosti a počtu pixelů. Obrázek z URL se načítá přes společnou bezpečnou HTTP funkci aplikace a znovu se zpracuje do WebP.

## 3. Dvoukrokové vyplnění a náhled

Přidání akce probíhá ve dvou krocích:

1. Uživatel vyplní formulář a odešle jej tlačítkem pro náhled.
2. Server provede validaci, uloží rozpracovaný návrh a přesměruje na `event/preview`.

Náhled zobrazuje detail akce v podobě odpovídající výslednému detailu, včetně obrázku, pokud byl zadán. Uživatel má možnost:
- vrátit se k úpravě formuláře,
- odeslat akci,
- zrušit rozpracovaný návrh.

Při úpravě se znovu načtou uložené hodnoty. Při zrušení se smaže rozpracovaný návrh i jeho dočasný obrázek.

### Uložení rozpracovaného návrhu

- Pro každého uživatele existuje jeden soubor draftu v `data/tmp`.
- Dočasný obrázek je uložen odděleně v `data/tmp` jako WebP.
- Náhled obrázku je obsloužen chráněnou routou `event/draft-image`; adresář `data` se nezpřístupňuje přímo.
- Název dočasného obrázku se ověřuje proti ID přihlášeného uživatele.
- Starší dočasné soubory uklízí cron podle `tmp_ttl_sec` z konfigurace. Nejde o okamžité mazání při každém požadavku.

Pokud formulář obsahuje validační chybu, uživatel se vrátí na formulář s chybami a dosavadní draft zůstává zachován. Pokud je potřeba obrázek vybrat znovu, formulář na tuto situaci upozorní.

## 4. Odeslání akce

Tlačítko Odeslat provede serverovou kontrolu draftu a znovu ověří data. Následně se podle oprávnění rozhodne mezi přímým zveřejněním a vytvořením návrhu:

### Přímé zveřejnění

Podle aktuálního kódu se používá pro role `trusted` a vyšší:
- vytvoří se řádek v tabulce `events`,
- uloží se vazby kapel v `event_bands`,
- detailní údaje se uloží do JSON souboru v `data/events`,
- obrázek se uloží jako WebP varianty `_s` a `_l`,
- po úspěšném uložení se odstraní draft a dočasný obrázek,
- uživatel je přesměrován na zveřejněnou akci.

### Odeslání do schvalovací fronty

Pro běžnou roli `user`:
- vytvoří se řádek v tabulce `pending`,
- hodnoty návrhu se uloží do JSON souboru v `data/pending/<bucket>/<id>.json`,
- obrázek návrhu se uloží vedle JSON jako `<id>_l.webp`,
- draft a jeho dočasný obrázek se po úspěšném vložení do pending odstraní,
- uživatel dostane potvrzení o odeslání návrhu.

Před vložením se kontroluje:
- limit čekajících návrhů daného typu na uživatele,
- denní limit příspěvků,
- duplicita proti již zveřejněným akcím a u návrhů také proti dalším čekajícím návrhům.

Limity jsou řízeny konfigurací, nikoli pevně zapsanými hodnotami v jednotlivých stránkách.

## 5. Moje návrhy – `/my`

Přihlášený uživatel otevře stránku `/my`, kde se zobrazují jeho čekající návrhy.

- Výpis je omezen konfigurovaným maximálním počtem položek na stránku.
- Uživatel může odstranit vlastní čekající návrh.
- Při odstranění se smaže databázový řádek, JSON soubor i případný obrázek návrhu.
- Uživatel nesmí odstranit cizí návrh; oprávnění se kontroluje na serveru.
- Moderátor a vyšší role mohou spravovat návrhy podle serverových oprávnění.

## 6. Schvalování – `/review`

Schvalovací fronta je dostupná od role `moderator` (role 3) výše.

### Seznam a detail návrhu

- Základní stránka `/review` zobrazuje čekající návrhy.
- Otevřením návrhu se zobrazí jeho detail.
- U návrhu akce se detail sestaví z uložených hodnot a aktuálních názvů města, klubu a kapel.
- Obrázek návrhu se načítá přes chráněnou routu `pending/image`.
- Autor návrhu a jeho ID se určují z databázového záznamu, nikoli z odeslaných parametrů.

### Schválení akce

Při schválení server:
1. znovu načte návrh z DB a JSON,
2. znovu vyčistí a validuje všechny hodnoty,
3. zkontroluje duplicitu proti zveřejněným akcím,
4. zveřejní akci stejnou publikační funkcí, kterou používá přímé zveřejnění,
5. odstraní záznam z `pending` včetně JSON a obrázku,
6. zvýší autorovi počitadlo schválených příspěvků `approved_count`,
7. zapíše událost do bezpečnostního logu.

Pokud validace nebo kontrola duplicity neprojde, návrh se neschválí a zůstává ve frontě, aby jej bylo možné znovu prověřit.

Po dosažení hodnoty `limits.trusted_after` schválených příspěvků se autor s rolí `user` automaticky povýší na `trusted`. Aktuální konfigurace stanovuje tento limit na 5. Povýšení se vztahuje pouze na uživatele s rolí `user`; administrátorské a jiné role se tímto mechanismem nemění.

### Zamítnutí návrhu

Při zamítnutí:
- návrh se odstraní z DB,
- odstraní se jeho JSON,
- odstraní se případný obrázek,
- zapíše se událost do bezpečnostního logu.

V aktuální implementaci se při zamítnutí nepřičítá schválený příspěvek.

### Omezení typů návrhů

Schválení je v aktuální implementaci podporováno pouze pro návrhy akcí. U ostatních typů návrhů je možné zamítnutí; schválení se odmítne jako nepodporovaná operace. Moderátor návrh v tomto procesu neupravuje.

## 7. Úklid souborů a ochrana dat

- Veřejné routy nesmějí zpřístupnit adresář `data` přímo.
- Obrázky draftů a pending návrhů se vydávají pouze po ověření přihlášení a oprávnění.
- Po schválení nebo zamítnutí se pending záznam a jeho soubory odstraní, aby fronta zbytečně nenarůstala.
- Dočasné drafty se uklízejí podle TTL nastavení cronu.
- Chyba při zápisu publikované akce musí vrátit transakci DB a odstranit případné částečně vytvořené soubory.

## 8. Ověřovací scénáře

| Scénář | Stav |
|---|---|
| Formulář nové akce a jeho validace | Částečně ověřeno průchodem uživatele |
| Obrázek načtený z URL a jeho náhled | Ověřeno uživatelem |
| Náhled detailu před odesláním | Ověřeno uživatelem |
| Odeslání role 2 do pending | Uživatel hlásí jako funkční; rozpor s aktuální definicí role 2 je nutné prověřit |
| Schválení návrhu účtem role 4 | Ověřeno uživatelem |
| Zveřejnění schválené akce | Ověřeno uživatelem |
| Přímé zveřejnění rolí trusted a vyšší | Implementováno podle kódu; prakticky neověřeno |
| Odeslání běžnou rolí 1 | Neověřeno |
| Úprava draftu po návratu z náhledu | Neověřeno |
| Zrušení draftu a odstranění obrázku | Neověřeno |
| `/my` – výpis a smazání vlastního návrhu | Neověřeno |
| Zamítnutí návrhu moderátorem | Neověřeno |
| Duplicita a limity pending / za den | Neověřeno |
| Automatické povýšení po pěti schválených příspěvcích | Implementováno podle kódu; prakticky neověřeno |
| Návrhy jiných typů než akce | Implementované omezení; prakticky neověřeno |

## 9. Dotčené soubory a routy

Hlavní implementační soubory:
- `src/events_add.php` – formulář, draft, validace, obrázky, pending vytvoření a publikace.
- `src/review.php` – moje návrhy, fronta schvalování, schválení a zamítnutí.
- `templates/event_form.php` – formulář nové akce.
- `templates/event_preview.php` – náhled před odesláním.
- `templates/review.php` – schvalovací fronta a detail návrhu.
- `templates/my.php` – moje čekající návrhy.
- `assets/js/event-form.js` – chování formuláře a náhledu obrázku.
- `config.php` – role, limity, nastavení obrázků a dočasných souborů.
- `index.php` – mapování GET a POST rout.

Hlavní routy:
- GET: `event/new`, `event/preview`, `event/draft-image`, `pending/image`, `my`, `review`.
- POST: `event/new`, `event/submit`, `event/cancel`, `my/delete`, `review`.

Při dalších úpravách se nejprve kontroluje tento dokument. Pokud se změní pravidla rolí nebo způsob schvalování, musí se aktualizovat také tabulka rolí, stav testů a upozornění na případné rozpory.


## 10. Našeptávač názvu akce

Ve formuláři `/event/new` je našeptávač názvů již zveřejněných akcí:
- požadavek se posílá průběžně při psaní s krátkým zpožděním,
- minimální délka dotazu a počet výsledků se přebírají z `paging.autocomplete_min_chars` a `paging.autocomplete_limit` v `config.php`,
- hledá se podle normalizovaného začátku názvu v `events.name_ascii`,
- výsledky jsou jedinečné názvy zveřejněných akcí; čekající návrhy se nezobrazují,
- kliknutí na výsledek pouze doplní název do pole. Nevytváří vazbu na existující akci ani neslučuje koncerty se stejným názvem,
- endpoint `event/name-suggest` je dostupný pouze přihlášeným uživatelům s oprávněním `submit` a odpovídá JSON bez ukládání do cache.

Toto je pouze první krok našeptávání. Města, kluby a kapely budou řešeny samostatně.
