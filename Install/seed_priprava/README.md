# Příprava dat pro seed K&F

## Cíl

Postupně vybudovat ověřenou propojenou evidenci českých a slovenských kapel, jejich akcí, line-upů a míst konání v letech **2026 až 2000**. Základním bodem průzkumu je vždy jedna kapela. Každá její nalezená akce rozšíří evidenci akcí, míst a dalších účinkujících kapel.

Pracovní data se ukládají do JSON souborů. Akce jsou rozdělené podle kalendářních roků, protože jich bude nejvíce a jeden společný soubor by zbytečně narůstal. SQL se v této fázi nevytváří a nic se nezapisuje do databáze.

## Hlavní postup

1. Vezmi kapelu z `bands.json` v poli `aktual`.
2. Procházej její doloženou koncertní historii v libovolném pořadí. Nemusíš dokončit jeden kalendářní rok, než přejdeš k jiné nalezené akci nebo roku; koncerty zapisuj průběžně podle toho, na které narazíš.
3. Akci ukládej do souboru `event_YYYY.json` podle roku jejího konání. Pokud už je v příslušném souboru v poli `aktual`, `ok` nebo `next`, znovu ji nezakládej.
4. Novou akci přidej do `next` příslušného ročního souboru. Po ověření ji přesuň do `ok`.
5. U každého místa konání ověř, zda už existuje v `venues.json`. Pokud ne, přidej nové místo do `venues.json/next`.
6. Projdi celý line-up akce. Každou novou kapelu, která ještě není v `bands.json` v `aktual`, `ok` ani `next`, přidej do `bands.json/next`.
7. České a slovenské kapely zůstávají v `next` a čekají na vlastní průzkum. Zahraniční kapely po ověření původu přesuň rovnou do `ok`; jejich koncertní historii neprocházej.
8. Po dokončení všech let 2026–2000 aktuální kapelu přesuň z `bands.json/aktual` do `bands.json/ok`.
9. Vezmi další českou nebo slovenskou kapelu z `bands.json/next`, přesuň ji do `aktual` a postup opakuj.

## Rozdělení souborů a pracovních stavů

- `bands.json` — centrální seznam kapel a fronta jejich zpracování.
- `venues.json` — centrální seznam fyzických míst konání.
- `event_2026.json` až `event_2000.json` — samostatná evidence akcí pro každý kalendářní rok.
- `tours.txt` — globální evidence turné, která mohou zasahovat do více roků.
- `research_progress.txt` — checkpoint průzkumu a informace, kde pokračovat.
- `evidence.json` — registr zdrojů a jednotlivých tvrzení, která zdroje potvrzují.
- `research_log.txt` — stručný chronologický protokol dokončených dávek a dalších kroků.
- `band_archive_index.json` — kontrolní seznam zdrojových archivů a ročních počtů položek pro aktuálně zpracovávanou kapelu; slouží ke kontrole, že nebyl přeskočen celý rok nebo část archivu, nikoli k určení pořadí práce.

Každý soubor s kapelami, místy i akcemi používá pole `aktual`, `ok` a `next`:

- `aktual` — právě zpracovávaný záznam.
- `ok` — ověřené záznamy.
- `next` — nově objevené záznamy čekající na ověření nebo další krok.

V `bands.json/aktual` je vždy jedna právě procházená česká nebo slovenská kapela. Akce se zapisují do ročníku odpovídajícího datu konání.

## Pravidla evidence

### Ověřování a zdroje — `evidence.json`

- Každý zdroj dostane stabilní ID `SRC-XXXX`; jeden zdroj lze použít pro více tvrzení.
- Každé důležité tvrzení má vlastní ID `CLM-XXXX`, typ entity, ID entity, pole, hodnotu, stav a seznam zdrojů.
- Stavy tvrzení: `verified`, `unverified`, `conflict`. Neúspěšné hledání není důkazem neexistence.
- Přednost mají primární zdroje: oficiální stránky kapel, pořadatelů a míst. Sekundární zdroje slouží ke křížové kontrole.
- Zdroje v samotném záznamu (`sources`) zůstávají jako URL; `evidence.json` navíc přesně určuje, které tvrzení který zdroj potvrzuje.
- Pokud se zdroje rozcházejí, údaj se nesmí odhadnout ani označit jako ověřený.

### Kapely — `bands.json`

- Každá skutečná kapela má jedno stabilní pracovní `id`.
- Před přidáním nové kapely kontroluj duplicity podle názvu, země, města, členů, diskografie a oficiálních profilů.
- Zaznamenávej přesný název, ověřený stát a město původu, žánr a případně oficiální URL.
- Kapela objevená v line-upu musí být v `bands.json`, i když je zahraniční a její historii nebudeme zpracovávat.
- Zahraniční kapely se po ověření původu přesouvají rovnou do `ok`. Pokud stát původu není jistý, ponech je v `next` do ověření.
- Jedna kapela se v seznamu eviduje pouze jednou; v line-upu se na ni odkazuje přes `band_id`.

### Místa — `venues.json`

- Každé fyzické místo má jedno stabilní pracovní `id`.
- Nově objevené místo přidej do `next`, po ověření přesuň do `ok`.
- Eviduj název, město, stát, ověřenou adresu a případně URL.
- Před založením porovnej také historické názvy a adresu. Přejmenování téhož prostoru nesmí bez kontroly vytvořit duplicitu.
- V akci se místo uvádí pouze jako `venue_id`; název ani adresa místa se v ročníkovém souboru neopakují.
- Pokud místo nelze spolehlivě identifikovat, nevymýšlej jeho ID ani údaje. Akce může mít `venue_id: null`, dokud se místo nepodaří ověřit.

