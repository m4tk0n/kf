<?php
// templates/layout.php
declare(strict_types=1);
/** @var string $title @var string $content @var ?array $user @var array $menu @var ?string $flash @var string $lang @var string $path */
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> – <?= e((string)cfg('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<header class="top">
  <a class="brand" href="<?= e(url('')) ?>"><?= e((string)cfg('app.name')) ?></a>
  <nav class="main" aria-label="<?= e(t('nav.main')) ?>">
    <a href="<?= e(url('events')) ?>"><?= e(t('nav.events')) ?></a>
    <a href="<?= e(url('bands')) ?>"><?= e(t('nav.bands')) ?></a>
    <a href="<?= e(url('venues')) ?>"><?= e(t('nav.venues')) ?></a>
  </nav>
  <div class="tools">
    <form class="lang" method="post" action="<?= e(url('set-lang')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($path) ?>">
      <?php foreach ((array)cfg('app.supported_langs') as $l): ?>
        <button type="submit" name="lang" value="<?= e((string)$l) ?>" class="<?= $l === $lang ? 'on' : '' ?>" aria-label="<?= e(t('common.language')) ?>: <?= e(strtoupper((string)$l)) ?>"><?= e(strtoupper((string)$l)) ?></button>
      <?php endforeach; ?>
    </form>
    <?php if ($user === null): ?>
      <a class="btn" href="<?= e(url('login')) ?>"><?= e(t('nav.login')) ?></a>
    <?php else: ?>
      <details class="usermenu">
        <summary aria-label="<?= e((string)$user['display_name']) ?>"><?= avatar_html($user) ?></summary>
        <div class="menu">
          <div class="who"><?= e((string)$user['display_name']) ?></div>
          <?php foreach ($menu as $it): ?>
            <a href="<?= e((string)$it['href']) ?>"><?= e((string)$it['label']) ?></a>
          <?php endforeach; ?>
          <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="linklike"><?= e(t('nav.logout')) ?></button>
          </form>
        </div>
      </details>
    <?php endif; ?>
  </div>
</header>
<?php if ($flash !== null): ?>
  <div class="flash" role="status"><?= e($flash) ?></div>
<?php endif; ?>
<main class="page">
<?= $content ?>
</main>
<footer class="foot"><?= e((string)cfg('app.name')) ?></footer>
</body>
</html>
