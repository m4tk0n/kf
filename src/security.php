<?php
// src/security.php
declare(strict_types=1);

function request_is_https(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return ($https !== '' && $https !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** IP klienta. Za reverzní proxy se bere poslední adresa z cfg('security.ip_header') (tu přidala proxy; levé části může podvrhnout klient). */
function client_ip(): string
{
    static $ip = null;
    if ($ip === null) {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $hdr = cfg('security.ip_header');
        if (is_string($hdr) && $hdr !== '' && !empty($_SERVER[$hdr])) {
            $parts = array_map('trim', explode(',', (string)$_SERVER[$hdr]));
            $cand = (string)end($parts);
            if (filter_var($cand, FILTER_VALIDATE_IP)) {
                $ip = $cand;
            }
        }
    }
    return $ip;
}

/** Bezpečnostní log: čas, IP, user_id, požadavek, důvod, kontext. */
function security_log(string $reason, array $ctx = []): void
{
    $uid = auth_cached_uid();
    $uri = substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 200);
    $json = $ctx ? substr((string)json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 300) : '';
    log_append(
        (string)cfg('storage.security_log_file'),
        date('c') . "\t" . client_ip() . "\t" . ($uid ?? '-') . "\t" . ($_SERVER['REQUEST_METHOD'] ?? '-') . ' ' . $uri . "\t" . $reason . "\t" . $json
    );
}

/**
 * Rate-limit podle IP a skupiny (jediný sdílený soubor). Vrací true, pokud je limit překročen.
 * $count = false jen zjišťuje stav bez započtení.
 */
function ratelimit_hit(string $bucket, int $max, bool $count = true): bool
{
    $key = $bucket . '|' . client_ip();
    $window = (int)cfg('limits.rate_limit_window_sec');
    $cap = (int)cfg('limits.rate_limit_max_entries');
    $now = time();
    return (bool)json_locked((string)cfg('storage.ratelimit_file'), static function (array &$d) use ($key, $window, $max, $count, $now, $cap): bool {
        ratelimit_purge($d, $now, $cap);
        $cur = $d[$key] ?? [0, $now + $window];
        if ($count) {
            $cur[0]++;
            $d[$key] = $cur;
        }
        return $cur[0] > $max;
    });
}

function ratelimit_purge(array &$d, int $now, int $cap): void
{
    foreach ($d as $k => $v) {
        if (!is_array($v) || (int)($v[1] ?? 0) <= $now) {
            unset($d[$k]);
        }
    }
    if ($cap > 0 && count($d) > $cap) {
        uasort($d, static fn(array $a, array $b): int => $b[1] <=> $a[1]);
        $d = array_slice($d, 0, $cap, true);
    }
}

/** Cron: vyčistí expirované záznamy rate-limitu. */
function ratelimit_cleanup(): void
{
    $now = time();
    $cap = (int)cfg('limits.rate_limit_max_entries');
    json_locked((string)cfg('storage.ratelimit_file'), static function (array &$d) use ($now, $cap): void {
        ratelimit_purge($d, $now, $cap);
    });
}

function send_security_headers(bool $authenticated): void
{
    if (headers_sent()) {
        return;
    }
    foreach ((array)cfg('security.headers') as $name => $value) {
        header($name . ': ' . $value);
    }
    $csp = cfg('security.csp');
    if (is_string($csp) && $csp !== '') {
        header('Content-Security-Policy: ' . $csp);
    }
    if ($authenticated && cfg('security.no_store_for_auth')) {
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
    }
}

/** Podezřelé vstupy (SQLi/XSS vzory) se jen zalogují; blokování řeší prepared statements a escapování. */
function scan_input(): void
{
    if (!cfg('security.scan_input')) {
        return;
    }
    $patterns = (array)cfg('security.suspicious_patterns');
    $found = null;
    $walk = static function (mixed $v, string $path, int $depth) use (&$walk, $patterns, &$found): void {
        if ($found !== null || $depth > 4) {
            return;
        }
        if (is_array($v)) {
            foreach ($v as $k => $item) {
                $walk($item, $path . '[' . $k . ']', $depth + 1);
            }
            return;
        }
        if (!is_string($v) || $v === '' || strlen($v) > 4000) {
            return;
        }
        foreach ($patterns as $i => $re) {
            if (@preg_match((string)$re, $v) === 1) {
                $found = ['field' => substr($path, 0, 60), 'pattern' => $i, 'sample' => substr($v, 0, 80)];
                return;
            }
        }
    };
    $walk($_GET, 'GET', 0);
    $walk($_POST, 'POST', 0);
    if ($found !== null) {
        security_log('suspicious_input', $found);
    }
}
