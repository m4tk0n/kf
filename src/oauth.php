<?php
// src/oauth.php
declare(strict_types=1);

function oauth_provider_id(string $provider): int
{
    return (int)cfg('enums.oauth_provider.' . $provider);
}

/** Název poskytovatele podle čísla (systémový účet nemá žádný). */
function oauth_provider_name(int $id): ?string
{
    foreach ((array)cfg('enums.oauth_provider') as $name => $v) {
        if ($name !== 'system' && (int)$v === $id) {
            return (string)$name;
        }
    }
    return null;
}

function oauth_clean_text(string $s, int $max): string
{
    $s = strip_tags($s);
    $s = (string)preg_replace('/[\p{C}\s]+/u', ' ', $s);
    return mb_substr(trim($s), 0, max(1, $max));
}

/** Konfigurace poskytovatele doplněná o klíče z .env; null = vypnutý nebo nenastavený. */
function oauth_provider_cfg(string $name): ?array
{
    if (!preg_match('/^[a-z]+$/', $name)) {
        return null;
    }
    $c = cfg('oauth.' . $name);
    if (!is_array($c) || empty($c['enabled']) || !isset($c['auth_url'])) {
        return null;
    }
    $id = (string)env((string)$c['env_id'], '');
    $secret = (string)env((string)$c['env_secret'], '');
    if ($id === '' || $secret === '') {
        return null;
    }
    $ver = (string)($c['api_version'] ?? '');
    $rep = static fn($u): string => str_replace('{version}', $ver, (string)$u);
    $redirect = (string)env((string)$c['env_redirect'], '');
    if ($redirect === '') {
        $redirect = url(str_replace('{provider}', $name, (string)cfg('oauth.callback_path')));
    }
    return array_merge($c, [
        'name' => $name,
        'client_id' => $id,
        'client_secret' => $secret,
        'redirect' => $redirect,
        'auth_url' => $rep($c['auth_url']),
        'token_url' => $rep($c['token_url']),
        'userinfo_url' => $rep($c['userinfo_url']),
    ]);
}

/** @return array<string,array> použitelní poskytovatelé (název => konfigurace) */
function oauth_available(): array
{
    $out = [];
    foreach (array_keys(array_filter((array)cfg('oauth'), 'is_array')) as $name) {
        $c = oauth_provider_cfg((string)$name);
        if ($c !== null) {
            $out[(string)$name] = $c;
        }
    }
    return $out;
}

function oauth_login_url(string $provider, string $next = ''): string
{
    $u = url(str_replace('{provider}', $provider, (string)cfg('oauth.login_path')));
    $next = safe_next($next, '');
    return $next !== '' ? $u . '?next=' . rawurlencode($next) : $u;
}

function oauth_b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function oauth_b64_decode(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/'), true);
}

function oauth_sign(string $data): string
{
    return hash_hmac('sha256', $data, (string)env('APP_KEY', ''));
}

function oauth_cookie_set(string $value, int $expires): void
{
    if (headers_sent()) {
        return;
    }
    setcookie((string)cfg('session.oauth_state_cookie'), $value, [
        'expires' => $expires,
        'path' => session_cookie_path(),
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax', // musí projít při návratu z poskytovatele (top-level GET)
    ]);
}

/** HTTP požadavek na poskytovatele (pevné HTTPS adresy z configu). @return array{0:int,1:array} */
function oauth_http(string $method, string $url, array $headers = [], ?array $form = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)cfg('http.timeout_sec'),
        CURLOPT_CONNECTTIMEOUT => (int)cfg('http.timeout_sec'),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => (string)cfg('http.user_agent'),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query((array)$form);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($body)) {
        return [0, []];
    }
    $data = json_decode($body, true);
    return [$status, is_array($data) ? $data : []];
}

