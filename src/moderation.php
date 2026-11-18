<?php
declare(strict_types=1);

// Moderace: fronta `pending` -> hotové tabulky. Schválení i zamítnutí smaže řádek z pending.
// Klub: DB (id, city_id, name) + JSON (adresa, telefon, GPS, popis) + fotky {id}_{slug}_{n}.webp.

function mod_pending_rows(): array
{
    $rows = db()->query(
        'SELECT p.id, p.type, p.name, p.country_id, p.city, p.payload, u.display_name, u.email
         FROM ' . tbl('pending') . ' p
         LEFT JOIN ' . tbl('users') . ' u ON u.id = p.user_id
         ORDER BY p.id'
    )->fetchAll();
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

function mod_fetch(int $id): array
{
    $q = db()->prepare('SELECT id, type, name, country_id, city, payload FROM ' . tbl('pending') . ' WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row) {
        throw new RuntimeException(L('mod-error-missing'));
    }
    $decoded = json_decode((string)$row['payload'], true);
    $row['data'] = is_array($decoded) ? $decoded : [];
    $row['kind'] = (string)(cfg('enums.pending_type')[(int)$row['type']] ?? 'unknown');
    return $row;
}

function mod_reject(int $id): void
{
    $row = mod_fetch($id);
    db()->prepare('DELETE FROM ' . tbl('pending') . ' WHERE id = ?')->execute([$id]);
    if ($row['kind'] === 'venue') {
        venue_remove_dir(mod_pending_dir($id));
    }
}

function mod_approve(int $id): void
{
    $row = mod_fetch($id);
    match ($row['kind']) {
        'venue' => mod_approve_venue($row),
        'band' => mod_approve_band($row),
        'event' => mod_approve_event($row),
        default => throw new RuntimeException(L('mod-error-type')),
    };
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

        $pdo->prepare('INSERT INTO ' . tbl('venues') . ' (city_id, name) VALUES (?, ?)')->execute([$cityId, (string)$row['name']]);
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
