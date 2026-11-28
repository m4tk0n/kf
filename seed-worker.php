<?php
declare(strict_types=1);

/** Chráněný PHP worker volaný GitHub Actions. Bez modelu a bez placeného vyhledávacího API. */
define('KF_ROOT', __DIR__);
require_once KF_ROOT . '/bootstrap.php';

function seed_reply(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!cfg('seed_research.enabled', false)) seed_reply(404, ['error' => 'disabled']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    seed_reply(405, ['error' => 'method_not_allowed']);
}

$keyName = (string)cfg('seed_research.key_env', 'AI_WRITE_KEY');
$expected = (string)env($keyName, '');
$provided = (string)($_SERVER['HTTP_X_AI_KEY'] ?? '');
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    security_log('seed_research_key_invalid');
    seed_reply(403, ['error' => 'forbidden']);
}

$body = file_get_contents('php://input', false, null, 0, 2049);
if (!is_string($body) || strlen($body) > 2048) seed_reply(413, ['error' => 'request_too_large']);
$request = json_decode($body, true);
if (!is_array($request)) seed_reply(400, ['error' => 'invalid_json']);

$maxQueries = (int)cfg('seed_research.max_queries_per_request', 12);
$budget = filter_var($request['max_queries'] ?? $maxQueries, FILTER_VALIDATE_INT);
if ($budget === false || $budget < 1 || $budget > $maxQueries) {
    seed_reply(422, ['error' => 'invalid_max_queries', 'max' => $maxQueries]);
}

$queuePath = abs_path((string)cfg('seed_research.queue_file'));
$statePath = abs_path((string)cfg('seed_research.state_file'));
$resultsPath = abs_path((string)cfg('seed_research.results_file'));
$queryLogPath = abs_path((string)cfg('seed_research.query_log_file'));
$lockPath = abs_path((string)cfg('seed_research.lock_file'));

foreach ([$queuePath, $statePath, $resultsPath, $queryLogPath, $lockPath] as $path) {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        seed_reply(500, ['error' => 'storage_unavailable']);
    }
}
foreach ([$resultsPath, $queryLogPath] as $path) {
    if (!is_file($path) && file_put_contents($path, '') === false) {
        seed_reply(500, ['error' => 'cannot_initialize_storage']);
    }
}

$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    seed_reply(409, ['error' => 'worker_already_running']);
}

function seed_json_read(string $path, mixed $fallback): mixed
{
    if (!is_file($path)) return $fallback;
    $raw = file_get_contents($path);
    if (!is_string($raw)) return $fallback;
    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function seed_json_write(string $path, mixed $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('JSON encode failed');
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, $path)) {
        throw new RuntimeException('Cannot write checkpoint: ' . basename($path));
    }
}

function seed_append_jsonl(string $path, array $row): void
{
    $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || file_put_contents($path, $json . "\n", FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Cannot append research result');
    }
}

function seed_normalize(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii)) $value = $ascii;
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function seed_queries(string $name): array
{
    $plain = trim($name);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $plain);
    $variants = [
        '"' . $plain . '"',
        '"' . $plain . '" koncert OR koncerty',
        '"' . $plain . '" live OR tour OR gig',
        '"' . $plain . '" festival OR lineup OR program',
        '"' . $plain . '" setlist OR playlist',
        '"' . $plain . '" band history OR biography',
        '"' . $plain . '" klub OR club',
    ];
    $variants[] = is_string($ascii) && $ascii !== '' && mb_strtolower($ascii) !== mb_strtolower($plain)
        ? '"' . $ascii . '" concert OR koncert'
        : '"' . $plain . '" 2024 OR 2025 OR 2026';
    return $variants;
}

