<?php
// src/users.php
declare(strict_types=1);

function user_get(int $id): ?array
{
    $row = q('SELECT id, provider, provider_uid, display_name, role, user_state, approved_count, created_at, last_login_at FROM ' . tbl('users') . ' WHERE id = ?', [$id])->fetch();
    return $row ?: null;
}

function user_clean_name(string $s): string
{
    return oauth_clean_text($s, (int)cfg('users.display_name_max'));
}

/**
 * Najde uživatele podle provider + provider_uid (nikdy podle e-mailu) nebo ho založí.
 * První běžný uživatel po čisté instalaci se stane adminem; rozhodnutí je atomické (transakce + zámek).
 * @return array{id:int,state:int,new:bool}
 */
function user_login_or_register(string $provider, array $info): array
{
    $pid = oauth_provider_id($provider);
    $pdo = db();
    $now = time();
    $name = $info['name'] !== '' ? $info['name'] : t('user.default_name');
    $pdo->beginTransaction();
    try {
        $row = q('SELECT id, user_state FROM ' . tbl('users') . ' WHERE provider = ? AND provider_uid = ? FOR UPDATE', [$pid, $info['uid']])->fetch();
        if ($row) {
            $id = (int)$row['id'];
            $state = (int)$row['user_state'];
            $new = false;
            q('UPDATE ' . tbl('users') . ' SET last_login_at = ? WHERE id = ?', [$now, $id]);
        } else {
            $role = (int)cfg('enums.role.user');
            if (cfg('users.first_user_admin')) {
                $admins = (int)q(
                    'SELECT COUNT(*) FROM ' . tbl('users') . ' WHERE role = ? AND provider <> ? FOR UPDATE',
                    [(int)cfg('enums.role.admin'), (int)cfg('enums.oauth_provider.system')]
                )->fetchColumn();
                if ($admins === 0) {
                    $role = (int)cfg('enums.role.admin');
                }
            }
            $state = (int)cfg('enums.user_state.active');
            q(
                'INSERT INTO ' . tbl('users') . ' (provider, provider_uid, display_name, role, user_state, approved_count, created_at, last_login_at)
                 VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
                [$pid, $info['uid'], mb_substr($name, 0, (int)cfg('users.display_name_max')), $role, $state, $now, $now]
            );
            $id = (int)$pdo->lastInsertId();
            $new = true;
            security_log('user_registered', ['user_id' => $id, 'role' => $role]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    if ($state === (int)cfg('enums.user_state.active')) {
        $prof = user_profile_get($id);
        $prof['email'] = $info['email'];
        $prof['avatar'] = $info['avatar'];
        user_profile_put($id, $prof);
    }
    return ['id' => $id, 'state' => $state, 'new' => $new];
}

function user_rename(int $id, string $name): bool
{
    $name = user_clean_name($name);
    if ($name === '') {
        return false;
    }
    q('UPDATE ' . tbl('users') . ' SET display_name = ? WHERE id = ?', [$name, $id]);
    return true;
}

function user_set_lang(int $id, string $lang): void
{
    if (!lang_valid($lang)) {
        return;
    }
    $p = user_profile_get($id);
    $p['lang'] = $lang;
    user_profile_put($id, $p);
}

/** Smaže účet a vše osobní; publikované záznamy zůstanou (created_by = NULL), návrhy v pending se smažou i se soubory. */
function user_delete(int $id): void
{
    if ($id === (int)cfg('users.ai_user_id')) {
        throw new RuntimeException('Systémový účet nelze smazat.');
    }
    foreach (q('SELECT id FROM ' . tbl('pending') . ' WHERE created_by = ?', [$id])->fetchAll(PDO::FETCH_COLUMN) as $pid) {
        json_remove('pending', (int)$pid);
    }
    @unlink(abs_path(draft_rel($id)));
    user_profile_delete($id);
    q('DELETE FROM ' . tbl('users') . ' WHERE id = ?', [$id]); // sessions a pending přes CASCADE, created_by přes SET NULL
}

/** Povýšení user -> trusted po cfg('limits.trusted_after') schválených příspěvcích (volá schvalování, krok 3). */
function user_approved_increment(int $id): void
{
    if ($id === (int)cfg('users.ai_user_id')) {
        return;
    }
    q('UPDATE ' . tbl('users') . ' SET approved_count = approved_count + 1 WHERE id = ?', [$id]);
    q(
        'UPDATE ' . tbl('users') . ' SET role = ? WHERE id = ? AND role = ? AND approved_count >= ?',
        [(int)cfg('enums.role.trusted'), $id, (int)cfg('enums.role.user'), (int)cfg('limits.trusted_after')]
    );
}

/** @return string prázdný řetězec = hotovo, jinak kód chyby */
function user_change_role(int $targetId, int $newRole): string
{
    $t = user_get($targetId);
    if ($t === null) {
        return 'not_found';
    }
    if (!can('change_role', $t)) {
        return 'denied';
    }
    $allowed = [(int)cfg('enums.role.user'), (int)cfg('enums.role.trusted'), (int)cfg('enums.role.moderator'), (int)cfg('enums.role.admin')];
    if (!in_array($newRole, $allowed, true)) {
        return 'invalid';
    }
    $admin = (int)cfg('enums.role.admin');
    if ((int)$t['role'] === $admin && $newRole !== $admin && count_active_admins() <= 1) {
        return 'last_admin';
    }
    if ((int)$t['role'] !== $newRole) {
        q('UPDATE ' . tbl('users') . ' SET role = ? WHERE id = ?', [$newRole, $targetId]);
        session_destroy_user($targetId);
        security_log('role_changed', ['target' => $targetId, 'from' => (int)$t['role'], 'to' => $newRole]);
    }
    return '';
}

/** @return string prázdný řetězec = hotovo, jinak kód chyby */
function user_change_state(int $targetId, int $newState): string
{
    $t = user_get($targetId);
    if ($t === null) {
        return 'not_found';
    }
    if (!can('change_state', $t)) {
        return 'denied';
    }
    $active = (int)cfg('enums.user_state.active');
    $susp = (int)cfg('enums.user_state.suspended');
    if (!in_array($newState, [$active, $susp], true)) {
        return 'invalid';
    }
    if ($newState === $susp && (int)$t['role'] === (int)cfg('enums.role.admin') && count_active_admins() <= 1) {
        return 'last_admin';
    }
    if ((int)$t['user_state'] !== $newState) {
        q('UPDATE ' . tbl('users') . ' SET user_state = ? WHERE id = ?', [$newState, $targetId]);
        session_destroy_user($targetId);
        security_log('state_changed', ['target' => $targetId, 'from' => (int)$t['user_state'], 'to' => $newState]);
    }
    return '';
}

/** @return string prázdný řetězec = hotovo, jinak kód chyby */
function user_admin_delete(int $targetId): string
{
    $t = user_get($targetId);
    if ($t === null) {
        return 'not_found';
    }
    if (!can('delete_user', $t)) {
        return 'denied';
    }
    if ((int)$t['role'] === (int)cfg('enums.role.admin') && count_active_admins() <= 1) {
        return 'last_admin';
    }
    session_destroy_user($targetId);
    user_delete($targetId);
    security_log('user_deleted', ['target' => $targetId]);
    return '';
}

function pending_count(): int
{
    return (int)q('SELECT COUNT(*) FROM ' . tbl('pending'))->fetchColumn();
}

// ---------- uložené filtry (JSON profil) ----------

/** @return array{enabled:bool,sets:array<string,int[]>} */
function filters_get(int $uid): array
{
    $p = user_profile_get($uid);
    $sets = [];
    foreach (array_keys((array)cfg('filters.types')) as $type) {
        $sets[$type] = array_values(array_unique(array_map('intval', (array)($p['filters'][$type] ?? []))));
    }
    return ['enabled' => (bool)($p['filters_enabled'] ?? true), 'sets' => $sets];
}

/** @return array<int,array{id:int,name:string}> nabídka položek jednoho typu pro formulář filtrů */
function filters_options(string $type): array
{
    $key = cfg('filters.types.' . $type);
    if (!is_string($key)) {
        return [];
    }
    $col = $type === 'countries' ? country_col() : 'name';
    $limit = (int)cfg('filters.list_limit');
    return q('SELECT id, ' . $col . ' AS name FROM ' . tbl($key) . ' ORDER BY ' . $col . ' LIMIT ' . $limit)->fetchAll();
}

/** Uloží vybrané filtry; ID se ověřují proti DB a omezují počtem. */
function filters_save(int $uid, array $input): void
{
    $p = user_profile_get($uid);
    $max = (int)cfg('filters.max_per_type');
    $saved = [];
    foreach ((array)cfg('filters.types') as $type => $key) {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)($input[$type] ?? [])), static fn(int $i): bool => $i > 0))), 0, $max);
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $ids = array_map('intval', q('SELECT id FROM ' . tbl((string)$key) . ' WHERE id IN (' . $in . ')', $ids)->fetchAll(PDO::FETCH_COLUMN));
        }
        $saved[$type] = $ids;
    }
    $p['filters'] = $saved;
    $p['filters_enabled'] = !empty($input['enabled']);
    user_profile_put($uid, $p);
}

function filters_toggle(int $uid): bool
{
    $p = user_profile_get($uid);
    $p['filters_enabled'] = !(bool)($p['filters_enabled'] ?? true);
    user_profile_put($uid, $p);
    return $p['filters_enabled'];
}
