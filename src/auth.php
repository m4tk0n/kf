<?php
// src/auth.php
declare(strict_types=1);

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64url_decode(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/'), true);
}

function hmac_sign(string $payload): string
{
    return b64url(hash_hmac('sha256', $payload, (string)env('APP_KEY', ''), true));
}

/** Podepsaná hodnota <base64url(json)>.<hmac>; klíč „e“ je čas expirace. */
function signed_pack(array $data): string
{
    $p = b64url((string)json_encode($data, JSON_UNESCAPED_SLASHES));
    return $p . '.' . hmac_sign($p);
}

function signed_unpack(mixed $s): ?array
{
    if (!is_string($s) || strlen($s) > 2000 || substr_count($s, '.') !== 1) {
        return null;
    }
    [$p, $sig] = explode('.', $s);
    if (!hash_equals(hmac_sign($p), $sig)) {
        return null;
    }
    $d = json_decode(b64url_decode($p), true);
    return is_array($d) && (int)($d['e'] ?? 0) >= time() ? $d : null;
}

/** @return string[] názvy poskytovatelů z cfg('enums.oauth_provider') kromě systémového */
function oauth_provider_names(): array
{
    $out = [];
    foreach (array_keys((array)cfg('enums.oauth_provider')) as $k) {
        if ($k !== 'system' && is_array(cfg('oauth.' . $k))) {
            $out[] = (string)$k;
        }
    }
    return $out;
}

function oauth_provider_id(string $name): int
{
    return (int)cfg('enums.oauth_provider.' . $name);
}

function oauth_provider_name(int $id): ?string
{
    foreach (oauth_provider_names() as $n) {
        if (oauth_provider_id($n) === $id) {
            return $n;
        }
    }
    return null;
}

/** Nastavení poskytovatele včetně klíčů z .env; null = vypnutý nebo bez klíčů. */
function oauth_conf(string $name): ?array
{
    if (!in_array($name, oauth_provider_names(), true)) {
        return null;
    }
    $c = (array)cfg('oauth.' . $name);
    if (empty($c['enabled'])) {
        return null;
    }
    $id = trim((string)env((string)($c['env_id'] ?? ''), ''));
    $secret = trim((string)env((string)($c['env_secret'] ?? ''), ''));
    if ($id === '' || $secret === '') {
        return null;
    }
    $redirect = trim((string)env((string)($c['env_redirect'] ?? ''), ''));
    if ($redirect === '') {
        $redirect = url(str_replace('{provider}', $name, trim((string)cfg('oauth.callback_path'), '/')));
    }
    foreach (['auth_url', 'token_url', 'userinfo_url'] as $k) {
        $c[$k] = str_replace('{version}', (string)($c['api_version'] ?? ''), (string)($c[$k] ?? ''));
    }
    return $c + ['name' => $name, 'client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => $redirect];
}

/** @return array<string,array> použitelní poskytovatelé podle názvu */
function oauth_available(): array
{
    $out = [];
    foreach (oauth_provider_names() as $n) {
        $c = oauth_conf($n);
        if ($c !== null) {
            $out[$n] = $c;
        }
    }
    return $out;
}

function oauth_login_url(string $provider, string $next = ''): string
{
    $u = url(str_replace('{provider}', $provider, trim((string)cfg('oauth.login_path'), '/')));
    return $next !== '' ? $u . '?next=' . rawurlencode($next) : $u;
}

function oauth_set_cookie(string $value, int $expires): void
{
    setcookie((string)cfg('session.oauth_state_cookie'), $value, [
        'expires' => $expires,
        'path' => session_cookie_path(),
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function oauth_fail(string $reason): never
{
    auth_fail($reason);
    redirect('login?m=login_failed');
}

/** Zahájí přihlášení: podepsaný state + krátce platná cookie s nonce a PKCE verifierem. */
function oauth_start(string $provider, string $next): never
{
    $c = oauth_conf($provider);
    if ($c === null) {
        abort(404, t('error.404'));
    }
    $exp = time() + (int)cfg('session.oauth_state_ttl_sec');
    $nonce = random_hex(16);
    $verifier = b64url(random_bytes(32));
    oauth_set_cookie(signed_pack(['n' => $nonce, 'v' => $verifier, 'p' => $provider, 'e' => $exp]), $exp);
    $state = signed_pack(['n' => $nonce, 'p' => $provider, 'x' => safe_next($next, ''), 'e' => $exp]);
    $params = [
        'client_id' => $c['client_id'],
        'redirect_uri' => $c['redirect_uri'],
        'response_type' => 'code',
        'scope' => (string)$c['scope'],
        'state' => $state,
    ];
    if (!empty($c['pkce'])) {
        $params['code_challenge'] = b64url(hash('sha256', $verifier, true));
        $params['code_challenge_method'] = 'S256';
    }
    if ($provider === 'google') {
        $params['prompt'] = 'select_account';
    }
    header('Location: ' . $c['auth_url'] . '?' . http_build_query($params), true, 302);
    exit;
}

/** @return string access token */
function oauth_exchange(array $c, string $code, string $verifier): string
{
    $body = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $c['redirect_uri'],
        'client_id' => $c['client_id'],
        'client_secret' => $c['client_secret'],
    ];
    if (!empty($c['pkce'])) {
        $body['code_verifier'] = $verifier;
    }
    $res = http_request((string)$c['token_url'], [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        'body' => $body,
        'max_bytes' => 65536,
    ]);
    $j = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !is_array($j) || empty($j['access_token']) || !is_string($j['access_token'])) {
        throw new RuntimeException('Výměna kódu za token selhala (' . $res['status'] . ').');
    }
    return $j['access_token'];
}

function oauth_clean_text(mixed $v, int $max): string
{
    $s = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$v)));
    return mb_substr($s, 0, $max);
}

