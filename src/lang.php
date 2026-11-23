<?php
// src/lang.php
declare(strict_types=1);

// jazyk: podle prohlížeče (Accept-Language); nastavení přihlášeného uživatele se doplní s profilem
function kf_lang(): string
{
    static $lang = null;
    if ($lang !== null) return $lang;
    // profil přihlášeného > cookie > prohlížeč (i18n.php)
    if (function_exists('current_lang')) return $lang = current_lang();
    $supported = (array)cfg('app.supported_langs');
    foreach (explode(',', strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))) as $part) {
        $code = substr(trim(explode(';', $part)[0]), 0, 2);
        if ($code === 'sk') $code = 'cs'; // slovenština se mapuje na češtinu
        if (in_array($code, $supported, true)) return $lang = $code;
    }
    return $lang = (string)cfg('app.default_lang');
}

function kf_msg(string $key): mixed
{
    static $data = [];
    $lang = kf_lang();
    if (!isset($data[$lang])) {
        $file = abs_path((string)cfg('paths.lang') . '/' . $lang . '.php');
        $data[$lang] = is_file($file) ? (array)(require $file) : [];
    }
    return $data[$lang][$key] ?? null;
}

function kf_t(string $key): string
{
    $v = kf_msg($key);
    return is_string($v) ? $v : $key;
}

// množné číslo podle cfg('plural.<lang>')
function kf_n(string $key, int $n): string
{
    $forms = kf_msg($key);
    if (!is_array($forms)) return $key;
    $rules = (array)cfg('plural.' . kf_lang());
    $form = 'other';
    if (isset($rules['one']) && $n === (int)$rules['one']) $form = 'one';
    elseif (isset($rules['few']) && in_array($n, (array)$rules['few'], true)) $form = 'few';
    return str_replace('{n}', (string)$n, (string)($forms[$form] ?? $forms['other'] ?? ''));
}
