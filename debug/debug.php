<?php
// debug/debug.php
declare(strict_types=1);

require_once KF_ROOT . '/debug/test_profiles.php';

function diagnostics_state(int $userId): bool
{
    $profile = user_profile_get($userId);
    return ($profile['diagnostika'] ?? false) === true;
}

/** Diagnostiku smí používat jen hlavní účet (ID 2). */
function diagnostics_allowed(?array $u): bool
{
    $real = auth_real_resolve();
    return $real !== null && (int)($real['id'] ?? 0) === 2;
}

function route_diagnostics(): never
{
    require_login();
    if (!diagnostics_allowed(null)) {
        security_log('forbidden', ['action' => 'diagnostics']);
        abort(403, t('error.403'));
    }
    page(t('diagnostics.title'), 'diagnostics', ['active' => diagnostics_state(2)]);
}

function route_diagnostics_post(): never
{
    require_login();
    if (!diagnostics_allowed(null)) {
        security_log('forbidden', ['action' => 'diagnostics']);
        abort(403, t('error.403'));
    }
    json_locked(user_profile_rel(2), static function (array &$data): void {
        if (($data['diagnostika'] ?? false) === true) {
            unset($data['diagnostika']);
        } else {
            $data['diagnostika'] = true;
        }
    });
    redirect('diagnostics');
}

/**
 * GET diagnostics/log: posledních řádků PHP error logu a bezpečnostního logu pro záložku Server.
 * Do index.php (pole $get v kf_dispatch) přidat: 'diagnostics/log' => 'route_diagnostics_log',
 */
function route_diagnostics_log(): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!diagnostics_allowed(auth_resolve())) {
        security_log('forbidden', ['action' => 'diagnostics_log']);
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'time' => date('c'),
        'error' => tail_lines((string)cfg('storage.error_log_file'), 80),
        'security' => tail_lines((string)cfg('storage.security_log_file'), 60),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Diagnostika je aktivní pouze při explicitní hodnotě true v JSON profilu uživatele. */
function kf_debug_enabled(?array $user): bool
{
    if (!diagnostics_allowed($user)) {
        return false;
    }

    return (user_profile_get(2)['diagnostika'] ?? false) === true;
}

/** Načte výhradně oddělené prostředky Developer Tools. JS bez defer: zachytí i chyby a požadavky ostatních skriptů. */
function kf_debug_assets(): void
{
    $css = KF_ROOT . '/debug/debug.css';
    $js = KF_ROOT . '/debug/debug.js';

    if (is_file($css)) {
        echo '<link rel="stylesheet" href="' . e(url('debug/debug.css?v=' . (int)filemtime($css))) . '">' . "\n";
    }
    if (is_file($js)) {
        echo '<script src="' . e(url('debug/debug.js?v=' . (int)filemtime($js))) . '"></script>' . "\n";
    }
}

/** Kostra panelu; veškerý obsah doplňuje debug.js přes textContent. Serverové údaje jdou jako data-atributy. */
function kf_debug_render(): void
{
    $u = auth_resolve();
    $ms = isset($_SERVER['REQUEST_TIME_FLOAT']) ? round((microtime(true) - (float)$_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) : 0;
    $attrs = [
        'server-ms' => (string)$ms,
        'php' => PHP_VERSION,
        'mem-peak' => (string)round(memory_get_peak_usage(true) / 1048576, 2),
        'mem-limit' => (string)ini_get('memory_limit'),
        'files' => (string)count(get_included_files()),
        'user-id' => (string)(int)($u['id'] ?? 0),
        'real-user-id' => (string)(int)(auth_real_resolve()['id'] ?? 0),
        'user-role' => (string)(int)($u['role'] ?? 0),
        'lang' => current_lang(),
        'env' => (string)env('APP_ENV', ''),
        'csrf-field' => (string)cfg('security.csrf_field'),
        'log-url' => url('diagnostics/log'),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        'opcache' => function_exists('opcache_get_status') && ini_get('opcache.enable') ? '1' : '0',
    ];
    $data = '';
    foreach ($attrs as $k => $v) {
        $data .= ' data-' . $k . '="' . e($v) . '"';
    }

    $tabs = [
        'console' => 'Console',
        'network' => 'Network',
        'errors' => 'Errors',
        'events' => 'Events',
        'app' => 'App',
        'page' => 'Stránka',
        'checks' => 'Kontroly',
        'server' => 'Server',
        'settings' => '⚙',
    ];
    $counted = ['console', 'network', 'errors', 'events', 'checks'];

    echo '<section id="kf-debug-panel" aria-label="K&amp;F Developer Tools"' . $data . '>'
        . '<header class="kf-debug-header">'
        . '<strong class="kf-debug-brand">K&amp;F DEVTOOLS</strong>'
        . '<a href="' . e(url('debug/users')) . '" class="kf-debug-profile-link">Profily</a>'
        . '<span class="kf-debug-summary" data-debug-summary>Inicializace…</span>'
        . '<span class="kf-debug-spacer"></span>'
        . '<button type="button" data-debug-pause aria-pressed="false" title="Pozastavit zaznamenávání" aria-label="Pozastavit zaznamenávání">❚❚</button>'
        . '<button type="button" data-debug-clear title="Vymazat záznamy" aria-label="Vymazat záznamy">⌫</button>'
        . '<button type="button" data-debug-copy title="Zkopírovat report do schránky" aria-label="Zkopírovat report">⧉</button>'
        . '<button type="button" data-debug-dock title="Přepnout nahoru / dolů" aria-label="Přepnout umístění panelu">⇅</button>'
        . '<button type="button" data-debug-toggle aria-expanded="true" title="Sbalit / rozbalit (Alt+D)" aria-label="Sbalit panel">▾</button>'
        . '</header>'
        . '<nav class="kf-debug-tabs" role="tablist" aria-label="Diagnostické panely">';
    foreach ($tabs as $key => $label) {
        echo '<button type="button" role="tab" data-debug-tab="' . e($key) . '" aria-selected="false">' . e($label);
        if (in_array($key, $counted, true)) {
            echo ' <span class="kf-debug-badge" data-debug-count="' . e($key) . '">0</span>';
        }
        echo '</button>';
    }
    echo '</nav>'
        . '<div class="kf-debug-tools">'
        . '<input type="search" data-debug-filter placeholder="Filtrovat…" aria-label="Filtrovat diagnostické záznamy">'
        . '<span class="kf-debug-chips" data-debug-chips></span>'
        . '<span class="kf-debug-meta" data-debug-meta></span>'
        . '</div>'
        . '<div class="kf-debug-content">';
    foreach (array_keys($tabs) as $key) {
        echo '<div class="kf-debug-view" role="tabpanel" data-debug-view="' . e($key) . '" hidden></div>';
    }
    echo '</div></section>';
}
