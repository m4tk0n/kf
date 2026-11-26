<?php
// debug/test_profiles.php
declare(strict_types=1);

/**
 * Vytvoří nebo obnoví pět testovacích profilů.
 * Volá se pouze samostatnou akcí instalátoru; vyžaduje existující hlavní účet ID 2.
 *
 * @return array{ok:bool,count:int,message:string}
 */
function debug_test_profiles_create(): array
{
    $pdo = db();
    $owner = q('SELECT id FROM ' . tbl('users') . ' WHERE id = 2')->fetchColumn();
    if (!$owner) {
        throw new RuntimeException('Nejdříve vytvoř a přihlas hlavní účet (user ID 2). Testovací profily zatím nelze vytvořit.');
    }

    $path = KF_ROOT . '/debug/test_profiles.sql';
    $sql = @file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Chybí SQL soubor debug/test_profiles.sql.');
    }

    $prefix = (string)cfg('db.prefix');
    if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        throw new RuntimeException('Neplatný prefix databázových tabulek.');
    }
    $sql = str_replace('{prefix}', $prefix, $sql);
    if (str_contains($sql, '{prefix}')) {
        throw new RuntimeException('Nepodařilo se doplnit prefix databázové tabulky.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $count = (int)q(
            'SELECT COUNT(*) FROM ' . tbl('users') . " WHERE provider = 3 AND provider_uid IN
             ('kf_test_user', 'kf_test_blocked', 'kf_test_trusted', 'kf_test_moderator', 'kf_test_admin')"
        )->fetchColumn();
        if ($count !== 5) {
            throw new RuntimeException('V databázi není všech pět testovacích profilů.');
        }
        $pdo->commit();
        security_log('debug_test_profiles_created', ['count' => $count]);
        return ['ok' => true, 'count' => $count, 'message' => 'Testovací profily jsou připravené.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/** Testovací identita je dostupná jen při zachování skutečné session hlavního administrátora. */
function debug_test_profiles_admin(): bool
{
    $real = auth_real_resolve();
    return $real !== null && (int)$real['id'] === 2
        && (int)$real['role'] === (int)cfg('enums.role.admin')
        && (int)$real['user_state'] === (int)cfg('enums.user_state.active');
}

function debug_test_profiles_ids(): array
{
    return ['kf_test_user', 'kf_test_blocked', 'kf_test_trusted', 'kf_test_moderator', 'kf_test_admin'];
}

/** Vrátí pouze předem vytvořené interní profily; žádné ID z cookie se nepovažuje samo o sobě za důvěryhodné. */
function debug_test_profiles_rows(): array
{
    $ids = debug_test_profiles_ids();
    $in = implode(',', array_fill(0, count($ids), '?'));
    return q(
        'SELECT id, provider, provider_uid, display_name, role, user_state, approved_count
           FROM ' . tbl('users') . ' WHERE provider = 3 AND provider_uid IN (' . $in . ') ORDER BY FIELD(provider_uid, '
           . implode(',', array_fill(0, count($ids), '?')) . ')',
        array_merge($ids, $ids)
    )->fetchAll();
}

function debug_test_profiles_cookie_clear(): void
{
    if (!headers_sent()) {
        setcookie('KF_DEBUG_PROFILE', '', [
            'expires' => time() - 86400,
            'path' => session_cookie_path(),
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/** Nastaví krátkodobý podepsaný výběr testovací identity. */
function debug_test_profiles_cookie_set(int $userId): void
{
    $real = auth_real_resolve();
    if ($real === null || (int)$real['id'] !== 2 || (int)$real['role'] !== (int)cfg('enums.role.admin')) {
        throw new RuntimeException('Simulaci může nastavit pouze skutečný administrátor ID 2.');
    }
    $expires = time() + 28800;
    $sessionHash = (string)$real['token_hash_hex'];
    $payload = $userId . '.' . $expires . '.' . $sessionHash;
    $signature = hash_hmac('sha256', 'debug-profile|' . $payload, (string)env('APP_KEY', ''));
    setcookie('KF_DEBUG_PROFILE', $payload . '.' . $signature, [
        'expires' => $expires,
        'path' => session_cookie_path(),
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Ověří cookie a vrátí ID profilu; při jakékoli chybě výběr zruší. */
function debug_test_profiles_selected_id(): int
{
    if (!debug_test_profiles_admin()) {
        return 0;
    }
    $raw = (string)($_COOKIE['KF_DEBUG_PROFILE'] ?? '');
    $parts = explode('.', $raw);
    if (count($parts) !== 4 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])
        || !preg_match('/^[a-f0-9]{64}$/', $parts[2])) {
        return 0;
    }
    [$idRaw, $expRaw, $sessionHash, $signature] = $parts;
    $real = auth_real_resolve();
    $id = (int)$idRaw;
    $expires = (int)$expRaw;
    $payload = $idRaw . '.' . $expRaw . '.' . $sessionHash;
    $expected = hash_hmac('sha256', 'debug-profile|' . $payload, (string)env('APP_KEY', ''));
    if ($real === null || !hash_equals((string)$real['token_hash_hex'], $sessionHash)
        || $id < 1 || $expires < time() || $expires > time() + 28800
        || !hash_equals($expected, $signature)) {
        debug_test_profiles_cookie_clear();
        return 0;
    }
    return $id;
}

/** Přepíše jen efektivní identitu. Token, stáří a skutečný účet session zůstávají administrátorovy. */
function debug_test_profiles_effective_user(array $real): ?array
{
    if ((int)($real['id'] ?? 0) !== 2) {
        return null;
    }
    $id = debug_test_profiles_selected_id();
    if ($id < 1) {
        return null;
    }
    $row = q(
        'SELECT id, provider, provider_uid, display_name, role, user_state, approved_count
           FROM ' . tbl('users') . ' WHERE id = ? AND provider = 3 LIMIT 1',
        [$id]
    )->fetch();
    if (!$row || !in_array((string)$row['provider_uid'], debug_test_profiles_ids(), true)) {
        debug_test_profiles_cookie_clear();
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'provider' => (int)$row['provider'],
        'display_name' => (string)$row['display_name'],
        'role' => (int)$row['role'],
        'user_state' => (int)$row['user_state'],
        'approved_count' => (int)$row['approved_count'],
        'token_hash_hex' => (string)$real['token_hash_hex'],
        'session_created_at' => (int)$real['session_created_at'],
        'is_impersonating' => true,
        'real_user_id' => (int)$real['id'],
    ];
}

/** Stránka přepínače. Samotné přepnutí nemění ani nevytváří DB session testovacího účtu. */
function route_debug_test_profiles(): never
{
    if (!debug_test_profiles_admin()) {
        security_log('forbidden', ['action' => 'debug_test_profiles']);
        abort(403, t('error.403'));
    }

    $rows = debug_test_profiles_rows();
    $selected = debug_test_profiles_selected_id();
    $labels = [
        1 => 'User',
        2 => 'Důvěryhodný',
        3 => 'Moderátor',
        4 => 'Administrátor',
    ];
    $options = '<option value="0">Skutečný administrátor (ID 2)</option>';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $state = (int)$row['user_state'] === (int)cfg('enums.user_state.suspended') ? ' – blokovaný' : '';
        $options .= '<option value="' . $id . '"' . ($selected === $id ? ' selected' : '') . '>'
            . e((string)$row['display_name'] . ' – ' . ($labels[(int)$row['role']] ?? 'Uživatel') . $state)
            . '</option>';
    }

    $message = '';
    if ((string)($_GET['m'] ?? '') === 'missing') {
        $message = '<p class="warn">Testovací profily nejsou vytvořené. Nejdříve je vytvoř v instalátoru.</p>';
    } elseif ((string)($_GET['m'] ?? '') === 'invalid') {
        $message = '<p class="warn">Vybraný profil není dostupný.</p>';
    }
    $current = 'Skutečný administrátor (ID 2)';
    foreach ($rows as $row) {
        if ((int)$row['id'] === $selected) {
            $current = (string)$row['display_name'];
            break;
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="' . e(current_lang()) . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Testovací profily – K&amp;F</title>'
        . '<style>body{margin:0;padding:16px;background:#f4f5f7;color:#20242b;font:16px/1.5 system-ui,sans-serif}'
        . 'main{max-width:560px;margin:8vh auto;background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px}'
        . 'h1{font-size:1.35rem;margin:0 0 8px}p{margin:8px 0 16px}.muted{color:#626b78;font-size:.92rem}'
        . 'select,button{width:100%;min-height:44px;padding:9px 12px;border:1px solid #b9c0ca;border-radius:8px;font:inherit}'
        . 'button{margin-top:10px;background:#185abd;color:#fff;border:0;font-weight:600;cursor:pointer}'
        . '.stop{background:#555}.warn{color:#a33}.links{display:flex;gap:16px;flex-wrap:wrap;margin-top:18px;font-size:.92rem}'
        . 'a{color:#185abd}</style></head><body><main><h1>Testovací profily</h1>'
        . '<p class="muted">Skutečné přihlášení administrátora zůstává zachováno. Aplikace bude používat vybranou identitu a její oprávnění.</p>'
        . '<p>Aktivní identita: <strong>' . e($current) . '</strong></p>' . $message;
    if (!$rows) {
        echo '<p class="warn">Testovací profily nejsou vytvořené. Spusť jejich vytvoření samostatným tlačítkem v instalátoru.</p>';
    } else {
        echo '<form method="post" action="' . e(url('debug/users')) . '">'
            . csrf_field() . '<input type="hidden" name="action" value="switch">'
            . '<label for="profile">Přepnout identitu</label><select id="profile" name="profile_id">' . $options . '</select>'
            . '<button type="submit">Přepnout uživatele</button></form>';
    }
    if ($selected > 0) {
        echo '<form method="post" action="' . e(url('debug/users')) . '">'
            . csrf_field() . '<input type="hidden" name="action" value="stop">'
            . '<button class="stop" type="submit">Ukončit simulaci a vrátit administrátora</button></form>';
    }
    echo '<nav class="links"><a href="' . e(url('')) . '">Zpět do K&amp;F</a>'
        . '<a href="' . e(url('diagnostics')) . '">Diagnostika</a></nav></main></body></html>';
    exit;
}

/** POST přepínače. Přístup je kontrolován proti skutečné session, nikoli simulované roli. */
function route_debug_test_profiles_post(): never
{
    if (!debug_test_profiles_admin()) {
        security_log('forbidden', ['action' => 'debug_test_profiles_post']);
        abort(403, t('error.403'));
    }
    $action = post_str('action', 10);
    if ($action === 'stop') {
        debug_test_profiles_cookie_clear();
        security_log('debug_profile_simulation_stopped', ['admin_id' => 2]);
        redirect('debug/users');
    }
    if ($action !== 'switch') {
        redirect('debug/users?m=invalid');
    }
    $id = (int)post_str('profile_id', 10);
    if ($id === 0) {
        debug_test_profiles_cookie_clear();
        security_log('debug_profile_simulation_stopped', ['admin_id' => 2]);
        redirect('debug/users');
    }
    $exists = q(
        'SELECT id FROM ' . tbl('users') . ' WHERE id = ? AND provider = 3 AND provider_uid IN ('
        . implode(',', array_fill(0, count(debug_test_profiles_ids()), '?')) . ') LIMIT 1',
        array_merge([$id], debug_test_profiles_ids())
    )->fetchColumn();
    if (!$exists) {
        redirect('debug/users?m=invalid');
    }
    debug_test_profiles_cookie_set($id);
    security_log('debug_profile_simulation_started', ['admin_id' => 2, 'target_id' => $id]);
    redirect('debug/users');
}