/** Zahájení přihlášení: podepsaný state + cookie s nonce, PKCE verifierem a cílem návratu. */
function oauth_start(string $provider, string $next): never
{
    $c = oauth_provider_cfg($provider);
    if ($c === null) {
        security_log('oauth_unknown_provider', ['p' => substr($provider, 0, 20)]);
        abort(404, t('error.404'));
    }
    $ttl = (int)cfg('session.oauth_state_ttl_sec');
    $nonce = random_hex(16);
    $verifier = random_hex(32);
    $payload = oauth_b64((string)json_encode([
        'n' => $nonce, 'p' => $provider, 'v' => $verifier, 'nx' => safe_next($next, ''), 'e' => time() + $ttl,
    ]));
    oauth_cookie_set($payload . '.' . oauth_sign('cookie|' . $payload), time() + $ttl);

    $params = [
        'client_id' => $c['client_id'],
        'redirect_uri' => $c['redirect'],
        'response_type' => 'code',
        'scope' => $c['scope'],
        'state' => $nonce . '.' . oauth_sign('state|' . $provider . '|' . $nonce),
    ];
    if (!empty($c['pkce'])) {
        $params['code_challenge'] = oauth_b64(hash('sha256', $verifier, true));
        $params['code_challenge_method'] = 'S256';
    }
    header('Location: ' . $c['auth_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

function oauth_fail(string $reason): never
{
    oauth_cookie_set('', time() - 86400);
    auth_fail($reason);
    redirect('login?m=oauth_failed');
}

/** Návrat z poskytovatele: ověření state, výměna kódu, načtení profilu, přihlášení / registrace. */
function oauth_callback(string $provider): never
{
    $c = oauth_provider_cfg($provider);
    if ($c === null) {
        security_log('oauth_unknown_provider', ['p' => substr($provider, 0, 20)]);
        abort(404, t('error.404'));
    }

    // cookie (single-use) a state
    $raw = (string)($_COOKIE[(string)cfg('session.oauth_state_cookie')] ?? '');
    oauth_cookie_set('', time() - 86400);
    $parts = explode('.', $raw, 2);
    $pl = null;
    if (count($parts) === 2 && hash_equals(oauth_sign('cookie|' . $parts[0]), $parts[1])) {
        $d = json_decode(oauth_b64_decode($parts[0]), true);
        $pl = is_array($d) ? $d : null;
    }
    if ($pl === null || (int)($pl['e'] ?? 0) < time() || ($pl['p'] ?? '') !== $provider) {
        oauth_fail('oauth_cookie_invalid');
    }
    $sp = explode('.', (string)($_GET['state'] ?? ''), 2);
    if (count($sp) !== 2 || !hash_equals(oauth_sign('state|' . $provider . '|' . $sp[0]), $sp[1])
        || !hash_equals((string)$pl['n'], $sp[0])) {
        oauth_fail('oauth_state_invalid');
    }
    if (isset($_GET['error'])) {
        oauth_fail('oauth_denied');
    }
    $code = $_GET['code'] ?? '';
    if (!is_string($code) || $code === '' || strlen($code) > 2048) {
        oauth_fail('oauth_code_missing');
    }

    // výměna kódu za access token
    $form = [
        'client_id' => $c['client_id'],
        'client_secret' => $c['client_secret'],
        'code' => $code,
        'redirect_uri' => $c['redirect'],
        'grant_type' => 'authorization_code',
    ];
    if (!empty($c['pkce'])) {
        $form['code_verifier'] = (string)$pl['v'];
    }
    [$st, $tok] = oauth_http('POST', (string)$c['token_url'], [], $form);
    $access = (string)($tok['access_token'] ?? '');
    if ($st !== 200 || $access === '') {
        oauth_fail('oauth_token_failed');
    }

    // profil uživatele
    if ($provider === 'google') {
        [$st, $u] = oauth_http('GET', (string)$c['userinfo_url'], ['Authorization: Bearer ' . $access]);
    } else {
        $sep = str_contains((string)$c['userinfo_url'], '?') ? '&' : '?';
        [$st, $u] = oauth_http('GET', (string)$c['userinfo_url'] . $sep . 'access_token=' . rawurlencode($access));
    }
    $uid = (string)($u['sub'] ?? $u['id'] ?? '');
    if ($st !== 200 || !preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $uid)) {
        oauth_fail('oauth_userinfo_failed');
    }
    $email = trim((string)($u['email'] ?? ''));
    if ($email === '' || strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || ($provider === 'google' && ($u['email_verified'] ?? true) === false)) {
        $email = '';
    }
    $pic = $provider === 'google' ? ($u['picture'] ?? '') : ($u['picture']['data']['url'] ?? '');
    $avatar = is_string($pic) && strlen($pic) <= 500 && str_starts_with($pic, 'https://') ? $pic : '';

    $res = user_login_or_register($provider, [
        'uid' => $uid,
        'name' => oauth_clean_text((string)($u['name'] ?? ''), (int)cfg('users.display_name_max')),
        'email' => $email,
        'avatar' => $avatar,
    ]);
    if ($res['state'] !== (int)cfg('enums.user_state.active')) {
        security_log('oauth_login_inactive', ['user_id' => $res['id'], 'state' => $res['state']]);
        redirect('login?m=account_inactive');
    }
    session_create($res['id']);
    redirect(safe_next((string)($pl['nx'] ?? ''), ''));
}
