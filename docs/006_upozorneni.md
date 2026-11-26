# Upozornění

## Účel a rozsah

Tento dokument je samostatnou specifikací upozornění v aplikaci K&F. Pravidla upozornění se nedokumentují v souboru o oblíbených položkách.

## Aktuálně dohodnuté chování

**Stav: vizuální ikona je implementována v kódu; čeká na praktické ověření po nasazení. Funkčnost upozornění není specifikována ani implementována.**

- Ikona upozornění se zobrazuje pouze přihlášenému uživateli.
- V hlavičce je pořadí ikon zleva doprava: upozornění (zvonek), oblíbené (hvězdička), avatar.
- Zvonek je v současnosti pouze vizuální, neaktivní ikona.
- Zvonek nemá počítadlo ani odznak s počtem upozornění.
- Kliknutí na zvonek neotevírá nabídku ani jiné rozhraní a neprovádí žádnou akci.
- V této fázi se nepřidává databázová struktura, ukládání, načítání, vytváření ani správa upozornění.

## Budoucí specifikace

Jakmile bude dohodnuta funkčnost upozornění, budou pravidla doplněna výhradně do tohoto dokumentu. Samostatně se zde popíše zejména:

- jaké události upozornění vytvářejí,
- komu a za jakých podmínek se upozornění zobrazují,
- ukládání a životní cyklus upozornění,
- počet nepřečtených upozornění a jeho aktualizace,
- otevření a zobrazení seznamu upozornění,
- označování jako přečtené a případné mazání,
- oprávnění, ochrana a chování na různých zařízeních.

Žádný z těchto budoucích bodů se nepovažuje za již schválenou funkčnost.

## Dotčené soubory

- `templates/layout.php` – zobrazení statické ikony zvonku v hlavičce.
- `assets/css/app.css` – vzhled ikony.
