<?php
declare(strict_types=1);

// Moderace: fronta `pending` -> hotové tabulky. Schválení i zamítnutí smaže řádek z pending.
// Klub: DB (id, city_id, name) + JSON (adresa, telefon, GPS, popis) + fotky {id}_{slug}_{n}.webp.

function mod_pending_counts(): array
{
    $counts = ['event' => 0, 'venue' => 0, 'band' => 0, 'venue_change' => (int)db()->query('SELECT COUNT(*) FROM ' . tbl('venue_changes'))->fetchColumn()];
    $rows = db()->query('SELECT type, COUNT(*) AS total FROM ' . tbl('pending') . ' GROUP BY type')->fetchAll();
    foreach ($rows as $row) {
        $kind = (string)(cfg('enums.pending_type')[(int)$row['type']] ?? '');
        if (isset($counts[$kind])) $counts[$kind] = (int)$row['total'];
    }
    return $counts;
}

function mod_pending_rows(?string $kind = null): array
{
    $sql = 'SELECT p.id, p.type, p.name, p.country_id, p.city, p.payload, u.display_name, u.email
            FROM ' . tbl('pending') . ' p
            LEFT JOIN ' . tbl('users') . ' u ON u.id = p.user_id';
    $args = [];
    if ($kind !== null) {
        $typeId = array_search($kind, cfg('enums.pending_type'), true);
        if ($kind === 'venue_change') {
            $q = db()->query('SELECT vc.id, vc.venue_id, vc.proposed_data AS payload, u.display_name, u.email FROM ' . tbl('venue_changes') . ' vc LEFT JOIN ' . tbl('users') . ' u ON u.id=vc.user_id ORDER BY vc.id DESC');
            $rows = $q->fetchAll();
            foreach ($rows as &$row) { $row['data'] = json_decode((string)$row['payload'], true) ?: []; $row['kind'] = 'venue_change'; }
            return $rows;
        }
        if ($typeId === false || !in_array($kind, ['event', 'venue', 'band'], true)) return [];
        $sql .= ' WHERE p.type = ?';
        $args[] = (int)$typeId;
    }
    $sql .= ' ORDER BY p.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $decoded = json_decode((string)$row['payload'], true);
        $row['data'] = is_array($decoded) ? $decoded : [];
        $row['kind'] = (string)(cfg('enums.pending_type')[(int)$row['type']] ?? 'unknown');
    }
    return $rows;
}

/** Data pro šablonu venue-detail z řádku fronty (fotky z img/pending/venue/{id}). */
function mod_venue_view(array $row): array
{
    $data = $row['data'];
    $gps = is_array($data['gps'] ?? null) ? $data['gps'] : [];
    $q = db()->prepare('SELECT iso2 FROM ' . tbl('countries') . ' WHERE id = ? LIMIT 1');
    $q->execute([(int)$row['country_id']]);
    $base = rtrim(cfg('app.base_url'), '/') . '/';
    $images = [];
    foreach ((array)($data['images'] ?? []) as $image) {
        if (($image['type'] ?? '') === 'upload') {
            $images[] = $base . implode('/', array_map('rawurlencode', explode('/', (string)$image['value'])));
        }
    }
    return [
        'id' => 0,
        'name' => (string)$row['name'],
        'city' => (string)$row['city'],
        'country_code' => (string)$q->fetchColumn(),
        'address' => (string)($data['address'] ?? ''),
        'phone' => (string)($data['phone'] ?? ''),
        'lat' => isset($gps['latitude']) ? (float)$gps['latitude'] : null,
        'lng' => isset($gps['longitude']) ? (float)$gps['longitude'] : null,
        'description' => (string)($data['description'] ?? ''),
        'images' => $images,
    ];
}

function mod_pending_dir(int $pendingId): string
{
    return path('img', 'pending/venue/' . $pendingId);
}

