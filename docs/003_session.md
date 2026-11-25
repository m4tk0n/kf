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


## Bezpečnost ověřování identity při každém požadavku

Aplikace je navrhována také pro budoucí veřejný produkční provoz s tisíci uživatelů. Ověření identity a oprávnění proto musí být vždy prováděno na serveru. Klientská část nesmí být zdrojem pravdy o identitě uživatele.

### Závazná pravidla

- Při každém přímém načtení chráněné stránky server ověří platnost session proti databázi a ověří, zda má uživatel potřebná oprávnění.
- Stejné ověření se provede při každém následném serverovém požadavku, který čte nebo mění uživatelská data, včetně AJAX/fetch požadavků a ukládání filtrů.
- Identita uživatele se vždy odvodí ze serverem ověřené session. Nikdy se nepřebírá důvěryhodné ID uživatele z URL, POST/GET parametrů, JSON těla požadavku, JavaScriptu, cookies s ID uživatele ani z úložišť prohlížeče.
- Klientské údaje mohou určit požadovanou operaci nebo obsah filtrů, nikdy však nesmí určit, pod kterým uživatelským účtem se operace provede.
- Server musí při každém požadavku ověřit, že session stále existuje, nevypršela a patří aktivnímu účtu. Musí respektovat zneplatnění session po novém přihlášení, odhlášení, suspendaci, změně oprávnění nebo smazání účtu.
- Ověření oprávnění se provádí na serveru i tehdy, když je odkaz nebo ovládací prvek v klientském rozhraní skrytý. Skrytí položky menu není bezpečnostní kontrola.
- Změnové požadavky musí být chráněny proti CSRF. Ověření CSRF tokenu nenahrazuje ověření session ani oprávnění.
- Neplatná nebo chybějící session nesmí být automaticky obnovena ani nahrazena identitou předanou klientem. Požadavek se odmítne a klient dostane odpovídající informaci o nutnosti přihlášení.
- Chráněné odpovědi nesmí být sdíleně cachovány mezi uživateli.

### Pravidlo pro stránku Filtry

- Při načtení společné stránky filtrů server ověří režim a platnost identity.
- Režim otevřený z uživatelského menu vyžaduje přihlášení. Nepřihlášený uživatel nesmí položku Filtry v menu nikdy vidět a přímý přístup na chráněnou adresu musí server odmítnout nebo přesměrovat na přihlášení.
- Režim rychlých filtrů otevřený z veřejného výpisu je dostupný i hostovi.
- Každé načtení, uložení nebo jiné serverové zpracování filtrů znovu ověří session a oprávnění. U přihlášeného uživatele server sám určí účet, jehož JSON profil smí číst nebo měnit.
- Klient nesmí přepnutím parametru režimu získat přístup k trvalému ukládání do cizího nebo vlastního profilu bez platné autentizace a serverového ověření oprávnění.

### Kontrola otevřené stránky

- Během otevřené stránky se v přiměřeném intervalu provádí lehký požadavek na ověření platnosti session, aby klient zjistil její zneplatnění, například po přihlášení na jiném zařízení.
- Kontrola při otevřené stránce je pouze doplňková. Nenahrazuje povinné ověření při každém serverovém požadavku.
- Při zjištění neplatné session klient okamžitě zablokuje další chráněné akce a zobrazí odpovídající stav odhlášení. Server současně odmítne všechny další chráněné požadavky.
- Kontrola se nesmí provádět při každém pohybu myši nebo každé změně pixelu; ověřuje se při serverových požadavcích a samostatně v přiměřeném intervalu.

### Ověřovací scénáře

1. Nepřihlášený návštěvník otevře přímo chráněnou adresu filtrů z menu. Server přístup odmítne.
2. Přihlášený uživatel načte stránku filtrů. Server ověří session a oprávnění.
3. Klient odešle požadavek s podvrženým ID jiného uživatele. Server toto ID ignoruje a identitu odvodí z platné session.
4. Session je zneplatněna přihlášením na jiném zařízení. Následující požadavek na čtení i ukládání je odmítnut.
5. Klient se pokusí změnit režim stránky nebo parametry požadavku tak, aby získal trvalé ukládání bez oprávnění. Server operaci odmítne.
6. Změnový požadavek bez platné CSRF ochrany je odmítnut.
