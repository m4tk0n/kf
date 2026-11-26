<?php
// src/events_add.php
declare(strict_types=1);

// Přidání akce ve dvou krocích: formulář -> náhled (draft v data/tmp) -> Odeslat (pending / přímá publikace) | Upravit | Zrušit.
// Publikaci sdílí přímé odeslání (trusted+) i schvalování (src/review.php).

// ---------- obrázky ----------

/** Dekóduje obrázek z bajtů; vrací GdImage, nebo kód chyby (string). */
function kf_img_decode(string $bin): GdImage|string
{
    if ($bin === '' || strlen($bin) > (int)cfg('img.max_bytes')) {
        return 'img_size';
    }
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info['mime'], (array)cfg('img.allowed_mime'), true)) {
        return 'img_type';
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > (int)cfg('img.max_pixels', 25000000)) {
        return 'img_dim';
    }
    $im = @imagecreatefromstring($bin);
    return $im instanceof GdImage ? $im : 'img_type';
}

/** Uloží zadané velikosti ('s', 'l') jako $absBase_<s|l>.webp (rozměry a kvalita z cfg img.thumb_*). */
function kf_img_write(GdImage $src, string $absBase, array $sizes = ['s', 'l']): void
{
    ensure_dir(dirname($absBase));
    $w = imagesx($src);
    $h = imagesy($src);
    foreach ($sizes as $size) {
        $c = (array)cfg('img.thumb_' . $size);
        $r = min(1.0, (int)$c['max_w'] / $w, (int)$c['max_h'] / $h);
        $nw = max(1, (int)round($w * $r));
        $nh = max(1, (int)round($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int)imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagewebp($dst, $absBase . '_' . $size . '.webp', (int)$c['quality']);
        imagedestroy($dst);
        if (!$ok) {
            throw new RuntimeException('Nelze uložit obrázek.');
        }
    }
}

/** Z velkého WebP ($srcL) vytvoří finální dvojici $destBase_l.webp (kopie) a $destBase_s.webp (náhled). */
function kf_img_install(string $srcL, string $destBase): void
{
    ensure_dir(dirname($destBase));
    if (!copy($srcL, $destBase . '_l.webp')) {
        throw new RuntimeException('Nelze zkopírovat obrázek.');
    }
    $im = @imagecreatefromwebp($srcL);
    if (!$im instanceof GdImage) {
        throw new RuntimeException('Nelze načíst obrázek.');
    }
    kf_img_write($im, $destBase, ['s']);
}

function kf_img_remove(string $absBase): void
{
    foreach (['s', 'l'] as $size) {
        @unlink($absBase . '_' . $size . '.webp');
    }
}

function kf_serve_webp(string $path): never
{
    header('Content-Type: image/webp');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
}

/** @return array{0:?GdImage,1:?string} [obrázek, kód chyby]; obojí null = nic nezadáno */
function kf_event_image_request(string $url): array
{
    $bin = '';
    $f = $_FILES['image'] ?? null;
    if (is_array($f) && (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $e = (int)$f['error'];
        if ($e === UPLOAD_ERR_INI_SIZE || $e === UPLOAD_ERR_FORM_SIZE) {
            return [null, 'img_size'];
        }
        if ($e !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
            return [null, 'img_type'];
        }
        $bin = (string)file_get_contents((string)$f['tmp_name']);
    } elseif ($url !== '') {
        try {
            $r = http_request($url, ['max_bytes' => (int)cfg('img.max_bytes')]);
            if ($r['status'] !== 200) {
                return [null, 'img_url'];
            }
            $bin = $r['body'];
        } catch (Throwable) {
            return [null, 'img_url'];
        }
    }
    if ($bin === '') {
        return [null, null];
    }
    $dec = kf_img_decode($bin);
    return $dec instanceof GdImage ? [$dec, null] : [null, $dec];
}

// ---------- draft (jeden soubor na uživatele + dočasný obrázek v data/tmp) ----------

function kf_draft_get(int $uid): ?array
{
    $d = json_read_file(draft_rel($uid));
    return isset($d['values']) && is_array($d['values']) ? $d : null;
}

function kf_draft_put(int $uid, array $values, string $image): void
{
    unset($values['image_url']);
    json_write_file(draft_rel($uid), ['values' => $values, 'image' => $image, 'ts' => time()]);
}

/** Absolutní cesta k velkému dočasnému obrázku; název se přísně ověřuje (nesmí vést mimo tmp_dir). */
function kf_draft_image_path(int $uid, string $name): ?string
{
    if (!preg_match('/^u' . $uid . '_[0-9a-f]{16}$/', $name)) {
        return null;
    }
    $p = abs_path((string)cfg('storage.tmp_dir')) . '/' . $name . '_l.webp';
    return is_file($p) ? $p : null;
}

function kf_draft_image_remove(int $uid, string $name): void
{
    $p = kf_draft_image_path($uid, $name);
    if ($p !== null) {
        @unlink($p);
    }
}

function kf_draft_clear(int $uid): void
{
    $d = kf_draft_get($uid);
    if ($d !== null) {
        kf_draft_image_remove($uid, (string)($d['image'] ?? ''));
    }
    @unlink(abs_path(draft_rel($uid)));
}

// ---------- hodnoty akce: čištění, validace ----------

/** Sjednotí typografické pomlčky v názvu na běžný spojovník. */
function kf_event_name_normalize(string $name): string
{
    return strtr($name, ['–' => '-', '—' => '-', '‑' => '-', '−' => '-']);
}

/** Z libovolného pole (POST, draft, pending JSON) vytvoří kanonickou, otypovanou a oříznutou podobu. */
function kf_event_clean(array $r): array
{
    $s = static fn(string $k, int $max): string => is_string($r[$k] ?? null) ? mb_substr(trim($r[$k]), 0, $max) : '';
    $i = static fn(string $k): int => is_scalar($r[$k] ?? null) ? (int)$r[$k] : 0;
    $bands = [];
    $seen = [];
    $max = max(0, (int)cfg('limits.event_bands_max', 10));
    foreach ((array)($r['bands'] ?? []) as $b) {
        if (!is_array($b)) {
            continue;
        }
        $id = is_scalar($b['id'] ?? null) ? (int)$b['id'] : 0;
        if ($id < 1 || isset($seen[$id])) {
            continue;
        }
        if (count($bands) >= $max) {
            break;
        }
        $seen[$id] = true;
        $bands[] = ['id' => $id, 'time' => is_string($b['time'] ?? null) ? mb_substr(trim($b['time']), 0, 5) : ''];
    }
    return [
        'name' => mb_substr(trim(kf_event_name_normalize(is_string($r['name'] ?? null) ? $r['name'] : '')), 0, 160), 'type' => $i('type'),
        'date_from' => $s('date_from', 10), 'date_to' => $s('date_to', 10), 'time_start' => $s('time_start', 5),
        'city_id' => $i('city_id'), 'venue_id' => $i('venue_id'),
        'description' => $s('description', 5000), 'www' => $s('www', 300),
        'bands' => $bands,
    ];
}

/** Ověří (a doplní: date_to, město z klubu) hodnoty; vrací kódy chyb. */
function kf_event_validate(array &$v): array
{
    $err = [];
    $validDate = static fn(string $d): bool => (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    $validTime = static fn(string $t): bool => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);

    if ($v['name'] === '') {
        $err[] = 'name';
    }
    if (!in_array($v['type'], array_map('intval', array_values((array)cfg('enums.event_type'))), true)) {
        $err[] = 'type';
    }
    if (!$validDate($v['date_from'])) {
        $err[] = 'date';
    } else {
        if ($v['date_to'] === '') {
            $v['date_to'] = $v['date_from'];
        }
        if (!$validDate($v['date_to']) || $v['date_to'] < $v['date_from']) {
            $err[] = 'date_range';
        }
    }
    if ($v['time_start'] !== '' && !$validTime($v['time_start'])) {
        $err[] = 'time';
    }
    if ($v['venue_id'] > 0) {
        $vc = q('SELECT city_id FROM ' . tbl('venues') . ' WHERE id = ?', [$v['venue_id']])->fetchColumn();
        if ($vc === false) {
            $err[] = 'venue';
            $v['venue_id'] = 0;
        } elseif ($v['city_id'] < 1) {
            $v['city_id'] = (int)$vc;
        }
    }
    if ($v['city_id'] < 1 || !q('SELECT 1 FROM ' . tbl('cities') . ' WHERE id = ?', [$v['city_id']])->fetchColumn()) {
        $err[] = 'city';
    }
    if ($v['www'] !== '' && kf_safe_url($v['www']) === null) {
        $err[] = 'www';
    }
    if ($v['bands']) {
        $ids = array_column($v['bands'], 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $found = q('SELECT COUNT(*) FROM ' . tbl('bands') . ' WHERE id IN (' . $in . ')', $ids)->fetchColumn();
        if ((int)$found !== count($ids)) {
            $err[] = 'band';
        }
        foreach ($v['bands'] as $b) {
            if ($b['time'] !== '' && !$validTime($b['time'])) {
                $err[] = 'band_time';
                break;
            }
        }
    }
    return array_values(array_unique($err));
}

/** Hodnoty z POST formuláře: [hodnoty (+ image_url), chyby]. */
function kf_event_input(): array
{
    $bands = [];
    $ids = $_POST['band_id'] ?? [];
    $times = $_POST['band_time'] ?? [];
    if (is_array($ids)) {
        foreach ($ids as $k => $id) {
            $bands[] = ['id' => $id, 'time' => is_array($times) ? ($times[$k] ?? '') : ''];
        }
    }
    $v = kf_event_clean([
        'name' => $_POST['name'] ?? '', 'type' => $_POST['type'] ?? 0,
        'date_from' => $_POST['date_from'] ?? '', 'date_to' => $_POST['date_to'] ?? '', 'time_start' => $_POST['time_start'] ?? '',
        'city_id' => $_POST['city_id'] ?? 0, 'venue_id' => $_POST['venue_id'] ?? 0,
        'description' => $_POST['description'] ?? '', 'www' => $_POST['www'] ?? '', 'bands' => $bands,
    ]);
    $err = kf_event_validate($v);
    $v['image_url'] = post_str('image_url', 300);
    return [$v, $err];
}

function kf_event_defaults(): array
{
    $v = kf_event_clean([]);
    $v['type'] = 1;
    $v['date_from'] = date('Y-m-d');
    $v['image_url'] = '';
    return $v;
}

/** Názvy města, státu, klubu a kapel pro zobrazení (náhled, schvalování). */
function kf_event_view_data(array $v): array
{
    $c = q(
        'SELECT c.name AS city, co.iso2, co.`' . country_col() . '` AS country FROM ' . tbl('cities') . ' c JOIN ' . tbl('countries') . ' co ON co.id = c.country_id WHERE c.id = ?',
        [$v['city_id']]
    )->fetch() ?: ['city' => '', 'iso2' => '', 'country' => ''];
    $venue = '';
    if ($v['venue_id'] > 0) {
        $venue = (string)q('SELECT name FROM ' . tbl('venues') . ' WHERE id = ?', [$v['venue_id']])->fetchColumn();
    }
    $bands = [];
    foreach ($v['bands'] as $b) {
        $n = q('SELECT name FROM ' . tbl('bands') . ' WHERE id = ?', [$b['id']])->fetchColumn();
        if ($n !== false) {
            $bands[] = ['name' => (string)$n, 'time' => $b['time']];
        }
    }
    return ['v' => $v, 'city' => (string)$c['city'], 'iso2' => (string)$c['iso2'], 'country' => (string)$c['country'], 'venue' => $venue, 'bands' => $bands];
}

function kf_event_duplicate(array $v, bool $inclPending): bool
{
    $a = kf_ascii($v['name']);
    if (q('SELECT 1 FROM ' . tbl('events') . ' WHERE name_ascii = ? AND date_from = ? LIMIT 1', [$a, $v['date_from']])->fetchColumn()) {
        return true;
    }
    return $inclPending && (bool)q(
        'SELECT 1 FROM ' . tbl('pending') . ' WHERE type = ? AND name_ascii = ? AND date_from = ? LIMIT 1',
        [(int)cfg('enums.pending_type.event'), $a, $v['date_from']]
    )->fetchColumn();
}

/** Limity čekajících návrhů a denní limit (jen pro role bez přímé publikace). */
function kf_event_limit_error(int $uid): ?string
{
    $pend = (int)q('SELECT COUNT(*) FROM ' . tbl('pending') . ' WHERE created_by = ? AND type = ?', [$uid, (int)cfg('enums.pending_type.event')])->fetchColumn();
    if ($pend >= (int)cfg('limits.pending_per_type.event')) {
        return 'limit_pending';
    }
    $today = (int)q('SELECT COUNT(*) FROM ' . tbl('pending') . ' WHERE created_by = ? AND created_at >= ?', [$uid, strtotime('today')])->fetchColumn();
    return $today >= (int)cfg('limits.submissions_per_day') ? 'limit_day' : null;
}

// ---------- uložení ----------

/** Publikuje akci: events + event_bands + JSON + obrázek (_l kopie, _s vygenerovaný). Vrací ID. */
function kf_event_publish(array $v, ?string $srcL, ?int $createdBy): int
{
    $v['name'] = kf_event_name_normalize((string)($v['name'] ?? ''));
    $pdo = db();
    $now = time();
    $time = $v['time_start'] !== '' ? $v['time_start'] . ':00' : null;
    $nameAscii = kf_ascii($v['name']);
    $slug = trim((string)preg_replace('~[^a-z0-9]+~', '-', $nameAscii), '-');
    $slug = substr($slug !== '' ? $slug : 'akce', 0, 60) . '-' . str_replace('-', '', $v['date_from']) . '-' . random_hex(3);
    $id = 0;
    $base = '';
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO ' . tbl('events') . ' (slug, name, name_ascii, type, date_from, date_to, time_start, city_id, venue_id, flyer, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$slug, $v['name'], $nameAscii, $v['type'], $v['date_from'], $v['date_to'], $time, $v['city_id'],
                $v['venue_id'] > 0 ? $v['venue_id'] : null, $srcL !== null ? 1 : 0, $createdBy, $now]
        );
        $id = (int)$pdo->lastInsertId();
        $n = 0;
        foreach ($v['bands'] as $b) {
            q(
                'INSERT INTO ' . tbl('event_bands') . ' (event_id, band_id, sort_order, time_play) VALUES (?, ?, ?, ?)',
                [$id, $b['id'], min(255, ++$n), $b['time'] !== '' ? $b['time'] . ':00' : null]
            );
        }
        json_write_file(json_bucket_rel('events', $id), [
            'description' => $v['description'], 'www' => kf_safe_url($v['www']) ?? '', 'flyer_l' => $srcL !== null,
        ]);
        if ($srcL !== null) {
            $base = abs_path((string)cfg('img.subdirs.events')) . '/' . intdiv($id, (int)cfg('img.folder_divisor')) . '/' . $id;
            kf_img_install($srcL, $base);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($id > 0) {
            json_remove('events', $id);
        }
        if ($base !== '') {
            kf_img_remove($base);
        }
        throw $e;
    }
}

/** Návrh do fronty pending: řádek v DB, JSON s hodnotami v data/pending/<bucket>/<id>.json, obrázek <id>_l.webp vedle. */
function kf_event_pending_create(array $v, ?string $srcL, int $uid): int
{
    $v['name'] = kf_event_name_normalize((string)($v['name'] ?? ''));
    $pdo = db();
    $id = 0;
    $img = '';
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO ' . tbl('pending') . ' (type, created_by, title, name_ascii, date_from, source_url, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)',
            [(int)cfg('enums.pending_type.event'), $uid, $v['name'], kf_ascii($v['name']), $v['date_from'], time()]
        );
        $id = (int)$pdo->lastInsertId();
        $rel = json_bucket_rel('pending', $id);
        json_write_file($rel, ['kind' => 'event', 'values' => $v, 'flyer' => $srcL !== null]);
        if ($srcL !== null) {
            $img = abs_path(dirname($rel)) . '/' . $id . '_l.webp';
            if (!copy($srcL, $img)) {
                throw new RuntimeException('Nelze uložit obrázek návrhu.');
            }
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($id > 0) {
            json_remove('pending', $id);
        }
        if ($img !== '') {
            @unlink($img);
        }
        throw $e;
    }
}

// ---------- stránky ----------

function kf_event_form_page(array $v, array $errors, string $image, int $status = 200): never
{
    $cities = q(
        'SELECT c.id, c.name, co.`' . country_col() . '` AS country FROM ' . tbl('cities') . ' c JOIN ' . tbl('countries') . ' co ON co.id = c.country_id ORDER BY country, c.name'
    )->fetchAll();
    $venues = q('SELECT v.id, v.name, c.name AS city FROM ' . tbl('venues') . ' v JOIN ' . tbl('cities') . ' c ON c.id = v.city_id ORDER BY v.name')->fetchAll();
    $bands = q('SELECT id, name FROM ' . tbl('bands') . ' ORDER BY name')->fetchAll();
    page(t('event.new.title'), 'event_form', [
        'old' => $v, 'errors' => $errors, 'cities' => $cities, 'venues' => $venues, 'bands' => $bands,
        'imgUrl' => $image !== '' ? url('event/draft-image') . '?v=' . time() : '',
        'maxBands' => max(1, (int)cfg('limits.event_bands_max', 10)),
    ], $status);
}

function route_event_new(): never
{
    $u = guard('submit');
    $d = kf_draft_get((int)$u['id']);
    if ($d === null) {
        kf_event_form_page(kf_event_defaults(), [], '');
    }
    $v = kf_event_clean((array)$d['values']);
    $v['image_url'] = '';
    $img = (string)($d['image'] ?? '');
    kf_event_form_page($v, [], kf_draft_image_path((int)$u['id'], $img) !== null ? $img : '');
}

/** Krok 1 -> 2: validace, uložení draftu a dočasného obrázku, přesměrování na náhled. */
function route_event_new_post(): never
{
    $u = guard('submit');
    $uid = (int)$u['id'];
    [$v, $err] = kf_event_input();
    $d = kf_draft_get($uid);
    $image = (string)($d['image'] ?? '');
    if (kf_draft_image_path($uid, $image) === null) {
        $image = '';
    }
    $newImg = null;
    if (!$err) {
        [$newImg, $e] = kf_event_image_request($v['image_url']);
        if ($e !== null) {
            $err[] = $e;
        }
    }
    if ($err) {
        kf_event_form_page($v, $err, $image, 422);
    }
    try {
        if ($newImg !== null) {
            kf_draft_image_remove($uid, $image);
            $image = 'u' . $uid . '_' . random_hex(8);
            kf_img_write($newImg, abs_path((string)cfg('storage.tmp_dir')) . '/' . $image, ['l']);
        } elseif ($image !== '' && post_str('remove_image', 1) === '1') {
            kf_draft_image_remove($uid, $image);
            $image = '';
        }
        kf_draft_put($uid, $v, $image);
    } catch (Throwable $e) {
        error_log('event_draft: ' . $e->getMessage());
        kf_event_form_page($v, ['save'], '', 500);
    }
    redirect('event/preview');
}

function route_event_preview(): never
{
    $u = guard('submit');
    $uid = (int)$u['id'];
    $d = kf_draft_get($uid);
    if ($d === null) {
        redirect('event/new');
    }
    $v = kf_event_clean((array)$d['values']);
    $img = (string)($d['image'] ?? '');
    page(t('event.preview.title'), 'event_preview', [
        'ev' => kf_event_view_data($v),
        'imgUrl' => kf_draft_image_path($uid, $img) !== null ? url('event/draft-image') . '?v=' . (int)($d['ts'] ?? 0) : '',
        'direct' => can('publish_direct'),
    ]);
}

function route_event_draft_image(): never
{
    $u = guard('submit');
    $d = kf_draft_get((int)$u['id']);
    $p = $d !== null ? kf_draft_image_path((int)$u['id'], (string)($d['image'] ?? '')) : null;
    if ($p === null) {
        abort(404, t('error.404'));
    }
    kf_serve_webp($p);
}

/** Krok 3: Odeslat. Trusted+ publikuje rovnou, ostatní vytvoří návrh v pending. */
function route_event_submit(): never
{
    $u = guard('submit');
    $uid = (int)$u['id'];
    $direct = can('publish_direct');
    $d = kf_draft_get($uid);
    if ($d === null) {
        redirect('event/new');
    }
    $v = kf_event_clean((array)$d['values']);
    if (kf_event_validate($v)) {
        redirect('event/new?m=invalid');
    }
    if (!$direct && ($lim = kf_event_limit_error($uid)) !== null) {
        redirect('event/preview?m=event_' . $lim);
    }
    if (kf_event_duplicate($v, !$direct)) {
        redirect('event/preview?m=event_duplicate');
    }
    $img = (string)($d['image'] ?? '');
    $src = kf_draft_image_path($uid, $img);
    try {
        if ($direct) {
            $id = kf_event_publish($v, $src, $uid);
        } else {
            kf_event_pending_create($v, $src, $uid);
        }
    } catch (Throwable $e) {
        error_log('event_submit: ' . $e->getMessage());
        redirect('event/preview?m=save_failed');
    }
    kf_draft_clear($uid);
    redirect($direct ? '?event=' . $id . '&m=event_saved' : '?m=event_submitted');
}

function route_event_cancel(): never
{
    $u = guard('submit');
    kf_draft_clear((int)$u['id']);
    redirect('event/new?m=event_cancelled');
}


/** JSON našeptávač názvů již zveřejněných akcí. */
function route_event_name_suggest(): never
{
    guard('submit');
    $term = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $min = max(1, (int)cfg('paging.autocomplete_min_chars', 2));
    $limit = max(1, min(30, (int)cfg('paging.autocomplete_limit', 10)));
    $ascii = kf_ascii(mb_substr($term, 0, 100));
    // Různé typografické pomlčky bereme jako běžný spojovník; ostatní interpunkci zachováváme.
    $ascii = strtr($ascii, ['–' => '-', '—' => '-', '‑' => '-', '−' => '-']);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (mb_strlen($ascii) < $min) {
        echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $rows = q(
        'SELECT MIN(name) AS name FROM ' . tbl('events') . ' WHERE LOCATE(?, LOWER(REPLACE(REPLACE(REPLACE(name_ascii, ?, ?), ?, ?), ?, ?))) = 1 GROUP BY LOWER(REPLACE(REPLACE(REPLACE(name_ascii, ?, ?), ?, ?), ?, ?)) ORDER BY LOWER(REPLACE(REPLACE(REPLACE(name_ascii, ?, ?), ?, ?), ?, ?)) LIMIT ' . $limit,
        [strtolower($ascii), '–', '-', '—', '-', '‑', '-', '–', '-', '—', '-', '‑', '-', '–', '-', '—', '-', '‑', '-']
    )->fetchAll();
    echo json_encode(
        ['items' => array_values(array_map(static fn(array $row): string => (string)$row['name'], $rows))],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}
