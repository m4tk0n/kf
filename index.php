<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$base  = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/');
$uri   = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path  = trim(substr($uri, strlen($base)), '/');
$parts = $path === '' ? [] : explode('/', $path);
$langs = cfg('app.languages');

// Bez jazyka v URL: přesměrování podle prohlížeče, jinak výchozí jazyk
if (!$parts || !in_array($parts[0], $langs, true)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . rtrim(cfg('app.base_url'), '/') . '/' . detect_lang() . '/' . $path . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}

// Přepnutí jazyka přepínačem: zapamatovat volbu a vrátit se na čistou adresu
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

match ($route[0] ?? '') {
    ''      => page('home', ['title' => L('home-title')]),
    default => page('404', ['title' => L('err-404')], 404),
};
