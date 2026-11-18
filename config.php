<?php
// Veškeré nastavení aplikace na jednom místě. Tajné hodnoty jdou z .env přes env().
// V kódu se čte jen přes cfg('sekce.klic'), žádné výchozí hodnoty v kódu.
return [
    'app' => [
        'base_url'     => env('BASE_URL'),
        'key'          => env('APP_KEY'),
        'debug'        => env('DEBUG') === '1',
        'timezone'     => 'Europe/Prague',
        'default_lang' => 'cs',
        'languages'    => ['cs', 'en'],
        'cookie_lang'  => 'kf_lang',
        'cookie_days'  => 365,
    ],
    'db' => [
        'host'    => env('DB_HOST'),
        'name'    => env('DB_NAME'),
        'user'    => env('DB_USER'),
        'pass'    => env('DB_PASS'),
        'prefix'  => env('TABLE_PREFIX'),
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        'templates' => 'templates',
        'lang'      => 'lang',
        'data'      => 'data',
        'img'       => 'img',
        'cache'     => 'cache',
    ],
    'paging' => [
        'events' => 20, 'bands' => 30, 'venues' => 30,
        'autocomplete' => 10, 'autocomplete_min_chars' => 2, 'debounce_ms' => 250,
    ],
    'img' => [
        'format' => 'webp', 'quality' => 80, 'max_bytes' => 3145728,
        'max_pixels' => 16000000,
        'folder_divisor' => 1000,
        'band'  => ['s' => 96,  'l' => 400],
        'event' => ['s' => 150, 'l' => 600],
        'venue' => ['l' => 800],
    ],
    'limits' => [
        'pending_per_user' => 10, 'submissions_per_day' => 20,
        'emails_per_hour' => 30, 'trusted_after' => 5,
    ],
    'auth' => ['password_min' => 8],
    'enums' => [
        'event_type'   => [1 => 'concert', 2 => 'festival', 3 => 'other'],
        'role'         => [1 => 'user', 2 => 'trusted', 3 => 'moderator', 4 => 'admin'],
        'user_state'   => [0 => 'ok', 1 => 'no_contrib', 2 => 'blocked'],
        'pending_type' => [1 => 'event', 2 => 'band', 3 => 'venue', 4 => 'city', 5 => 'genre'],
    ],
    // Pravidla množného čísla: vrací příponu klíče (one / few / many)
    'plural' => [
        'cs' => static fn(int $n): string => $n === 1 ? 'one' : ($n >= 2 && $n <= 4 ? 'few' : 'many'),
        'en' => static fn(int $n): string => $n === 1 ? 'one' : 'many',
    ],
    'install' => [
        'min_php'    => '8.3.0',
        'extensions' => ['pdo_mysql', 'gd', 'mbstring', 'json'],
        'writable'   => ['data', 'img', 'cache'],
    ],
];
