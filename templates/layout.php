<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#111111">
<title><?= e($title) ?> · Koncerty &amp; Festivaly</title>
<?php $basePath = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/'); ?>
<link rel="stylesheet" href="<?= e($basePath) ?>/assets/css/site.css">
<?php foreach (cfg('app.languages') as $l): ?>
<link rel="alternate" hreflang="<?= e($l) ?>" href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>">
<?php endforeach; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(url()) ?>">Koncerty <span>&amp;</span> Festivaly</a>
  <div class="header-actions">
    <a class="login-link" href="<?= e(url('login')) ?>">Přihlásit</a>
    <nav class="language-switch" aria-label="Jazyk">
<?php foreach (cfg('app.languages') as $l): ?>
      <a href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>?setlang"<?= lang_code() === $l ? ' aria-current="page"' : '' ?>><?= e(strtoupper($l)) ?></a>
<?php endforeach; ?>
    </nav>
  </div>
</header>
<main id="content" class="site-main"><?= $content ?></main>
<footer class="site-footer">Koncerty &amp; Festivaly</footer>
</body>
</html>