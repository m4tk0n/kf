<?php
// debug/debug.php
declare(strict_types=1);

/** Diagnostika je aktivní pouze při explicitní hodnotě true v JSON profilu uživatele. */
function kf_debug_enabled(?array $user): bool
{
    if ($user === null || (int)($user['id'] ?? 0) <= 0) {
        return false;
    }

    return (user_profile_get((int)$user['id'])['diagnostika'] ?? false) === true;
}

/** Vloží pouze prostředky diagnostiky; načítají se výhradně při jejím zapnutí. */
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

/** Plovoucí panel diagnostiky. */
function kf_debug_render(): void
{
    echo '<aside id="kf-debug" class="kf-debug" aria-label="Debug" aria-live="polite">'
        . '<div class="kf-debug-head">'
        . '<strong>DEBUG</strong>'
        . '<button type="button" class="kf-debug-toggle" data-debug-toggle aria-expanded="true" aria-label="Sbalit diagnostiku">−</button>'
        . '</div>'
        . '<div class="kf-debug-body">'
        . '<div class="kf-debug-row"><span>Stav</span><b>Aktivní</b></div>'
        . '<div class="kf-debug-row"><span>Obrazovka</span><b data-debug-screen>—</b></div>'
        . '<div class="kf-debug-row"><span>Viewport</span><b data-debug-viewport>—</b></div>'
        . '<div class="kf-debug-row"><span>Online</span><b data-debug-online>—</b></div>'
        . '<div class="kf-debug-row"><span>Čas</span><b data-debug-time>—</b></div>'
        . '</div>'
        . '</aside>';
}
