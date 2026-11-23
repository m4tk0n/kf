# Přihlášení přes Google a Facebook

## Cíl

K&F používá výhradně přihlášení přes Google a Facebook. Vlastní účty s heslem, registrace heslem ani vlastní ověřovací e-maily se nepoužívají. Ověření přihlášení provádí poskytovatel OAuth 2.0.

## Rozlišení ověření účtu a e-mailu

- Úspěšný OAuth proces prokazuje, že uživatel ovládá daný účet Google nebo Facebook.
- Identita uživatele v K&F je určena výhradně dvojicí provider + provider_uid. E-mail není identifikátor účtu a podle e-mailu se účty nespojují.
- Google: e-mail je považován za ověřený pouze při výslovném email_verified = true v odpovědi OpenID Connect.
- Facebook: běžná odpověď Graph API /me neposkytuje spolehlivý příznak ověření e-mailu. Vrácený e-mail lze uložit jako kontaktní údaj, ale jeho stav zůstává neověřený. Přihlášení Facebook účtu je přesto platné a nevyžaduje vlastní e-mailové ověření K&F.
- Pokud poskytovatel e-mail nevrátí, účet se přesto může přihlásit; profil zobrazí, že e-mail nebyl poskytnut.
- Vlastní potvrzovací e-mail se neposílá a K&F neukládá ani nespravuje hesla.

## Průběh OAuth

1. Uživatel na stránce přihlášení zvolí Google nebo Facebook.
2. Server vytvoří krátkodobý podepsaný stav OAuth a uloží odpovídající hodnotu do HttpOnly cookie.
3. Prohlížeč přejde na autorizační stránku zvoleného poskytovatele.
4. Poskytovatel po úspěšném ověření vrátí uživatele na callback K&F s autorizačním kódem a stavem.
5. Server ověří cookie, podpis, shodu a platnost state a jednorázovost požadavku.
6. Server vymění autorizační kód za access token přes HTTPS.
7. Server načte profil uživatele. U Google používá OpenID Connect UserInfo; u Facebooku Graph API /me s poli id,name,email,picture a appsecret_proof.
8. Server ověří stabilní ID uživatele od poskytovatele a založí nebo načte účet podle provider + provider_uid.
9. Uloží zobrazované jméno, případný e-mail, stav ověření e-mailu a avatar do profilu JSON uživatele.
10. Vytvoří se vlastní session K&F. Platí stávající pravidlo jediné aktivní session na uživatelský účet.

## Bezpečnostní pravidla

- Google OAuth používá PKCE S256.
- state je podepsaný pomocí HMAC s APP_KEY, svázaný s krátkodobou HttpOnly cookie a po callbacku se smaže.
- Facebook Graph API požadavky obsahují appsecret_proof = HMAC-SHA256(access_token, FACEBOOK_APP_SECRET).
- Access token se nepředává klientskému JavaScriptu ani se neukládá do databáze či uživatelského profilu.
- Návratová adresa po přihlášení musí být relativní cesta uvnitř K&F.
- Role se přiděluje výhradně na serveru. První běžný uživatel po čisté instalaci se může stát adminem podle stávajícího pravidla; další uživatelé získají roli user.
- Účet se nikdy nevyhledává ani neslučuje podle e-mailu. Shodná e-mailová adresa Google a Facebook automaticky nevytvoří propojení účtů.
- Neaktivní / pozastavený účet se nepřihlásí.

## Konfigurace

V .env se nastavují:

- GOOGLE_CLIENT_ID
- GOOGLE_CLIENT_SECRET
- GOOGLE_REDIRECT_URI
- FACEBOOK_APP_ID
- FACEBOOK_APP_SECRET
- FACEBOOK_REDIRECT_URI

Callback adresy musí být stejné v .env a v nastavení OAuth aplikace u poskytovatele. Přihlašovací tlačítko se zobrazí pouze tehdy, když je poskytovatel povolen v config.php a jsou vyplněné jeho klíče.

## Testovací scénáře

1. Google: úspěšné přihlášení s email_verified=true; účet se založí / načte a e-mail se v profilu označí jako ověřený.
2. Google: chybějící nebo nepravdivé email_verified; přihlášení účtu může proběhnout, ale e-mail se nesmí označit jako ověřený.
3. Facebook: úspěšné přihlášení s vráceným e-mailem; účet se založí / načte, e-mail se zobrazí jako kontaktní, ale neověřený.
4. Facebook: poskytovatel e-mail nevrátí; přihlášení proběhne bez e-mailu.
5. Stejný e-mail přes Google a Facebook: nevznikne automatické sloučení účtů.
6. Neplatný nebo expirovaný state, chybějící cookie, odmítnuté oprávnění nebo chyba token endpointu: přihlášení se odmítne a zapíše se bezpečnostní událost.
7. Pozastavený účet: přihlášení se odmítne.
8. Přihlášení na druhém zařízení: použije se stávající potvrzení převzetí účtu a zneplatní se původní session.

## Dotčené soubory

- src/oauth.php – OAuth start/callback, ověření identity a e-mailu, Facebook appsecret_proof.
- src/users.php – uložení e-mailu, avataru a příznaku email_verified do JSON profilu.
- src/routes.php a templates/profile.php – předání a zobrazení stavu e-mailu.
- config.php – OAuth endpointy a povolení Google / Facebook.
- env.example – názvy proměnných pro OAuth tajné údaje.
- templates/login.php – dynamická tlačítka dostupných poskytovatelů.
- lang/cs.php, lang/en.php – texty přihlášení a stavu e-mailu.

**Stav implementace:** kód je zapracovaný. Praktické přihlášení je nutné ověřit s reálnými OAuth aplikacemi Google a Meta; bez vyplněných tajných údajů v produkčním .env nelze přihlášení aktivovat.
