# Hlavička detailu

Společná hlavička detailních stránek akcí, kapel a klubů/míst.

## Rozložení

1. Horní řádek: hvězdička oblíbených vlevo, šipka zpět s textem „Zpět“ vpravo.
2. Obrázek entity, pokud je dostupný.
3. Název aktuální entity:
   - detail akce: název akce,
   - detail kapely: název kapely,
   - detail klubu/místa: název klubu/místa.
4. Dosavadní obsah detailu pokračuje pod názvem beze změny.

Na detailu akce se pod jejím názvem v hlavičce samostatně nevypisují názvy kapel ani klubu.

## Implementace

- Všechny typy detailu používají společnou šablonu `templates/detail.php`.
- Horní řádek používá `detail-toolbar`, název `detail-title`.
- Hvězdička používá stejné SVG a stejné rozměry/čáru jako globální hvězdička vedle avataru. Aktivní stav je žlutě vyplněný, neaktivní žlutý obrys; nepřihlášeným se zobrazuje neaktivní šedá hvězdička. Chování a stav z databáze zůstávají beze změny.
- Ovládání návratu zobrazuje šipku `←` a lokalizovaný text „Zpět“. Zachovává původní cílovou URL i atribut `data-detail-back`.
- Obrázek zůstává volitelný. Název se zobrazuje vždy.


## Globální pravidlo tlačítek Zpět

Tlačítko v detailu je vzorem pro všechna tlačítka návratu v projektu: jednotný vzhled, barva a text „← Zpět“. Každé tlačítko si zachovává vlastní funkční cíl návratu. Výjimka platí pouze na výslovný pokyn uživatele.

- Hvězdička i tlačítko „← Zpět“ jsou v horním řádku zarovnané k horní hraně detailové karty. Jejich horní odstup od hrany karty odpovídá vnitřnímu odsazení karty. Hvězdička má rámeček jako tlačítko Zpět: 32 × 32 px, stejnou barvu okraje a zaoblení. Celý ovládací prvek je posunut o 9 px nahoru, aby kompenzoval horní padding karty 9 px a začínal u jejího horního okraje. SVG má rozměr 20 × 20 px a nemá dodatečný posun.
