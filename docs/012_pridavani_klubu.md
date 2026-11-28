# Přidávání klubů a míst

## Stav

Implementace je na GitHubu. Automatická kontrola syntaxe a nasazení se ověřují přes GitHub Actions; funkční průchod formulářem zatím není ručně potvrzen.

## Formulář /venue/new

Přidávání klubu je dostupné přihlášeným uživatelům s oprávněním submit. Formulář navazuje na dvoukrokový proces přidávání akcí:

1. Uživatel vyplní údaje.
2. Server zkontroluje povinná pole, e-mail, URL a existenci města.
3. Návrh se uloží do uživatelského draftu.
4. Uživatel uvidí kompletní náhled detailu.
5. Může se vrátit k úpravě, odeslat návrh nebo jej zrušit.

### Pole

- Název klubu / místa
- Město a stát (výběr existujícího města; stát je určen městem)
- Adresa
- E-mail
- Telefon
- Webová adresa
- Odkaz / GPS Google Maps
- Odkaz / GPS Mapy.com
- Popis
- Obrázek (soubor nebo URL)

Obrázek se ukládá jako WebP a je součástí náhledu i detailu. V hlavním seznamu klubů /?s=venues se obrázek nezobrazuje.

## Odeslání a oprávnění

- Role user vytvoří návrh v tabulce pending s typem venue.
- Role trusted a vyšší mohou klub publikovat přímo podle oprávnění publish_direct.
- Kontroluje se limit čekajících návrhů, denní limit a duplicita názvu ve stejném městě.
- Rozpracovaný návrh používá existující úložiště draftů v data/tmp; obrázek je uložen odděleně a přístupný přes chráněnou routu venue/draft-image.

## Schvalování

Na stránce /review se návrh klubu zobrazí stejně jako ostatní položky fronty. Moderátor nebo administrátor může otevřít náhled, schválit nebo zamítnout návrh. Při schválení se:

1. znovu ověří hodnoty a duplicita,
2. vytvoří záznam v tabulce venues,
3. uloží doplňující údaje do JSON v data/venues,
4. uloží obrázek do img/venues ve variantách _s a _l,
5. odstraní záznam a soubory z fronty pending,
6. zvýší autorovi počet schválených příspěvků.

Při zamítnutí se odstraní návrh i jeho obrázek.

## Detail klubu

Detail používá existující tabulku venues a JSON bucket venues. Adresa, e-mail, telefon, web, popis a odkazy na mapy se načítají z JSON. Obrázek se zobrazuje v detailu. V seznamu klubů se nezobrazuje.

## Poznámky k ověření

Je potřeba prakticky vyzkoušet:
- běžný uživatel: formulář → náhled s obrázkem → pending,
- moderátor: otevření a schválení klubu,
- zamítnutí klubu,
- přímé zveřejnění rolí trusted a vyšších,
- zobrazení detailu a ověření, že seznam klubů zůstává bez obrázků.