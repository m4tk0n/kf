<?php
// src/api_ai.php
declare(strict_types=1);

/**
 * Chráněný endpoint pro externí AI worker (GitHub Actions):
 *   GET  <ai_import.endpoint>/sources  – zdroje a už známé source_url
 *   POST <ai_import.endpoint>/import   – balíček {source_url, event, venue?, bands[]}
 * Hlavička X-AI-Key musí odpovídat AI_WRITE_KEY. Deduplikaci a zápis do pending dodá krok 3 (ai_import_package()).
 */
function api_ai_handle(string $sub): never
{
    if (!cfg('ai_import.enabled')) {
        abort(404, 'not_found');
    }
    if (ratelimit_hit('ai', (int)cfg('limits.ai_endpoint_max_hits'))) {
        security_log('ai_rate_limit');
        json_out(['error' => 'rate_limited'], 429);
    }
    $key = (string)env('AI_WRITE_KEY', '');
    $sent = (string)($_SERVER['HTTP_X_AI_KEY'] ?? '');
    if ($key === '' || $sent === '' || !hash_equals($key, $sent)) {
        security_log('ai_key_invalid');
        json_out(['error' => 'forbidden'], 403);
    }

    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($sub === 'sources' && $method === 'GET') {
        $known = q(
            'SELECT source_url FROM ' . tbl('pending') . ' WHERE source_url IS NOT NULL ORDER BY id DESC LIMIT 500'
        )->fetchAll(PDO::FETCH_COLUMN);
        json_out(['sources' => array_values((array)cfg('ai_import.sources')), 'known_urls' => $known]);
    }

    if ($sub === 'import' && $method === 'POST') {
        $max = (int)cfg('ai_import.max_body_bytes');
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) {
            json_out(['error' => 'too_large'], 413);
        }
        $raw = (string)file_get_contents('php://input', false, null, 0, $max + 1);
        if (strlen($raw) > $max) {
            json_out(['error' => 'too_large'], 413);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            json_out(['error' => 'invalid_json'], 400);
        }

        $src = (string)($data['source_url'] ?? '');
        $ev = $data['event'] ?? null;
        $venue = $data['venue'] ?? null;
        $bands = $data['bands'] ?? [];
        if (!filter_var($src, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $src) || strlen($src) > 255) {
            json_out(['error' => 'invalid_source_url'], 422);
        }
        if (!is_array($ev) || trim((string)($ev['name'] ?? '')) === '' || mb_strlen((string)$ev['name']) > 160) {
            json_out(['error' => 'invalid_event_name'], 422);
        }
        $df = (string)($ev['date_from'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $df, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            json_out(['error' => 'invalid_event_date'], 422);
        }
        if (!is_array($bands) || ($venue !== null && !is_array($venue))) {
            json_out(['error' => 'invalid_package'], 422);
        }
        $items = 1 + ($venue !== null ? 1 : 0) + count($bands);
        if ($items > (int)cfg('ai_import.max_items_per_request')) {
            json_out(['error' => 'too_many_items', 'max' => (int)cfg('ai_import.max_items_per_request')], 422);
        }
        if (!function_exists('ai_import_package')) {
            json_out(['error' => 'not_implemented', 'message' => 'Zápis do pending se doplňuje v kroku 3.'], 501);
        }
        json_out((array)ai_import_package($data));
    }

    json_out(['error' => 'not_found'], 404);
}
