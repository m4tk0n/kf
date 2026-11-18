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
    $rest = $_GET;
    unset($rest['setlang']);
    header('Location: ' . url(implode('/', array_slice($parts, 1)), $parts[0]) . ($rest ? '?' . http_build_query($rest) : ''), true, 302);
    exit;
}

lang_code($parts[0]);
$route = array_slice($parts, 1);
$routeName = implode('/', $route);
$GLOBALS['KF_ROUTE'] = $routeName;

// Revalidate every authenticated request against the database. Any DB error fails closed.
$authenticatedUser = refresh_authenticated_user();
if ($authenticatedUser !== null) {
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
}
if (in_array($routeName, ['profile', 'settings', 'add'], true) && $authenticatedUser === null) {
    header('Location: ' . url('login'), true, 303);
    exit;
}
if ($routeName === 'moderation') {
    require_role(3); // Moderator (3) and administrator (4).
}
if ($routeName === 'admin') {
    require_role(4); // Administrator is role ID 4.
}
if (is_array($_SESSION['user'] ?? null) && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf(): void
{
    require_csrf_token();
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


if ($routeName === 'add' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $user = require_role(1);
    $type = (string)($_POST['type'] ?? '');
    $typeIds = ['event' => 1, 'venue' => 2, 'band' => 3];
    if (!isset($typeIds[$type])) { http_response_code(400); exit('Invalid submission type'); }
    $name = trim((string)($_POST['name'] ?? ''));
    $error = $name === '' || mb_strlen($name, 'UTF-8') > 120 ? L('add-error-name') : '';
    $payload = ['name' => $name];
    try {
        if ($error !== '') throw new RuntimeException($error);
        if ($type === 'band') {
            $q = db()->prepare('SELECT c.id country_id, g.id genre_id FROM ' . tbl('countries') . ' c CROSS JOIN ' . tbl('genres') . ' g WHERE c.iso2 = ? AND g.id = ?');
            $q->execute([strtoupper((string)($_POST['country'] ?? '')), (int)($_POST['genre'] ?? 0)]);
            $ref = $q->fetch();
            if (!$ref) throw new RuntimeException(L('add-error-reference'));
            $payload += ['country_id'=>(int)$ref['country_id'], 'genre_id'=>(int)$ref['genre_id']];
            $dupkey = 'band:' . slugify($name);
        } elseif ($type === 'venue') {
            $city = (int)($_POST['city_id'] ?? 0);
            $q = db()->prepare('SELECT id FROM ' . tbl('cities') . ' WHERE id=?');
            $q->execute([$city]);
            if (!$q->fetchColumn()) throw new RuntimeException(L('add-error-reference'));
            $payload['city_id'] = $city;
            $dupkey = 'venue:' . $city . ':' . slugify($name);
        } else {
            $venue = (int)($_POST['venue_id'] ?? 0);
            $eventType = (int)($_POST['event_type'] ?? 1);
            $startRaw = (string)($_POST['starts_at'] ?? '');
            $endRaw = (string)($_POST['ends_at'] ?? '');
            $q = db()->prepare('SELECT id FROM ' . tbl('venues') . ' WHERE id=?');
            $q->execute([$venue]);
            $start = DateTime::createFromFormat('Y-m-d\\TH:i', $startRaw);
            $end = $endRaw !== '' ? DateTime::createFromFormat('Y-m-d\\TH:i', $endRaw) : null;
            if (!$q->fetchColumn() || !in_array($eventType,[1,2,3],true) || !$start || ($endRaw !== '' && (!$end || $end < $start))) throw new RuntimeException(L('add-error-event'));
            $payload += ['venue_id'=>$venue,'event_type'=>$eventType,'starts_at'=>$start->format('Y-m-d H:i:s'),'ends_at'=>$end ? $end->format('Y-m-d H:i:s') : null];
            $dupkey = 'event:' . slugify($name) . ':' . $start->format('YmdHi');
        }
        $stmt = db()->prepare('INSERT INTO ' . tbl('pending') . ' (type, dupkey, user_id, payload) VALUES (?, ?, ?, ?)');
        $stmt->execute([$typeIds[$type], $dupkey, (int)$user['id'], json_encode($payload, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        $_SESSION['add_success'] = L('add-submitted');
    } catch (Throwable $e) {
        $_SESSION['add_error'] = $e instanceof RuntimeException ? $e->getMessage() : L('add-error-save');
        if (!$e instanceof RuntimeException) error_log('K&F add submission: '.$e->getMessage());
    }
    header('Location: ' . url('add') . '?type=' . rawurlencode($type), true, 303);
    exit;
}

match ($routeName) {
    ''      => page('home', ['title' => L('home-title')]),
    'login' => page('login', ['title' => L('login-title')]),
    'profile' => page('profile', ['title' => L('profile-title')]),
    'settings' => page('settings', ['title' => L('settings-title')]),
    'add' => page('add', ['title' => L('add-title')]),
    'moderation' => page('role-center', ['title' => L('moderation-title'), 'center' => 'moderation']),
    'admin' => page('role-center', ['title' => L('admin-title'), 'center' => 'admin']),
    'auth/google/start' => require_google_auth('start'),
    'auth/google/callback' => require_google_auth('callback'),
    default => page('404', ['title' => L('err-404')], 404),
};