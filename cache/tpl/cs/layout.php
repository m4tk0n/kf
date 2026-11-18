<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php foreach (cfg('app.languages') as $l): ?>
<link rel="alternate" hreflang="<?= e($l) ?>" href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>">
<?php endforeach; ?>
</head>
<body>
<header>
  <a href="<?= e(url()) ?>">Koncerty &amp; Festivaly</a>
  <nav>
<?php foreach (cfg('app.languages') as $l): ?>
    <a href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>?setlang"><?= e(strtoupper($l)) ?></a>
<?php endforeach; ?>
  </nav>
</header>
<main id="content"><?= $content ?></main>
</body>
</html>
