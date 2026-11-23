<?php
// src/views.php
declare(strict_types=1);

/** URL statického souboru s parametrem podle filemtime(), aby se po úpravě vždy načetla aktuální verze. */
function asset(string $rel): string
{
    $rel = ltrim($rel, '/');
    $dir = (string)cfg('paths.assets');
    $mt = @filemtime(abs_path($dir . '/' . $rel));
    return url($dir . '/' . $rel) . ($mt ? '?v=' . $mt : '');
}

/** Vykreslí šablonu z cfg('paths.templates') do řetězce. */
function render(string $tpl, array $vars = []): string
{
    if (!preg_match('/^[a-z_]+$/', $tpl)) {
        throw new InvalidArgumentException('Neplatný název šablony.');
    }
    $file = abs_path((string)cfg('paths.templates')) . '/' . $tpl . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Šablona nenalezena: ' . $tpl);
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        include $file;
        return (string)ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}

/** Zpráva z parametru ?m=<kód>; zobrazí se jen kód, který existuje v jazykovém souboru. */
function flash_text(): ?string
{
    if (!empty(auth_store()['replaced'])) {
        return t('flash.session_replaced');
    }
    $m = (string)($_GET['m'] ?? '');
    if ($m === '' || !preg_match('/^[a-z_]{1,30}$/', $m)) {
        return null;
    }
    $key = 'flash.' . $m;
    return array_key_exists($key, lang_strings(current_lang())) ? t($key) : null;
}

/** Položky uživatelského menu vybrané jen podle role z DB (host nedostane nic). */
function menu_items(?array $user): array
{
    if ($user === null) {
        return [];
    }
    $role = (int)$user['role'];
    $items = [
        ['label' => t('menu.add_event'), 'href' => url('event/new')],
        ['label' => t('menu.add_band'), 'href' => url('band/new')],
        ['label' => t('menu.add_venue'), 'href' => url('venue/new')],
        ['label' => t('menu.my_pending'), 'href' => url('my')],
        ['label' => t('menu.profile'), 'href' => url('profile')],
        ['label' => t('menu.filters'), 'href' => url('filters')],
    ];
    if ($role >= role_value('moderator')) {
        $items[] = ['label' => t('menu.review', ['n' => pending_count()]), 'href' => url('review')];
    }
    if ($role >= role_value('admin')) {
        $items[] = ['label' => t('menu.users'), 'href' => url('admin/users')];
        $items[] = ['label' => t('menu.settings'), 'href' => url('admin/settings')];
    }
    return $items;
}

/** Kulatý avatar: fotka z účtu poskytovatele, jinak barevné kolečko s prvním písmenem jména. */
function avatar_html(array $user): string
{
    $initial = mb_strtoupper(mb_substr(trim((string)$user['display_name']), 0, 1)) ?: '?';
    $cls = 'av' . ((int)$user['id'] % 8);
    $img = '';
    $src = (string)(user_profile_get((int)$user['id'])['avatar'] ?? '');
    if ($src !== '' && str_starts_with($src, 'https://')) {
        $img = '<img class="avatar-img" src="' . e($src) . '" alt="" referrerpolicy="no-referrer" loading="lazy">';
    }
    return '<span class="avatar ' . e($cls) . '"><span class="avatar-ini">' . e($initial) . '</span>' . $img . '</span>';
}

/** Celá stránka: obsah se vykreslí do šablony layout. */
function page(string $title, string $tpl, array $vars = [], int $status = 200): never
{
    $user = auth_resolve();
    $content = render($tpl, $vars + ['user' => $user]);
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo render('layout', [
        'title' => $title,
        'content' => $content,
        'user' => $user,
        'menu' => menu_items($user),
        'flash' => flash_text(),
        'lang' => current_lang(),
        'path' => req_path(),
        'section' => (string)($vars['section'] ?? 'events'),
    ]);
    exit;
}

/** Chybová stránka v rozvržení aplikace (volá ji abort()). */
function render_error_page(int $code, string $message): never
{
    if (!headers_sent()) {
        send_security_headers(auth_resolve() !== null);
    }
    $known = [403 => 'error.403', 404 => 'error.404', 429 => 'error.429'];
    $heading = isset($known[$code]) ? t($known[$code]) : t('error.generic');
    page($heading, 'error', ['code' => $code, 'message' => $message !== '' ? $message : $heading], $code);
}
