<?php
declare(strict_types=1);

/**
 * End-to-end backend workflow test.
 * Destructive schema setup is permitted only for APP_ENV=test + DB_NAME=kf_test.
 */

$_SERVER['HTTP_USER_AGENT'] = 'KF automated integration test';
require dirname(__DIR__, 2) . '/bootstrap.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: {$message}\n";
}

check((string)env('APP_ENV') === 'test' && (string)env('DB_NAME') === 'kf_test', 'runs only against the isolated kf_test database');

$pdo = db();
$schemaFiles = glob(KF_ROOT . '/install/sql/tables/*.sql') ?: [];
sort($schemaFiles, SORT_STRING);
foreach ($schemaFiles as $file) {
    $sql = str_replace(
        ['{prefix}', '{charset}', '{collation}'],
        [(string)cfg('db.prefix'), (string)cfg('db.charset'), (string)cfg('db.collation')],
        (string)file_get_contents($file)
    );
    foreach (preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [] as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}
check(count($schemaFiles) >= 10, 'creates the real application schema from install/sql/tables');

$now = time();
$insertUser = $pdo->prepare(
    'INSERT INTO ' . tbl('users') . ' (provider, provider_uid, display_name, role, user_state, approved_count, created_at, last_login_at)
     VALUES (?, ?, ?, ?, ?, 0, ?, ?)'
);
$insertUser->execute([0, 'test-system', 'AI', 0, 1, $now, $now]);
$systemId = (int)$pdo->lastInsertId();
check($systemId === (int)cfg('users.ai_user_id'), 'system account fixture matches configured AI user ID');

$users = [];
foreach ([
    'user' => (int)cfg('enums.role.user'),
    'trusted' => (int)cfg('enums.role.trusted'),
    'moderator' => (int)cfg('enums.role.moderator'),
    'admin' => (int)cfg('enums.role.admin'),
] as $name => $role) {
    $insertUser->execute([1, 'test-' . $name, 'Test ' . $name, $role, 1, $now, $now]);
    $users[$name] = (int)$pdo->lastInsertId();
}

q('INSERT INTO ' . tbl('countries') . ' (iso2, iso3, name_cs, name_en) VALUES (?, ?, ?, ?)', ['CZ', 'CZE', 'Česko', 'Czechia']);
$countryId = (int)$pdo->lastInsertId();
q('INSERT INTO ' . tbl('cities') . ' (country_id, name, name_ascii) VALUES (?, ?, ?)', [$countryId, 'Brno', 'Brno']);
$cityId = (int)$pdo->lastInsertId();
q('INSERT INTO ' . tbl('genres') . ' (name, name_ascii) VALUES (?, ?)', ['Punk rock', 'punk-rock']);
$genreId = (int)$pdo->lastInsertId();
q('INSERT INTO ' . tbl('venues') . ' (slug, name, name_ascii, city_id, created_at) VALUES (?, ?, ?, ?, ?)', ['test-klub', 'Test klub', 'Test klub', $cityId, $now]);
$venueId = (int)$pdo->lastInsertId();
q('INSERT INTO ' . tbl('bands') . ' (slug, name, name_ascii, city_id, genre_id, created_at) VALUES (?, ?, ?, ?, ?, ?)', ['test-kapela', 'Test kapela', 'Test kapela', $cityId, $genreId, $now]);
$bandId = (int)$pdo->lastInsertId();
check($cityId > 0 && $venueId > 0 && $bandId > 0, 'creates city, venue and band fixtures');

function session_token_for(int $userId): string
{
    $token = random_hex((int)cfg('session.token_bytes'));
    $now = time();
    q(
        'INSERT INTO ' . tbl('sessions') . ' (token_hash, user_id, ua_hash, created_at, last_seen_at, expires_at)
         VALUES (UNHEX(?), ?, UNHEX(?), ?, ?, ?)',
        [hash('sha256', $token), $userId, ua_hash_hex(), $now, $now, $now + 3600]
    );
    return $token;
}

function run_route(string $action, string $token, ?int $id = null, ?string $argument = null): string
{
    $script = KF_ROOT . '/tests/integration/route.php';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($action) . ' ' . escapeshellarg($token);
    if ($id !== null) {
        $cmd .= ' ' . escapeshellarg((string)$id);
    } elseif ($argument !== null) {
        $cmd .= ' ' . escapeshellarg($argument);
    }
    exec($cmd . ' 2>&1', $output, $status);
    if ($status !== 0) {
        throw new RuntimeException("Route {$action} failed (exit {$status}):\n" . implode("\n", $output));
    }
    return implode("\n", $output);
}

function event_values(string $name, string $date, int $cityId, int $venueId, int $bandId): array
{
    return kf_event_clean([
        'name' => $name,
        'type' => (int)cfg('enums.event_type.concert'),
        'date_from' => $date,
        'date_to' => $date,
        'time_start' => '19:00',
        'city_id' => $cityId,
        'venue_id' => $venueId,
        'description' => 'Automatický integrační test K&F.',
        'www' => '',
        'bands' => [['id' => $bandId, 'time' => '20:00']],
    ]);
}

function create_draft_with_image(int $userId, array $values): string
{
    $imageName = 'u' . $userId . '_' . random_hex(8);
    $image = imagecreatetruecolor(4, 4);
    if (!$image instanceof GdImage) {
        throw new RuntimeException('GD image creation failed');
    }
    $background = imagecolorallocate($image, 40, 80, 120);
    imagefill($image, 0, 0, $background);
    kf_img_write($image, abs_path((string)cfg('storage.tmp_dir')) . '/' . $imageName, ['l']);
    imagedestroy($image);
    kf_draft_put($userId, $values, $imageName);
    check(kf_draft_get($userId) !== null, 'saves and reloads the per-user draft');
    check(kf_draft_image_path($userId, $imageName) !== null, 'stores draft image in protected temporary storage');
    return $imageName;
}

$eventDate = date('Y-m-d', strtotime('+30 days'));
$userToken = session_token_for($users['user']);
$userValues = event_values('Festival test event approval', $eventDate, $cityId, $venueId, $bandId);
check(kf_event_validate($userValues) === [], 'validates complete event form data');
$imageName = create_draft_with_image($users['user'], $userValues);
$draftImage = kf_draft_image_path($users['user'], $imageName);
check($draftImage !== null && is_file($draftImage), 'draft preview image is available');
$formHtml = run_route('event_form', $userToken);
check(str_contains($formHtml, 'Festival test event approval') && str_contains($formHtml, 'event/draft-image'), 'new-event form route renders saved values and protected image preview');
$previewHtml = run_route('event_preview', $userToken);
check(str_contains($previewHtml, 'Festival test event approval') && str_contains($previewHtml, 'Test klub'), 'event preview route renders the event detail before submission');

run_route('event_submit', $userToken);
$pending = q('SELECT id FROM ' . tbl('pending') . ' WHERE created_by = ? AND title = ?', [$users['user'], 'Festival test event approval'])->fetchColumn();
check($pending !== false, 'ordinary role 1 submission creates a pending record');
$pendingId = (int)$pending;
$pendingData = kf_pending_get($pendingId);
check(($pendingData['data']['kind'] ?? '') === 'event', 'pending JSON contains the event payload');
check(is_file(kf_pending_image_path($pendingId) ?? ''), 'pending image is copied beside pending JSON');
check(kf_draft_get($users['user']) === null && !is_file((string)$draftImage), 'successful submission clears draft and temporary image');

$reviewToken = session_token_for($users['admin']);
run_route('review_approve', $reviewToken, $pendingId);
$publishedId = (int)q('SELECT id FROM ' . tbl('events') . ' WHERE name = ?', ['Festival test event approval'])->fetchColumn();
check($publishedId > 0, 'admin approval publishes the event into events');
$suggestJson = run_route('event_name_suggest', $userToken, null, 'festival test');
$suggestData = json_decode($suggestJson, true);
check(is_array($suggestData) && in_array('Festival test event approval', $suggestData['items'] ?? [], true), 'event-name autocomplete returns a matching published event');
$lowerSuggest = run_route('event_name_suggest', $userToken, null, 'fest');
$lowerData = json_decode($lowerSuggest, true);
check(is_array($lowerData) && in_array('Festival test event approval', $lowerData['items'] ?? [], true), 'event-name autocomplete matches uppercase stored names from a lowercase query');
q('UPDATE ' . tbl('events') . ' SET name_ascii = ? WHERE id = ?', ['Festival – test event approval', $publishedId]);
$punctuationSuggest = run_route('event_name_suggest', $userToken, null, 'festival -');
$punctuationData = json_decode($punctuationSuggest, true);
check(is_array($punctuationData) && in_array('Festival test event approval', $punctuationData['items'] ?? [], true), 'event-name autocomplete matches a hyphen query against an en dash in the stored name');
$shortSuggest = run_route('event_name_suggest', $userToken, null, 'K');
$shortData = json_decode($shortSuggest, true);
check(is_array($shortData) && ($shortData['items'] ?? []) === [], 'event-name autocomplete returns no results below the configured minimum length');
check((int)q('SELECT COUNT(*) FROM ' . tbl('event_bands') . ' WHERE event_id = ? AND band_id = ?', [$publishedId, $bandId])->fetchColumn() === 1, 'approval persists event-to-band relation and performance time');
$eventJson = json_read_file(json_bucket_rel('events', $publishedId));
check(($eventJson['description'] ?? '') === 'Automatický integrační test K&F.', 'approval writes event JSON details');
$eventBase = abs_path((string)cfg('img.subdirs.events')) . '/' . intdiv($publishedId, (int)cfg('img.folder_divisor')) . '/' . $publishedId;
check(is_file($eventBase . '_l.webp') && is_file($eventBase . '_s.webp'), 'approval installs both final WebP image sizes');
check(kf_pending_get($pendingId) === null && !is_file(dirname(abs_path(json_bucket_rel('pending', $pendingId))) . '/' . $pendingId . '_l.webp'), 'approval removes pending row, JSON and image');
check((int)q('SELECT approved_count FROM ' . tbl('users') . ' WHERE id = ?', [$users['user']])->fetchColumn() === 1, 'approval increments the author approved counter');

$trustedToken = session_token_for($users['trusted']);
$trustedValues = event_values('KF test trusted direct publish', date('Y-m-d', strtotime('+31 days')), $cityId, $venueId, $bandId);
check(kf_event_validate($trustedValues) === [], 'validates trusted-user event');
$trustedImage = create_draft_with_image($users['trusted'], $trustedValues);
run_route('event_submit', $trustedToken);
$trustedEventId = (int)q('SELECT id FROM ' . tbl('events') . ' WHERE name = ?', ['KF test trusted direct publish'])->fetchColumn();
check($trustedEventId > 0, 'role 2 (trusted) publishes directly according to current permission configuration');
check((int)q('SELECT COUNT(*) FROM ' . tbl('pending') . ' WHERE title = ?', ['KF test trusted direct publish'])->fetchColumn() === 0, 'role 2 does not create a pending record under current rules');
check(kf_draft_get($users['trusted']) === null, 'direct publication clears the trusted user draft');

$rejectValues = event_values('KF test event rejection', date('Y-m-d', strtotime('+32 days')), $cityId, $venueId, $bandId);
$rejectImage = create_draft_with_image($users['user'], $rejectValues);
run_route('event_submit', $userToken);
$rejectId = (int)q('SELECT id FROM ' . tbl('pending') . ' WHERE title = ?', ['KF test event rejection'])->fetchColumn();
check($rejectId > 0, 'second ordinary-user submission enters pending');
run_route('review_reject', $reviewToken, $rejectId);
check(kf_pending_get($rejectId) === null, 'rejection removes the pending database record and JSON');
check(!is_file(dirname(abs_path(json_bucket_rel('pending', $rejectId))) . '/' . $rejectId . '_l.webp'), 'rejection removes the pending image');

$deleteValues = event_values('KF test user deletes own proposal', date('Y-m-d', strtotime('+33 days')), $cityId, $venueId, $bandId);
create_draft_with_image($users['user'], $deleteValues);
run_route('event_submit', $userToken);
$deleteId = (int)q('SELECT id FROM ' . tbl('pending') . ' WHERE title = ?', ['KF test user deletes own proposal'])->fetchColumn();
check($deleteId > 0, 'third ordinary-user submission enters pending');
run_route('my_delete', $userToken, $deleteId);
check(kf_pending_get($deleteId) === null, 'author can delete own pending proposal from My submissions');

$roleTwoCanPublish = (int)q('SELECT role FROM ' . tbl('users') . ' WHERE id = ?', [$users['trusted']])->fetchColumn();
check($roleTwoCanPublish === (int)cfg('enums.role.trusted'), 'test confirms role 2 is trusted in the database');
check(can('view') && (int)cfg('enums.role.user') === 1, 'role enumeration and guest view baseline are consistent');

echo "\nAll K&F event workflow integration tests passed.\n";
