<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#111111">
<title><?= e($title) ?> · <?= e(L('site-name')) ?></title>
<?php
$scriptDirectory = str_replace('\\\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$assetBasePath = $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');
$cssFile = __DIR__ . '/../assets/css/site.css';
$cssVersion = is_file($cssFile) ? (string)filemtime($cssFile) : '1';
$jsFiles = glob(__DIR__ . '/../assets/js/*.js') ?: [];
?>
<link rel="stylesheet" href="<?= e($assetBasePath) ?>/assets/css/site.css?v=<?= e($cssVersion) ?>">
<?php foreach (cfg('app.languages') as $l): ?>
<link rel="alternate" hreflang="<?= e($l) ?>" href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>">
<?php endforeach; ?>
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= e(url()) ?>"><span class="brand-word">Koncerty</span> <span class="brand-amp">&amp;</span> <span class="brand-word">Festivaly</span></a>
  <div class="header-actions">
<?php $currentUser = $_SESSION['user'] ?? null; ?>
<?php if (is_array($currentUser) && !empty($currentUser['name'])): ?>
<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$avatarLetter = mb_strtoupper(mb_substr(trim((string)$currentUser['name']), 0, 1, 'UTF-8'), 'UTF-8');
$avatarUrl = (string)($currentUser['picture'] ?? '');
if (!preg_match('~^https://[a-z0-9.-]+/[^\\s]*$~i', $avatarUrl)) $avatarUrl = '';
$roleNames = cfg('enums.role');
$roleName = $roleNames[(int)($currentUser['role'] ?? 0)] ?? 'unknown';
$roleLabel = L('role-' . $roleName);
?>
    <details class="account-menu">
      <summary class="user-avatar" aria-label="<?= e(L('account-menu')) ?>" title="<?= e(L('account-menu')) ?>">
<?php if ($avatarUrl !== ''): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($avatarLetter) ?><?php endif; ?>
      </summary>
      <div class="account-dropdown">
        <div class="account-identity">
          <div class="account-name-line"><strong><?= e($currentUser['name']) ?></strong><span class="account-role"><?= e($roleLabel) ?></span></div>
        </div>
        <a class="account-menu-link" href="<?= e(url('profile')) ?>"><?= e(L('account-profile')) ?></a>
        <a class="account-menu-link" href="<?= e(url('settings')) ?>"><?= e(L('account-settings')) ?></a>
<?php if (in_array((int)($currentUser['role'] ?? 0), [3, 4], true)): ?>
        <div class="account-menu-section"><?= e(L('account-management')) ?></div>
        <a class="account-menu-link" href="<?= e(url('moderation')) ?>"><?= e(L('account-moderation')) ?></a>
<?php endif; ?>
<?php if ((int)($currentUser['role'] ?? 0) === 4): ?>
        <a class="account-menu-link" href="<?= e(url('admin')) ?>"><?= e(L('account-administration')) ?></a>
<?php endif; ?>
        <form method="post" action="<?= e(url('logout')) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
          <button type="submit" class="logout-button"><?= e(L('account-logout')) ?></button>
        </form>
      </div>
    </details>
<?php else: ?>
    <a class="login-link" href="<?= e(url('login')) ?>"><?= e(L('nav-login')) ?></a>
<?php endif; ?>
    <nav class="language-switch" aria-label="<?= e(L('language-label')) ?>">
<?php foreach (cfg('app.languages') as $l): ?>
      <a href="<?= e(url($GLOBALS['KF_ROUTE'] ?? '', $l)) ?>?setlang"<?= lang_code() === $l ? ' aria-current="page"' : '' ?>><?= e(strtoupper($l)) ?></a>
<?php endforeach; ?>
    </nav>
  </div>
</header>
<?php if (($GLOBALS['KF_ROUTE'] ?? '') === ''): ?>
<nav class="section-nav" aria-label="<?= e(L('section-navigation')) ?>">
  <form class="section-toolbar" method="get" action="<?= e(url()) ?>">
    <label class="sr-only" for="section-select"><?= e(L('section-navigation')) ?></label>
    <select id="section-select" name="section" onchange="this.form.submit()">
      <option value="events"<?= (($_GET['section'] ?? 'events') === 'events') ? ' selected' : '' ?>><?= e(L('section-events')) ?></option>
      <option value="venues"<?= (($_GET['section'] ?? '') === 'venues') ? ' selected' : '' ?>><?= e(L('section-venues')) ?></option>
      <option value="bands"<?= (($_GET['section'] ?? '') === 'bands') ? ' selected' : '' ?>><?= e(L('section-bands')) ?></option>
    </select>
    <label class="sr-only" for="global-search"><?= e(L('search-label')) ?></label>
    <input id="global-search" class="global-search" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(L('search-placeholder')) ?>" autocomplete="off">
    <a class="filter-button" href="#filtry"><?= e(L('home-filters')) ?></a>
  </form>
</nav>
<?php endif; ?>
<main id="content" class="site-main"><?= $content ?></main>
<footer class="site-footer"><?= e(L('site-name')) ?></footer>
<?php foreach ($jsFiles as $jsFile): ?>
<script src="<?= e($assetBasePath) ?>/assets/js/<?= e(basename($jsFile)) ?>?v=<?= e((string)filemtime($jsFile)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>