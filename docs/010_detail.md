# Hlavička detailu

Společná hlavička detailních stránek akcí, kapel a klubů/míst.

## Rozložení

1. Horní řádek: hvězdička oblíbených vlevo, tlačítko Zpět vpravo.
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
- Hvězdička zachovává stávající chování a stav z databáze; nepřihlášeným se zobrazuje pouze neaktivní šedá hvězdička.
- Tlačítko Zpět zachovává původní cílovou URL i atribut `data-detail-back`.
- Obrázek zůstává volitelný. Název se zobrazuje vždy.
