<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/google_auth.php';

$base  = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/');
$uri   = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path  = trim(substr($uri, strlen($base)), '/');
$parts = $path === '' ? [] : explode('/', $path);
$langs = cfg('app.languages');

// Google OAuth callback is deliberately outside the language-prefixed routes.
if ($path === 'auth/google/callback') {
    $returnLang = (string)($_SESSION['google_oauth_lang'] ?? cfg('app.default_lang'));
    lang_code(in_array($returnLang, $langs, true) ? $returnLang : cfg('app.default_lang'));
    require_google_auth('callback');
}

if (!$parts || !in_array($parts[0], $langs, true)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . rtrim(cfg('app.base_url'), '/') . '/' . detect_lang() . '/' . $path . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}

if (isset($_GET['setlang'])) {
    set_lang_cookie($parts[0]);
    $rest = $_GET;
    unset($rest['setlang']);
    header('Location: ' . url(implode('/', array_slice($parts, 1)), $parts[0]) . ($rest ? '?' . http_build_query($rest) : ''), true, 302);
    exit;
}

lang_code($parts[0]);
$route = array_slice($parts, 1);
$GLOBALS['KF_ROUTE'] = implode('/', $route);

match (implode('/', $route)) {
    ''      => page('home', ['title' => L('home-title')]),
    'login' => page('login', ['title' => L('login-title')]),
    'auth/google/start' => require_google_auth('start'),
    // Keep compatibility with an older language-prefixed callback URL.
    'auth/google/callback' => require_google_auth('callback'),
    default => page('404', ['title' => L('err-404')], 404),
};
