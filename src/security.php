<?php
// src/security.php
declare(strict_types=1);

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function request_is_https(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    return ($https !== '' && $https !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** IP klienta. Za reverzní proxy se bere poslední adresa z cfg('security.ip_header') (tu přidala proxy). */
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

function ua_hash_hex(): string
{
    return substr(hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
}

/** Origin/Referer musí patřit hostiteli z BASE_URL. */
function origin_ok(): bool
{
    $base = parse_url((string)env('BASE_URL', ''));
    $src = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($src === '') {
        $src = (string)($_SERVER['HTTP_REFERER'] ?? '');
    }
    if ($src === '' || !is_array($base)) {
        return false;
    }
    $o = parse_url($src);
    return is_array($o)
        && strtolower((string)($o['host'] ?? '')) === strtolower((string)($base['host'] ?? ''))
        && ($o['port'] ?? null) === ($base['port'] ?? null);
}

/** Bezpečnostní log: čas, IP, user_id, požadavek, důvod, kontext. */
function security_log(string $reason, array $ctx = []): void
{
    try {
        $uri = substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 200);
        $json = $ctx ? substr((string)json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 300) : '';
        log_append(
            (string)cfg('storage.security_log_file'),
            date('c') . "\t" . client_ip() . "\t-\t" . ($_SERVER['REQUEST_METHOD'] ?? '-') . ' ' . $uri . "\t" . $reason . "\t" . $json
        );
    } catch (Throwable) {
        // logování nesmí způsobit další chybu
    }
}

/** Rate-limit podle IP a skupiny (jediný sdílený soubor). Vrací true, pokud je limit překročen; $count = false jen zjišťuje stav. */
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
