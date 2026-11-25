# KALENDÁŘ – SPECIFIKACE A FUNKČNOST

## 1. Účel kalendáře

Kalendář slouží k rychlému vyhledávání akcí podle data. Je propojený s katalogem akcí a pagerem, který umožňuje přecházet mezi dny, ve kterých se konají akce.

Kalendář není samostatný seznam akcí. Je to ovládací prvek pro výběr data a orientaci v časovém rozložení koncertů, festivalů a dalších typů akcí.

Veškerá data se načítají z databáze. Nesmí se používat pevně zadaná data ani předem připravené seznamy akcí.

## 2. Otevření kalendáře

Kalendář se otevírá kliknutím na datum v pageru nad seznamem akcí.

Po otevření se zobrazí modální okno uprostřed stránky.

Modální okno:
- je umístěné uprostřed obrazovky,
- má tmavé poloprůhledné pozadí za sebou,
- nepřekrývá celý obsah neprůhlednou plochou,
- musí být dobře použitelné na mobilních telefonech,
- nesmí způsobovat horizontální posouvání stránky.

Kalendář lze zavřít:
- kliknutím na vybrané datum,
- kliknutím mimo modální okno,
- klávesou Escape.

## 3. Rozložení kalendáře

Kalendář má vždy pevnou mřížku 7 sloupců a 6 řádků.

Dny jsou řazeny od pondělí do neděle.

Hlavička obsahuje:
- tlačítko předchozí měsíc,
- název měsíce a rok,
- tlačítko následující měsíc.

Pod hlavičkou jsou zkratky dnů:
PO, ÚT, ST, ČT, PÁ, SO, NE.

Pod nimi následuje mřížka 42 kalendářních buněk.

Kalendář vždy zobrazuje všech 42 buněk, i když měsíc začíná nebo končí uprostřed týdne.

Dny patřící do předchozího nebo následujícího měsíce jsou zobrazeny odlišeným, méně výrazným stylem.

## 4. Označení dnů s akcemi

Kalendář zobrazuje pod číslem dne barevné tečky podle typů akcí, které se v daný den konají.

Barvy:
- koncert – barva definovaná pro koncerty v aplikaci,
- festival – barva definovaná pro festivaly v aplikaci,
- ostatní typy akcí – žlutá barva.

Každý typ se zobrazuje maximálně jednou tečkou za daný den. Pokud se v jeden den koná více akcí stejného typu, nezobrazují se duplicitní tečky.

Pokud se v jeden den koná více různých typů akcí, zobrazí se odpovídající kombinace teček.

Tečky musí vycházet ze skutečných záznamů v databázi a z jejich typů.

## 5. Vícedenní akce

Kalendář musí správně zohledňovat také akce, které trvají více dní.

Pokud má akce datum začátku a datum konce, musí být označeny všechny kalendářní dny, do kterých akce zasahuje.

Příklad:
- začátek: 26. 11. 2026,
- konec: 27. 11. 2026.

Tečka příslušného typu se zobrazí jak 26. listopadu, tak 27. listopadu.

Stejná logika musí fungovat také:
- při přechodu mezi měsíci,
- při přechodu mezi roky,
- u akcí trvajících více než dva dny.

Akce se započítává do každého dne, pro který platí:
`date_from <= den AND date_to >= den`.

## 6. Načítání dat

Data kalendáře se načítají samostatným AJAX/Fetch požadavkem.

Používá se endpoint:

`?cal=YYYY-MM`

Příklad:

`?cal=2026-11`

Endpoint vrací data pro zobrazení kalendáře v požadovaném měsíci.

Načítání musí respektovat aktuální filtry katalogu, aby kalendář zobrazoval pouze akce odpovídající nastavenému výběru.

Při přechodu na předchozí nebo následující měsíc se načtou data nového měsíce bez nutnosti obnovit celou stránku.

Při načítání se zachová otevřené modální okno a jeho ovládání.

## 7. Výběr data

Kliknutím na konkrétní datum se:
1. zavře modální okno,
2. nastaví vybrané datum jako filtr katalogu,
3. zachovají všechny ostatní aktivní filtry,
4. nastaví pager na výchozí pozici,
5. obnoví seznam akcí pro zvolené datum.

Výběr data musí fungovat i u dnů, které patří do předchozího nebo následujícího měsíce zobrazené mřížky.

Výběr data nesmí odstranit například filtr typu akce, interpreta, místa nebo jiná aktivní nastavení.

## 8. Propojení s katalogem akcí

Kalendář a seznam akcí musí používat stejnou logiku filtrování.

Pokud je vybrán konkrétní den, v seznamu se zobrazí také vícedenní akce, které v daném dni stále probíhají, i když začaly dříve.

Podmínka pro zahrnutí vícedenní akce:

`date_to >= vybrané_datum`

Akce, která začala před vybraným datem, ale končí až po něm, nesmí být ze seznamu vyřazena.

Příklad:
- akce začíná 26. 11.,
- akce končí 27. 11.,
- uživatel vybere 27. 11.,
- akce se musí zobrazit.

