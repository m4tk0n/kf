<?php
declare(strict_types=1);

// Kluby: cesty k souborům, JSON s detaily, kontrola duplicit a koncept před odesláním.
// Slug se do DB neukládá. Počítá se za běhu (slugify) jen pro URL, názvy souborů a kontrolu duplicit.
// V DB je u klubu jen id, city_id, name; adresa, telefon, GPS a popis jsou v JSON.

// ---------- cesty a data ----------
function venue_bucket(int $id): int
{
    return intdiv($id, max(1, (int)cfg('img.folder_divisor')));
}

function venue_json_path(int $id): string
{
    return path('data', 'venue/' . venue_bucket($id) . '/' . $id . '.json');
}

/** Obsah JSON klubu: address, phone, gps{latitude,longitude}, desc_cs, desc_en. Chybí-li soubor, vrací []. */
function venue_data(int $id): array
{
    $file = venue_json_path($id);
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function venue_write_data(int $id, array $data): void
{
    $file = venue_json_path($id);
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create ' . $dir);
    }
    $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($file, $json, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write venue data');
    }
}

/** Popis v jazyce uživatele; není-li, tak ve výchozím jazyce, jinak v libovolném. */
function venue_description(array $data): string
{
    foreach ([lang_code(), cfg('app.default_lang')] as $lang) {
        $text = trim((string)($data['desc_' . $lang] ?? ''));
        if ($text !== '') {
            return $text;
        }
    }
    // Starší i aktuální payload z formuláře používá description + lang.
    $description = trim((string)($data['description'] ?? ''));
    if ($description !== '') {
        return $description;
    }
    foreach ($data as $key => $value) {
        if (str_starts_with((string)$key, 'desc_') && is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

/** Název souboru fotky: {id}_{slug}_{n}.webp (slug je jen kosmetický, hledá se podle {id}_). */
function venue_image_name(int $id, string $name, int $n): string
{
    return $id . '_' . slugify($name) . '_' . $n . '.webp';
}

/** URL fotek klubu z disku: img/venue/{id÷divisor}/{id}_*.webp, seřazené podle čísla na konci. */
function venue_image_urls(int $id): array
{
    $bucket = venue_bucket($id);
    $files = glob(path('img', 'venue/' . $bucket) . '/' . $id . '_*.webp') ?: [];
    sort($files, SORT_NATURAL);
    $base = rtrim(cfg('app.base_url'), '/') . '/img/venue/' . $bucket . '/';
    return array_map(static fn(string $f): string => $base . rawurlencode(basename($f)), $files);
}

/** Akce klubu rozdělené podle začátku; minulé od nejnovější, budoucí od nejbližší. */
function venue_events(int $venueId, bool $past): array
{
    $comparison = $past ? '<' : '>=';
    $direction = $past ? 'DESC' : 'ASC';
    $sql = 'SELECT e.id, e.name, e.type, e.starts_at, e.ends_at,
                   GROUP_CONCAT(b.name ORDER BY eb.pos, b.name SEPARATOR ", ") AS bands
            FROM ' . tbl('events') . ' e
            LEFT JOIN ' . tbl('event_bands') . ' eb ON eb.event_id = e.id
            LEFT JOIN ' . tbl('bands') . ' b ON b.id = eb.band_id
            WHERE e.venue_id = ? AND COALESCE(e.ends_at, e.starts_at) ' . $comparison . ' NOW()
            GROUP BY e.id, e.name, e.type, e.starts_at, e.ends_at
            ORDER BY e.starts_at ' . $direction . ', e.id ' . $direction;
    $q = db()->prepare($sql);
    $q->execute([$venueId]);
    return $q->fetchAll();
}

/** Klub pro detail: řádek z DB + JSON + fotky. null, když neexistuje. */
function venue_load(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $col = lang_code() === 'en' ? 'en' : 'cs';
    $q = db()->prepare(
        'SELECT v.id, v.name, c.' . $col . ' AS city, co.iso2 AS country_code
         FROM ' . tbl('venues') . ' v
         JOIN ' . tbl('cities') . ' c ON c.id = v.city_id
         JOIN ' . tbl('countries') . ' co ON co.id = c.country_id
         WHERE v.id = ? LIMIT 1'
    );
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) {
        return null;
    }
    $data = venue_data($id);
    $gps = is_array($data['gps'] ?? null) ? $data['gps'] : [];
    return [
        'id'           => (int)$row['id'],
        'name'         => (string)$row['name'],
        'city'         => (string)$row['city'],
        'country_code' => (string)$row['country_code'],
        'address'      => (string)($data['address'] ?? ''),
        'phone'        => (string)($data['phone'] ?? ''),
        'lat'          => isset($gps['latitude']) ? (float)$gps['latitude'] : null,
        'lng'          => isset($gps['longitude']) ? (float)$gps['longitude'] : null,
        'description'  => venue_description($data),
        'images'       => venue_image_urls($id),
        'past_events'  => venue_events($id, true),
        'future_events'=> venue_events($id, false),
    ];
}

// ---------- duplicity ----------
function venue_pending_type(): int
{
    return (int)(array_flip(cfg('enums.pending_type'))['venue'] ?? 0);
}

/** Klíč duplicity ve frontě: země + město + adresa (název se nepočítá, názvy se v jednom městě smí opakovat). */
function venue_dupkey(int $countryId, string $city, string $address): string
{
    return 'venue:' . $countryId . ':' . substr(sha1(slugify($city) . '|' . slugify($address)), 0, 32);
}

/**
 * Existuje už klub na stejné adrese ve stejném městě? Hledá se v hotových klubech
 * (adresa je v JSON, porovnává se slugify) i ve frontě ke schválení.
 * @return array{id:int,name:string}|null
 */
function venue_find_duplicate(int $countryId, string $city, string $address, int $ignorePendingId = 0): ?array
{
    $addrKey = slugify($address);
    if ($addrKey === '') {
        return null;
    }

    $q = db()->prepare('SELECT id FROM ' . tbl('cities') . ' WHERE country_id = ? AND (slug = ? OR cs = ? OR en = ?) LIMIT 1');
    $q->execute([$countryId, slugify($city), $city, $city]);
    $cityId = (int)$q->fetchColumn();
    if ($cityId > 0) {
        $q = db()->prepare('SELECT id, name FROM ' . tbl('venues') . ' WHERE city_id = ?');
        $q->execute([$cityId]);
        foreach ($q->fetchAll() as $row) {
            $data = venue_data((int)$row['id']);
            if (slugify((string)($data['address'] ?? '')) === $addrKey) {
                return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
            }
        }
    }

    $q = db()->prepare(
        'SELECT id, name, payload FROM ' . tbl('pending') . '
         WHERE type = ? AND country_id = ? AND city = ? AND id <> ?'
    );
    $q->execute([venue_pending_type(), $countryId, $city, $ignorePendingId]);
    foreach ($q->fetchAll() as $row) {
        $pendingData = json_decode((string)$row['payload'], true);
        if (is_array($pendingData) && slugify((string)($pendingData['address'] ?? '')) === $addrKey) {
            return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
    }
    return null;
}

// ---------- validace formuláře ----------
/** Hodnoty formuláře pro předvyplnění (jen skaláry, omezená délka). */
function venue_form_values(array $post): array
{
    $out = [];
    foreach (['name', 'country_display', 'country_id', 'city', 'address', 'phone', 'description', 'latitude', 'longitude', 'image_urls'] as $key) {
        $out[$key] = is_scalar($post[$key] ?? null) ? mb_substr((string)$post[$key], 0, 4000, 'UTF-8') : '';
    }
    return $out;
}

/**
 * Ověří formulář klubu. Vrací [fields, payload]; chyba = RuntimeException s přeloženou zprávou.
 * @return array{0:array,1:array}
 */
function venue_validate(array $post): array
{
    $s = static fn(string $key): string => is_scalar($post[$key] ?? null) ? trim((string)$post[$key]) : '';
    $len = static fn(string $v): int => mb_strlen($v, 'UTF-8');

    $name = $s('name');
    if ($name === '' || $len($name) > 120) {
        throw new RuntimeException(L('add-error-name'));
    }

    $countryId = (int)$s('country_id');
    $city = $s('city');
    $address = $s('address');
    $phone = $s('phone');
    $description = $s('description');
    if ($countryId < 1 || $city === '' || $address === '' ||
        $len($city) > 100 || $len($address) > 190 || $len($phone) > 40 || $len($description) > 3000) {
        throw new RuntimeException(L('add-error-venue-fields'));
    }

    $q = db()->prepare('SELECT id FROM ' . tbl('countries') . ' WHERE id = ? LIMIT 1');
    $q->execute([$countryId]);
    if ((int)$q->fetchColumn() < 1) {
        throw new RuntimeException(L('add-error-reference'));
    }

    $gps = null;
    $latRaw = $s('latitude');
    $lngRaw = $s('longitude');
    if ($latRaw !== '' || $lngRaw !== '') {
        if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
            throw new RuntimeException(L('add-error-gps'));
        }
        $lat = (float)$latRaw;
        $lng = (float)$lngRaw;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new RuntimeException(L('add-error-gps'));
        }
        $gps = ['latitude' => $lat, 'longitude' => $lng];
    }

    $images = [];
    foreach (preg_split('/\R/u', $s('image_urls')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = parse_url($line);
        if ($len($line) > 2048 || !is_array($parts) ||
            !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) ||
            empty($parts['host']) || preg_match('/[\r\n\s]/', $line)) {
            throw new RuntimeException(L('add-error-image-url'));
        }
        $images[] = ['type' => 'url', 'value' => $line];
        if (count($images) > 10) {
            throw new RuntimeException(L('add-error-image-count'));
        }
    }

    return [
        ['name' => $name, 'country_id' => $countryId, 'city' => $city],
        [
            'address'     => $address,
            'phone'       => $phone !== '' ? $phone : null,
            'description' => $description,
            'lang'        => lang_code(),
            'gps'         => $gps,
            'images'      => $images,
        ],
    ];
}

// ---------- nahrané fotky a koncept ----------
function venue_tmp_dir(string $token): string
{
    if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
        throw new RuntimeException('Invalid draft token');
    }
    return path('img', 'tmp/' . $token);
}

