# Příprava dat pro seed K&F

## Cíl

Postupně vybudovat ověřenou propojenou evidenci českých a slovenských kapel, jejich akcí, line-upů a míst konání v letech **2026 až 2000**. Základním bodem průzkumu je vždy jedna kapela. Každá její nalezená akce rozšíří evidenci akcí, míst a dalších účinkujících kapel.

V této fázi se vytvářejí pouze pracovní JSON soubory. SQL se nevytváří a nic se nezapisuje do databáze.

## Hlavní postup

1. Vezmi kapelu z `bands.json` v poli `aktual`.
2. Procházej její doloženou koncertní historii postupně od roku 2026 zpět do roku 2000.
3. U každé nalezené akce ověř, zda už existuje v `events.json` v některém z polí `aktual`, `ok` nebo `next`. Pokud ne, přidej ji do `events.json` do `next`.
4. U každého místa konání ověř, zda už existuje v `venues.json`. Pokud ne, přidej nové místo do `venues.json` do `next`.
5. Projdi celý line-up akce. Každou novou kapelu, která ještě není v `bands.json` v `aktual`, `ok` ani `next`, přidej do `bands.json` do `next`.
6. U nové kapely ověř zemi původu. České a slovenské kapely zůstávají v `next` a čekají na vlastní průzkum. Zahraniční kapely po ověření původu přesuň rovnou do `ok`; jejich koncertní historii neprocházej.
7. Po dokončení všech let 2026–2000 aktuální kapelu přesuň z `aktual` do `ok`.
8. Vezmi další kapelu z `next`, přesuň ji do `aktual` a postup opakuj.

## Význam pracovních stavů

V souborech se používají pole:

- `aktual` — právě zpracovávaný záznam nebo právě zpracovávaná kapela.
- `ok` — ověřené a dokončené záznamy.
- `next` — nově objevené záznamy čekající na ověření nebo další krok.

U kapel je v `aktual` vždy pouze jedna právě procházená česká nebo slovenská kapela. U akcí a míst se nové nálezy nejprve ukládají do `next`; po ověření se přesunou do `ok`.

## Pravidla evidence

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
- V akci se místo uvádí pouze jako `venue_id`; název ani adresa místa se v `events.json` neopakují.
- Pokud místo nelze spolehlivě identifikovat, nevymýšlej jeho ID ani údaje. Akce může mít `venue_id: null`, dokud se místo nepodaří ověřit.

### Akce a line-up — `events.json`

- Každá konkrétní akce má jedno stabilní pracovní `id` a patří do `aktual`, `ok` nebo `next`.
- Nově objevenou akci přidej do `next`; po ověření údajů a zdrojů ji přesuň do `ok`.
- Typ je `concert` nebo `festival`. Koncert je jednodenní hudební akce; festival má hudební program ve více dnech. Samotné slovo Fest/Festival v názvu typ neurčuje.
- U vícedenní akce vyplň `date_from` a `date_to`; u jednodenní akce jsou stejné.
- Uváděj město a stát konání. Místo odkazuj přes `venue_id`.
- Line-up obsahuje odkazy `band_id`, pořadí `sort_order` a případně `time_play`. Název kapely se neopakuje.
- Každá zastávka turné je samostatná akce s vlastním datem, místem a line-upem. Nadřazené turné se eviduje jednou v globálním souboru `tours.txt`; akce na něj odkazuje přes `tour_id`.
- Pokud je údaj neověřený, ponech jej jako `null` nebo jej vynech podle šablony. Nic neodhaduj.

## Identifikace a duplicity

- Před založením akce porovnej název, datum, město, místo, line-up a zdroje. Stejná akce nalezená u více kapel se zapíše pouze jednou.
- Před založením místa porovnej název, adresu, město, stát a historické názvy.
- Před založením kapely ověř, zda nejde o jinou kapelu se stejným nebo podobným názvem.
- Pokud se objeví rozpor ve zdrojích, zaznamenej jej a nepovažuj údaj za potvrzený.
- Každý záznam akce musí mít alespoň jeden dohledatelný zdroj, který potvrzuje její konání nebo program. Zdroje ukládej přímo do pole `sources` jako URL.

## Turné

Turné je samostatný nadřazený záznam se společným názvem. Každá zastávka je vlastní akce v `events.json`, má vlastní datum, místo a line-up. Zastávky mohou být napříč roky. Do turné spojuj pouze akce, u kterých zdroj potvrzuje společné turné; podobný název nebo blízká data nestačí. Globální evidence turné zůstává v `tours.txt`.

## Rozsah a úplnost

- Pro každou českou a slovenskou kapelu projdi období od roku 2026 do roku 2000 včetně.
- Zahraniční kapely eviduj jako účinkující v line-upu, ale jejich další koncertní historii neprocházej.
- Zahrnuj pouze skutečně doložitelné akce. Neexistence nalezeného zdroje není důkazem, že se akce nekonala.
- Výsledkem bude co nejúplnější ověřená síť kapel, akcí, míst a line-upů. Nelze bez důkazů tvrdit, že byly nalezeny úplně všechny historické koncerty.

## Práce a checkpointy

Po každé menší dávce ukládej změny JSON souborů na GitHub a aktualizuj `research_progress.txt`, aby bylo možné pokračovat po přerušení. Zapisuj, kterou kapelu a které roky jsi prověřil, co bylo ověřeno a jaký je další konkrétní krok.

**Základní princip:** procházíme historii jedné kapely; její akce odhalují další místa a kapely; nové české a slovenské kapely následně procházejí stejným cyklem.