## 9. Pager podle začátků akcí

Pager přechází pouze mezi kalendářními daty, ve kterých začíná alespoň jedna filtrovaná akce. Neprochází jednotlivé dny trvání vícedenní akce.

Podmínka pro zařazení data do navigace:

`date_from = datum začátku akce`

Důsledky:
- jednodenní akce vytvoří jeden navigační bod,
- vícedenní festival vytvoří jeden navigační bod na první den svého konání,
- dny mezi začátkem a koncem festivalu se při klikání na šipky přeskočí, pokud v nich nezačíná jiná akce,
- pokud během festivalu začíná další akce, pager se na její datum zastaví,
- kalendář i ruční výběr data nadále umožňují zobrazit libovolný den festivalu.

Směry:
- `<` – předchozí datum začátku akce,
- `>` – následující datum začátku akce,
- `<<` – nejbližší datum začátku akce v předchozím měsíci,
- `>>` – nejbližší datum začátku akce v následujícím měsíci.

Pager musí respektovat stejné hledání, oblíbené a uživatelské filtry jako výpis akcí. Vícedenní akce se v pageru počítá pouze podle svého `date_from`, nikoliv podle všech dnů svého trvání.

### Příklad vícedenního festivalu

Pokud festival trvá 16.–28. 2. 2028 a během jeho trvání nezačíná žádná další akce, navigace je:

| Pager | Co se zobrazí |
|---|---|
| 16. 2. 2028 | Festival a další akce od tohoto data |
| 1. 3. 2028 | Další akce začínající 1. 3. 2028 |

Pokud například 20. 2. začíná samostatný koncert, pager se zastaví také na 20. 2. Festival se přitom zobrazí i v tomto dni, protože stále probíhá.

Při změně data se:
- nastaví nové datum,
- resetuje stránkování na první stránku,
- resetuje případný offset akcí.

## 10. Technické požadavky

- Kalendář musí být plně responzivní.
- Prioritou je mobilní zobrazení.
- Mřížka musí mít vždy 7 × 6 buněk.
- Načítání měsíců nesmí obnovovat celou stránku.
- Data musí pocházet z databáze.
- Musí být respektovány všechny aktivní filtry.
- Vícedenní akce musí být označeny ve všech dnech svého trvání.
- Výběr data musí správně aktualizovat katalog i pager.
- Nesmí se duplikovat pravidla filtrování na více místech, pokud lze použít společnou funkci.
- Úpravy kalendáře nesmí narušit ostatní funkce katalogu.

## 11. Zásada při dalších úpravách

Před každou změnou je nutné zkontrolovat aktuální implementaci kalendáře, pageru, filtrování a SQL dotazů.

Nesmí se měnit chování kalendáře pouze kvůli úpravě vzhledu.

Při opravách je nutné zachovat:
- pevnou mřížku 7 × 6,
- pondělí jako první den týdne,
- barevné označení typů akcí,
- označování všech dnů vícedenních akcí,
- zachování aktivních filtrů,
- výběr data bez zbytečného obnovení stránky,
- navigaci pageru pouze mezi daty začátků filtrovaných akcí,
- samostatný přehled aktuálně probíhajících akcí podle dnešního data a aktivních filtrů.

Každá změna musí být ověřena také na příkladu vícedenní akce přesahující do dalšího dne nebo měsíce.

## 12. Vazba kalendáře, pageru a výpisu

Kalendář, pager a výpis vycházejí ze stejného časového intervalu akce, ale mají odlišný účel:

- Kalendář označuje každý den trvání akce tečkou podle jejího typu.
- Pager přechází pouze mezi daty začátků filtrovaných akcí.
- Výpis pro zvolené datum nadále zahrnuje také vícedenní akce, které v tento den ještě probíhají, i když začaly dříve.
- Samostatný přehled „Probíhající akce“ zobrazuje akce, které zahrnují dnešní datum, nezávisle na datu právě nastaveném v pageru.

U vícedenní akce se proto její celé trvání zachovává ve výpisu a kalendáři, ale pager kvůli ní nevytváří navigační zastávku pro každý den.

## 13. Informační panel probíhajících akcí

Mezi horní pager a hlavní výpis akcí se vloží pouze informační panel s textem:

`Probíhající akce dnes: N`

Panel neobsahuje vysvětlující větu, není rozbalovací a nezobrazuje seznam akcí.

Pravidla:
- počet zahrnuje jednodenní i vícedenní akce, které probíhají dnes,
- každý záznam se započítá pouze jednou bez ohledu na počet dnů jeho trvání,
- počet respektuje aktuální hledání, oblíbené a všechny aktivní uživatelské filtry,
- počet se počítá podle dnešního data v časové zóně aplikace, nikoliv podle data zvoleného v pageru,
- změna data pageru nemění počet,
- panel se aktualizuje společně s pagerem a výpisem při živém hledání a změnách filtrů,
- panel se vykresluje pouze v sekci akcí, nikoliv v sekci kapel a klubů.