function venue_remove_dir(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($dir);
}

/** Úklid opuštěných konceptů (starších než 24 h); na hostingu bez cronu se volá při práci s formulářem. */
function venue_cleanup_tmp(): void
{
    foreach (glob(path('img', 'tmp') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (filemtime($dir) < time() - 86400) {
            venue_remove_dir($dir);
        }
    }
}

function venue_drop_draft(): void
{
    $draft = $_SESSION['add_draft'] ?? null;
    if (is_array($draft) && preg_match('/^[a-f0-9]{24}$/', (string)($draft['token'] ?? ''))) {
        venue_remove_dir(venue_tmp_dir((string)$draft['token']));
    }
    unset($_SESSION['add_draft']);
}

/**
 * Zmenší nahrané obrázky a uloží je jako WebP do $dir. Vrací názvy souborů.
 * Při chybě smaže, co už v tomto volání uložila, a vyhodí RuntimeException.
 * @return list<string>
 */
function venue_save_uploads(mixed $files, string $dir, int $remaining): array
{
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }
    $maxBytes = (int)cfg('img.max_bytes');
    $maxSide = (int)cfg('img.venue.l');
    $quality = (int)cfg('img.quality');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $saved = [];

    try {
        $incoming = 0;
        foreach (array_keys($files['name']) as $i) {
            $error = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (++$incoming > $remaining) {
                throw new RuntimeException(L('add-error-image-count'));
            }
            $tmp = (string)($files['tmp_name'][$i] ?? '');
            $size = (int)($files['size'][$i] ?? 0);
            if ($error !== UPLOAD_ERR_OK || $size < 1 || $size > $maxBytes || !is_uploaded_file($tmp)) {
                throw new RuntimeException(L('add-error-image-upload'));
            }
            if (!in_array($finfo->file($tmp), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                throw new RuntimeException(L('add-error-image-type'));
            }
            $raw = file_get_contents($tmp);
            // Rozměry se ověří před dekódováním, aby obří obrázek nevyčerpal paměť.
            $info = $raw !== false ? @getimagesizefromstring($raw) : false;
            if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 10000 || $info[1] > 10000 || $info[0] * $info[1] > 16000000) {
                throw new RuntimeException(L('add-error-image-type'));
            }
            $source = @imagecreatefromstring($raw);
            if (!$source) {
                throw new RuntimeException(L('add-error-image-type'));
            }
            $width = imagesx($source);
            $height = imagesy($source);
            $scale = min(1, $maxSide / max($width, $height));
            $newWidth = max(1, (int)round($width * $scale));
            $newHeight = max(1, (int)round($height * $scale));
            $target = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                imagedestroy($source);
                imagedestroy($target);
                throw new RuntimeException(L('add-error-image-upload'));
            }
            $name = bin2hex(random_bytes(12)) . '.webp';
            $ok = imagewebp($target, $dir . '/' . $name, $quality);
            imagedestroy($source);
            imagedestroy($target);
            if (!$ok) {
                throw new RuntimeException(L('add-error-image-upload'));
            }
            $saved[] = $name;
        }
    } catch (Throwable $e) {
        foreach ($saved as $name) {
            @unlink($dir . '/' . $name);
        }
        throw $e;
    }
    return $saved;
}

