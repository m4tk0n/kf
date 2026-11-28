# Příprava zdrojových dat pro seed (2000–2027)

## Cíl

Vytvořit co nejúplnější, ale pouze fakticky ověřený přehled koncertních akcí, míst konání a kapel v letech **2000–2027**. Výsledkem této fáze jsou výhradně zdrojová textová data. **SQL se v této fázi nevytváří a nic se nezapisuje do databáze.**

Nejde jen o seznam známých festivalů. Cílem je postupně vybudovat síť vazeb:

- akce → místo konání → účinkující kapely,
- kapela → další akce, na kterých vystupovala,
- nová akce → další účinkující kapely,
- nově objevené kapely → jejich další doložené akce.

Toto procházení se opakuje, dokud přibývají nové ověřitelné akce a kapely.

## Rozsah hledání

- Časové období: kalendářní roky **2000 až 2027 včetně**.
- Postupovat **od roku 2027 směrem do minulosti**, rok po roce.
- Základní územní rozsah akcí: **Česká republika a Slovensko**.
- Zahrnout festivaly, klubové koncerty, koncertní série i další veřejné hudební akce.
- U velkých a známých festivalů procházet jednotlivé ročníky a jejich programy.
- Kapely mohou pocházet z libovolné země.
- Při průzkumu historie každé kapely zapisovat také její doložená vystoupení v **klubech v zahraničí** (mimo ČR a SR). U takového vystoupení uložit konkrétní akci i klub/místo, datum, město, stát a zdroje. Tato zahraniční klubová vystoupení se evidují kvůli úplnějšímu přehledu koncertní historie kapely.
- Zahraniční festivaly a jiné neklubové akce mimo ČR/SR se do tohoto rozšířeného rozsahu nepřidávají; výjimku lze doplnit pouze podle dalšího výslovného pokynu.
- Neomezovat se na punk. Zahrnout také další žánry, pokud odpovídají dohledaným akcím.
- U akcí, které se teprve mají konat, výslovně označit stav **plánováno**. Budoucí akci nikdy neoznačovat jako uskutečněnou.

## Výchozí zdroje a festivaly

Nejdříve vytvořit seznam významných českých a slovenských festivalů a opakovaných akcí. Jako první prověřit například:

- Pod Parou Fest
- Brněnec Fest
- Mighty Sounds
- Punkáči Deťom

Tento seznam je pouze výchozí. Postupně přidávat další festivaly, klubové série a významné akce, které se objeví při rešerši.

U každého festivalu hledat jednotlivé ročníky samostatně. Název festivalu sám o sobě není dokladem, že se konkrétní ročník konal nebo že na něm vystupovala určitá kapela.

## Hlavní postup: rozšiřování přes kapely

1. Začít rokem 2027 a vybrat výchozí festival nebo jinou známou akci.
2. Dohledat konkrétní akce daného roku; po jeho zpracování pokračovat rokem 2026, 2025 atd. až do roku 2000.
3. Pro každý ročník ověřit datum, název akce, místo, město, stát a účinkující.
4. Zapsat akci, místo, všechny ověřené účinkující kapely a zdroje.
5. Vzít každou nově nalezenou kapelu a projít její koncertní historii v ČR a SR v období 2000–2027; při tomto průzkumu zaznamenat také doložené zahraniční klubové koncerty.
6. U každého nalezeného koncertu zjistit další účinkující, přesné místo a zdroje.
7. Nově objevené kapely zařadit do fronty kapel k prozkoumání.
8. Stejným způsobem pokračovat přes další akce a kapely.
9. Průběžně kontrolovat, zda už akce, místo nebo kapela v některém ročníku není. V ročníkových souborech se mohou stejné kapely, akce i místa opakovat, protože každý ročník je samostatný pracovní celek.
10. Pokračovat, dokud se daří nacházet nové ověřitelné vazby.

### Pravidlo pro zahraniční vystoupení kapel

Pokud například na Brněnec Festu vystoupí zahraniční kapela, její účast na festivalu se zapíše. Potom se prověří její další vystoupení v ČR a SR a také doložené koncerty v zahraničních klubech. U každého nalezeného zahraničního klubového koncertu se uloží samostatná akce a klub/místo, aby bylo možné zobrazit kompletní časovou osu vystoupení kapely. Zahraniční festivaly mimo ČR/SR se zatím nezapisují.

## Adresářová struktura

Každý ročník má samostatný adresář, aby jednotlivé soubory nebyly příliš rozsáhlé:

```text
/Install/seed_priprava/
    README.md
    2000/
        bands.txt
        venues.txt
        events.txt
        sources.txt
    2001/
        bands.txt
        venues.txt
        events.txt
        sources.txt
    ...
    2027/
        bands.txt
        venues.txt
        events.txt
        sources.txt
```

Adresáře a soubory zakládat podle potřeby. Pracovní data zapisovat do adresáře odpovídajícího roku konání akce.

## Obsah souborů

### events.txt — akce

Každý konkrétní ročník nebo samostatná akce je samostatný záznam. Uvádět:

