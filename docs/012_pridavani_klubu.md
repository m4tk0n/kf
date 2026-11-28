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

- Povinné údaje: název, název města, vybraný stát a alespoň jedna lokalizační informace (adresa nebo GPS odkaz Google Maps / Mapy.com).
- Název klubu / místa
- Lokalita je zobrazena v jednom řádku se třemi poli: Město, Stát a ISO2.
- Město: textové pole s našeptávačem z tabulky `cities` a s městy z vlastních návrhů uživatele (`pending`). Návrhy ostatních uživatelů se nezobrazují. Pokud město v databázi není, lze ponechat volný text; do návrhu se uloží `city_name` a `country_id`, `city_id` zůstane 0.
- Stát: textové pole s našeptávačem z tabulky countries. Výběr města automaticky doplní odpovídající stát i jeho ISO2; při výběru státu se našeptávání měst omezí na tento stát. Pouhé psaní do pole města ani státu nesmí přepisovat obsah druhého pole. Stát se doplní až po výběru nalezeného města; psaní státu nesmí vymazat ani změnit město.
- ISO2: automaticky doplňovaný textový údaj bez inputu; zobrazuje se podle vybraného státu.
- Odeslání vyžaduje název města o délce alespoň 2 znaků a platné ID státu. Pokud je vybráno existující město, server ověří jeho příslušnost ke státu. Nové město se do `cities` nezapisuje při odeslání návrhu.
- Adresa (pokud je vyplněna, musí mít alespoň 2 znaky; je povinná, pokud není vyplněn žádný GPS odkaz)
- E-mail (pokud je vyplněn, musí mít platný formát)
- Telefon
- Webová adresa (pokud je vyplněna, musí jít o platnou URL)
- Odkaz / GPS Google Maps (alternativa k adrese; musí jít o platnou URL)
- Odkaz / GPS Mapy.com (alternativa k adrese; musí jít o platnou URL)
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
4. uloží doplňující údaje do JSON v data/venues,
5. uloží obrázek do img/venues ve variantách _s a _l,
6. odstraní záznam a soubory z fronty pending,
7. zvýší autorovi počet schválených příspěvků.

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

## Společná validace formulářů

Validace je centralizována v `src/form_validation.php` (server) a `assets/js/form-validation.js` (prohlížeč). Společné funkce kontrolují formát e-mailu a URL a minimální délku adresy a města. Formulář klubu navíc vyžaduje adresu alespoň 2 znaky nebo platný GPS odkaz. Formulář akce používá stejnou kontrolu URL. Další formuláře, včetně samostatného formuláře kapely, mají používat stejné funkce a datové atributy; pravidla se nemají kopírovat do jejich vlastních JS souborů.


## Nová města u akcí

Formulář nové akce používá stejnou podporu neznámých měst jako formulář klubu. Pokud město není v `cities`, uloží se do návrhu `city_name` a `country_id` s `city_id = 0`. Našeptávač zobrazuje města z `cities` a města z vlastních návrhů uživatele. Při schválení se znovu ověří existence města; případně se založí v `cities` a akce se uloží s jeho ID.
