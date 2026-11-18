<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/google_auth.php';

$base  = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/');
$uri   = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path  = trim(substr($uri, strlen($base)), '/');
$parts = $path === '' ? [] : explode('/', $path);
$langs = cfg('app.languages');

if ($path === 'auth/google/callback') {
    $returnLang = (string)($_SESSION['google_oauth_lang'] ?? cfg('app.default_lang'));
    lang_code(in_array($returnLang, $langs, true) ? $returnLang : cfg('app.default_lang'));
    require_google_auth('callback');
}

if (!$parts || !in_array($parts[0], $langs, true)) {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . rtrim(cfg('app.base_url'), '/') . '/' . detect_lang() . '/' . $path . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}

if (isset($_GET['setlang'])) {
    $rest = $_GET;
    unset($rest['setlang']);
    header('Location: ' . url(implode('/', array_slice($parts, 1)), $parts[0]) . ($rest ? '?' . http_build_query($rest) : ''), true, 302);
    exit;
}

lang_code($parts[0]);
$route = array_slice($parts, 1);
$routeName = implode('/', $route);
$GLOBALS['KF_ROUTE'] = $routeName;

// Autocomplete pro formulář přidání klubu.
if (in_array($routeName, ['suggest/venues', 'suggest/countries'], true)) {
    require_login();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $term = trim((string)($_GET['q'] ?? ''));
    $minimum = $routeName === 'suggest/venues' ? 3 : 2;
    if (mb_strlen($term, 'UTF-8') < $minimum || mb_strlen($term, 'UTF-8') > 80) {
        echo json_encode([]);
        exit;
    }
    if ($routeName === 'suggest/venues') {
        $stmt = db()->prepare('SELECT DISTINCT name FROM ' . tbl('venues') . ' WHERE name LIKE ? ORDER BY name LIMIT 10');
        $stmt->execute(['%' . $term . '%']);
        echo json_encode(array_map(static fn(array $row): array => ['value' => $row['name']], $stmt->fetchAll()), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $labelField = lang_code() === 'en' ? 'en' : 'cs';
    $stmt = db()->prepare('SELECT id, iso2, iso3, cs, en FROM ' . tbl('countries') . " WHERE iso2 LIKE ? OR iso3 LIKE ? OR {$labelField} LIKE ? ORDER BY {$labelField} LIMIT 12");
    $like = '%' . $term . '%';
    $stmt->execute([$like, $like, $like]);
    $countryRows = $stmt->fetchAll();
    echo json_encode(array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'iso3' => strtolower($row['iso3']),
        'label' => strtolower($row['iso2']) . '.' . strtolower($row['iso3']) . '.' . $row[$labelField],
    ], $countryRows), JSON_UNESCAPED_UNICODE);
    exit;
}