function seed_bing_rss(string $query): array
{
    $endpoint = (string)cfg('seed_research.search_endpoint');
    $url = $endpoint . '?' . http_build_query(['format' => 'rss', 'q' => $query], '', '&', PHP_QUERY_RFC3986);
    $timeout = (int)cfg('seed_research.query_timeout_sec', 12);
    $raw = false;
    $status = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'KF-SeedResearch/1.0 (+https://7x.wz.cz/_kf/)',
            CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml;q=0.9, */*;q=0.5'],
        ]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($raw)) throw new RuntimeException('Bing request failed: ' . $error);
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
            'header' => "User-Agent: KF-SeedResearch/1.0\r\nAccept: application/rss+xml\r\n",
        ]]);
        $raw = @file_get_contents($url, false, $context);
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m)) $status = (int)$m[1];
        }
        if (!is_string($raw)) throw new RuntimeException('Bing request failed without cURL');
    }

    if ($status !== 200) throw new RuntimeException('Bing returned HTTP ' . $status);
    if (strlen($raw) > 2_000_000) throw new RuntimeException('Bing RSS response too large');
    if (!function_exists('simplexml_load_string')) throw new RuntimeException('PHP SimpleXML extension is missing');

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    if (!$xml instanceof SimpleXMLElement) throw new RuntimeException('Bing returned invalid RSS');

    $items = [];
    foreach ($xml->channel->item ?? [] as $item) {
        $link = trim((string)$item->link);
        if (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $link)) continue;
        $host = strtolower((string)parse_url($link, PHP_URL_HOST));
        if ($host === '' || str_contains($host, 'bing.com')) continue;
        $items[] = [
            'title' => trim(html_entity_decode(strip_tags((string)$item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'url' => $link,
            'description' => trim(html_entity_decode(strip_tags((string)$item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        ];
    }
    return $items;
}

try {
    $queue = seed_json_read($queuePath, []);
    if (!is_array($queue)) throw new RuntimeException('Band queue is not a JSON array');
    $state = seed_json_read($statePath, []);
    if (!is_array($state)) $state = [];
    $knownIds = [];
    $existingLines = file($resultsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($existingLines as $line) {
        $old = json_decode($line, true);
        if (is_array($old) && isset($old['id'])) $knownIds[(string)$old['id']] = true;
    }

    $queriesDone = 0;
    $newResults = 0;
    $errors = [];
    $delayMs = (int)cfg('seed_research.query_delay_ms', 600);

    while ($queriesDone < $budget) {
        $queueIndex = null;
        foreach ($queue as $index => $row) {
            if (is_array($row) && in_array((string)($row['status'] ?? 'pending'), ['pending', 'searching'], true)) {
                $queueIndex = $index;
                break;
            }
        }
        if ($queueIndex === null) break;

        $row = $queue[$queueIndex];
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            $queue[$queueIndex]['status'] = 'invalid';
            seed_json_write($queuePath, $queue);
            continue;
        }
        $variants = seed_queries($name);
        $queryIndex = max(0, (int)($row['query_index'] ?? 0));
        if ($queryIndex >= count($variants)) {
            $queue[$queueIndex]['status'] = 'searched';
            $queue[$queueIndex]['searched_at'] = gmdate('c');
            seed_json_write($queuePath, $queue);
            continue;
        }

        $query = $variants[$queryIndex];
        $queue[$queueIndex]['status'] = 'searching';
        $queue[$queueIndex]['last_query'] = $query;
        seed_json_write($queuePath, $queue);

        try {
            $items = seed_bing_rss($query);
            $resultCount = 0;
            foreach ($items as $rank => $item) {
                $urlKey = hash('sha256', seed_normalize($name) . '|' . strtolower(rtrim($item['url'], '/')));
                if (isset($knownIds[$urlKey])) continue;
                seed_append_jsonl($resultsPath, [
                    'id' => $urlKey,
                    'band' => $name,
                    'band_key' => (string)($row['key'] ?? seed_normalize($name)),
                    'query' => $query,
                    'query_variant' => $queryIndex + 1,
                    'rank' => $rank + 1,
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'snippet' => $item['description'],
                    'engine' => 'Bing RSS',
                    'found_at' => gmdate('c'),
                    'review_status' => 'unverified_source_lead',
                ]);
                $knownIds[$urlKey] = true;
                $resultCount++;
            }
            $newResults += $resultCount;
            seed_append_jsonl($queryLogPath, [
                'band' => $name, 'query' => $query, 'query_variant' => $queryIndex + 1,
                'result_count' => $resultCount, 'status' => 'ok', 'searched_at' => gmdate('c'),
            ]);
            $queue[$queueIndex]['query_index'] = $queryIndex + 1;
            $queue[$queueIndex]['results_count'] = (int)($queue[$queueIndex]['results_count'] ?? 0) + $resultCount;
            $queue[$queueIndex]['last_error'] = '';
            if ($queryIndex + 1 >= count($variants)) {
                $queue[$queueIndex]['status'] = 'searched';
                $queue[$queueIndex]['searched_at'] = gmdate('c');
            } else {
                $queue[$queueIndex]['status'] = 'pending';
            }
            $queriesDone++;
        } catch (Throwable $error) {
            $message = mb_substr($error->getMessage(), 0, 500);
            $errors[] = ['band' => $name, 'query' => $query, 'error' => $message];
            $queue[$queueIndex]['status'] = 'error';
            $queue[$queueIndex]['last_error'] = $message;
            seed_append_jsonl($queryLogPath, [
                'band' => $name, 'query' => $query, 'query_variant' => $queryIndex + 1,
                'status' => 'error', 'error' => $message, 'searched_at' => gmdate('c'),
            ]);
        }

        seed_json_write($queuePath, $queue);
        $state['last_band'] = $name;
        $state['last_query'] = $query;
        $state['last_run'] = gmdate('c');
        $state['queries_completed'] = (int)($state['queries_completed'] ?? 0) + ($errors === [] ? 1 : 0);
        $state['last_result_count'] = $newResults;
        seed_json_write($statePath, $state);

        if ($delayMs > 0) usleep($delayMs * 1000);
        if ($errors !== []) break;
    }

    $pending = $searched = $failed = 0;
    foreach ($queue as $row) {
        $status = (string)($row['status'] ?? 'pending');
        if (in_array($status, ['pending', 'searching'], true)) $pending++;
        elseif ($status === 'searched') $searched++;
        elseif ($status === 'error') $failed++;
    }

    seed_reply(200, [
        'ok' => true, 'queries_this_run' => $queriesDone, 'new_source_leads' => $newResults,
        'pending_bands' => $pending, 'searched_bands' => $searched, 'failed_bands' => $failed,
        'errors' => $errors,
        'note' => 'Výsledky jsou pouze neověřené zdrojové stopy, nikoli potvrzené koncerty.',
    ]);
} catch (Throwable $error) {
    security_log('seed_research_worker_error', ['message' => mb_substr($error->getMessage(), 0, 500)]);
    seed_reply(500, ['error' => 'worker_failed', 'message' => mb_substr($error->getMessage(), 0, 300)]);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