function mod_fetch(int $id, string $kind = ''): array
{
    if ($kind === 'venue_change') {
    $change = db()->prepare('SELECT vc.*, v.name AS current_name, c.country_id, c.' . (lang_code() === 'en' ? 'en' : 'cs') . ' AS current_city FROM ' . tbl('venue_changes') . ' vc JOIN ' . tbl('venues') . ' v ON v.id=vc.venue_id JOIN ' . tbl('cities') . ' c ON c.id=v.city_id WHERE vc.id=? LIMIT 1');
    $change->execute([$id]);
    $changeRow = $change->fetch();
    if ($changeRow) { $changeRow['data'] = json_decode((string)$changeRow['proposed_data'], true) ?: []; $changeRow['original'] = json_decode((string)$changeRow['original_data'], true) ?: []; $changeRow['kind'] = 'venue_change'; $changeRow['name'] = (string)($changeRow['data']['name'] ?? $changeRow['current_name']); $changeRow['city'] = (string)($changeRow['data']['city'] ?? $changeRow['current_city']); return $changeRow; }
    }
    $q = db()->prepare('SELECT id, type, name, country_id, city, payload FROM ' . tbl('pending') . ' WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) {
        throw new RuntimeException(L('mod-error-missing'));
    }
    $decoded = json_decode((string)$row['payload'], true);
    $row['data'] = is_array($decoded) ? $decoded : [];
    $row['name'] = trim((string)($row['name'] ?? '')) !== '' ? $row['name'] : (string)($row['data']['name'] ?? '');
    $row['kind'] = (string)(cfg('enums.pending_type')[(int)$row['type']] ?? 'unknown');
    return $row;
}

function mod_reject(int $id, string $kind = ''): void
{
    $row = mod_fetch($id, $kind);
    if ($row['kind'] === 'venue_change') { db()->prepare('DELETE FROM ' . tbl('venue_changes') . ' WHERE id=?')->execute([$id]); return; }
    db()->prepare('DELETE FROM ' . tbl('pending') . ' WHERE id = ?')->execute([$id]);
    if ($row['kind'] === 'venue') {
        venue_remove_dir(mod_pending_dir($id));
    }
}

function mod_approve(int $id, string $kind = ''): void
{
    $row = mod_fetch($id, $kind);
    match ($row['kind']) {
        'venue' => mod_approve_venue($row),
        'venue_change' => mod_approve_venue_change($row),
        'band' => mod_approve_band($row),
        'event' => mod_approve_event($row),
        default => throw new RuntimeException(L('mod-error-type')),
    };
}