// Revalidate every authenticated request against the database. Any DB error fails closed.
$authenticatedUser = refresh_authenticated_user();
if ($authenticatedUser !== null) {
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
}
// Protected pages use one server-side identity check; page templates keep their own layout.
if (in_array($routeName, ['profile', 'settings', 'add'], true)) {
    $authenticatedUser = require_login();
}
if ($routeName === 'moderation') {
    require_permission('moderation');
}
if ($routeName === 'admin') {
    require_permission('administration');
}
if ((is_array($_SESSION['user'] ?? null) || $routeName === 'add') && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf(): void
{
    require_csrf_token();
}

if (in_array($routeName, ['logout', 'profile/save', 'account/delete-request', 'account/delete-cancel'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
    require_csrf();
    $currentUser = $_SESSION['user'] ?? null;
    if (!is_array($currentUser) || empty($currentUser['id'])) {
        header('Location: ' . url('login'), true, 303);
        exit;
    }

    if ($routeName === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $params['path'],
                'domain' => $params['domain'], 'secure' => $params['secure'],
                'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        header('Location: ' . url(''), true, 303);
        exit;
    }

    $users = tbl('users');
    $userId = (int)$currentUser['id'];
    if ($routeName === 'profile/save') {
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 80) {
            $_SESSION['profile_error'] = L('profile-name-invalid');
        } else {
            db()->prepare("UPDATE {$users} SET display_name = ? WHERE id = ?")->execute([$displayName, $userId]);
            $_SESSION['user']['name'] = $displayName;
            $_SESSION['profile_notice'] = L('profile-saved');
        }
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
    if ($routeName === 'account/delete-request') {
        db()->prepare("UPDATE {$users} SET deletion_scheduled_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ? AND deletion_scheduled_at IS NULL")->execute([$userId]);
        $_SESSION['user']['deletion_scheduled_at'] = true;
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
    if ($routeName === 'account/delete-cancel') {
        db()->prepare("UPDATE {$users} SET deletion_scheduled_at = NULL WHERE id = ?")->execute([$userId]);
        unset($_SESSION['user']['deletion_scheduled_at']);
        header('Location: ' . url('profile'), true, 303);
        exit;
    }
}


if ($routeName === 'add' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $user = require_role(1);
    $type = (string)($_POST['type'] ?? '');
    $typeIds = ['event' => 1, 'venue' => 2, 'band' => 3];
    if (!isset($typeIds[$type])) { http_response_code(400); exit('Invalid submission type'); }

    $name = trim((string)($_POST['name'] ?? ''));
    $error = $name === '' || mb_strlen($name, 'UTF-8') > 120 ? L('add-error-name') : '';
    $payload = ['name' => $name];
    $venueFields = null;
    $savedFiles = [];

    try {
        if ($error !== '') throw new RuntimeException($error);

        if ($type === 'band') {
            $q = db()->prepare('SELECT c.id country_id, g.id genre_id FROM ' . tbl('countries') . ' c CROSS JOIN ' . tbl('genres') . ' g WHERE c.iso2 = ? AND g.id = ?');
            $q->execute([strtoupper((string)($_POST['country'] ?? '')), (int)($_POST['genre'] ?? 0)]);
            $ref = $q->fetch();
            if (!$ref) throw new RuntimeException(L('add-error-reference'));
            $payload += ['country_id'=>(int)$ref['country_id'], 'genre_id'=>(int)$ref['genre_id']];
            $dupkey = 'band:' . slugify($name);
        } elseif ($type === 'venue') {
            $countryId = (int)($_POST['country_id'] ?? 0);
            $city = trim((string)($_POST['city'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $latitudeRaw = trim((string)($_POST['latitude'] ?? ''));
            $longitudeRaw = trim((string)($_POST['longitude'] ?? ''));

            if ($countryId < 1 || $city === '' ||
                mb_strlen($city, 'UTF-8') > 100 || mb_strlen($address, 'UTF-8') > 190 ||
                mb_strlen($phone, 'UTF-8') > 40 || mb_strlen($description, 'UTF-8') > 3000) {
                throw new RuntimeException(L('add-error-venue-fields'));
            }
            $countryQuery = db()->prepare('SELECT id FROM ' . tbl('countries') . ' WHERE id = ? LIMIT 1');
            $countryQuery->execute([$countryId]);
            $countryId = (int)$countryQuery->fetchColumn();
            if ($countryId < 1) throw new RuntimeException(L('add-error-reference'));

            $latitude = null;
            $longitude = null;
            if ($latitudeRaw !== '' || $longitudeRaw !== '') {
                if ($latitudeRaw === '' || $longitudeRaw === '' || !is_numeric($latitudeRaw) || !is_numeric($longitudeRaw)) {
                    throw new RuntimeException(L('add-error-gps'));
                }
                $latitude = (float)$latitudeRaw;
                $longitude = (float)$longitudeRaw;
                if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    throw new RuntimeException(L('add-error-gps'));
                }
            }

            $imageUrls = [];
            $urlsRaw = trim((string)($_POST['image_urls'] ?? ''));
            if ($urlsRaw !== '') {
                foreach (preg_split('/\\R/u', $urlsRaw) ?: [] as $imageUrl) {
                    $imageUrl = trim($imageUrl);
                    if ($imageUrl === '') continue;
                    $parts = parse_url($imageUrl);
                    if (mb_strlen($imageUrl, 'UTF-8') > 2048 || !is_array($parts) ||
                        !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) ||
                        empty($parts['host']) || preg_match('/[\\r\\n\\s]/', $imageUrl)) {
                        throw new RuntimeException(L('add-error-image-url'));
                    }
                    $imageUrls[] = ['type' => 'url', 'value' => $imageUrl];
                    if (count($imageUrls) > 10) throw new RuntimeException(L('add-error-image-count'));
                }
            }

            $payload = [
                'address' => $address !== '' ? $address : null,
                'phone' => $phone !== '' ? $phone : null,
                'description' => $description,
                'gps' => $latitude !== null ? ['latitude' => $latitude, 'longitude' => $longitude] : null,
                'images' => $imageUrls,
            ];
            $venueFields = [
                'name' => $name,
                'country_id' => $countryId,
                'city' => $city,
            ];
            $dupkey = 'venue:' . $countryId . ':' . slugify($city) . ':' . slugify($name);
        } else {
            $venue = (int)($_POST['venue_id'] ?? 0);
            $eventType = (int)($_POST['event_type'] ?? 1);
            $startRaw = (string)($_POST['starts_at'] ?? '');
            $endRaw = (string)($_POST['ends_at'] ?? '');
            $q = db()->prepare('SELECT id FROM ' . tbl('venues') . ' WHERE id=?');
            $q->execute([$venue]);
            $start = DateTime::createFromFormat('Y-m-d\\TH:i', $startRaw);
            $end = $endRaw !== '' ? DateTime::createFromFormat('Y-m-d\\TH:i', $endRaw) : null;
            if (!$q->fetchColumn() || !in_array($eventType,[1,2,3],true) || !$start || ($endRaw !== '' && (!$end || $end < $start))) throw new RuntimeException(L('add-error-event'));
            $payload += ['venue_id'=>$venue,'event_type'=>$eventType,'starts_at'=>$start->format('Y-m-d H:i:s'),'ends_at'=>$end ? $end->format('Y-m-d H:i:s') : null];
            $dupkey = 'event:' . slugify($name) . ':' . $start->format('YmdHi');
        }

        if ($type === 'venue') {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO ' . tbl('pending') . ' (type, dupkey, user_id, name, country_id, city, payload) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $typeIds[$type], $dupkey, (int)$user['id'], $venueFields['name'],
                $venueFields['country_id'], $venueFields['city'],
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pendingId = (int)$pdo->lastInsertId();

            $files = $_FILES['images'] ?? null;
            if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
                $fileCount = count($files['name']);
                if ($fileCount > 5) throw new RuntimeException(L('add-error-image-count'));
                $uploadDir = path('img', 'pending/venue/' . $pendingId);
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException(L('add-error-image-upload'));
                }
                $maxBytes = (int)cfg('img.max_bytes');
                $maxDimension = (int)cfg('img.venue.l');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                for ($i = 0; $i < $fileCount; $i++) {
                    $uploadError = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError === UPLOAD_ERR_NO_FILE) continue;
                    if ($uploadError !== UPLOAD_ERR_OK || (int)($files['size'][$i] ?? 0) < 1 ||
                        (int)$files['size'][$i] > $maxBytes || !is_uploaded_file((string)$files['tmp_name'][$i])) {
                        throw new RuntimeException(L('add-error-image-upload'));
                    }
                    $tmp = (string)$files['tmp_name'][$i];
                    $mime = $finfo->file($tmp);
                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                        throw new RuntimeException(L('add-error-image-type'));
                    }
                    $raw = file_get_contents($tmp);
                    $source = $raw !== false ? @imagecreatefromstring($raw) : false;
                    if (!$source) throw new RuntimeException(L('add-error-image-type'));
                    $width = imagesx($source);
                    $height = imagesy($source);
                    if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
                        imagedestroy($source);
                        throw new RuntimeException(L('add-error-image-type'));
                    }
                    $scale = min(1, $maxDimension / max($width, $height));
                    $newWidth = max(1, (int)round($width * $scale));
                    $newHeight = max(1, (int)round($height * $scale));
                    $target = imagecreatetruecolor($newWidth, $newHeight);
                    imagealphablending($target, false);
                    imagesavealpha($target, true);
                    imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    $filename = bin2hex(random_bytes(12)) . '.webp';
                    $absolute = $uploadDir . '/' . $filename;
                    $relative = 'img/pending/venue/' . $pendingId . '/' . $filename;
                    $saved = imagewebp($target, $absolute, (int)cfg('img.quality'));
                    imagedestroy($source);
                    imagedestroy($target);
                    if (!$saved) throw new RuntimeException(L('add-error-image-upload'));
                    $savedFiles[] = $absolute;
                    $payload['images'][] = ['type' => 'upload', 'value' => $relative];
                }
            }

            $update = $pdo->prepare('UPDATE ' . tbl('pending') . ' SET payload = ? WHERE id = ?');
            $update->execute([json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $pendingId]);
            $pdo->commit();
        } else {
            $stmt = db()->prepare('INSERT INTO ' . tbl('pending') . ' (type, dupkey, user_id, payload) VALUES (?, ?, ?, ?)');
            $stmt->execute([$typeIds[$type], $dupkey, (int)$user['id'], json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        }
        $_SESSION['add_success'] = L('add-submitted');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        foreach ($savedFiles as $file) if (is_file($file)) @unlink($file);
        $_SESSION['add_error'] = $e instanceof RuntimeException ? $e->getMessage() : L('add-error-save');
        if (!$e instanceof RuntimeException) error_log('K&F add submission: '.$e->getMessage());
    }
    header('Location: ' . url('add') . '?type=' . rawurlencode($type), true, 303);
    exit;
}

// Schvalování: přehled -> kategorie -> detail návrhu.
if ($routeName === 'moderation' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $kind = (string)($_POST['kind'] ?? '');
    try {
        if (!$id || $id < 1) throw new RuntimeException(L('mod-error-missing'));
        if ($action === 'save' || $action === 'approve') {
            mod_update_pending((int)$id, $_POST);
        }
        if ($action === 'approve') {
            mod_approve((int)$id);
            $_SESSION['moderation_notice'] = L('mod-approved');
        } elseif ($action === 'reject') {
            mod_reject((int)$id);
            $_SESSION['moderation_notice'] = L('mod-rejected');
        } elseif ($action === 'save') {
            $_SESSION['moderation_notice'] = L('mod-saved');
        } else {
            throw new RuntimeException(L('mod-error-action'));
        }
    } catch (Throwable $e) {
        $_SESSION['moderation_error'] = $e instanceof RuntimeException ? $e->getMessage() : L('mod-error-action');
        if (!$e instanceof RuntimeException) error_log('K&F moderation: ' . $e->getMessage());
    }
    $target = 'moderation';
    if (in_array($kind, ['event', 'venue', 'band'], true)) $target .= '?type=' . rawurlencode($kind);
    if (($action === 'save' || !empty($_SESSION['moderation_error'])) && $id) $target .= (str_contains($target, '?') ? '&' : '?') . 'id=' . (int)$id;
    header('Location: ' . url($target), true, 303);
    exit;
}


if ($routeName === 'moderation') {
    $moderationType = (string)($_GET['type'] ?? '');
    if (!in_array($moderationType, ['event', 'venue', 'band'], true)) $moderationType = '';
    $moderationId = max(0, (int)($_GET['id'] ?? 0));
    $moderationRow = null;
    if ($moderationId > 0) {
        try {
            $moderationRow = mod_fetch($moderationId);
            $moderationType = $moderationRow['kind'];
        } catch (Throwable $e) {
            $_SESSION['moderation_error'] = $e->getMessage();
        }
    }
    $moderationRows = $moderationType !== '' ? mod_pending_rows($moderationType) : [];
    $moderationCounts = mod_pending_counts();
    $moderationNotice = (string)($_SESSION['moderation_notice'] ?? '');
    $moderationError = (string)($_SESSION['moderation_error'] ?? '');
    unset($_SESSION['moderation_notice'], $_SESSION['moderation_error']);
    page('moderation', [
        'title' => L('moderation-title'),
        'rows' => $moderationRows,
        'counts' => $moderationCounts,
        'type' => $moderationType,
        'selected' => $moderationRow,
        'notice' => $moderationNotice,
        'error' => $moderationError,
    ]);
    exit;
}

match ($routeName) {
    ''      => page('home', ['title' => L('home-title')]),
    'login' => page('login', ['title' => L('login-title')]),
    'profile' => page('profile', ['title' => L('profile-title')]),
    'settings' => page('settings', ['title' => L('settings-title')]),
    'add' => page('add', ['title' => L('add-title')]),
    'moderation' => page('role-center', ['title' => L('moderation-title'), 'center' => 'moderation']),
    'admin' => page('role-center', ['title' => L('admin-title'), 'center' => 'admin']),
    'auth/google/start' => require_google_auth('start'),
    'auth/google/callback' => require_google_auth('callback'),
    default => page('404', ['title' => L('err-404')], 404),
};
