# Upozornění

## Účel a rozsah

Tento dokument je samostatnou specifikací upozornění v aplikaci K&F. Pravidla upozornění se nedokumentují v souboru o oblíbených položkách.

## Aktuálně dohodnuté chování

**Stav: vizuální ikona je implementována v kódu; čeká na praktické ověření po nasazení. Funkčnost upozornění zatím není implementována.**

- Ikona upozornění se zobrazuje pouze přihlášenému uživateli.
- V hlavičce je pořadí ikon zleva doprava: upozornění (zvonek), oblíbené (hvězdička), avatar.
- Zvonek je v současnosti pouze vizuální, neaktivní ikona.
- Zvonek nemá počítadlo ani odznak s počtem upozornění.
- Kliknutí na zvonek neotevírá nabídku ani jiné rozhraní a neprovádí žádnou akci.
- V této fázi se nepřidává databázová struktura, ukládání, načítání, vytváření ani správa upozornění.

## Návrhy obsahu upozornění

Následující texty jsou návrhy typů zpráv, nikoli zatím schválené podmínky jejich vytváření:

- **Nový koncert oblíbené kapely:** „{oblibena kapela} má nový koncert“
- **Nová akce oblíbeného klubu:** „{oblibeny klub} má novou akci“
- **Nový údaj v katalogu:** informační zpráva označená ikonou [info], například „Nový stát“, „Nový žánr“ nebo „Nové město“.

U posledního typu zatím není určeno, zda se má upozornění vytvářet při každém přidání údaje, až po jeho schválení, nebo pouze za jiných podmínek.

## Návrhy stavů a vzhledu zvonku

Zvažované vizuální stavy:

- **Žádná zpráva / všechny zprávy přečtené:** zvonek pouze v obrysovém provedení, bez barevného upozornění.
- **Nová nepřečtená zpráva:** zvonek s barevným vizuálním upozorněním.

## Návrhy chování po kliknutí

Zatím jsou otevřené tyto varianty:

- **Bez nové zprávy (xx1):** zvonek může být neaktivní, případně může po kliknutí zobrazit informační modal, že zatím není žádná zpráva.
- **Všechny zprávy přečtené (xx2):** po kliknutí se může zobrazit modal nebo samostatná stránka s několika posledními upozorněními.

Není zatím rozhodnuto, zda se použije modal, nebo samostatná stránka, kolik posledních zpráv se zobrazí ani jak se budou zprávy označovat jako přečtené.

## Otevřené body k dohodnutí

- Přesné podmínky vzniku jednotlivých typů upozornění.
- Zda se upozornění vytváří až po schválení nového záznamu.
- Jak dlouho se upozornění uchovávají a kolik posledních zpráv se zobrazuje.
- Zda kliknutí na upozornění otevře související detail.
- Zda se zpráva označí jako přečtená při otevření seznamu, nebo až po jejím otevření.
- Konečná podoba stavů zvonku a chování při nulovém počtu zpráv.

Do schválení těchto bodů zůstává v platnosti pouze aktuální neaktivní vizuální ikona popsaná výše. Návrhy v tomto dokumentu se nesmějí považovat za implementační zadání.

## Dotčené soubory

- `templates/layout.php` – zobrazení statické ikony zvonku v hlavičce.
- `assets/css/app.css` – vzhled ikony.
