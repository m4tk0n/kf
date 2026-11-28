<?php
// src/detail.php
declare(strict_types=1);

/** Typ v URL (?event=ID, ?band=ID, ?venue=ID) => tabulka a JSON bucket. */
const KF_DETAIL_KINDS = ['event' => 'events', 'band' => 'bands', 'venue' => 'venues'];

function kf_json(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Jen http(s) adresa bez mezer a znaků, které by rozbily atribut. */
function kf_safe_url(string $u): ?string
{
    $u = trim($u);
    return $u !== '' && strlen($u) <= 300 && preg_match('~^https?://[^\s<>"\']+$~i', $u) ? $u : null;
}

function kf_jtext(array $j, string $key, int $max): string
{
    $v = $j[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

/** @return array{0:string,1:int}|null [typ, id] z ?event= / ?band= / ?venue= */
function kf_detail_request(): ?array
{
    foreach (array_keys(KF_DETAIL_KINDS) as $kind) {
        $v = kf_get($kind);
        if ($v !== '' && preg_match('/^\d{1,8}$/', $v) && (int)$v > 0) {
            return [$kind, (int)$v];
        }
    }
    return null;
}

/** Detailní data z JSON souboru (data/<bucket>/<id div divisor>/<id>.json), vše ověřené a oříznuté. */
function kf_detail_extra(string $kind, int $id): array
{
    $j = json_read_file(json_bucket_rel(KF_DETAIL_KINDS[$kind], $id));
    $email = kf_jtext($j, 'email', 120);
    $flag = ['event' => 'flyer_l', 'band' => 'image_l', 'venue' => 'img_l'][$kind];
    $lat = $j['lat'] ?? null;
    $lng = $j['lng'] ?? null;
    $geo = is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 90 && abs((float)$lng) <= 180
        ? [(float)$lat, (float)$lng] : null;
    $admission = $j['admission'] ?? null;
    $price = '';
    if ($kind === 'event' && is_array($admission)) {
        if (($admission['type'] ?? '') === 'text' && is_string($admission['value'] ?? null)) {
            $price = mb_substr(trim($admission['value']), 0, 100);
        } elseif (($admission['type'] ?? '') === 'amount' && is_numeric($admission['value'] ?? null)
            && preg_match('/^[A-Z]{3}$/', (string)($admission['currency'] ?? ''))) {
            $amount = rtrim(rtrim(number_format((float)$admission['value'], 2, '.', ''), '0'), '.');
            $price = $amount . ' ' . $admission['currency'];
        }
    }
    if ($price === '' && $kind === 'event') {
        $price = kf_jtext($j, 'price', 100);
    }
    return [
        'desc' => kf_jtext($j, 'description', 5000),
        'price' => $price,
        'www' => kf_safe_url(kf_jtext($j, 'www', 300)),
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
        'phone' => trim((string)preg_replace('/[^0-9+() \-]/', '', kf_jtext($j, 'phone', 30))),
        'address' => kf_jtext($j, 'address', 200),
        'geo' => $kind === 'venue' ? $geo : null,
        'google_maps' => $kind === 'venue' ? (kf_safe_url(kf_jtext($j, 'google_maps', 500)) ?? '') : '',
        'mapy_maps' => $kind === 'venue' ? (kf_safe_url(kf_jtext($j, 'mapy_maps', 500)) ?? '') : '',
        'large' => $kind === 'band' ? (!empty($j['image_l']) || !empty($j['logo_l'])) : !empty($j[$flag]),
        'facebook' => $kind === 'band' ? (kf_safe_url(kf_jtext($j, 'facebook', 300)) ?? '') : '',
        'bandzone' => $kind === 'band' ? (kf_safe_url(kf_jtext($j, 'bandzone', 300)) ?? '') : '',
    ];
}

/** Načte detail z DB + JSON; null = záznam neexistuje. */
function kf_detail_load(string $kind, int $id): ?array
{
    if (!isset(KF_DETAIL_KINDS[$kind])) {
        return null;
    }
    $col = 'co.`name_' . current_lang() . '`'; // jazyk je ověřený proti app.supported_langs
    $geo = ' JOIN ' . tbl('cities') . ' c ON c.id = a.city_id JOIN ' . tbl('countries') . ' co ON co.id = c.country_id';
    $today = date('Y-m-d');
    $lim = max(1, min((int)cfg('paging.detail_events'), (int)cfg('paging.max_per_page')));
    $d = ['kind' => $kind, 'id' => $id, 'bucket' => KF_DETAIL_KINDS[$kind], 'bands' => [], 'upcoming' => [], 'past' => []];

    if ($kind === 'event') {
        $row = q(
            'SELECT a.id, a.name, a.type, a.date_from, a.date_to, a.time_start, a.venue_id, c.name AS city, co.iso2, '
            . $col . ' AS country, v.name AS venue FROM ' . tbl('events') . ' a' . $geo
            . ' LEFT JOIN ' . tbl('venues') . ' v ON v.id = a.venue_id WHERE a.id = ?',
            [$id]
        )->fetch();
        if (!$row) {
            return null;
        }
        $d['bands'] = q(
            'SELECT b.id, b.name, eb.time_play FROM ' . tbl('event_bands') . ' eb JOIN ' . tbl('bands') . ' b ON b.id = eb.band_id'
            . ' WHERE eb.event_id = ? ORDER BY eb.sort_order, b.name',
            [$id]
        )->fetchAll();
    } elseif ($kind === 'band') {
        $row = q(
            'SELECT a.id, a.name, c.name AS city, co.iso2, ' . $col . ' AS country, g.name AS genre FROM ' . tbl('bands') . ' a' . $geo
            . ' LEFT JOIN ' . tbl('genres') . ' g ON g.id = a.genre_id WHERE a.id = ?',
            [$id]
        )->fetch();
        if (!$row) {
            return null;
        }
        $ev = 'SELECT e.id, e.name, e.date_from, e.date_to FROM ' . tbl('event_bands') . ' eb JOIN ' . tbl('events')
            . ' e ON e.id = eb.event_id WHERE eb.band_id = ? AND ';
        $d['upcoming'] = q($ev . 'e.date_to >= ? ORDER BY e.date_from, e.id LIMIT ' . $lim, [$id, $today])->fetchAll();
        $d['past'] = q($ev . 'e.date_to < ? ORDER BY e.date_from DESC, e.id DESC LIMIT ' . $lim, [$id, $today])->fetchAll();
    } else {
        $row = q(
            'SELECT a.id, a.name, c.name AS city, co.iso2, ' . $col . ' AS country FROM ' . tbl('venues') . ' a' . $geo . ' WHERE a.id = ?',
            [$id]
        )->fetch();
        if (!$row) {
            return null;
        }
        $ev = 'SELECT id, name, date_from, date_to FROM ' . tbl('events') . ' WHERE venue_id = ? AND ';
        $d['upcoming'] = q($ev . 'date_to >= ? ORDER BY date_from, id LIMIT ' . $lim, [$id, $today])->fetchAll();
        $d['past'] = q($ev . 'date_to < ? ORDER BY date_from DESC, id DESC LIMIT ' . $lim, [$id, $today])->fetchAll();
    }

    $u = auth_resolve();
    $d['row'] = $row;
    $d['extra'] = kf_detail_extra($kind, $id);
    $d['fav'] = $u !== null && fav_is((int)$u['id'], $kind, $id);
    // Návrat vede na přesný výpis, ze kterého byl detail otevřen.
    // U přímého odkazu bez uloženého výpisu zůstává datum akce jako rozumný fallback.
    $d['back'] = kf_detail_return_url($kind === 'event' ? (string)$row['date_from'] : '');
    return $d;
}

function kf_detail_page(string $kind, int $id): never
{
    $d = kf_detail_load($kind, $id);
    if ($d === null) {
        abort(404, t('error.404'));
    }
    if (auth_resolve() !== null) {
        header('Cache-Control: no-store'); // hvězdička vychází ze stavu v DB
    }
    $expectedSlug = kf_detail_slug($kind, $d['row']);
    if (kf_get('slug') !== $expectedSlug) {
        header('Location: ' . kf_detail_url($kind, $id, $d['row']), true, 301);
        exit;
    }
    page((string)$d['row']['name'], 'detail', ['d' => $d, 'section' => KF_DETAIL_KINDS[$kind]]);
}

// ---------- oblíbené ----------

function fav_table(string $kind): string
{
    return tbl('fav_' . KF_DETAIL_KINDS[$kind]);
}

function fav_is(int $uid, string $kind, int $id): bool
{
    if (!isset(KF_DETAIL_KINDS[$kind])) {
        return false;
    }
    return (bool)q('SELECT 1 FROM ' . fav_table($kind) . ' WHERE user_id = ? AND id = ?', [$uid, $id])->fetchColumn();
}

/** Množina (id => true) oblíbených mezi zadanými id; jeden dotaz pro celou stránku výpisu. */
function fav_ids_in(int $uid, string $kind, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0));
    if (!$ids || !isset(KF_DETAIL_KINDS[$kind])) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (q('SELECT id FROM ' . fav_table($kind) . ' WHERE user_id = ? AND id IN (' . $in . ')', array_merge([$uid], $ids))->fetchAll(PDO::FETCH_COLUMN) as $i) {
        $out[(int)$i] = true;
    }
    return $out;
}

/** Tlačítko hvězdičky (výpis i detail); obsluhuje ho app.js (POST fav, bez obnovení stránky). */
function kf_fav_button(string $kind, int $id, bool $on, string $extra = ''): string
{
    $label = t($on ? 'detail.fav_remove' : 'detail.fav_add');
    return '<button type="button" class="fav' . ($extra !== '' ? ' ' . e($extra) : '') . ($on ? ' on' : '') . '" data-fav'
        . ' data-url="' . e(url('fav')) . '" data-csrf-field="' . e((string)cfg('security.csrf_field')) . '" data-csrf="' . e(csrf_token()) . '"'
        . ' data-kind="' . e($kind) . '" data-id="' . $id . '" aria-pressed="' . ($on ? 'true' : 'false') . '"'
        . ' data-label-on="' . e(t('detail.fav_remove')) . '" data-label-off="' . e(t('detail.fav_add')) . '"'
        . ' data-msg-limit="' . e(t('detail.fav_limit')) . '" data-msg-error="' . e(t('detail.fav_error')) . '"'
        . ' aria-label="' . e($label) . '" title="' . e($label) . '">★</button>';
}

function fav_count(int $uid, string $kind): int
{
    if (!isset(KF_DETAIL_KINDS[$kind])) {
        return 0;
    }
    return (int)q('SELECT COUNT(*) FROM ' . fav_table($kind) . ' WHERE user_id = ?', [$uid])->fetchColumn();
}

/** Idempotentní přidání/odebrání. @return string ok | invalid | missing | limit */
function fav_set(int $uid, string $kind, int $id, bool $on): string
{
    if (!isset(KF_DETAIL_KINDS[$kind]) || $id < 1) {
        return 'invalid';
    }
    if (!q('SELECT 1 FROM ' . tbl(KF_DETAIL_KINDS[$kind]) . ' WHERE id = ?', [$id])->fetchColumn()) {
        return 'missing';
    }
    $fav = fav_table($kind);
    if (!$on) {
        q('DELETE FROM ' . $fav . ' WHERE user_id = ? AND id = ?', [$uid, $id]);
        return 'ok';
    }
    if (!fav_is($uid, $kind, $id)) {
        $n = (int)q('SELECT COUNT(*) FROM ' . $fav . ' WHERE user_id = ?', [$uid])->fetchColumn();
        if ($n >= (int)cfg('limits.favorites_max')) {
            return 'limit';
        }
        q('INSERT IGNORE INTO ' . $fav . ' (user_id, id) VALUES (?, ?)', [$uid, $id]);
    }
    return 'ok';
}

/** POST fav (AJAX): kind, id, op = add|remove. CSRF a rate-limit řeší kf_dispatch(). */
function route_fav(): never
{
    $u = auth_resolve();
    if ($u === null) {
        security_log('forbidden', ['action' => 'fav']);
        kf_json(['ok' => false, 'error' => 'login'], 403);
    }
    if (!can('favorite')) {
        security_log('forbidden', ['action' => 'fav_restricted']);
        kf_json(['ok' => false, 'error' => 'restricted'], 403);
    }
    $kind = post_str('kind', 10);
    $id = (int)post_str('id', 10);
    $op = post_str('op', 10);
    if (!isset(KF_DETAIL_KINDS[$kind]) || !in_array($op, ['add', 'remove'], true)) {
        kf_json(['ok' => false, 'error' => 'invalid'], 400);
    }
    $res = fav_set((int)$u['id'], $kind, $id, $op === 'add');
    if ($res !== 'ok') {
        kf_json(['ok' => false, 'error' => $res], ['invalid' => 400, 'missing' => 404, 'limit' => 409][$res] ?? 400);
    }
    kf_json(['ok' => true, 'on' => fav_is((int)$u['id'], $kind, $id)]);
}
