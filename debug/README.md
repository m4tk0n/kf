# Jednoduchý debug

Debug se zapíná a vypíná na stránce **DEBUG** v uživatelském menu. Nastavení se ukládá do profilu konkrétního uživatele.

Stránku DEBUG a diagnostický panel mohou používat pouze uživatel s ID 2 a administrátoři. Panelový JavaScript a CSS se do stránky připojí jen tehdy, když má oprávněný uživatel debug aktivní. Vypnutý debug se na stránce vůbec nenačítá.

Panel zaznamenává volání `alert()` včetně zásobníku volání, JavaScript chyby, odmítnuté Promise, kliknutí a požadavky Fetch včetně HTTP statusu. Záznam lze zkopírovat.
