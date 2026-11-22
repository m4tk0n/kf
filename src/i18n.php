<?php
// src/i18n.php
declare(strict_types=1);

function lang_valid(string $lang): bool
{
    return in_array($lang, (array)cfg('app.supported_langs'), true);
}

/** Jazyk požadavku: profil přihlášeného > cookie > Accept-Language (cs/sk -> cs, en -> en) > výchozí. */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $default = (string)cfg('app.default_lang');

    $u = auth_resolve();
    if ($u !== null) {
        $pl = (string)(user_profile_get($u['id'])['lang'] ?? '');
        if (lang_valid($pl)) {
            return $lang = $pl;
        }
    }
    $cookie = (string)($_COOKIE[(string)cfg('app.lang_cookie')] ?? '');
    if ($u === null && lang_valid($cookie)) {
        return $lang = $cookie;
    }

    $cands = [];
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $i => $part) {
        $bits = explode(';', trim($part));
        $code = strtolower(substr(trim($bits[0]), 0, 2));
        $q = 1.0;
        foreach (array_slice($bits, 1) as $b) {
            if (preg_match('/^\s*q=([0-9.]+)/', $b, $m)) {
                $q = (float)$m[1];
            }
        }
        if ($code !== '' && $q > 0) {
            $cands[] = [$q, -$i, $code];
        }
    }
    rsort($cands);
    foreach ($cands as [, , $code]) {
        $mapped = $code === 'sk' ? 'cs' : $code;
        if (lang_valid($mapped)) {
            return $lang = $mapped;
        }
    }
    return $lang = lang_valid($default) ? $default : 'cs';
}

/** Explicitní volba jazyka nepřihlášeného návštěvníka (volat z POST akce). */
function lang_set_cookie(string $lang): void
{
    if (lang_valid($lang) && !headers_sent()) {
        setcookie((string)cfg('app.lang_cookie'), $lang, [
            'expires' => time() + (int)cfg('app.lang_cookie_ttl_sec'),
            'path' => base_path(),
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function lang_strings(string $lang): array
{
    static $cache = [];
    if (!lang_valid($lang)) {
        $lang = (string)cfg('app.default_lang');
    }
    if (!isset($cache[$lang])) {
        $file = abs_path((string)cfg('paths.lang')) . '/' . $lang . '.php';
        $data = is_file($file) ? require $file : [];
        $cache[$lang] = is_array($data) ? $data : [];
    }
    return $cache[$lang];
}

function t(string $key, array $params = []): string
{
    $val = lang_strings(current_lang())[$key] ?? lang_strings((string)cfg('app.default_lang'))[$key] ?? $key;
    if (is_array($val)) {
        $val = (string)($val['other'] ?? $key);
    }
    $repl = [];
    foreach ($params as $k => $v) {
        $repl['{' . $k . '}'] = (string)$v;
    }
    return strtr((string)$val, $repl);
}

/** Tvar pro počet podle cfg('plural.<jazyk>'). */
function plural_form(string $lang, int $n): string
{
    $rule = (array)cfg('plural.' . $lang, []);
    if (isset($rule['one']) && $n === (int)$rule['one']) {
        return 'one';
    }
    if (isset($rule['few']) && in_array($n, (array)$rule['few'], true)) {
        return 'few';
    }
    return 'other';
}

/** Množné číslo: hodnota klíče je pole one/few/other s {n}. */
function tn(string $key, int $n, array $params = []): string
{
    $lang = current_lang();
    $val = lang_strings($lang)[$key] ?? lang_strings((string)cfg('app.default_lang'))[$key] ?? null;
    if (!is_array($val)) {
        return t($key, $params + ['n' => $n]);
    }
    $form = plural_form($lang, $n);
    $text = (string)($val[$form] ?? $val['other'] ?? $key);
    $repl = ['{n}' => (string)$n];
    foreach ($params as $k => $v) {
        $repl['{' . $k . '}'] = (string)$v;
    }
    return strtr($text, $repl);
}

/** Název sloupce s názvem země: name_ + ověřený jazyk (nikdy ze surového vstupu). */
function country_col(?string $lang = null): string
{
    $lang = $lang ?? current_lang();
    return 'name_' . (lang_valid($lang) ? $lang : (string)cfg('app.default_lang'));
}
