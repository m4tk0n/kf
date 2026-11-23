# Jednoduchý debug

Debug se zapíná parametrem `?debug=1`. Vypne se odebráním parametru nebo změnou na `?debug=0`.

Panel zaznamenává volání `alert()` včetně zásobníku volání, JavaScript chyby, odmítnuté Promise, kliknutí a požadavky Fetch včetně HTTP statusu. Záznam lze zkopírovat.

Debug je určen pouze pro testování. Není chráněn přihlášením ani tokenem.
