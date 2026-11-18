<?php
declare(strict_types=1);

/**
 * K&F – bezpečné ruční přidání kapely do katalogu.
 * Přístup mají pouze přihlášení uživatelé s rolí admin (4).
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$user = $_SESSION['user'] ?? null;
if (!is_array($user) || (int)($user['role'] ?? 0) !== 4) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = db();
$countriesTable = tbl('countries');
$genresTable = tbl('genres');
$bandsTable = tbl('bands');
$bandGenresTable = tbl('band_genres');

$countries = $pdo->query("SELECT iso2, cs, en FROM {$countriesTable} ORDER BY cs")->fetchAll();
$genres = $pdo->query("SELECT slug, name FROM {$genresTable} ORDER BY name")->fetchAll();

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        exit('Neplatný bezpečnostní token.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $countryCode = strtoupper(trim((string)($_POST['country'] ?? '')));
    $genreSlug = trim((string)($_POST['genre'] ?? ''));

    if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
        $error = 'Zadej název kapely (maximálně 120 znaků).';
    } elseif (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        $error = 'Vyber platnou zemi.';
    } elseif (!preg_match('/^[a-z0-9-]{1,60}$/', $genreSlug)) {
        $error = 'Vyber platný žánr.';
    } else {
        $slug = slugify($name);
        if ($slug === '' || strlen($slug) > 80) {
            $error = 'Z názvu nelze vytvořit platný identifikátor kapely.';
        } else {
            try {
                $pdo->beginTransaction();

                $countryQuery = $pdo->prepare("SELECT id FROM {$countriesTable} WHERE iso2 = ? LIMIT 1");
                $countryQuery->execute([$countryCode]);
                $countryId = $countryQuery->fetchColumn();

                $genreQuery = $pdo->prepare("SELECT id FROM {$genresTable} WHERE slug = ? LIMIT 1");
                $genreQuery->execute([$genreSlug]);
                $genreId = $genreQuery->fetchColumn();

                if (!$countryId || !$genreId) {
                    throw new RuntimeException('Vybraná země nebo žánr v databázi neexistuje.');
                }

                $findBand = $pdo->prepare("SELECT id, name FROM {$bandsTable} WHERE slug = ? LIMIT 1");
                $findBand->execute([$slug]);
                $existing = $findBand->fetch();

                if ($existing) {
                    $bandId = (int)$existing['id'];
                    if (mb_strtolower((string)$existing['name'], 'UTF-8') !== mb_strtolower($name, 'UTF-8')) {
                        throw new RuntimeException('Stejný identifikátor už používá jiná kapela. Název je příliš podobný.');
                    }
                    $status = 'Kapela už existovala';
                } else {
                    $insertBand = $pdo->prepare("INSERT INTO {$bandsTable} (country_id, slug, name) VALUES (?, ?, ?)");
                    $insertBand->execute([(int)$countryId, $slug, $name]);
                    $bandId = (int)$pdo->lastInsertId();
                    $status = 'Kapela byla přidána';
                }

                $link = $pdo->prepare("INSERT IGNORE INTO {$bandGenresTable} (band_id, genre_id) VALUES (?, ?)");
                $link->execute([$bandId, (int)$genreId]);

                $pdo->commit();
                $message = $status . ' a žánr byl přiřazen.';
                $_POST = [];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Uložení se nepodařilo. Zkontroluj databázi a její strukturu.';
                error_log('K&F admin add band: ' . $e->getMessage());
            }
        }
    }
}

$lang = lang_code();
$countryLabel = $lang === 'en' ? 'en' : 'cs';
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $lang === 'en' ? 'Add band' : 'Přidat kapelu' ?> – K&F</title>
<link rel="stylesheet" href="<?= e(rtrim(cfg('app.base_url'), '/') . '/assets/css/site.css') ?>">
<style>
.admin-wrap{max-width:560px;margin:32px auto;padding:20px}
.admin-wrap form{display:grid;gap:14px}
.admin-wrap label{display:grid;gap:6px}
.admin-wrap input,.admin-wrap select{width:100%;min-height:44px;padding:9px 12px}
.admin-wrap button{min-height:44px;padding:10px 16px;cursor:pointer}
.admin-msg{padding:12px;border-radius:8px;margin:12px 0}
.admin-ok{background:#e8f5e9;color:#1b5e20}.admin-error{background:#ffebee;color:#b71c1c}
</style>
</head>
<body>
<main class="admin-wrap">
<h1><?= $lang === 'en' ? 'Add band' : 'Přidat kapelu' ?></h1>
<?php if ($message !== ''): ?><p class="admin-msg admin-ok"><?= e($message) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="admin-msg admin-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<label><?= $lang === 'en' ? 'Band name' : 'Název kapely' ?>
<input name="name" maxlength="120" required value="<?= e($_POST['name'] ?? '') ?>"></label>
<label><?= $lang === 'en' ? 'Country' : 'Země' ?>
<select name="country" required>
<option value="">—</option>
<?php foreach ($countries as $country): ?>
<option value="<?= e($country['iso2']) ?>" <?= (($_POST['country'] ?? '') === $country['iso2']) ? 'selected' : '' ?>><?= e($country[$countryLabel]) ?> (<?= e($country['iso2']) ?>)</option>
<?php endforeach; ?>
</select></label>
<label><?= $lang === 'en' ? 'Genre' : 'Žánr' ?>
<select name="genre" required>
<option value="">—</option>
<?php foreach ($genres as $genre): ?>
<option value="<?= e($genre['slug']) ?>" <?= (($_POST['genre'] ?? '') === $genre['slug']) ? 'selected' : '' ?>><?= e($genre['name']) ?></option>
<?php endforeach; ?>
</select></label>
<button type="submit"><?= $lang === 'en' ? 'Save band' : 'Uložit kapelu' ?></button>
</form>
</main>
</body>
</html>
