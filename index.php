<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Cron se načítá pouze při výslovném zapnutí v config.php.
// Při enabled = false se cron.php vůbec nenačte ani nezaregistruje žádnou akci.
if (cfg('cron.enabled', false) === true && is_file(__DIR__ . '/cron/cron.php')) {
    require_once __DIR__ . '/cron/cron.php';
}

send_security_headers(false);
header('Content-Type: text/html; charset=utf-8');

/** Obslouží přihlášení, profil, filtry, oblíbené a administraci; ostatní cesty vrací do výpisu. */
function kf_dispatch(): void
{
    $path = req_path();
    $post = is_post();
    $m = [];

    $get = [
        'login' => 'route_login',
        'login/confirm' => 'route_login_confirm',
        'profile' => 'route_profile',
        'diagnostics' => 'route_diagnostics',
        'filters' => 'route_filters',
        'admin/users' => 'route_admin_users',
        'admin/settings' => 'route_admin_settings',
    ];
    $posts = [
        'logout' => 'route_logout',
        'login/confirm' => 'route_login_confirm_post',
        'lang' => 'route_lang',
        'profile' => 'route_profile_post',
        'diagnostics' => 'route_diagnostics_post',
        'profile/delete' => 'route_profile_delete',
        'filters' => 'route_filters_post',
        'filters/toggle' => 'route_filters_toggle',
        'fav' => 'route_fav',
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

// JSON data pro měsíční kalendář v pageru akcí (respektuje hledání i oblíbené: fav=1).
if (isset($_GET['cal'])) {
    $month = is_string($_GET['cal']) ? $_GET['cal'] : '';
    if (!preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $month)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'invalid_month']);
        exit;
    }
    $qa = kf_ascii(mb_substr(trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''), 0, 100));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['month' => $month, 'days' => kf_calendar($month, $qa)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// Detail akce / kapely / klubu: ?event=ID, ?band=ID, ?venue=ID
$detail = kf_detail_request();
if ($detail !== null) {
    kf_detail_page($detail[0], $detail[1]);
}

$req = kf_request();
$view = kf_load($req);

// Částečná odpověď (jen pager + výpis + stav) pro app.js: hvězdička v liště, stránkování, kalendář.
if (kf_get('partial') === '1') {
    header('Cache-Control: no-store');
    $user = auth_resolve();
    $favActive = $user !== null && (int)$req['fav'] === 1;
    require abs_path((string)cfg('paths.templates')) . '/list.php';
    echo '<div hidden data-view-state data-fav="' . ($favActive ? 1 : 0) . '" data-fav-href="'
        . e(kf_url($req, ['fav' => $favActive ? 0 : 1, 'p' => 1, 'k' => 0])) . '"></div>';
    exit;
}

require abs_path((string)cfg('paths.templates')) . '/layout.php';
