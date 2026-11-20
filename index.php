<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cron/cron.php';

$path = req_path();

// AI endpoint (autorizace hlavičkou X-AI-Key, bez session)
$aiEndpoint = trim((string)cfg('ai_import.endpoint'), '/');
if ($aiEndpoint !== '' && ($path === $aiEndpoint || str_starts_with($path, $aiEndpoint . '/'))) {
    send_security_headers(false);
    scan_input();
    require_once __DIR__ . '/' . cfg('paths.src') . '/api_ai.php';
    api_ai_handle(trim(substr($path, strlen($aiEndpoint)), '/'));
}

scan_input();
$user = auth_resolve();
send_security_headers($user !== null);

if (is_post()) {
    if (ratelimit_hit('post', (int)cfg('limits.rate_limit_max_hits'))) {
        security_log('post_rate_limit');
        abort(429, t('error.429'));
    }
    csrf_verify();
}

// Veřejné stránky, přihlášení a formuláře doplní kroky 2 a 3 (router + šablony).
if ($path === '') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="' . e(current_lang()) . '"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e((string)cfg('app.name')) . '</title>'
        . '<h1>' . e((string)cfg('app.name')) . '</h1><p>' . e(t('app.tagline')) . '</p>'
        . ($user !== null ? '<p>' . e($user['display_name']) . '</p>' : '');
    exit;
}

abort(404, t('error.404'));