/** Data pro šablonu venue-detail z konceptu (stejný tvar jako venue_load). */
function venue_preview(array $draft): array
{
    $fields = (array)$draft['fields'];
    $payload = (array)$draft['payload'];
    $q = db()->prepare('SELECT iso2 FROM ' . tbl('countries') . ' WHERE id = ? LIMIT 1');
    $q->execute([(int)$fields['country_id']]);
    $gps = is_array($payload['gps'] ?? null) ? $payload['gps'] : [];
    $base = rtrim(cfg('app.base_url'), '/') . '/img/tmp/' . $draft['token'] . '/';
    $images = [];
    foreach ((array)($draft['files'] ?? []) as $file) {
        $images[] = $base . rawurlencode((string)$file);
    }
    return [
        'id'           => 0,
        'name'         => (string)$fields['name'],
        'city'         => (string)$fields['city'],
        'country_code' => (string)$q->fetchColumn(),
        'address'      => (string)($payload['address'] ?? ''),
        'phone'        => (string)($payload['phone'] ?? ''),
        'lat'          => isset($gps['latitude']) ? (float)$gps['latitude'] : null,
        'lng'          => isset($gps['longitude']) ? (float)$gps['longitude'] : null,
        'description'  => (string)($payload['description'] ?? ''),
        'images'       => $images,
    ];
}

