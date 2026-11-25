<?php
// src/routes.php
declare(strict_types=1);

function post_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

/** Kdo nemá oprávnění: 403 + záznam do bezpečnostního logu (hosté i uživatelé). */
function guard(string $action, ?array $rec = null): array
{
    $u = require_login();
    require_can($action, $rec);
    return $u;
}

/** Citlivé akce vyžadují nedávné přihlášení: jinak se uživatel znovu přihlásí u svého poskytovatele a vrátí se zpět. */
function require_fresh(string $backPath): void
{
    if (auth_is_fresh()) {
        return;
    }
    $u = auth_resolve();
    $prov = $u !== null ? oauth_provider_name((int)$u['provider']) : null;
    if ($prov === null) {
        abort(403, t('error.403'));
    }
    security_log('fresh_login_required', ['user_id' => $u['id']]);
    header('Location: ' . oauth_login_url($prov, $backPath), true, 303);
    exit;
}

function route_home(): never
{
    page(t('nav.home'), 'home');
}

function route_login(): never
{
    $next = safe_next((string)($_GET['next'] ?? ''), '');
    if (auth_resolve() !== null) {
        redirect($next);
    }
    page(t('nav.login'), 'login', ['providers' => oauth_available(), 'next' => $next]);
}

function route_login_confirm(): never
{
    $pending = oauth_pending_payload();
    if ($pending === null) {
        oauth_pending_cookie_set('', time() - 86400);
        redirect('login?m=oauth_failed');
    }
    page(t('login.confirm.title'), 'login_confirm', ['expires' => (int)$pending['e']]);
}

function route_login_confirm_post(): never
{
    $pending = oauth_pending_payload();
    if ($pending === null) {
        oauth_pending_cookie_set('', time() - 86400);
        redirect('login?m=oauth_failed');
    }
    $action = post_str('action', 10);
    oauth_pending_cookie_set('', time() - 86400);
    if ($action === 'cancel') {
        redirect('login');
    }
    if ($action !== 'confirm') {
        redirect('login?m=oauth_failed');
    }
    $user = user_get((int)$pending['u']);
    if ($user === null || (int)$user['user_state'] !== (int)cfg('enums.user_state.active')) {
        redirect('login?m=account_inactive');
    }
    session_login_replace((int)$pending['u']);
    redirect(safe_next((string)($pending['nx'] ?? ''), ''));
}

function route_oauth_start(array $m): never
{
    oauth_start($m[1], (string)($_GET['next'] ?? ''));
}

function route_oauth_callback(array $m): never
{
    oauth_callback($m[1]);
}

function route_logout(): never
{
    session_destroy_current();
    redirect('');
}

function route_lang(): never
{
    $lang = post_str('lang', 5);
    if (lang_valid($lang)) {
        $u = auth_resolve();
        if ($u !== null) {
            user_set_lang((int)$u['id'], $lang);
        } else {
            lang_set_cookie($lang);
        }
    }
    redirect(post_str('next', 300));
}

function route_profile(): never
{
    $u = guard('edit_profile');
    $prof = user_profile_get((int)$u['id']);
    page(t('menu.profile'), 'profile', [
        'email' => (string)($prof['email'] ?? ''),
        'email_verified' => !empty($prof['email_verified']),
        'provider' => (string)oauth_provider_name((int)$u['provider']),
        'fresh' => auth_is_fresh(),
        'can_delete' => can('delete_account'),
    ]);
}

function route_profile_post(): never
{
    $u = guard('edit_profile');
    $action = post_str('action', 20);
    if ($action === 'name') {
        redirect(user_rename((int)$u['id'], post_str('display_name', 200)) ? 'profile?m=saved' : 'profile?m=invalid');
    }
    if ($action === 'lang') {
        user_set_lang((int)$u['id'], post_str('lang', 5));
        redirect('profile?m=saved');
    }
    redirect('profile');
}

function route_profile_delete(): never
{
    $u = guard('delete_account');
    require_fresh('profile');
    if (post_str('confirm', 5) !== '1') {
        redirect('profile?m=confirm_needed');
    }
    security_log('account_deleted', ['user_id' => $u['id']]);
    user_delete((int)$u['id']);
    session_clear_cookie();
    redirect('?m=account_deleted');
}

function route_filters(): never
{
    $u = guard('edit_profile');
    $f = filters_get((int)$u['id']);
    $options = [];
    foreach ((array)cfg('filters.sections') as $section => $fields) {
        foreach ($fields as $field) {
            $options[$section][$field] = filters_options((string)$section, (string)$field);
        }
    }
    page(t('menu.filters'), 'filters', ['filters' => $f, 'options' => $options]);
}

function route_filters_post(): never
{
    $u = guard('edit_profile');
    filters_save((int)$u['id'], $_POST);
    redirect('filters?m=filters_saved&clear_filter_session=1');
}

