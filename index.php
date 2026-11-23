<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// cron jen registruje shutdown funkci (běží po odeslání odpovědi)
if (is_file(__DIR__ . '/cron/cron.php')) {
    require_once __DIR__ . '/cron/cron.php';
}

send_security_headers(false);
header('Content-Type: text/html; charset=utf-8');

/** Obslouží přihlášení, profil, filtry a administraci; ostatní cesty vrací do výpisu. */
function kf_dispatch(): void
{
    $path = req_path();
    $post = is_post();
    $m = [];

    $get = [
        'login' => 'route_login',
        'profile' => 'route_profile',
        'filters' => 'route_filters',
        'admin/users' => 'route_admin_users',
        'admin/settings' => 'route_admin_settings',
    ];
    $posts = [
        'logout' => 'route_logout',
        'lang' => 'route_lang',
        'profile' => 'route_profile_post',
        'profile/delete' => 'route_profile_delete',
        'filters' => 'route_filters_post',
        'filters/toggle' => 'route_filters_toggle',
        'admin/users' => 'route_admin_users_post',
    ];

    $handler = null;
    $args = [];
    if (preg_match('~^auth/([a-z]+)/(login|callback)$~', $path, $m)) {
        if ($post) {
            abort(405, 'Method Not Allowed');
        }
        $handler = $m[2] === 'login' ? 'route_oauth_start' : 'route_oauth_callback';
        $args = [$m];
    } elseif ($post && isset($posts[$path])) {
        $handler = $posts[$path];
    } elseif (!$post && isset($get[$path])) {
        $handler = $get[$path];
    }
    if ($handler === null) {
        return;
    }

    send_security_headers(auth_resolve() !== null);
    if ($post) {
        if (ratelimit_hit('post', (int)cfg('limits.rate_limit_max_hits'))) {
            security_log('post_rate_exceeded');
            abort(429, t('error.429'));
        }
        csrf_verify();
    }
    $handler(...$args);
}

kf_dispatch();

$req = kf_request();
$view = kf_load($req);
require abs_path((string)cfg('paths.templates')) . '/layout.php';
