<?php
// auth/index.php — samostatná obsluha OAuth přihlášení; nevyžaduje změnu index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

send_security_headers(false);

$path = req_path();
$loginPath = trim((string)cfg('oauth.login_path'), '/');
$callbackPath = trim((string)cfg('oauth.callback_path'), '/');
$provider = 'google';
$expectedLogin = str_replace('{provider}', $provider, $loginPath);
$expectedCallback = str_replace('{provider}', $provider, $callbackPath);

if ($path === $expectedLogin && !is_post()) {
    oauth_google_start();
}
if ($path === $expectedCallback && !is_post()) {
    oauth_google_callback();
}
abort(404, t('error.404'));

function oauth_google_start(): never
{
    if (!cfg('oauth.google.enabled')) {
        abort(404, 'Přihlášení přes Google není povoleno.');
    }
    $clientId = trim((string)env('GOOGLE_CLIENT_ID', ''));
    $clientSecret = trim((string)env('GOOGLE_CLIENT_SECRET', ''));
    if ($clientId === '' || $clientSecret === '') {
        abort(503, 'Přihlášení přes Google zatím není nakonfigurováno. Chybí GOOGLE_CLIENT_ID nebo GOOGLE_CLIENT_SECRET v .env.');
    }

    $state = random_hex(32);
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $nonce = random_hex(32);
    $next = safe_next((string)($_GET['next'] ?? ''), '');
    $payload = json_encode([
        'state' => $state,
        'verifier' => $verifier,
        'nonce' => $nonce,
        'next' => $next,
        'exp' => time() + (int)cfg('session.oauth_state_ttl_sec'),
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        throw new RuntimeException('Nelze připravit přihlášení.');
    }
    $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, (string)env('APP_KEY', ''));
    setcookie((string)cfg('session.oauth_state_cookie'), $encoded . '.' . $signature, [
        'expires' => time() + (int)cfg('session.oauth_state_ttl_sec'),
        'path' => base_path(),
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $redirectUri = trim((string)env('GOOGLE_REDIRECT_URI', ''));
    if ($redirectUri === '') {
        $redirectUri = url(str_replace('{provider}', 'google', trim((string)cfg('oauth.callback_path'), '/')));
    }
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => (string)cfg('oauth.google.scope'),
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ];
    header('Location: ' . (string)cfg('oauth.google.auth_url') . '?' . http_build_query($params), true, 302);
    exit;
}

function oauth_google_callback(): never
{
    $cookieName = (string)cfg('session.oauth_state_cookie');
    $raw = $_COOKIE[$cookieName] ?? '';
    setcookie($cookieName, '', [
        'expires' => time() - 3600,
        'path' => base_path(),
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (isset($_GET['error'])) {
        abort(400, 'Přihlášení přes Google bylo zrušeno nebo odmítnuto.');
    }
    $parts = is_string($raw) ? explode('.', $raw, 2) : [];
    if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], (string)env('APP_KEY', '')), $parts[1])) {
        auth_fail('oauth_state_signature_invalid');
        abort(400, 'Přihlášení vypršelo. Zkus to znovu.');
    }
    $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
    $data = is_string($json) ? json_decode($json, true) : null;
    $state = (string)($_GET['state'] ?? '');
    if (!is_array($data) || empty($data['state']) || !hash_equals((string)$data['state'], $state)
        || (int)($data['exp'] ?? 0) < time() || empty($data['verifier'])) {
        auth_fail('oauth_state_invalid');
        abort(400, 'Přihlášení vypršelo. Zkus to znovu.');
    }
    $code = (string)($_GET['code'] ?? '');
    if ($code === '' || strlen($code) > 4096) {
        abort(400, 'Google neposlal autorizační kód.');
    }

    $redirectUri = trim((string)env('GOOGLE_REDIRECT_URI', ''));
    if ($redirectUri === '') {
        $redirectUri = url(str_replace('{provider}', 'google', trim((string)cfg('oauth.callback_path'), '/')));
    }
    $tokenResponse = http_request((string)cfg('oauth.google.token_url'), [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        'body' => [
            'code' => $code,
            'client_id' => (string)env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string)env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => (string)$data['verifier'],
        ],
        'max_bytes' => 65536,
        'timeout' => 10,
    ]);
    $token = json_decode($tokenResponse['body'], true);
    if ($tokenResponse['status'] !== 200 || !is_array($token) || empty($token['access_token'])) {
        security_log('oauth_google_token_failed', ['status' => $tokenResponse['status']]);
        abort(502, 'Google se nepodařilo ověřit přihlášení. Zkus to znovu.');
    }

    $userResponse = http_request((string)cfg('oauth.google.userinfo_url'), [
        'headers' => ['Authorization: Bearer ' . (string)$token['access_token'], 'Accept: application/json'],
        'max_bytes' => 65536,
        'timeout' => 10,
    ]);
    $profile = json_decode($userResponse['body'], true);
    if ($userResponse['status'] !== 200 || !is_array($profile)
        || empty($profile['sub']) || empty($profile['email'])
        || !filter_var((string)$profile['email'], FILTER_VALIDATE_EMAIL)
        || !in_array($profile['email_verified'] ?? false, [true, 1, 'true'], true)
        || empty($profile['name'])) {
        security_log('oauth_google_profile_invalid', ['status' => $userResponse['status']]);
        abort(403, 'Google neposkytl ověřený e-mail a platný profil.');
    }

    $provider = (int)cfg('enums.oauth_provider.google');
    $uid = substr((string)$profile['sub'], 0, 100);
    $user = q('SELECT id, user_state FROM ' . tbl('users') . ' WHERE provider = ? AND provider_uid = ?', [$provider, $uid])->fetch();
    $now = time();
    if ($user) {
        if ((int)$user['user_state'] !== (int)cfg('enums.user_state.active')) {
            abort(403, 'Tento účet není aktivní.');
        }
        $userId = (int)$user['id'];
        q('UPDATE ' . tbl('users') . ' SET display_name = ?, last_login_at = ? WHERE id = ?', [mb_substr(trim((string)$profile['name']), 0, 80), $now, $userId]);
    } else {
        $existing = (int)q(
            'SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE provider <> ? AND user_state <> ?',
            [(int)cfg('enums.oauth_provider.system'), (int)cfg('enums.user_state.deleted')]
        )->fetchColumn();
        $role = $existing === 0 && cfg('users.first_user_admin')
            ? (int)cfg('enums.role.admin')
            : (int)cfg('enums.role.user');
        q(
            'INSERT INTO ' . tbl('users') . ' (provider, provider_uid, display_name, role, user_state, approved_count, created_at, last_login_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
            [$provider, $uid, mb_substr(trim((string)$profile['name']), 0, 80), $role, (int)cfg('enums.user_state.active'), $now, $now]
        );
        $userId = (int)db()->lastInsertId();
    }

    session_create($userId);
    redirect((string)($data['next'] ?? ''), 303);
}