function oauth_clean_url(mixed $v): ?string
{
    $s = trim((string)$v);
    return $s !== '' && strlen($s) <= 500 && preg_match('~^https://[^\s]+$~i', $s) ? $s : null;
}

/** @return array{uid:string,name:string,email:?string,avatar:?string} */
function oauth_userinfo(array $c, string $token): array
{
    $url = (string)$c['userinfo_url'];
    $headers = ['Accept: application/json'];
    if ($c['name'] === 'facebook') {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'access_token' => $token,
            'appsecret_proof' => hash_hmac('sha256', $token, (string)$c['client_secret']),
        ]);
    } else {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $res = http_request($url, ['headers' => $headers, 'max_bytes' => 65536]);
    $j = json_decode($res['body'], true);
    if ($res['status'] !== 200 || !is_array($j)) {
        throw new RuntimeException('Načtení profilu selhalo (' . $res['status'] . ').');
    }
    if ($c['name'] === 'facebook') {
        $uid = (string)($j['id'] ?? '');
        $email = $j['email'] ?? null;
        $avatar = $j['picture']['data']['url'] ?? null;
    } else {
        $uid = (string)($j['sub'] ?? '');
        $verified = in_array($j['email_verified'] ?? false, [true, 1, 'true'], true);
        $email = $verified ? ($j['email'] ?? null) : null;
        $avatar = $j['picture'] ?? null;
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $uid)) {
        throw new RuntimeException('Poskytovatel nevrátil platné ID uživatele.');
    }
    $name = oauth_clean_text($j['name'] ?? '', (int)cfg('users.display_name_max'));
    $email = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 120) : null;
    return ['uid' => $uid, 'name' => $name, 'email' => $email, 'avatar' => oauth_clean_url($avatar)];
}

/** Dokončí přihlášení: ověří state + cookie, vymění kód, najde/založí uživatele a vytvoří novou session. */
function oauth_callback(string $provider): never
{
    $c = oauth_conf($provider);
    if ($c === null) {
        abort(404, t('error.404'));
    }
    $cookieName = (string)cfg('session.oauth_state_cookie');
    $ck = signed_unpack($_COOKIE[$cookieName] ?? null);
    oauth_set_cookie('', time() - 3600); // state je jednorázový

    if (isset($_GET['error'])) {
        security_log('oauth_provider_error', ['provider' => $provider, 'error' => substr((string)$_GET['error'], 0, 40)]);
        redirect('login?m=login_failed');
    }
    $st = signed_unpack($_GET['state'] ?? null);
    $code = (string)($_GET['code'] ?? '');
    if ($ck === null || $st === null || $code === '' || strlen($code) > 2048
        || ($st['p'] ?? '') !== $provider || ($ck['p'] ?? '') !== $provider
        || !hash_equals((string)($ck['n'] ?? ''), (string)($st['n'] ?? ''))) {
        oauth_fail('oauth_state_invalid');
    }

    try {
        $token = oauth_exchange($c, $code, (string)($ck['v'] ?? ''));
        $info = oauth_userinfo($c, $token);
    } catch (Throwable $e) {
        security_log('oauth_exchange_failed', ['provider' => $provider, 'error' => substr($e->getMessage(), 0, 120)]);
        redirect('login?m=login_failed');
    }

    $res = user_login_or_register($provider, $info);
    if ($res['state'] !== (int)cfg('enums.user_state.active')) {
        security_log('login_denied_inactive', ['user_id' => $res['id'], 'state' => $res['state']]);
        abort(403, t('error.account_inactive'));
    }
    if (auth_resolve() !== null) {
        session_destroy_current(); // znovupřihlášení nahradí současnou session
    }
    session_create($res['id']);
    redirect((string)($st['x'] ?? ''));
}
