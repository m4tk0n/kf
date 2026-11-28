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

/** Kanonizuje název akce; je idempotentní a zachovává běžnou interpunkci včetně spojovníků. */
function kf_event_name_normalize(string $name): string
{
    $name = kf_text_normalize($name);
    $name = preg_replace('/\\s+/u', ' ', trim($name)) ?? trim($name);
    return mb_substr($name, 0, 160);
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
        'name' => kf_event_name_normalize(is_string($r['name'] ?? null) ? $r['name'] : ''), 'type' => $i('type'),
        'date_from' => $s('date_from', 10), 'date_to' => $s('date_to', 10), 'time_start' => $s('time_start', 5),
        'city_id' => $i('city_id'), 'city_name' => $s('city_name', 120), 'country_id' => $i('country_id'), 'venue_id' => $i('venue_id'),
        'description' => $s('description', 5000), 'www' => $s('www', 300),
        'admission_type' => in_array(($r['admission_type'] ?? ''), ['text', 'amount'], true) ? (string)$r['admission_type'] : '',
        'admission_value' => $s('admission_value', 100),
        'admission_currency' => strtoupper($s('admission_currency', 3)),
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
        $venueCity = q('SELECT c.id,c.name,c.country_id FROM ' . tbl('venues') . ' v JOIN ' . tbl('cities') . ' c ON c.id=v.city_id WHERE v.id=?', [$v['venue_id']])->fetch();
        if (!$venueCity) { $err[] = 'venue'; $v['venue_id'] = 0; }
        elseif ($v['city_id'] < 1 && $v['city_name'] === '') { $v['city_id']=(int)$venueCity['id'];$v['city_name']=(string)$venueCity['name'];$v['country_id']=(int)$venueCity['country_id']; }
    }
    if ($v['city_id'] > 0) {
        $city = q('SELECT name,country_id FROM ' . tbl('cities') . ' WHERE id=?', [$v['city_id']])->fetch();
        if (!$city) $err[]='city';
        else { $v['city_name']=(string)$city['name'];$v['country_id']=(int)$city['country_id']; }
    } elseif (!kf_form_valid_city($v['city_name']) || $v['country_id'] < 1 || !q('SELECT 1 FROM ' . tbl('countries') . ' WHERE id=?', [$v['country_id']])->fetchColumn()) {
        $err[] = 'city';
    }
    if ($v['www'] !== '' && !kf_form_valid_url($v['www'])) {
        $err[] = 'www';
    }
    if ($v['admission_type'] === 'text' && $v['admission_value'] === '') {
        $err[] = 'admission';
    } elseif ($v['admission_type'] === 'amount') {
        $v['admission_value'] = str_replace(',', '.', $v['admission_value']);
        if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $v['admission_value'])
            || !preg_match('/^[A-Z]{3}$/', $v['admission_currency'])
            || !q('SELECT 1 FROM ' . tbl('countries') . ' WHERE currency_code = ? LIMIT 1', [$v['admission_currency']])->fetchColumn()) {
            $err[] = 'admission';
        }
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
        'city_id' => $_POST['city_id'] ?? 0, 'city_name' => $_POST['city_name'] ?? '', 'country_id' => $_POST['country_id'] ?? 0, 'venue_id' => $_POST['venue_id'] ?? 0,
        'description' => $_POST['description'] ?? '', 'www' => $_POST['www'] ?? '',
        'admission_type' => $_POST['admission_type'] ?? '', 'admission_value' => $_POST['admission_value'] ?? '',
        'admission_currency' => $_POST['admission_currency'] ?? '', 'bands' => $bands,
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