### Akce a line-up — `event_YYYY.json`

- Každý soubor obsahuje akce jednoho kalendářního roku a vlastní pole `aktual`, `ok` a `next`.
- Každá konkrétní akce má stabilní pracovní `id`. ID musí být jedinečné napříč všemi ročníkovými soubory.
- Nově objevenou akci přidej do `next` souboru příslušného roku; po ověření údajů a zdrojů ji přesuň do `ok`.
- Typ je `concert` nebo `festival`. Koncert je jednodenní hudební akce; festival má hudební program ve více dnech. Samotné slovo Fest/Festival v názvu typ neurčuje.
- U vícedenní akce vyplň `date_from` a `date_to`; u jednodenní akce jsou stejné.
- Uváděj město a stát konání. Místo odkazuj přes `venue_id` z `venues.json`.
- Line-up obsahuje odkazy `band_id` z `bands.json`, pořadí `sort_order` a případně `time_play`. Název kapely se neopakuje.
- Každá zastávka turné je samostatná akce v ročníku podle data konání. Nadřazené turné se eviduje jednou v globálním souboru `tours.txt`; akce na něj odkazuje přes `tour_id`.
- Pokud je údaj neověřený, ponech jej jako `null` nebo jej vynech podle šablony. Nic neodhaduj.

## Identifikace a duplicity

- Před založením akce zkontroluj odpovídající ročníkový soubor. Porovnej název, datum, město, místo, line-up a zdroje. Stejná akce nalezená při průzkumu více kapel se zapíše pouze jednou.
- Před přidělením ID nové akce zkontroluj i ostatní ročníkové soubory, aby se pracovní ID neopakovalo.
- Před založením místa porovnej název, adresu, město, stát a historické názvy.
- Před založením kapely ověř, zda nejde o jinou kapelu se stejným nebo podobným názvem.
- Pokud se objeví rozpor ve zdrojích, zaznamenej jej a nepovažuj údaj za potvrzený.
- Každý záznam akce musí mít dohledatelné zdroje pro datum, místo, line-up a skutečné konání. Zdroje ukládej přímo do pole `sources` jako URL a jednotlivá tvrzení propojuj přes `evidence.json`.
- Ohlášený program sám o sobě nedokazuje, že se akce uskutečnila. Stav `held` vyžaduje důkaz o skutečném konání; plánovaná akce zůstává `planned`.

## Turné

Turné je samostatný nadřazený záznam se společným názvem. Každá zastávka je vlastní akce v ročníkovém souboru podle data konání, má vlastní datum, místo a line-up. Zastávky mohou být napříč roky. Do turné spojuj pouze akce, u kterých zdroj potvrzuje společné turné; podobný název nebo blízká data nestačí. Globální evidence turné zůstává v `tours.txt`.

## Kontrola úplnosti koncertní historie

- Před zahájením zápisu jedné kapely zjisti všechny dostupné primární a sekundární archivy a zapiš je do `band_archive_index.json`.
- Každý rok zpracuj celý, ne pouze několik prvních nalezených akcí. Počet položek v kontrolních archivech porovnej s počtem posouzených položek; rozdíly vysvětli (duplicitní záznam, soukromá akce, zrušení, ne koncert apod.).
- Pokud existují dva nezávislé archivy, použij je ke křížové kontrole. Rozdílné počty nejsou automaticky chybou; každou odlišnost je nutné vysvětlit.
- Roky lze zpracovávat v libovolném pořadí a průběžně se mezi nimi vracet. Není nutné dokončit jeden rok před zápisem akcí z jiného roku.
- Dokud nejsou zkontrolovány všechny roky 2026–2000 a vyřešeny rozdíly v dostupných archivech, kapela zůstává v `bands.json/aktual`.
- Průběžný stav každého roku zapisuj do `band_archive_index.json` a `research_progress.txt`.

## Rozsah a úplnost

- Pro každou českou a slovenskou kapelu projdi období od roku 2026 do roku 2000 včetně.
- Zahraniční kapely eviduj jako účinkující v line-upu, ale jejich další koncertní historii neprocházej.
- Zahrnuj pouze skutečně doložitelné akce. Neexistence nalezeného zdroje není důkazem, že se akce nekonala.
- Výsledkem bude co nejúplnější ověřená síť kapel, akcí, míst a line-upů. Nelze bez důkazů tvrdit, že byly nalezeny úplně všechny historické koncerty.

## Práce a checkpointy

Po každé menší dávce ukládej změny JSON souborů na GitHub a aktualizuj `research_progress.txt`, aby bylo možné pokračovat po přerušení. Zapisuj, kterou kapelu a které roky jsi prověřil, co bylo ověřeno a jaký je další konkrétní krok.

**Základní princip:** procházíme historii jedné kapely; její akce odhalují další místa a kapely; nové české a slovenské kapely následně procházejí stejným cyklem.


## Automatizovaný průzkum

Automatický sběr archivních podkladů a strukturální audit zajišťuje GitHub Actions workflow `K&F seed research crawler`. Popis spuštění, výstupů a omezení je v [automation/README.md](automation/README.md). Workflow ukládá pouze neověřené kandidáty; nikdy samo nepovyšuje nálezy do ověřených akcí.
