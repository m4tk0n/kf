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

- Hvězdička je bez rámečku i pozadí. Klikací plocha má rozměr 32 × 32 px; samotné SVG má rozměr 20 × 20 px. Levý horní roh SVG přesně lícuje s levým horním vnějším rohem detailové karty. Proto je ovládací plocha posunuta o 10 px nahoru a 10 px doleva (9 px padding + 1 px okraj karty).
