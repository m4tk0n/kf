<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#111111">
<title><?= e($title) ?> · <?= e(L('site-name')) ?></title>
<?php $basePath = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/'); ?>
<link rel="stylesheet" href="<?= e($basePath) ?>/assets/css/site.css">
<?php foreach (cfg('app.languages') as $l): ?>
<link rel="alternate" hreflang="<?= e($l) ?>" href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>">
<?php endforeach; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(url()) ?>"><span class="brand-word">Koncerty</span> <span class="brand-amp">&amp;</span> <span class="brand-word">Festivaly</span></a>
  <div class="header-actions">
    <a class="login-link" href="<?= e(url('login')) ?>">[nav-login]</a>
    <nav class="language-switch" aria-label="[language-label]">
<?php foreach (cfg('app.languages') as $l): ?>
      <a href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>?setlang"<?= lang_code() === $l ? ' aria-current="page"' : '' ?>><?= e(strtoupper($l)) ?></a>
<?php endforeach; ?>
    </nav>
  </div>
</header>
<?php if (($GLOBALS['KF_ROUTE'] ?? '') === ''): ?>
<nav class="section-nav" aria-label="[section-navigation]">
  <form class="section-toolbar" method="get" action="<?= e(url()) ?>">
    <label class="sr-only" for="section-select">[section-navigation]</label>
    <select id="section-select" name="section" onchange="this.form.submit()">
      <option value="events"<?= (($_GET['section'] ?? 'events') === 'events') ? ' selected' : '' ?>>[section-events]</option>
      <option value="venues"<?= (($_GET['section'] ?? '') === 'venues') ? ' selected' : '' ?>>[section-venues]</option>
      <option value="bands"<?= (($_GET['section'] ?? '') === 'bands') ? ' selected' : '' ?>>[section-bands]</option>
    </select>
    <label class="sr-only" for="global-search">[search-label]</label>
    <input id="global-search" class="global-search" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="[search-placeholder]" autocomplete="off">
    <a class="filter-button" href="#filtry">[home-filters]</a>
  </form>
</nav>
<?php endif; ?>
<main id="content" class="site-main"><?= $content ?></main>
<footer class="site-footer"><?= e(L('site-name')) ?></footer>
</body>
</html>
