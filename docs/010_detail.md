# Hlavička detailu

Společná hlavička detailních stránek akcí, kapel a klubů/míst.

## Rozložení

1. Horní řádek: pouze hvězdička oblíbených vlevo. Tlačítko Zpět se v detailu nezobrazuje.
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
- Obrázek zůstává volitelný. Název se zobrazuje vždy.


## Globální pravidlo tlačítek Zpět

Tlačítko Zpět se v detailu nezobrazuje. Ostatní tlačítka návratu v projektu si zachovávají jednotný vzhled, text „← Zpět“ a vlastní funkční cíl.

- Hvězdička je bez rámečku i pozadí. Klikací plocha má rozměr 32 × 32 px; samotné SVG má rozměr 20 × 20 px. Levý horní roh SVG přesně lícuje s levým horním vnějším rohem detailové karty. Ovládací plocha je posunuta o 5 px nahoru a 5 px doleva, takže hvězdička je proti předchozí pozici o 5 px níže a 5 px vpravo.
