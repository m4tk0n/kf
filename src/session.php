<?php
// src/session.php
declare(strict_types=1);

/** Sdílený stav ověření pro jeden požadavek. */
function &auth_store(): array
{
    static $st = ['resolved' => false, 'user' => null, 'inactive' => false, 'replaced' => false];
    return $st;
}

function auth_cached_uid(): ?int
{
    $u = auth_resolve();
    return $u !== null ? (int)$u['id'] : null;
}

function session_cookie_path(): string
{
    $p = cfg('session.cookie_path');
    return is_string($p) && $p !== '' ? $p : base_path();
}

function session_set_cookie(string $token, int $expires): void
{
    setcookie((string)cfg('session.name'), $token, [
        'expires' => $expires,
        'path' => session_cookie_path(),
        'secure' => cfg('session.cookie_secure_auto') ? request_is_https() : true,
        'httponly' => (bool)cfg('session.cookie_httponly'),
        'samesite' => (string)cfg('session.cookie_samesite'),
    ]);
}

function session_clear_cookie(): void
{
    if (!headers_sent()) {
        session_set_cookie('', time() - 86400);
    }
}

/** Neplatný pokus o autentizaci: log + rate-limit (po překročení 429). */
function auth_fail(string $reason): void
{
    security_log($reason);
    if (ratelimit_hit('auth_fail', (int)cfg('limits.auth_fail_max_hits'))) {
        security_log('auth_fail_rate_exceeded');
        abort(429, 'Příliš mnoho pokusů.');
    }
}

function session_delete_hash_hex(string $hex): void
{
    q('DELETE FROM ' . tbl('sessions') . ' WHERE token_hash = UNHEX(?)', [$hex]);
}

/**
 * Přihlášený uživatel aktuálního požadavku, nebo null. Role i user_state se vždy načítají z DB
 * (JOIN přes sessions); v cookie je jen náhodný token, v DB jeho SHA-256.
 */
function auth_real_resolve(): ?array
{
    $st = &auth_store();
    if ($st['resolved']) {
        return $st['user'];
    }
    $st['resolved'] = true;

    $tok = $_COOKIE[(string)cfg('session.name')] ?? null;
    if ($tok === null) {
        return null;
    }
    $hexLen = (int)cfg('session.token_bytes') * 2;
    if (!is_string($tok) || !preg_match('/^[0-9a-f]{' . $hexLen . '}$/', $tok)) {
        auth_fail('session_cookie_malformed');
        session_clear_cookie();
        return null;
    }
    $hashHex = hash('sha256', $tok);
    $row = q(
        'SELECT s.created_at AS s_created, s.last_seen_at, s.expires_at, HEX(s.ua_hash) AS ua_hex,
                u.id, u.provider, u.display_name, u.role, u.user_state, u.approved_count
           FROM ' . tbl('sessions') . ' s JOIN ' . tbl('users') . ' u ON u.id = s.user_id
          WHERE s.token_hash = UNHEX(?)',
        [$hashHex]
    )->fetch();
    if (!$row) {
        // Token už není v DB: typicky byla session nahrazena přihlášením na jiném zařízení.
        security_log('session_token_revoked');
        session_clear_cookie();
        $st['replaced'] = true;
        return null;
    }

    $now = time();
    if ((int)$row['expires_at'] <= $now || (int)$row['last_seen_at'] + (int)cfg('session.idle_sec') <= $now) {
        session_delete_hash_hex($hashHex);
        session_clear_cookie();
        return null;
    }
    if (cfg('session.check_user_agent') && !hash_equals(strtolower((string)$row['ua_hex']), ua_hash_hex())) {
        session_delete_hash_hex($hashHex);
        security_log('session_ua_mismatch', ['user_id' => (int)$row['id']]);
        session_clear_cookie();
        return null;
    }
    if ((int)$row['user_state'] === (int)cfg('enums.user_state.deleted') || (int)$row['provider'] === (int)cfg('enums.oauth_provider.system')) {
        session_delete_hash_hex($hashHex);
        security_log('inactive_user_session', ['user_id' => (int)$row['id'], 'state' => (int)$row['user_state']]);
        session_clear_cookie();
        $st['inactive'] = true;
        return null;
    }
    if ($now - (int)$row['last_seen_at'] >= (int)cfg('session.touch_interval_sec')) {
        q('UPDATE ' . tbl('sessions') . ' SET last_seen_at = ? WHERE token_hash = UNHEX(?)', [$now, $hashHex]);
    }

    $st['user'] = [
        'id' => (int)$row['id'],
        'provider' => (int)$row['provider'],
        'display_name' => (string)$row['display_name'],
        'role' => (int)$row['role'],
        'user_state' => (int)$row['user_state'],
        'approved_count' => (int)$row['approved_count'],
        'token_hash_hex' => $hashHex,
        'session_created_at' => (int)$row['s_created'],
    ];
    return $st['user'];
}

