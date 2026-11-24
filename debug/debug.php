<?php
// debug/debug.php
declare(strict_types=1);

function diagnostics_state(int $userId): bool
{
    $profile = user_profile_get($userId);
    return ($profile['diagnostika'] ?? false) === true;
}

function route_diagnostics(): never
{
    $u = require_login();
    if ((int)$u['id'] !== 2) {
        abort(403, t('error.403'));
    }
    page(t('diagnostics.title'), 'diagnostics', ['active' => diagnostics_state((int)$u['id'])]);
}

function route_diagnostics_post(): never
{
    $u = require_login();
    if ((int)$u['id'] !== 2) {
        abort(403, t('error.403'));
    }
    json_locked(user_profile_rel((int)$u['id']), static function (array &$data): void {
        if (($data['diagnostika'] ?? false) === true) {
            unset($data['diagnostika']);
        } else {
            $data['diagnostika'] = true;
        }
    }, true);
    redirect('diagnostics');
}

/** Diagnostika je aktivní pouze při explicitní hodnotě true v JSON profilu uživatele. */
function kf_debug_enabled(?array $user): bool
{
    if ($user === null || (int)($user['id'] ?? 0) <= 0) {
        return false;
    }

    return (user_profile_get((int)$user['id'])['diagnostika'] ?? false) === true;
}

/** Načte výhradně oddělené prostředky Developer Tools. */
function kf_debug_assets(): void
{
    $css = KF_ROOT . '/debug/debug.css';
    $js = KF_ROOT . '/debug/debug.js';

    if (is_file($css)) {
        echo '<link rel="stylesheet" href="' . e(url('debug/debug.css?v=' . (int)filemtime($css))) . '">' . "\n";
    }
    if (is_file($js)) {
        echo '<script src="' . e(url('debug/debug.js?v=' . (int)filemtime($js))) . '" defer></script>' . "\n";
    }
}

/** Plnohodnotný klientský diagnostický panel; obsah se vypisuje bezpečně přes textContent. */
function kf_debug_render(): void
{
    echo '<section id="kf-debug-panel" aria-label="K&F Developer Tools">'
        . '<header class="kf-debug-header">'
        . '<strong class="kf-debug-brand">K&amp;F DEVTOOLS</strong>'
        . '<span class="kf-debug-summary" data-debug-summary>Inicializace…</span>'
        . '<span class="kf-debug-spacer"></span>'
        . '<button type="button" data-debug-pause aria-pressed="false">Pozastavit</button>'
        . '<button type="button" data-debug-clear>Vymazat</button>'
        . '<button type="button" data-debug-toggle aria-expanded="true">Sbalit</button>'
        . '</header>'
        . '<nav class="kf-debug-tabs" aria-label="Diagnostické panely">'
        . '<button type="button" data-debug-tab="console" aria-selected="true">Console <span data-debug-count="console">0</span></button>'
        . '<button type="button" data-debug-tab="network" aria-selected="false">Network <span data-debug-count="network">0</span></button>'
        . '<button type="button" data-debug-tab="errors" aria-selected="false">Errors <span data-debug-count="errors">0</span></button>'
        . '<button type="button" data-debug-tab="info" aria-selected="false">Info</button>'
        . '</nav>'
        . '<div class="kf-debug-tools"><input type="search" data-debug-filter placeholder="Filtrovat záznamy…" aria-label="Filtrovat diagnostické záznamy"></div>'
        . '<div class="kf-debug-content">'
        . '<div class="kf-debug-view" data-debug-view="console"></div>'
        . '<div class="kf-debug-view" data-debug-view="network" hidden></div>'
        . '<div class="kf-debug-view" data-debug-view="errors" hidden></div>'
        . '<div class="kf-debug-view" data-debug-view="info" hidden></div>'
        . '</div>'
        . '</section>';
}