- název akce,
- stav akce (plánováno / uskutečněno / zrušeno, pokud je doloženo),
- datum nebo přesný rozsah dat,
- město,
- stát,
- místo konání,
- seznam ověřených účinkujících kapel,
- odkazy na zdroje.

U vícedenního festivalu uvést celý termín. Pokud není přesný den ověřen, nevymýšlet ho; zaznamenat pouze ověřený rozsah nebo údaj označit jako neověřený.

### venues.txt — místa konání

U každého místa uvádět:

- název klubu nebo místa,
- typ místa (klub, kulturní dům, festivalový areál, koupaliště, sokolovna, garáže apod.),
- město,
- stát,
- adresu,
- zdroj nebo zdroje potvrzující údaje.

Rozlišovat skutečný název místa od obecného popisu typu místa. Pokud se místo v průběhu let přejmenovalo, zachovat historický název použitý v době akce a případnou změnu doložit.

### bands.txt — kapely

U každé kapely uvádět:

- přesný název kapely,
- město původu,
- stát původu,
- žánr nebo žánry,
- zdroj nebo zdroje potvrzující údaje.

Nezaměňovat město původu kapely za město, kde odehrála koncert. Pokud původ, město nebo žánr nelze spolehlivě ověřit, údaj neodhadovat a ponechat jej jako neověřený.

### sources.txt — zdroje

Každý zdroj dostane jednoznačné označení použitelné v ostatních souborech. Uvádět:

- ID zdroje,
- URL,
- co přesně zdroj dokládá (konání akce, datum, program, účast kapely, místo, adresu, původ kapely nebo žánr),
- případně poznámku k důvěryhodnosti nebo rozporům.

Jeden zdroj může dokládat více údajů. U každého záznamu musí být zřejmé, které zdroje jej podporují.

## Ověřování a důvěryhodnost

Přednostně používat:

1. oficiální archiv pořadatele, festivalu nebo klubu,
2. dobové plakáty a programy,
3. oficiální stránky kapel a jejich archiv koncertů,
4. dobové články, reportáže a kulturní kalendáře,
5. důvěryhodné hudební archivy a databáze,
6. další dohledatelné zdroje, které konkrétní údaj skutečně potvrzují.

Vyhledávač, úryvek výsledku nebo samotná zmínka bez ověřitelného podkladu nejsou dostatečným potvrzením.

### Co se nesmí dělat

- Nevymýšlet názvy kapel, akcí, klubů, adresy, města, státy ani žánry.
- Neodvozovat účast kapely pouze z toho, že na akci běžně hrávala.
- Nezaměňovat podobně pojmenované kapely.
- Nepřepisovat současný název místa jako historický bez ověření.
- Nedoplňovat chybějící údaje odhadem.
- Nepovažovat neověřený záznam za hotový.
- Nevytvářet SQL ani databázové záznamy.

Pokud údaj nelze ověřit, uvést jej jako neověřený nebo jej do ověřených dat vůbec nezařadit. Rozpor mezi zdroji zaznamenat a nepředstírat jistotu.

## Duplicity a opakování

- Stejná kapela může být v souboru daného ročníku uvedena jen jednou, i když hrála na více akcích.
- Stejné místo se může objevit ve více ročnících.
- Stejná opakovaná akce se zapisuje pro každý skutečně konaný ročník zvlášť.
- Kapela, akce i místo se mohou opakovat v různých ročníkových adresářích.
- Při kontrole porovnávat názvy a možné varianty zápisu, aby nevznikaly falešné duplicity.
- Různé kapely se stejným nebo podobným názvem neslučovat bez ověření identity.

## Průběžná kontrola a dokončení

Po dokončení každé akce:

1. zkontrolovat úplnost jejích údajů,
2. zkontrolovat zdroje,
3. doplnit místa a kapely,
4. přidat nově objevené kapely do dalšího průzkumu.

Po dokončení průzkumu jedné kapely:

1. zkontrolovat její identitu a varianty názvu,
2. projít doložitelné koncerty v ČR/SR v letech 2000–2027,
3. doplnit nalezené akce a jejich účinkující,
4. přidat nové kapely do dalšího průzkumu.

Po dokončení ročníku zkontrolovat všechny čtyři soubory, jejich vzájemné vazby a duplicity. Nepřepisovat ani nema­zat dříve ověřené údaje bez důvodu a bez kontroly zdrojů.

## Výsledný účel

Teprve po dokončení sběru a samostatné kontrole dat se z připravených zdrojových souborů bude navrhovat a vytvářet SQL seed pro K&F.

Do výsledného SQL patří pouze autentické, ověřené kapely, akce a místa a správné vazby mezi nimi. Neověřené nebo fiktivní záznamy do seedu nepatří.

**Základní princip práce: každá ověřená akce přináší kapely, každá kapela může odhalit další akce a každá další akce přináší další kapely. Prohledáváme tímto způsobem historii české a slovenské koncertní scény v letech 2000–2027.**