/** Efektivní identita požadavku. Při testování zůstává autentizovaná session vždy skutečným účtem ID 2. */
function auth_resolve(): ?array
{
    $real = auth_real_resolve();
    if ($real === null) {
        return null;
    }
    if (function_exists('debug_test_profiles_effective_user')) {
        return debug_test_profiles_effective_user($real) ?? $real;
    }
    return $real;
}

/** Vytvoří novou session s novým tokenem (volat po každém přihlášení) a nastaví cookie. */
function session_create(int $userId): string
{
    $token = random_hex((int)cfg('session.token_bytes'));
    $now = time();
    q(
        'INSERT INTO ' . tbl('sessions') . ' (token_hash, user_id, ua_hash, created_at, last_seen_at, expires_at)
         VALUES (UNHEX(?), ?, UNHEX(?), ?, ?, ?)',
        [hash('sha256', $token), $userId, ua_hash_hex(), $now, $now, $now + (int)cfg('session.lifetime_sec')]
    );
    $max = (int)cfg('session.max_per_user');
    if ($max > 0) {
        q(
            'DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ? AND token_hash NOT IN
               (SELECT token_hash FROM (SELECT token_hash FROM ' . tbl('sessions') . ' WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $max . ') t)',
            [$userId, $userId]
        );
    }
    session_set_cookie($token, $now + (int)cfg('session.lifetime_sec'));
    $st = &auth_store();
    $st['resolved'] = false;
    $st['user'] = null;
    return $token;
}

/**
 * První přihlášení bez aktivní session. Zámek řádku uživatele serializuje souběžná
 * přihlášení, aby dvě zařízení nemohla současně projít kontrolou.
 */
function session_login_begin(int $userId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM ' . tbl('users') . ' WHERE id = ? FOR UPDATE', [$userId])->fetch();
        // Opětovné OAuth přihlášení na zařízení, které už má platnou session téhož účtu,
        // není převzetí z jiného zařízení.
        $current = auth_store()['user'];
        if ($current !== null && (int)$current['id'] === $userId) {
            $now = time();
            $other = q(
                'SELECT 1 FROM ' . tbl('sessions') . ' WHERE user_id = ? AND token_hash <> UNHEX(?)
                    AND expires_at > ? AND last_seen_at > ? LIMIT 1',
                [$userId, (string)$current['token_hash_hex'], $now, $now - (int)cfg('session.idle_sec')]
            )->fetchColumn();
            if (!$other) {
                session_create($userId);
                $pdo->commit();
                return true;
            }
        }
        $now = time();
        $exists = q(
            'SELECT 1 FROM ' . tbl('sessions') . ' WHERE user_id = ? AND expires_at > ? AND last_seen_at > ? LIMIT 1',
            [$userId, $now, $now - (int)cfg('session.idle_sec')]
        )->fetchColumn();
        if ($exists) {
            $pdo->commit();
            return false;
        }
        session_create($userId);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Potvrzené převzetí účtu: v jedné transakci zruší staré session a vytvoří jedinou novou. */
function session_login_replace(int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM ' . tbl('users') . ' WHERE id = ? FOR UPDATE', [$userId])->fetch();
        q('DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ?', [$userId]);
        session_create($userId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function session_destroy_current(): void
{
    $u = auth_real_resolve();
    if ($u !== null) {
        session_delete_hash_hex($u['token_hash_hex']);
    }
    session_clear_cookie();
    if (function_exists('debug_test_profiles_cookie_clear')) {
        debug_test_profiles_cookie_clear();
    }
    $st = &auth_store();
    $st['user'] = null;
}

/** Smaže všechny session uživatele (odhlášení všude, suspend, změna role, smazání účtu). */
function session_destroy_user(int $userId): void
{
    q('DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ?', [$userId]);
}

/** Cron: smaže expirované a neaktivní session. */
function session_cleanup(): int
{
    $now = time();
    return q(
        'DELETE FROM ' . tbl('sessions') . ' WHERE expires_at <= ? OR last_seen_at <= ?',
        [$now, $now - (int)cfg('session.idle_sec')]
    )->rowCount();
}

/** Přihlášení do 'fresh_sec' pro citlivé akce. */
function auth_is_fresh(): bool
{
    $u = auth_real_resolve();
    return $u !== null && time() - $u['session_created_at'] <= (int)cfg('session.fresh_sec');
}

function require_login(): array
{
    $u = auth_resolve();
    if ($u === null) {
        if (auth_store()['inactive']) {
            security_log('inactive_user_denied');
            abort(403, 'Účet není aktivní');
        }
        security_log('login_required');
        abort(403, t('error.403'));
    }
    return $u;
}

function require_fresh_login(): array
{
    $u = require_login();
    if (!auth_is_fresh()) {
        security_log('fresh_login_required', ['user_id' => $u['id']]);
        abort(403, 'Pro tuto akci je nutné se znovu přihlásit');
    }
    return $u;
}

// ---------- CSRF ----------

/**
 * CSRF tokeny: přihlášený = HMAC (APP_KEY) z tokenu session; host (formulář jazyka) = HMAC z IP, User-Agentu
 * a hodinového okna. Vrací platné tokeny (host: aktuální a předchozí okno).
 * @return string[]
 */
function csrf_candidates(): array
{
    $key = (string)env('APP_KEY', '');
    $u = auth_real_resolve();
    if ($u !== null) {
        return [hash_hmac('sha256', 'csrf|' . $u['token_hash_hex'], $key)];
    }
    $w = intdiv(time(), 3600);
    $mk = static fn(int $win): string => hash_hmac('sha256', 'csrf-guest|' . ua_hash_hex() . '|' . client_ip() . '|' . $win, $key);
    return [$mk($w), $mk($w - 1)];
}

function csrf_token(): string
{
    return csrf_candidates()[0];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . e((string)cfg('security.csrf_field')) . '" value="' . e(csrf_token()) . '">';
}

/** Každý POST musí mít platný CSRF token a (volitelně) Origin/Referer z BASE_URL. */
function csrf_verify(): void
{
    if (!is_post()) {
        return;
    }
    $sent = $_POST[(string)cfg('security.csrf_field')] ?? '';
    $valid = false;
    if (is_string($sent) && $sent !== '') {
        foreach (csrf_candidates() as $tok) {
            $valid = $valid || hash_equals($tok, $sent);
        }
    }
    if (!$valid) {
        security_log('csrf_invalid');
        abort(403, 'Neplatný CSRF token');
    }
    if (cfg('security.csrf_origin_check') && !origin_ok()) {
        security_log('csrf_origin_mismatch');
        abort(403, 'Neplatný původ požadavku');
    }
}

// ---------- oprávnění ----------

function role_value(string $name): int
{
    return (int)cfg('enums.role.' . $name);
}

function count_active_admins(): int
{
    return (int)q(
        'SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE role = ? AND user_state = ? AND provider <> ?',
        [role_value('admin'), (int)cfg('enums.user_state.active'), (int)cfg('enums.oauth_provider.system')]
    )->fetchColumn();
}

/**
 * Jediné místo, kde se rozhodují oprávnění. Role, user_id ani created_by se nikdy nečtou z formulářů ani URL;
 * $rec je záznam načtený z DB (návrh, cílový uživatel).
 */
function can(string $action, ?array $rec = null): bool
{
    $u = auth_resolve();
    $role = $u !== null ? (int)$u['role'] : role_value('guest');
    $uid = $u !== null ? (int)$u['id'] : 0;
    $ai = (int)cfg('users.ai_user_id');

    // Pozastavený/blokovaný účet zůstává přihlášený, ale smí pouze prohlížet veřejný obsah.
    if ($u !== null && (int)($u['user_state'] ?? 0) !== (int)cfg('enums.user_state.active') && $action !== 'view') {
        return false;
    }

    switch ($action) {
        case 'view':
            return true;
        case 'favorite':
            return $u !== null && $role >= role_value('user');
        case 'submit':
            return $role >= role_value('user');
        case 'publish_direct':
            return $role >= role_value('trusted');
        case 'edit_pending':
            return $role >= role_value('moderator')
                || ($role >= role_value('user') && $rec !== null && (int)($rec['created_by'] ?? 0) === $uid);
        case 'review':
        case 'edit_any':
        case 'delete_any':
            return $role >= role_value('moderator');
        case 'manage_users':
        case 'settings':
            return $role >= role_value('admin');
        case 'change_role':
        case 'change_state':
        case 'delete_user':
            if ($role < role_value('admin') || $rec === null) {
                return false;
            }
            $tid = (int)($rec['id'] ?? 0);
            if ($tid <= 0 || $tid === $ai || $tid === $uid
                || (!empty($u['is_impersonating']) && $tid === (int)($u['real_user_id'] ?? 0))) {
                return false;
            }
            return true;
        case 'delete_account':
            if ($u === null || $uid === $ai) {
                return false;
            }
            return !($role === role_value('admin') && count_active_admins() <= 1);
        case 'edit_profile':
            return $u !== null && $uid !== $ai;
        default:
            return false;
    }
}

function require_can(string $action, ?array $rec = null): void
{
    if (!can($action, $rec)) {
        security_log('forbidden', ['action' => $action]);
        abort(403, 'Přístup odepřen');
    }
}