/** Převod vstupného z formuláře do stabilní podoby ukládané v JSON akce. */
function kf_event_admission_json(array $v): ?array
{
    if (($v['admission_type'] ?? '') === 'text' && ($v['admission_value'] ?? '') !== '') {
        return ['type' => 'text', 'value' => (string)$v['admission_value']];
    }
    if (($v['admission_type'] ?? '') === 'amount' && is_numeric($v['admission_value'] ?? null)) {
        return ['type' => 'amount', 'value' => (float)$v['admission_value'], 'currency' => (string)$v['admission_currency']];
    }
    return null;
}

/** Názvy města, státu, klubu a kapel pro zobrazení (náhled, schvalování). */
function kf_event_view_data(array $v): array
{
    $c = $v['city_id']>0 ? q(
        'SELECT c.name AS city, co.iso2, co.`' . country_col() . '` AS country FROM ' . tbl('cities') . ' c JOIN ' . tbl('countries') . ' co ON co.id = c.country_id WHERE c.id = ?',
        [$v['city_id']]
    )->fetch() : false;
    if(!$c){$c=q('SELECT iso2, `'.country_col().'` AS country FROM '.tbl('countries').' WHERE id=?',[$v['country_id']])->fetch()?:['iso2'=>'','country'=>''];$c['city']=$v['city_name'];}
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
    $a = kf_text_normalize($v['name']);
    if (q('SELECT 1 FROM ' . tbl('events') . ' WHERE name COLLATE utf8mb4_unicode_ci = ? AND date_from = ? LIMIT 1', [$a, $v['date_from']])->fetchColumn()) {
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
    $id = 0;
    $base = '';
    $pdo->beginTransaction();
    try {
        $cityId = kf_city_resolve_or_create($v['city_id'], $v['city_name'], $v['country_id']);
        q(
            'INSERT INTO ' . tbl('events') . ' (name, type, date_from, date_to, time_start, city_id, venue_id, flyer, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['name'], $v['type'], $v['date_from'], $v['date_to'], $time, $cityId,
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
            'description' => $v['description'], 'www' => kf_safe_url($v['www']) ?? '',
        'admission' => kf_event_admission_json($v), 'flyer_l' => $srcL !== null,
        ], true);
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
        json_write_file($rel, ['kind' => 'event', 'values' => $v, 'flyer' => $srcL !== null], true);
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

function kf_event_form_page(array $v, array $errors, string $image, int $status = 200, array $options = []): never
{
    $cities = q(
        'SELECT c.id, c.name, co.`' . country_col() . '` AS country FROM ' . tbl('cities') . ' c JOIN ' . tbl('countries') . ' co ON co.id = c.country_id ORDER BY country, c.name'
    )->fetchAll();
    $venueName = '';
    if ((int)($v['venue_id'] ?? 0) > 0) {
        $venueName = (string)(q('SELECT name FROM ' . tbl('venues') . ' WHERE id = ?', [(int)$v['venue_id']])->fetchColumn() ?: '');
    }
    $v['venue_name'] = $venueName;
    if((int)$v['city_id']>0){$cityRow=q('SELECT name,country_id FROM '.tbl('cities').' WHERE id=?',[(int)$v['city_id']])->fetch();if($cityRow){$v['city_name']=(string)$cityRow['name'];$v['country_id']=(int)$cityRow['country_id'];}}
    $countryRow=$v['country_id']>0?q('SELECT `'.country_col().'` AS country,iso2,currency_code FROM '.tbl('countries').' WHERE id=?',[$v['country_id']])->fetch():false;
    $v['country_name']=(string)($countryRow['country']??'');$v['country_iso2']=(string)($countryRow['iso2']??'');
    $v['country_currency']=(string)($countryRow['currency_code']??'');
    if ($v['admission_currency'] === '' && $v['country_currency'] !== '') {
        $v['admission_currency'] = $v['country_currency'];
    }
    $countryCurrencies = q('SELECT id,currency_code FROM ' . tbl('countries'))->fetchAll();
    $currencies = q('SELECT DISTINCT currency_code FROM ' . tbl('countries') . ' WHERE currency_code IS NOT NULL AND currency_code <> "" ORDER BY currency_code')->fetchAll();
    $bands = q('SELECT id, name FROM ' . tbl('bands') . ' ORDER BY name')->fetchAll();
    page((string)($options['pageTitle'] ?? t('event.new.title')), 'event_form', [
        'old' => $v, 'errors' => $errors, 'cities' => $cities, 'bands' => $bands,
        'countryCurrencies' => $countryCurrencies, 'currencies' => $currencies,
        'imgUrl' => (string)($options['imgUrl'] ?? ($image !== '' ? url('event/draft-image') . '?v=' . time() : '')),
        'formAction' => (string)($options['formAction'] ?? url('event/new')),
        'pageTitle' => (string)($options['pageTitle'] ?? t('event.new.title')),
        'submitLabel' => (string)($options['submitLabel'] ?? t('event.btn.preview')),
        'backUrl' => (string)($options['backUrl'] ?? ''),
        'formId' => (string)($options['formId'] ?? ''),
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


/** JSON našeptávač již zveřejněných klubů a míst pro formulář akce. */
function route_venue_name_suggest(): never
{
    guard('submit');
    $term = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $min = max(1, (int)cfg('paging.autocomplete_venue_min_chars', 3));
    $limit = max(1, min(30, (int)cfg('paging.autocomplete_limit', 10)));
    $search = kf_text_normalize(mb_substr($term, 0, 100));

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (mb_strlen($search) < $min) {
        echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $rows = q(
        "SELECT v.id, v.name, v.address, c.id AS city_id, c.name AS city, co.id AS country_id, co.iso2 AS country, co.`" . country_col() . "` AS country_name FROM " . tbl('venues') . " v
         JOIN " . tbl('cities') . " c ON c.id = v.city_id
         JOIN " . tbl('countries') . " co ON co.id = c.country_id
         WHERE LOWER(REPLACE(REPLACE(REPLACE(v.name, '–', '-'), '—', '-'), '‑', '-')) COLLATE utf8mb4_unicode_ci LIKE ?
         ORDER BY v.name COLLATE utf8mb4_unicode_ci, v.name LIMIT " . $limit,
        [addcslashes($search, '%_\\\\') . '%']
    )->fetchAll();

    echo json_encode([
        'items' => array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'city_id' => (int)$row['city_id'],
            'city' => (string)$row['city'],
            'country_id' => (int)$row['country_id'],
            'country_name' => (string)$row['country_name'],
            'country' => (string)$row['country'],
            'address' => (string)($row['address'] ?? ''),
        ], $rows),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** JSON našeptávač názvů již zveřejněných akcí. */
function route_event_name_suggest(): never
{
    guard('submit');
    $term = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $min = max(1, (int)cfg('paging.autocomplete_min_chars', 2));
    $limit = max(1, min(30, (int)cfg('paging.autocomplete_limit', 10)));
    // Kolace MySQL porovnává název bez rozlišování velikosti písmen a diakritiky.
    $search = kf_text_normalize(mb_substr($term, 0, 100));
    $tokens = preg_split('/[\\s\\-–—‑]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $pattern = '';
    foreach ($tokens as $token) {
        $pattern .= addcslashes($token, '%_\\\\') . '%';
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (mb_strlen($search) < $min || $pattern === '') {
        echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $rows = q(
        "SELECT MIN(name) AS name FROM " . tbl('events') . " WHERE LOWER(REPLACE(REPLACE(REPLACE(name, '–', '-'), '—', '-'), '‑', '-')) COLLATE utf8mb4_unicode_ci LIKE ? GROUP BY LOWER(name) COLLATE utf8mb4_unicode_ci ORDER BY MIN(name) LIMIT " . $limit,
        [$pattern]
    )->fetchAll();
    echo json_encode(
        ['items' => array_values(array_map(static fn(array $row): string => (string)$row['name'], $rows))],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}