function mod_approve_venue_change(array $row): void
{
    $data = $row['data']; $venueId = (int)$row['venue_id']; $pdo = db();
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT id FROM '.tbl('cities').' WHERE country_id=? AND (slug=? OR cs=? OR en=?) LIMIT 1');
        $q->execute([(int)$data['country_id'],slugify((string)$data['city']),(string)$data['city'],(string)$data['city']]);
        $cityId=(int)$q->fetchColumn();
        if($cityId<1){$slug=slugify((string)$data['city'])?:'city-'.substr(sha1((string)$data['city']),0,8);$pdo->prepare('INSERT INTO '.tbl('cities').' (country_id,slug,cs,en) VALUES (?,?,?,?)')->execute([(int)$data['country_id'],substr($slug,0,80),(string)$data['city'],(string)$data['city']]);$cityId=(int)$pdo->lastInsertId();}
        $pdo->prepare('UPDATE '.tbl('venues').' SET city_id=?,name=? WHERE id=?')->execute([$cityId,(string)$data['name'],$venueId]);
        $current=venue_data($venueId);$current['address']=(string)($data['address']??'');$current['phone']=(string)($data['phone']??'');
        if(is_array($data['gps']??null))$current['gps']=$data['gps'];else unset($current['gps']);
        foreach((array)($data['descriptions']??[]) as $code=>$description) if(in_array($code,['cs','en'],true))$current['desc_'.$code]=(string)$description;
        venue_write_data($venueId,$current);
        $pdo->prepare('DELETE FROM '.tbl('venue_changes').' WHERE id=?')->execute([(int)$row['id']]);$pdo->commit();
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function mod_update_venue_change(int $id, array $input): void
{
    $row=mod_fetch($id, 'venue_change');if($row['kind']!=='venue_change')throw new RuntimeException(L('mod-error-missing'));
    $name=trim((string)($input['name']??''));$city=trim((string)($input['city']??''));$countryId=(int)($input['country_id']??0);
    $address=trim((string)($input['address']??''));$phone=trim((string)($input['phone']??''));$description=trim((string)($input['description']??''));
    if($name===''||mb_strlen($name,'UTF-8')>120||$city===''||mb_strlen($city,'UTF-8')>100||$countryId<1||mb_strlen($address,'UTF-8')>190||mb_strlen($phone,'UTF-8')>40||mb_strlen($description,'UTF-8')>3000)throw new RuntimeException(L('add-error-venue-fields'));
    $q=db()->prepare('SELECT 1 FROM '.tbl('countries').' WHERE id=?');$q->execute([$countryId]);if(!$q->fetchColumn())throw new RuntimeException(L('add-error-reference'));
    $lat=trim((string)($input['latitude']??''));$lng=trim((string)($input['longitude']??''));
    if(($lat!==''||$lng!=='')&&($lat===''||$lng===''||!is_numeric($lat)||!is_numeric($lng)||(float)$lat < -90||(float)$lat>90||(float)$lng < -180||(float)$lng>180))throw new RuntimeException(L('add-error-gps'));
    $data=$row['data'];$data['name']=$name;$data['city']=$city;$data['country_id']=$countryId;$data['address']=$address;$data['phone']=$phone;
    $data['gps']=$lat!==''?['latitude'=>(float)$lat,'longitude'=>(float)$lng]:null;$data['description']=$description;
    $data['descriptions']=(array)($data['descriptions']??[]);$data['descriptions'][lang_code()]=$description;
    db()->prepare('UPDATE '.tbl('venue_changes').' SET proposed_data=? WHERE id=?')->execute([json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
}

/** Unikátní slug v tabulce: základ, pak základ-2, základ-3 … (délka se hlídá podle sloupce). */
function mod_unique_slug(string $table, string $base, int $maxLength): string
{
    $base = trim(substr($base, 0, $maxLength - 6), '-');
    if ($base === '') {
        $base = 'item';
    }
    $q = db()->prepare('SELECT 1 FROM ' . tbl($table) . ' WHERE slug = ? LIMIT 1');
    for ($n = 1; $n < 200; $n++) {
        $slug = $n === 1 ? $base : $base . '-' . $n;
        $q->execute([$slug]);
        if (!$q->fetchColumn()) {
            return $slug;
        }
    }
    throw new RuntimeException('Cannot allocate slug');
}

function mod_approve_venue(array $row): void
{
    $id = (int)$row['id'];
    $data = $row['data'];
    $address = (string)($data['address'] ?? '');
    $countryId = (int)$row['country_id'];
    $cityName = (string)$row['city'];

    $dup = venue_find_duplicate($countryId, $cityName, $address, $id);
    if ($dup !== null) {
        throw new RuntimeException(L('add-error-duplicate', ['name' => $dup['name']]));
    }

    $pdo = db();
    $moved = [];
    $jsonFile = null;
    $pdo->beginTransaction();
    try {
        // Město: existující, jinak nové (zadaný název do cs i en, moderátor ho může později opravit).
        $q = $pdo->prepare('SELECT id FROM ' . tbl('cities') . ' WHERE country_id = ? AND (slug = ? OR cs = ? OR en = ?) LIMIT 1');
        $q->execute([$countryId, slugify($cityName), $cityName, $cityName]);
        $cityId = (int)$q->fetchColumn();
        if ($cityId < 1) {
            $citySlug = slugify($cityName);
            if ($citySlug === '') {
                $citySlug = 'city-' . substr(sha1($cityName), 0, 8);
            }
            $pdo->prepare('INSERT INTO ' . tbl('cities') . ' (country_id, slug, cs, en) VALUES (?, ?, ?, ?)')
                ->execute([$countryId, substr($citySlug, 0, 80), $cityName, $cityName]);
            $cityId = (int)$pdo->lastInsertId();
        }

        // Current schema stores a venue slug; older installations may not have this column yet.
        $venueTable = tbl('venues');
        $slugColumn = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $slugColumn->execute([cfg('db.prefix') . 'venues', 'slug']);
        if ((int)$slugColumn->fetchColumn() > 0) {
            $baseSlug = slugify((string)$row['name']);
            if ($baseSlug === '') $baseSlug = 'venue-' . $id;
            $baseSlug = substr($baseSlug, 0, 80);
            $venueSlug = $baseSlug;
            $suffix = 2;
            $slugCheck = $pdo->prepare("SELECT 1 FROM {$venueTable} WHERE city_id = ? AND slug = ? LIMIT 1");
            while (true) {
                $slugCheck->execute([$cityId, $venueSlug]);
                if (!$slugCheck->fetchColumn()) break;
                $tail = '-' . $suffix++;
                $venueSlug = substr($baseSlug, 0, 80 - strlen($tail)) . $tail;
            }
            $pdo->prepare("INSERT INTO {$venueTable} (city_id, slug, name) VALUES (?, ?, ?)")
                ->execute([$cityId, $venueSlug, (string)$row['name']]);
        } else {
            $pdo->prepare("INSERT INTO {$venueTable} (city_id, name) VALUES (?, ?)")
                ->execute([$cityId, (string)$row['name']]);
        }
        $venueId = (int)$pdo->lastInsertId();

        // JSON: adresa, telefon, GPS a popis v jazyce, ve kterém ho uživatel napsal.
        $lang = in_array((string)($data['lang'] ?? ''), cfg('app.languages'), true) ? (string)$data['lang'] : cfg('app.default_lang');
        $json = ['address' => $address];
        if (!empty($data['phone'])) $json['phone'] = (string)$data['phone'];
        if (is_array($data['gps'] ?? null)) $json['gps'] = $data['gps'];
        if (trim((string)($data['description'] ?? '')) !== '') $json['desc_' . $lang] = trim((string)$data['description']);
        venue_write_data($venueId, $json);
        $jsonFile = venue_json_path($venueId);

        // Fotky: img/pending/venue/{pending}/x.webp -> img/venue/{id÷divisor}/{id}_{slug}_{n}.webp
        $targetDir = path('img', 'venue/' . venue_bucket($venueId));
        $n = 1;
        foreach ((array)($data['images'] ?? []) as $image) {
            if (($image['type'] ?? '') !== 'upload') {
                continue;
            }
            $file = basename((string)$image['value']);
            $source = mod_pending_dir($id) . '/' . $file;
            if (!preg_match('/^[a-f0-9]{24}\.webp$/', $file) || !is_file($source)) {
                continue;
            }
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException(L('add-error-image-upload'));
            }
            $target = $targetDir . '/' . venue_image_name($venueId, (string)$row['name'], $n);
            if (!rename($source, $target)) {
                throw new RuntimeException(L('add-error-image-upload'));
            }
            $moved[$target] = $source;
            $n++;
        }

        $pdo->prepare('DELETE FROM ' . tbl('pending') . ' WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($moved as $target => $source) {
            @rename($target, $source);
        }
        if ($jsonFile !== null) {
            @unlink($jsonFile);
        }
        throw $e;
    }
    venue_remove_dir(mod_pending_dir($id));
}

function mod_approve_band(array $row): void
{
    $data = $row['data'];
    $name = (string)($data['name'] ?? $row['name']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slug = mod_unique_slug('bands', slugify($name), 80);
        $pdo->prepare('INSERT INTO ' . tbl('bands') . ' (country_id, slug, name) VALUES (?, ?, ?)')
            ->execute([(int)$data['country_id'], $slug, $name]);
        $bandId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO ' . tbl('band_genres') . ' (band_id, genre_id) VALUES (?, ?)')
            ->execute([$bandId, (int)$data['genre_id']]);
        $pdo->prepare('DELETE FROM ' . tbl('pending') . ' WHERE id = ?')->execute([(int)$row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function mod_approve_event(array $row): void
{
    $data = $row['data'];
    $name = (string)($data['name'] ?? $row['name']);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slug = mod_unique_slug('events', slugify($name) . '-' . date('Ymd', strtotime((string)$data['starts_at'])), 140);
        $pdo->prepare('INSERT INTO ' . tbl('events') . ' (venue_id, type, slug, name, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([(int)$data['venue_id'], (int)$data['event_type'], $slug, $name, $data['starts_at'], $data['ends_at'] ?? null]);
        $pdo->prepare('DELETE FROM ' . tbl('pending') . ' WHERE id = ?')->execute([(int)$row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/** Uloží editované údaje návrhu ještě před schválením. */
function mod_update_pending(int $id, array $input): void
{
    $row = mod_fetch($id);
    $kind = $row['kind'];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name, 'UTF-8') > 120) throw new RuntimeException(L('add-error-name'));
    $data = $row['data'];
    $data['name'] = $name;

    if ($kind === 'venue') {
        $city = trim((string)($input['city'] ?? ''));
        $countryId = (int)($input['country_id'] ?? 0);
        $address = trim((string)($input['address'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        if ($city === '' || mb_strlen($city, 'UTF-8') > 100 || $countryId < 1 ||
            mb_strlen($address, 'UTF-8') > 190 || mb_strlen($phone, 'UTF-8') > 40 ||
            mb_strlen($description, 'UTF-8') > 3000) {
            throw new RuntimeException(L('add-error-venue-fields'));
        }
        $check = db()->prepare('SELECT 1 FROM ' . tbl('countries') . ' WHERE id = ?');
        $check->execute([$countryId]);
        if (!$check->fetchColumn()) throw new RuntimeException(L('add-error-reference'));
        $lat = trim((string)($input['latitude'] ?? ''));
        $lng = trim((string)($input['longitude'] ?? ''));
        if (($lat !== '' || $lng !== '') && ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng) ||
            (float)$lat < -90 || (float)$lat > 90 || (float)$lng < -180 || (float)$lng > 180)) {
            throw new RuntimeException(L('add-error-gps'));
        }
        $data['address'] = $address;
        $data['phone'] = $phone;
        $data['description'] = $description;
        $data['gps'] = $lat !== '' ? ['latitude' => (float)$lat, 'longitude' => (float)$lng] : null;
        $row['country_id'] = $countryId;
        $row['city'] = $city;
    } elseif ($kind === 'band') {
        $countryId = (int)($input['country_id'] ?? 0);
        $genreId = (int)($input['genre_id'] ?? 0);
        $check = db()->prepare('SELECT 1 FROM ' . tbl('countries') . ' WHERE id = ?');
        $check->execute([$countryId]);
        if (!$check->fetchColumn()) throw new RuntimeException(L('add-error-reference'));
        $check = db()->prepare('SELECT 1 FROM ' . tbl('genres') . ' WHERE id = ?');
        $check->execute([$genreId]);
        if (!$check->fetchColumn()) throw new RuntimeException(L('add-error-reference'));
        $data['country_id'] = $countryId;
        $data['genre_id'] = $genreId;
    } elseif ($kind === 'event') {
        $venueId = (int)($input['venue_id'] ?? 0);
        $eventType = (int)($input['event_type'] ?? 0);
        $startRaw = trim((string)($input['starts_at'] ?? ''));
        $endRaw = trim((string)($input['ends_at'] ?? ''));
        $start = DateTime::createFromFormat('Y-m-d\\TH:i', $startRaw);
        $end = $endRaw !== '' ? DateTime::createFromFormat('Y-m-d\\TH:i', $endRaw) : null;
        $check = db()->prepare('SELECT 1 FROM ' . tbl('venues') . ' WHERE id = ?');
        $check->execute([$venueId]);
        if (!$check->fetchColumn() || !in_array($eventType, [1,2,3], true) || !$start ||
            ($endRaw !== '' && (!$end || $end < $start))) throw new RuntimeException(L('add-error-event'));
        $data['venue_id'] = $venueId;
        $data['event_type'] = $eventType;
        $data['starts_at'] = $start->format('Y-m-d H:i:s');
        $data['ends_at'] = $end ? $end->format('Y-m-d H:i:s') : null;
    }

    $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($kind === 'venue') {
        db()->prepare('UPDATE ' . tbl('pending') . ' SET name=?, country_id=?, city=?, payload=? WHERE id=?')
            ->execute([$name, (int)$row['country_id'], (string)$row['city'], $payload, $id]);
    } else {
        db()->prepare('UPDATE ' . tbl('pending') . ' SET payload=? WHERE id=?')->execute([$payload, $id]);
    }
}
