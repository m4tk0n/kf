<?php
// config.php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Koncerty & Kluby',
        'default_lang' => 'cs',
        'supported_langs' => ['cs', 'en'], // názvy sloupců name_<lang> v DB se skládají jen z ověřené hodnoty z tohoto pole
        'timezone' => 'Europe/Prague',
        'debug' => false,
        'lang_cookie' => 'KF_LANG',          // explicitní volba jazyka návštěvníka (nepřihlášený)
        'lang_cookie_ttl_sec' => 31536000,
    ],

    'db' => [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_czech_ci',
        'prefix' => '', // přepisuje se z .env (TABLE_PREFIX)
        'emulate_prepares' => false,
        // názvy tabulek bez prefixu (prefix se přidává v kódu z cfg('db.prefix'))
        'tables' => [
            'users' => 'users',
            'sessions' => 'sessions',
            'countries' => 'countries', // id, iso2, iso3, name_cs, name_en
            'cities' => 'cities',       // id, country_id, name, name_ascii
            'genres' => 'genres',
            'venues' => 'venues',
            'bands' => 'bands',
            'events' => 'events',
            'event_bands' => 'event_bands',
            'pending' => 'pending',
            'fav_events' => 'fav_events',
            'fav_bands' => 'fav_bands',
            'fav_venues' => 'fav_venues',
        ],
    ],

    'paths' => [
        'lang' => 'lang',
        'data' => 'data',
        'img' => 'img',
        'cron' => 'cron',
        'templates' => 'templates',
        'src' => 'src',
        'assets' => 'assets',
    ],

    'storage' => [
        'max_db_mb' => 200,
        'max_inodes' => 100000,
        'max_disk_gb' => 15,
        'security_log_file' => 'data/security.log',
        'security_log_max_bytes' => 1048576, // 1 MB před rotací/ořezem (platí i pro error_log_file)
        'error_log_file' => 'data/error.log',
        'ratelimit_file' => 'data/ratelimit.json',
        'users_dir' => 'data/users',
        'tmp_dir' => 'data/tmp',                 // dočasné obrázky z rozpracovaných formulářů (náhled)
        'tmp_ttl_sec' => 3600,                   // starší soubory v tmp_dir maže cron
        'draft_file' => 'data/tmp/draft_%d.json', // rozpracovaný formulář: jeden soubor na uživatele (%d = user_id)
        'json_buckets' => [
            'events' => 'data/events',
            'bands' => 'data/bands',
            'venues' => 'data/venues',
            'pending' => 'data/pending',   // JSON návrhů čekajících na schválení
        ],
    ],

    'img' => [
        'format' => 'webp',
        'folder_divisor' => 1000,
        'max_bytes' => 3145728, // 3 MB
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'thumb_s' => [
            'max_w' => 320,
            'max_h' => 320,
            'quality' => 80,
        ],
        'thumb_l' => [
            'max_w' => 1280,
            'max_h' => 1280,
            'quality' => 85,
        ],
        'subdirs' => [
            'events' => 'img/events',
            'bands' => 'img/bands',
            'venues' => 'img/venues',
        ],
    ],

    // Bezpečné odchozí HTTP požadavky (stažení obrázku z URL, případný interní AI import)
    'http' => [
        'timeout_sec' => 10,
        'max_redirects' => 3,
        'max_bytes' => 3145728,          // horní limit stahovaných dat (3 MB)
        'block_private_ips' => true,     // ochrana proti SSRF: blokovat privátní, loopback a link-local IP
        'allowed_schemes' => ['http', 'https'],
        'allowed_ports' => [80, 443],
        'user_agent' => 'KF-Bot/1.0',
    ],

    'paging' => [
        'per_page_events' => 10,   // akce: počet záznamů ve výpisu od vybraného data dále
        'per_page_bands' => 24,
        'per_page_venues' => 24,
        'per_page_users' => 25,
        'detail_events' => 30,     // max. akcí v seznamech minulých / budoucích akcí v detailu klubu a kapely
        'max_per_page' => 100,
        'autocomplete_min_chars' => 2,
        'autocomplete_limit' => 10,
    ],

    'limits' => [
        'pending_per_type' => [
            'event' => 5,
            'band' => 5,
            'venue' => 5,
            'city' => 5,
            'genre' => 5,
        ],
        'submissions_per_day' => 20,
        'favorites_max' => 500, // max. oblíbených položek jednoho typu na uživatele
        'trusted_after' => 5, // počet schválených příspěvků (users.approved_count) pro povýšení user -> trusted
        'rate_limit_window_sec' => 3600,
        'rate_limit_max_hits' => 120,   // max. POST požadavků na IP za rate_limit_window_sec
        'rate_limit_max_entries' => 5000, // strop záznamů v data/ratelimit.json
        'ai_endpoint_max_hits' => 60,   // max. požadavků na AI endpoint za rate_limit_window_sec
        'auth_fail_max_hits' => 20,     // neplatné session tokeny, neplatný state/CSRF a podobné pokusy na IP za rate_limit_window_sec
        'install_key_max_hits' => 10,   // pokusy o zadání INSTALL_KEY na IP za rate_limit_window_sec
    ],

    'enums' => [
        'role' => [
            'guest' => 0,
            'user' => 1,
            'trusted' => 2,
            'moderator' => 3,
            'admin' => 4,
        ],
        'user_state' => [
            'active' => 1,
            'suspended' => 2,
            'deleted' => 3,
        ],
        'oauth_provider' => [
            'system' => 0, // systémový účet (AI), nelze se přihlásit
            'google' => 1,
            'facebook' => 2,
        ],
        'event_type' => [
            'concert' => 1,
            'festival' => 2,
            'other' => 3,
        ],
        'pending_type' => [
            'event' => 1,
            'band' => 2,
            'venue' => 3,
            'city' => 4,
            'genre' => 5,
        ],
    ],

    'users' => [
        'ai_user_id' => 1,        // systémový účet AI (zakládá instalátor), autor záznamů z AI importu
        'ai_name' => 'AI',
        'first_user_admin' => true, // první běžný uživatel po čisté instalaci se stane adminem, další jsou user
        'display_name_max' => 80,
    ],

    // Uložené filtry uživatele (JSON profil): typ => klíč tabulky v db.tables
    'filters' => [
        'types' => [
            'countries' => 'countries',
            'cities' => 'cities',
            'venues' => 'venues',
            'genres' => 'genres',
            'bands' => 'bands',
        ],
        'max_per_type' => 100, // maximum vybraných položek jednoho typu
        'list_limit' => 500,   // maximum položek nabízených ve formuláři jednoho typu
    ],

    // Přihlášení: vlastní session v tabulce sessions (PHP session_start() se nepoužívá)
    'session' => [
        'name' => 'KF_SESSID',
        'lifetime_sec' => 2592000,   // absolutní platnost session (30 dní)
        'idle_sec' => 604800,        // zrušení po neaktivitě (7 dní)
        'fresh_sec' => 900,          // "nedávné přihlášení" pro citlivé akce (smazání účtu, změna role)
        'touch_interval_sec' => 60,  // last_seen_at se aktualizuje nejvýše jednou za tuto dobu
        'token_bytes' => 32,         // délka náhodného tokenu v cookie (v DB jen jeho SHA-256)
        'max_per_user' => 1,         // jeden účet může mít současně jen jednu session
        'cookie_path' => null,       // null => cesta z BASE_URL (např. /_kf/)
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure_auto' => true,
        'check_user_agent' => true,  // při změně User-Agentu se session zruší a incident se zaloguje
        'oauth_state_cookie' => 'KF_OAUTH', // krátce platná cookie vázaná na podepsaný OAuth state
        'oauth_state_ttl_sec' => 600,
        'pending_login_cookie' => 'KF_PENDING_LOGIN',
        'pending_login_ttl_sec' => 300,
    ],

    'security' => [
        'csrf_field' => '_csrf',
        'csrf_origin_check' => true, // u POST navíc kontrola Origin/Referer proti BASE_URL
        'ip_header' => 'HTTP_X_FORWARDED_FOR', // aplikace běží za reverzní proxy; bere se poslední (proxy přidaná) adresa; null = REMOTE_ADDR
        'scan_input' => true,        // podezřelé vstupy se jen logují (neblokují se)
        'suspicious_patterns' => [
            '~\bunion\b.{0,30}\bselect\b~is',
            '~<\s*script\b~i',
            '~\bon(?:error|load|click|mouseover)\s*=~i',
            '~[\'"]\s*or\s+[\'"0-9]+\s*=\s*[\'"0-9]+~i',
            '~\bsleep\s*\(\s*\d+\s*\)~i',
            '~javascript\s*:~i',
        ],
        'no_store_for_auth' => true, // Cache-Control: no-store pro stránky přihlášeného uživatele
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
        ],
        'csp' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
    ],

    'oauth' => [
        'login_path' => 'auth/{provider}/login',       // zahájení přihlášení (krok 2)
        'callback_path' => 'auth/{provider}/callback',
        'google' => [
            'enabled' => true,
            'label' => 'Google',
            'env_id' => 'GOOGLE_CLIENT_ID',
            'env_secret' => 'GOOGLE_CLIENT_SECRET',
            'env_redirect' => 'GOOGLE_REDIRECT_URI',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
            'pkce' => true,
        ],
        'facebook' => [
            'enabled' => true,
            'label' => 'Facebook',
            'env_id' => 'FACEBOOK_APP_ID',
            'env_secret' => 'FACEBOOK_APP_SECRET',
            'env_redirect' => 'FACEBOOK_REDIRECT_URI',
            'api_version' => 'v25.0', // ověřeno 11/2026: v25.0 podporována do 29. 7. 2028, nejnovější je v26.0
            'auth_url' => 'https://www.facebook.com/{version}/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/{version}/oauth/access_token',
            'userinfo_url' => 'https://graph.facebook.com/{version}/me?fields=id,name,email,picture.type(large)',
            'scope' => 'email,public_profile',
            'pkce' => false,
        ],
    ],

    'cron' => [
        'enabled' => true,
        'interval_sec' => 300, // minimální rozestup mezi běhy cronu (5 min)
        'lock_timeout_sec' => 120,
        'batch_limit' => 5,
        'curl_timeout_sec' => 10,
        'lock_file' => 'cron/cron.lock',
        'state_file' => 'cron/cron_state.json',
        'after_response' => true, // spouštět až po odeslání odpovědi (fastcgi_finish_request(), pokud existuje)
        'tasks' => ['session_cleanup', 'tmp_cleanup', 'ratelimit_cleanup', 'ai_import'], // ai_import běží jen při ai_import.mode = internal
    ],

    'ai_import' => [
        'enabled' => true,
        'mode' => 'external', // external = data posílá GitHub Actions na endpoint; internal = cron stahuje zdroje sám
        'endpoint' => 'api/ai', // GET api/ai/sources, POST api/ai/import (hlavička X-AI-Key = AI_WRITE_KEY z .env)
        'max_body_bytes' => 262144, // 256 kB na jeden požadavek
        'max_items_per_request' => 10,
        'auto_approve' => false, // false => do fronty pending, true => rovnou publikovat
        'max_sources_per_run' => 3,
        'max_new_events_per_run' => 10,
        'sources' => [
            'https://punkhudba.wz.cz/koncerty/',
        ],
    ],

    'plural' => [
        'cs' => [
            'one' => 1,
            'few' => [2, 3, 4],
            'other' => 0,
        ],
        'en' => [
            'one' => 1,
            'other' => 0,
        ],
    ],

    // Instalátor (install/index.php): bez migrací, vždy smazat a znovu vytvořit; chráněn INSTALL_KEY z .env (prázdný = vypnutý)
    'install' => [
        'min_php' => '8.3.0',
        'extensions' => ['pdo_mysql', 'gd', 'mbstring', 'json', 'curl'],
        'token_ttl_sec' => 900, // platnost odemknutí instalátoru po zadání INSTALL_KEY
        'writable_dirs' => ['data', 'data/tmp', 'img', 'cron'],
        'sql_tables_dir' => 'install/sql/tables', // jeden soubor = jedna tabulka, pořadí podle číselného prefixu (10_users.sql, 15_sessions.sql, ...)
        'sql_seed_dir' => 'install/sql/seed', // počáteční data (např. 20_countries.sql)
        // obsah těchto složek se při instalaci smaže (ID začínají znovu od 1, nesmí zůstat osiřelé soubory); složky samotné zůstanou
        'wipe_dirs' => [
            'data/events',
            'data/bands',
            'data/venues',
            'data/users',
            'data/pending',
            'data/tmp',
            'img/events',
            'img/bands',
            'img/venues',
        ],
        'wipe_files' => [
            'data/ratelimit.json',
            'cron/cron_state.json',
            'cron/cron.lock',
        ],
        'wipe_optional_files' => ['data/security.log', 'data/error.log'], // v instalátoru zaškrtávátko (výchozí zapnuto)
        'wipe_keep' => ['.htaccess', 'index.html'],     // ochranné soubory, které se nikdy nemažou
    ],
];
