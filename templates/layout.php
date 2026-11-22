<?php
// templates/layout.php
/** @var array $view */
$req = $view['req'];
$loginUrl = url(str_replace('{provider}', 'google', (string)cfg('oauth.login_path')));
?>
<!doctype html>
<html lang="<?= e(kf_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e((string)cfg('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(kf_asset('css/app.css')) ?>">
<script src="<?= e(kf_asset('js/app.js')) ?>" defer></script>
</head>
<body>
<div class="head">
  <header class="wrap top">
    <a class="brand" href="<?= e(url('')) ?>">K&amp;F</a>
    <a class="btn" href="<?= e($loginUrl) ?>"><?= e(kf_t('login')) ?></a>
  </header>
  <form class="wrap bar" method="get" action="<?= e(url('')) ?>">
    <select name="s" aria-label="<?= e(kf_t('nav.label')) ?>">
      <?php foreach (['events', 'bands', 'venues'] as $v): ?>
        <option value="<?= e($v) ?>"<?= $req['s'] === $v ? ' selected' : '' ?>><?= e(kf_t('nav.' . $v)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($req['q']) ?>" maxlength="100" placeholder="<?= e(kf_t('search.placeholder')) ?>" aria-label="<?= e(kf_t('search.label')) ?>" autocomplete="off">
    <button class="btn" type="button" disabled><?= e(kf_t('filters')) ?></button>
  </form>
</div>
<main class="wrap main">
<?php require __DIR__ . '/list.php'; ?>
</main>
</body>
</html>
