# Ověřený seed českých a slovenských punkových míst a kapel

## Rozsah a pravidla

- Prohledávané roky: **2024, 2025, 2026, 2027 a 2028**.
- Tato první dávka obsahuje **37 míst a 34 kapel** s doloženými akcemi v letech 2024–2027.
- Do seznamu míst patří fyzická místa, kde se v uvedeném období konala doložená akce s alespoň jednou českou nebo slovenskou kapelou žánru punk, punk rock nebo přímého punkového subžánru.
- Kapely se ukládají pouze jednou, bez ohledu na počet akcí.
- Místo se ukládá pouze jednou pro konkrétní fyzickou adresu. Přestěhovaný klub se nesmí automaticky spojovat se starou adresou.
- Město a stát se odvozují přes `city_id`; adresa je uložena samostatně.
- Zóna A, Tři sestry, Horkýže Slíže a Daniel Landa nejsou uznáni jako důkaz punkového žánru a nejsou do kapelového seedu zařazeni.
- Při nejistém původu kapely, žánru nebo adrese se záznam nezařazuje, dokud není ověřen.

## Zdroje akcí

Hlavní archivy a pořadatelské zdroje použité pro dohledání konkrétních akcí, dat, míst a sestav:

- [E!E – koncerty](https://eecka.eu/concerts/?year=2024) – archiv koncertů včetně dalších oznámených termínů.
- [Houba – koncerty](https://www.houba.info/koncerty/) – koncertní archiv a potvrzení punkrockového zaměření kapely.
- [Odpad – oficiální web a koncerty](https://punkodpad.sk/) – koncerty 2024–2027 a potvrzení slovenského původu a punkového žánru.
- [Darkzin](https://www.darkzin.cz/) – nezávislý archiv koncertů, festivalů, sestav a klubů.
- [Punkgen](https://punkgen.sk/koncerty.php) – nezávislý archiv punkových akcí.
- [Punkáči deťom](https://punkaci2024.dvebe.sk/) – festivalové ročníky a program.
- [Mighty Sounds](https://www.mightysounds.cz/) – festivalové programy.
- [Punk Fest Chrudim](https://qrticket.cz/udalost/punk-fest-chrudim-7) – program a místo konání.
- [Kemping Rybníky Opatovce – kontakt](https://www.campingopatovce.sk/contact.asp) – adresa areálu.
- [Aeroklub Tábor](https://aktabor.cz/kontakty/) – adresa letiště Čápův dvůr.
- [Areál koupaliště Volyně – Bandzone](https://bandzone.cz/klub/arealkoupaliste?at=contacts) – místo a kontaktní adresa.

## Ověřování adres a kapel

- Adresy míst byly porovnány s oficiálními kontakty provozovatelů a dalším nezávislým zdrojem (městský nebo turistický portál, adresní registr, GoOut, Firmy.cz, Artmap, Citylife, OMA nebo místní katalog).
- Města a žánry kapel byly porovnávány mezi profily kapel a nezávislými hudebními profily/databázemi (zejména Bandzone, Darkzin, GoOut, hudební slovníky a festivalové profily).
- Festivalové areály jsou vedeny pod názvem fyzického místa, nikoli pod názvem festivalu.
- U Melodky je v této dávce pouze nová adresa Pisárecká 525/7a. Starší koncerty se k ní nepřiřazují.
- U Prvního Patra je použita adresa návštěvnického vstupu uváděná provozovatelem.

## Nové žánry

V `40_genres.sql` byly doplněny žánry, které v původním seedu chyběly:

- Punk
- Industrial punk
- Folk punk

Již existující žánry Celtic punk, Hardcore punk, Punk rock a Ska punk se znovu nevytvářejí.

## Pokrytí let

- V této první dávce jsou doložená místa a kapely z let **2024–2027**.
- Pro rok **2028** jsou v archivech zatím oznámené některé akce, ale u nalezených termínů není spolehlivě potvrzen konkrétní název místa a jeho adresa. Proto se zatím žádné místo z roku 2028 do seedu nepřidává.
- Seznam je průběžná ověřená dávka, nikoli tvrzení, že byly nalezeny úplně všechny punkové akce v ČR a SR.

## Záměrně nezařazená místa

- **Brněnecfest** – zdroje se rozcházejí v přesné lokalitě areálu; oficiální navigační bod není samotné místo konání.
- **Bývalé vlakové nádraží, Horní Jiřetín** – v seedu je uveden název místa z festivalových archivů; poštovní adresa Černická 25/20 je ověřena přes budovu Hotelu Nádraží a adresní registr.
- **Urban Spot, Banská Bystrica** – rozporné údaje o čísle adresy.
- **Bunkr, Liberec** – rozporné adresy Tržní náměstí 1385/8 a 876/11.
- **Živák, Rakovník** – nepodařilo se nezávisle potvrdit úplnou adresu.
- **Kynologická hala Buštěhrad** – nalezený údaj adresy akce a adresní údaj haly se zatím nepodařilo bezpečně spojit s jedním fyzickým místem.
- **Lucky Pery, Jindřichův Hradec** – rozporné adresy 9. května 256/II a Rezkova 256.
- **Pantofel Piváreň, Čadca** – rozporné adresní údaje.
- **PBBowling, Příbram** – nepodařilo se spolehlivě spojit název místa z koncertního archivu s konkrétní adresou.

Tyto položky se mohou doplnit až po vyřešení rozporů; nejsou nahrazeny odhadem.
