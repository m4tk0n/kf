<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/google_auth.php';

$base  = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/');
$uri   = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path  = trim(substr($uri, strlen($base)), '/');
$parts = $path === '' ? [] : explode('/', $path);
$langs = cfg('app.languages');

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
$routeName = implode('/', $route);
$GLOBALS['KF_ROUTE'] = $routeName;

/*
 * Re-check the signed-in account on every full or partial page request.
 * The database is the source of truth for role and account state; session
 * values are only a cache and must never grant permissions by themselves.
 */
if (is_array($_SESSION['user'] ?? null) && !empty($_SESSION['user']['id'])) {
    $users = tbl('users');
    $check = db()->prepare("SELECT id, role, state, confirmed FROM {$users} WHERE id = ? LIMIT 1");
    $check->execute([(int)$_SESSION['user']['id']]);
    $freshUser = $check->fetch();

    if (!$freshUser || (int)$freshUser['state'] === 2 || (int)$freshUser['confirmed'] !== 1) {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    } else {
        $_SESSION['user']['role'] = (int)$freshUser['role'];
        $_SESSION['user']['state'] = (int)$freshUser['state'];
    }
}

if (is_array($_SESSION['user'] ?? null) && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf(): void
{
    $submitted = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if (in_array($routeName, ['logout', 'profile/save', 'account/delete-request', 'account/delete-cancel'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
    require_csrf();
    $currentUser = $_SESSION['user'] ?? null;
    if (!is_array($currentUser) || empty($currentUser['id'])) {
        header('Location: ' . url('login'), true, 303);
        exit;
    }

    if ($routeName === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $params['path'],
                'domain' => $params['domain'], 'secure' => $params['secure'],
                'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        header('Location: ' . url(''), true, 303);
        exit;
    }

    $users = tbl('users');
    $userId = (int)$currentUser['id'];
    if ($routeName === 'profile/save') {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 80) {
            $_SESSION['profile_error'] = L('profile-name-invalid');
        } else {
            db()->prepare("UPDATE {$users} SET display_name = ? WHERE id = ?")->execute([$displayName, $userId]);
            $_SESSION['user']['name'] = $displayName;
            $_SESSION['profile_notice'] = L('profile-saved');
        }
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
    if ($routeName === 'account/delete-request') {
        db()->prepare("UPDATE {$users} SET deletion_scheduled_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ? AND deletion_scheduled_at IS NULL")->execute([$userId]);
        $_SESSION['user']['deletion_scheduled_at'] = true;
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
    if ($routeName === 'account/delete-cancel') {
        db()->prepare("UPDATE {$users} SET deletion_scheduled_at = NULL WHERE id = ?")->execute([$userId]);
        unset($_SESSION['user']['deletion_scheduled_at']);
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
}

match ($routeName) {
    ''      => page('home', ['title' => L('home-title')]),
    'login' => page('login', ['title' => L('login-title')]),
    'profile' => page('profile', ['title' => L('profile-title')]),
    'settings' => page('settings', ['title' => L('settings-title')]),
    'auth/google/start' => require_google_auth('start'),
    'auth/google/callback' => require_google_auth('callback'),
    default => page('404', ['title' => L('err-404')], 404),
};