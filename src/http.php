<?php
// src/http.php
declare(strict_types=1);

/** Je IP adresa povolená jako cíl odchozího požadavku (ochrana proti SSRF)? */
function http_ip_allowed(string $ip): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    if (strlen($bin) === 16 && str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
        $ip = inet_ntop(substr($bin, 12)) ?: $ip; // IPv4 zabalená do IPv6
        $bin = inet_pton($ip) ?: $bin;
    }
    if (!cfg('http.block_private_ips')) {
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    if (strlen($bin) === 4) {
        $n = (int)sprintf('%u', ip2long($ip));
        if ($n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255')) { // CGNAT
            return false;
        }
        if ($n >= ip2long('192.0.0.0') && $n <= ip2long('192.0.0.255')) {
            return false;
        }
    } elseif (str_starts_with($bin, "\xfc") || str_starts_with($bin, "\xfd") || str_starts_with($bin, "\xfe\x80")) {
        return false; // unique-local a link-local IPv6
    }
    return true;
}

/** @return string[] IP adresy hostitele (IPv4 + volitelně IPv6). */
function http_resolve(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [$host];
    }
    $ips = (array)(@gethostbynamel($host) ?: []);
    if (function_exists('dns_get_record')) {
        foreach ((array)(@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
            if (!empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
    }
    return array_values(array_unique($ips));
}

function http_absolutize(string $base, string $loc): string
{
    if (preg_match('~^[A-Za-z][A-Za-z0-9+.-]*://~', $loc)) {
        return $loc;
    }
    $p = parse_url($base);
    $origin = ($p['scheme'] ?? 'http') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    if (str_starts_with($loc, '//')) {
        return ($p['scheme'] ?? 'http') . ':' . $loc;
    }
    if (str_starts_with($loc, '/')) {
        return $origin . $loc;
    }
    $dir = preg_replace('~/[^/]*$~', '/', (string)($p['path'] ?? '/')) ?? '/';
    return $origin . $dir . $loc;
}

/**
 * Jediná funkce pro odchozí HTTP (stažení obrázku, OAuth, interní import): schémata, porty, blokace
 * privátních/loopback/link-local IP po DNS i po každém přesměrování, připnutí IP (proti DNS rebindingu),
 * limit přesměrování, velikosti a timeoutu.
 * $o: method (GET|POST), headers (pole řádků), body (řetězec nebo pole), max_bytes, timeout.
 * @return array{status:int, headers:array<string,string>, body:string, url:string}
 */
function http_request(string $url, array $o = []): array
{
    $method = strtoupper((string)($o['method'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new InvalidArgumentException('Nepodporovaná HTTP metoda.');
    }
    $maxBytes = (int)($o['max_bytes'] ?? cfg('http.max_bytes'));
    $timeout = (int)($o['timeout'] ?? cfg('http.timeout_sec'));
    $body = $o['body'] ?? null;
    $maxRedirects = max(0, (int)($o['max_redirects'] ?? cfg('http.max_redirects')));

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $p = parse_url($url);
        $scheme = strtolower((string)($p['scheme'] ?? ''));
        $host = strtolower(rtrim((string)($p['host'] ?? ''), '.'));
        if ($host === '' || isset($p['user']) || isset($p['pass']) || !in_array($scheme, (array)cfg('http.allowed_schemes'), true)) {
            throw new RuntimeException('URL není povolená.');
        }
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, (array)cfg('http.allowed_ports'), true)) {
            throw new RuntimeException('Port není povolený.');
        }
        $ips = http_resolve($host);
        if (!$ips) {
            throw new RuntimeException('Doménu nelze přeložit.');
        }
        $pin = null;
        foreach ($ips as $ip) {
            if (!http_ip_allowed($ip)) {
                security_log('http_blocked_ip', ['host' => $host]);
                throw new RuntimeException('Cílová adresa je blokována.');
            }
            if ($pin === null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $pin = $ip;
            }
        }
        if ($pin === null) {
            throw new RuntimeException('Cíl nemá IPv4 adresu.');
        }

        $respHeaders = [];
        $buf = '';
        $tooBig = false;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 5),
            CURLOPT_USERAGENT => (string)cfg('http.user_agent'),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $pin],
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => (array)($o['headers'] ?? []),
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$respHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (&$buf, &$tooBig, $maxBytes): int {
                if (strlen($buf) + strlen($chunk) > $maxBytes) {
                    $tooBig = true;
                    return 0;
                }
                $buf .= $chunk;
                return strlen($chunk);
            },
        ];
        if ($method === 'POST' && $body !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : (string)$body;
        }
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($tooBig) {
            throw new RuntimeException('Odpověď překročila povolenou velikost.');
        }
        if ($errno !== 0) {
            throw new RuntimeException('Požadavek selhal (cURL ' . $errno . ').');
        }
        if (in_array($status, [301, 302, 303, 307, 308], true) && !empty($respHeaders['location'])) {
            $url = http_absolutize($url, $respHeaders['location']);
            $method = 'GET';
            $body = null;
            continue;
        }
        return ['status' => $status, 'headers' => $respHeaders, 'body' => $buf, 'url' => $url];
    }
    throw new RuntimeException('Příliš mnoho přesměrování.');
}
