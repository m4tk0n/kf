# Přihlášení přes Google a Facebook

## Cíl

K&F umožňuje výhradně přihlášení přes Google a Facebook OAuth 2.0. Samostatná registrace, hesla K&F ani e-mailové ověřování neexistují. K&F neodesílá žádné e-maily.

## Identita účtu

- Úspěšné OAuth přihlášení ověřuje uživatele u zvoleného poskytovatele.
- Účet K&F se identifikuje výhradně dvojicí `provider + provider_uid`. E-mail není identifikátor účtu a účty se podle něj nespojují.
- E-mail, pokud jej poskytovatel vrátí, se může uložit jako kontaktní údaj v JSON profilu uživatele a zobrazit v profilu. Je pouze informativní; není podmínkou přihlášení ani registrace.
- K&F e-mail neposílá, neověřuje pomocí potvrzovacích odkazů nebo kódů a nevyžaduje jeho zadání.
- Google může vrátit příznak `email_verified`; Facebook Graph API jej neposkytuje spolehlivě. Tento údaj nemění ověření identity OAuth účtu a nepoužívá se pro přístupová práva.
- Pokud poskytovatel e-mail nevrátí, účet se přesto může přihlásit.

## Průběh OAuth

1. Uživatel na stránce přihlášení zvolí Google nebo Facebook.
2. Server vytvoří krátkodobý podepsaný stav OAuth a uloží odpovídající hodnotu do HttpOnly cookie.
3. Prohlížeč přejde na autorizační stránku zvoleného poskytovatele.
4. Poskytovatel po úspěšném ověření vrátí uživatele na callback K&F s autorizačním kódem a stavem.
5. Server ověří cookie, podpis, shodu a platnost state.
6. Server vymění autorizační kód za access token přes HTTPS.
7. Server načte profil uživatele přes Google OpenID Connect UserInfo nebo Facebook Graph API /me.
8. Server založí nebo načte účet podle `provider + provider_uid`.
9. Uloží zobrazované jméno, případný kontaktní e-mail a avatar do JSON profilu.
10. Vytvoří se vlastní session K&F. Platí pravidlo jediné aktivní session na uživatelský účet.

## Bezpečnostní pravidla

- Google OAuth používá PKCE S256.
- `state` je podepsaný pomocí HMAC s `APP_KEY`, svázaný s krátkodobou HttpOnly cookie a po callbacku se smaže.
- Facebook Graph API požadavky používají `appsecret_proof`.
- Access token se nepředává klientskému JavaScriptu ani se neukládá do databáze či uživatelského profilu.
- Návratová adresa po přihlášení musí být relativní cesta uvnitř K&F.
- Role se přiděluje výhradně na serveru. První běžný uživatel po čisté instalaci se může stát adminem; další uživatelé získají roli user.
- Účet se nikdy nevyhledává ani neslučuje podle e-mailu.
- Neaktivní nebo pozastavený účet se nepřihlásí.
- Potvrzení převzetí účtu při přihlášení na druhém zařízení je samostatný bezpečnostní krok; nejde o e-mailové ověření.

## Konfigurace

V `.env` se nastavují:

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`
- `FACEBOOK_APP_ID`
- `FACEBOOK_APP_SECRET`
- `FACEBOOK_REDIRECT_URI`

Callback adresy musí být stejné v `.env` a v nastavení OAuth aplikace u poskytovatele. Přihlašovací tlačítko se zobrazí pouze tehdy, když je poskytovatel povolen v `config.php` a jsou vyplněné jeho klíče.

## Testovací scénáře

1. Google: úspěšné přihlášení založí nebo načte účet podle Google ID.
2. Facebook: úspěšné přihlášení založí nebo načte účet podle Facebook ID.
3. Poskytovatel e-mail vrátí nebo nevrátí: přihlášení proběhne v obou případech.
4. Stejný e-mail přes Google a Facebook: nevznikne automatické sloučení účtů.
5. Neplatný nebo expirovaný state, chybějící cookie, odmítnuté oprávnění nebo chyba token endpointu: přihlášení se odmítne a zapíše se bezpečnostní událost.
6. Pozastavený účet: přihlášení se odmítne.
7. Přihlášení na druhém zařízení: zobrazí se potvrzení převzetí účtu a po potvrzení se zneplatní původní session.

## Dotčené soubory

- `src/oauth.php` – OAuth start/callback a ověření identity.
- `src/users.php` – uložení kontaktního e-mailu a avataru do JSON profilu.
- `src/routes.php` a `templates/profile.php` – předání a zobrazení údajů profilu.
- `config.php` – OAuth endpointy a povolení Google / Facebook.
- `env.example` – názvy proměnných pro OAuth tajné údaje.
- `templates/login.php` – dynamická tlačítka dostupných poskytovatelů.

**Stav implementace:** OAuth kód je zapracovaný. Praktické přihlášení je nutné ověřit s reálnými OAuth aplikacemi Google a Meta; bez vyplněných tajných údajů v produkčním `.env` nelze přihlášení aktivovat.
.