/** Rychlé filtry platné pouze pro aktuální relaci a právě vybranou sekci katalogu. */
function route_quick_filters(): never
{
    $section = (string)($_GET['s'] ?? 'events');
    if (!in_array($section, ['events', 'bands', 'venues'], true)) {
        $section = 'events';
    }

    $user = auth_resolve();
    $filters = $user !== null
        ? filters_get((int)$user['id'])
        : ['show_all' => true, 'none' => false, 'sets' => []];

    // Výchozí výběr se při show_all řídí příznakem; není nutné vypisovat všechna ID.
    $baseSets = (array)$filters['sets'];

    $options = [];
    foreach ((array)cfg('filters.sections') as $kind => $fields) {
        foreach ($fields as $field) {
            $options[$kind][$field] = filters_options((string)$kind, (string)$field);
        }
    }

    $backParams = ['s' => $section];
    foreach (['q', 'p', 'd', 'k', 'fav'] as $key) {
        if (isset($_GET[$key]) && is_scalar($_GET[$key]) && (string)$_GET[$key] !== '') {
            $backParams[$key] = (string)$_GET[$key];
        }
    }

    page(t('filters.quick_title'), 'quick_filters', [
        'section' => $section,
        'options' => $options,
        'base_state' => ['show_all' => (bool)$filters['show_all'], 'sets' => $baseSets],
        'back_url' => url('') . '?' . http_build_query($backParams),
    ]);
}

function admin_error_flash(string $code): string
{
    return $code === '' ? 'saved' : ['last_admin' => 'last_admin', 'denied' => 'denied', 'invalid' => 'invalid', 'not_found' => 'invalid'][$code] ?? 'denied';
}

function route_admin_users(): never
{
    guard('manage_users');
    $per = (int)cfg('paging.per_page_users');
    $pageNo = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)q('SELECT COUNT(*) FROM ' . tbl('users'))->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $pageNo = min($pageNo, $pages);
    $rows = q(
        'SELECT id, provider, display_name, role, user_state, approved_count, last_login_at FROM ' . tbl('users')
        . ' ORDER BY id LIMIT ' . $per . ' OFFSET ' . (($pageNo - 1) * $per)
    )->fetchAll();
    page(t('menu.users'), 'users', ['rows' => $rows, 'page_no' => $pageNo, 'pages' => $pages, 'total' => $total]);
}

function route_admin_users_post(): never
{
    guard('manage_users');
    require_fresh('admin/users');
    $id = (int)post_str('id', 10);
    $action = post_str('action', 10);
    $back = 'admin/users?page=' . max(1, (int)post_str('page', 6));
    $sep = '&m=';
    if ($action === 'save') {
        $err = user_change_role($id, (int)post_str('role', 3));
        if ($err === '') {
            $err = user_change_state($id, (int)post_str('state', 3));
        }
        redirect($back . $sep . admin_error_flash($err));
    }
    if ($action === 'delete') {
        $err = user_admin_delete($id);
        redirect($back . $sep . ($err === '' ? 'user_deleted' : admin_error_flash($err)));
    }
    if ($action === 'logout') {
        $t = user_get($id);
        if ($t !== null && can('change_state', $t)) {
            session_destroy_user($id);
            security_log('sessions_revoked', ['target' => $id]);
            redirect($back . $sep . 'sessions_revoked');
        }
        redirect($back . $sep . 'denied');
    }
    redirect($back);
}

/** Posledních $n řádků textového souboru (čte jen konec souboru). */
function tail_lines(string $rel, int $n): array
{
    $f = abs_path($rel);
    if (!is_file($f)) {
        return [];
    }
    $size = (int)filesize($f);
    $fh = fopen($f, 'rb');
    if (!$fh) {
        return [];
    }
    fseek($fh, max(0, $size - 16384));
    $raw = (string)stream_get_contents($fh);
    fclose($fh);
    $lines = array_values(array_filter(explode("\n", $raw), static fn(string $l): bool => $l !== ''));
    return array_slice($lines, -$n);
}

function count_files_limited(string $absDir, int $limit): int
{
    if (!is_dir($absDir)) {
        return 0;
    }
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && ++$n >= $limit) {
            break;
        }
    }
    return $n;
}

function route_admin_settings(): never
{
    guard('settings');
    $names = array_map(static fn($n): string => (string)cfg('db.prefix') . $n, array_values((array)cfg('db.tables')));
    $in = implode(',', array_fill(0, count($names), '?'));
    $dbBytes = (int)q('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $in . ')', $names)->fetchColumn();
    $byRole = [];
    foreach (q('SELECT role, COUNT(*) AS n FROM ' . tbl('users') . ' GROUP BY role ORDER BY role')->fetchAll() as $r) {
        $byRole[(int)$r['role']] = (int)$r['n'];
    }
    $inodeLimit = (int)cfg('storage.max_inodes');
    $files = count_files_limited(abs_path((string)cfg('paths.data')), $inodeLimit) + count_files_limited(abs_path((string)cfg('paths.img')), $inodeLimit);
    $cron = json_read_file((string)cfg('cron.state_file'));
    page(t('menu.settings'), 'settings', [
        'db_mb' => round($dbBytes / 1048576, 2),
        'db_max' => (int)cfg('storage.max_db_mb'),
        'files' => $files,
        'files_max' => $inodeLimit,
        'by_role' => $byRole,
        'pending' => pending_count(),
        'cron_last' => (int)($cron['last_run'] ?? 0),
        'log' => tail_lines((string)cfg('storage.security_log_file'), 30),
    ]);
}
