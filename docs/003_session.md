# Přihlášení na jednom zařízení

## Účel

Jeden uživatelský účet může mít současně aktivní pouze jednu přihlášenou session. Pokud se uživatel přihlásí na jiném zařízení, předchozí zařízení bude odhlášeno.

## Pravidla

- Při úspěšném přihlášení se pro uživatele vytvoří nový náhodný session token.
- V rámci jedné databázové transakce se nejprve odstraní všechny dosavadní řádky uživatele z tabulky `sessions` a následně se vloží nová session.
- Operace musí být atomická, aby při dvou téměř současných přihlášeních nezůstaly aktivní dvě session. Rozhodující je poslední úspěšně dokončené přihlášení.
- Do cookie nového zařízení se uloží pouze nový náhodný token; v databázi zůstává pouze jeho SHA-256 hash.
- Cookie na původním zařízení nelze serverem vzdáleně smazat. Její token je ale po odstranění příslušného řádku v `sessions` neplatný.

## Chování původního zařízení

- Při každém požadavku přihlášeného uživatele server ověří session proti tabulce `sessions`.
- Pokud cookie obsahuje token, jehož hash již v tabulce neexistuje, server nesmí uživatele považovat za přihlášeného ani mu zpřístupnit chráněné funkce.
- Pokud jde o session zneplatněnou novým přihlášením, zobrazí se modální okno uprostřed stránky s textem:

  **„Byli jste odhlášeni. Váš účet byl přihlášen na jiném zařízení.“**

- Modální okno musí být výrazné, srozumitelné a použitelné na mobilu. Nesmí jít pouze o text vložený do běžného obsahu stránky.
- Po potvrzení se uživatel přesměruje na veřejnou část aplikace nebo na přihlášení. Do té doby nesmí být možné pokračovat v chráněné části aplikace.
- Zpráva se zobrazí pouze jednou. Server si pro tento účel bezpečně předá jednorázový stav, aniž by obnovil nebo znovu uznal neplatnou session.

## Výjimky a bezpečnost

- Běžné odhlášení, suspendace, změna role a smazání účtu nadále zneplatní všechny session daného uživatele.
- Neplatný nebo expirovaný token se nikdy nesmí automaticky obnovit.
- Veřejné stránky zůstávají dostupné jako pro nepřihlášeného návštěvníka.
- Veškeré rozhodování probíhá na serveru; klientská část pouze zobrazí odpovídající modální okno.
- Implementace musí respektovat vlastní session mechanismus aplikace. PHP `session_start()` se nepoužívá.

## Ověřovací scénář

1. Uživatel se přihlásí na zařízení A.
2. Uživatel se stejným účtem přihlásí na zařízení B.
3. V databázi zůstane pouze session zařízení B.
4. Zařízení B může pokračovat jako přihlášené.
5. Při dalším požadavku zařízení A server zjistí neplatný token a zobrazí uvedené modální okno.
6. Zařízení A již nemá přístup k žádné funkci vyžadující přihlášení.
.