/** Potvrzení náhledu: záznam do pending a přesun fotek z img/tmp do img/pending/venue/{id}. */
function venue_submit_draft(array $draft, int $userId): void
{
    $fields = (array)$draft['fields'];
    $payload = (array)$draft['payload'];
    $tmpDir = venue_tmp_dir((string)$draft['token']);

    // Mezi náhledem a potvrzením mohl stejný klub přidat někdo jiný.
    $dup = venue_find_duplicate((int)$fields['country_id'], (string)$fields['city'], (string)$payload['address']);
    if ($dup !== null) {
        throw new RuntimeException(L('add-error-duplicate', ['name' => $dup['name']]));
    }

    $files = [];
    foreach ((array)($draft['files'] ?? []) as $file) {
        if (is_string($file) && preg_match('/^[a-f0-9]{24}\.webp$/', $file) && is_file($tmpDir . '/' . $file)) {
            $files[] = $file;
        }
    }

    $pdo = db();
    $moved = [];
    $dir = null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO ' . tbl('pending') . ' (type, dupkey, user_id, name, country_id, city, payload) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            venue_pending_type(),
            venue_dupkey((int)$fields['country_id'], (string)$fields['city'], (string)$payload['address']),
            $userId,
            $fields['name'],
            (int)$fields['country_id'],
            $fields['city'],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $pendingId = (int)$pdo->lastInsertId();

        if ($files) {
            $dir = path('img', 'pending/venue/' . $pendingId);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException(L('add-error-image-upload'));
            }
            foreach ($files as $file) {
                if (!rename($tmpDir . '/' . $file, $dir . '/' . $file)) {
                    throw new RuntimeException(L('add-error-image-upload'));
                }
                $moved[$file] = $dir . '/' . $file;
                $payload['images'][] = ['type' => 'upload', 'value' => 'img/pending/venue/' . $pendingId . '/' . $file];
            }
            $pdo->prepare('UPDATE ' . tbl('pending') . ' SET payload = ? WHERE id = ?')->execute([
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $pendingId,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Fotky se vrátí do konceptu, aby je uživatel nemusel nahrávat znovu.
        foreach ($moved as $file => $absolute) {
            @rename($absolute, $tmpDir . '/' . $file);
        }
        if ($dir !== null && is_dir($dir)) {
            @rmdir($dir);
        }
        throw $e;
    }
    venue_remove_dir($tmpDir);
}
