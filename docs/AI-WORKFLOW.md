# Pravidla práce na K&F

Tento dokument je pracovní kontrakt pro každou další změnu. Před úpravou si načti tento soubor a aktuální zdrojové soubory z větve `main`. Nikdy neodvozuj stav aplikace pouze ze starší konverzace.

## 1. Zdroje pravdy

- Aktuální implementace: soubory v GitHub `main`.
- Databázové schéma: `install/sql/*.sql` a `install/migrations/*.sql`.
- Skutečný stav databáze: pouze výsledek diagnostiky instalátoru nad živou DB. SQL soubor není důkazem, že migrace proběhla.
- Skutečný stav serveru: poslední úspěšný server snapshot ve větvi `server-snapshot`; ověřit datum/run a porovnat konkrétní soubory s `main`.
- Nasazení: úspěšný FTP workflow run. Commit v `main` sám o sobě neprokazuje FTP deploy.
- Chování v prohlížeči: pouze skutečný HTTP/browser test. PHP lint ani CI jej neprokazují.
- Tajná konfigurace: `.env` na serveru. Nikdy ji nevypisovat, nestahovat do veřejného snapshotu ani nepřepisovat deployem.

## 2. Povinný postup před změnou

1. Zjisti aktuální HEAD větve `main` a načti relevantní soubory včetně jejich návazností.
2. Prohledej existující implementaci, než vytvoříš novou funkci. Nepřidávej druhý mechanismus pro něco, co už existuje.
3. Ověř datový tok: vstup → validace → oprávnění → DB → výstup/UI.
4. U DB změn zkontroluj instalační schéma, migrace i instalátor. DB se mění výhradně přes instalační GUI.
5. Změň jen potřebné soubory. Zachovej existující funkčnost, mobilní rozvržení a překlady CS/EN.
6. Spusť statické kontroly a ověř jejich skutečný výsledek.
7. Po commitu ověř commit SHA, CI a samostatně FTP deploy. Nezaměňuj jednotlivé stavy.

## 3. Povinné bezpečnostní a architektonické zásady

- PHP 8.3; konfigurace se čte přes `cfg()`, tajné hodnoty přes `env()`.
- Žádné `putenv()`.
- Prefix tabulek vždy z `.env` přes `TABLE_PREFIX`; žádný natvrdo zadaný prefix.
- `.env` se nesmí přepsat při FTP deployi.
- Session data se ukládají do DB; prohlížeč má pouze session cookie `KFSESSID`.
- Role jsou definované v `config.php`: 1 user, 2 trusted, 3 moderator, 4 admin. Role se ověřují serverově, nikdy pouze podle UI.
- Google OAuth musí validovat state, nonce, issuer, audience, expiraci a ověřený e-mail.
- Uživatelský vstup se validuje serverově; výstup se escapuje.
- Jazyk se vybírá podle explicitního prefixu URL, jinak podle hlavičky prohlížeče; cookie je preference po ruční změně.
- DB změny provádí instalátor; ruční SQL pokyny uživateli nedávat.
- Veřejné zobrazení země používá krátký kód (např. CZ).
- Festival zobrazuje celé datumové rozpětí.
- Rozhraní je mobile-first a kompaktní.

## 4. Jak formulovat stav

Používej přesné štítky:
- **Zdroj v main ověřen** – soubor byl právě načten z GitHubu.
- **Statická kontrola prošla** – konkrétní test skutečně doběhl s exit code 0.
- **CI prošlo** – konkrétní GitHub Actions run skončil success.
- **FTP nasazení prošlo** – deploy workflow skončil success.
- **Server ověřen** – konkrétní soubor/URL byl právě přečten ze serveru nebo snapshotu.
- **DB ověřena** – diagnostika četla skutečné schéma/data živé DB.
- **Prohlížeč ověřen** – daný scénář byl skutečně spuštěn.

Pokud důkaz chybí, napiš „neověřeno“. Nikdy nedoplňuj chybějící stav odhadem.

## 5. Výstup pro uživatele

- Nezahlcuj uživatele průběžným komentářem.
- Neopakuj zadání a neptej se na věci, které lze zjistit ze zdrojů.
- U změny uveď stručně: co se změnilo, commit, co prošlo za kontroly a co zůstává neověřené.
- Nikdy netvrď, že je něco nasazené nebo funkční, jen proto, že je to v `main`.
