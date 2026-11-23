<?php
// templates/layout.php
/** @var array|null $view  výpis (index.php); u stránek z page() je $content */
$req = $view['req'] ?? ['s' => 'events', 'q' => ''];
$user = $user ?? auth_resolve();
$menu = $menu ?? menu_items($user);
$flash = $flash ?? flash_text();
$isList = isset($view);
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(isset($title) && $title !== '' ? $title . ' – ' . (string)cfg('app.name') : (string)cfg('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(kf_asset('css/app.css')) ?>">
<script src="<?= e(kf_asset('js/app.js')) ?>" defer></script>
</head>
<body>
<div class="head">
  <header class="wrap top">
    <a class="brand" href="<?= e(url('')) ?>">K&amp;F</a>
    <?php if ($user === null): ?>
      <a class="btn" href="<?= e(url('login')) ?>"><?= e(kf_t('login')) ?></a>
    <?php else: ?>
      <details class="umenu">
        <summary aria-label="<?= e((string)$user['display_name']) ?>"><?= avatar_html($user) ?></summary>
        <nav class="umenu-list">
          <?php foreach ($menu as $it): ?>
            <a href="<?= e((string)$it['href']) ?>"><?= e((string)$it['label']) ?></a>
          <?php endforeach; ?>
          <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit"><?= e(t('menu.logout')) ?></button></form>
        </nav>
      </details>
    <?php endif; ?>
  </header>
  <form class="wrap bar" method="get" action="<?= e(url('')) ?>">
    <select name="s" aria-label="<?= e(kf_t('nav.label')) ?>">
      <?php foreach (['events', 'bands', 'venues'] as $v): ?>
        <option value="<?= e($v) ?>"<?= $req['s'] === $v ? ' selected' : '' ?>><?= e(kf_t('nav.' . $v)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e((string)$req['q']) ?>" maxlength="100" placeholder="<?= e(kf_t('search.placeholder')) ?>" aria-label="<?= e(kf_t('search.label')) ?>" autocomplete="off">
    <button class="btn" type="button" disabled><?= e(kf_t('filters')) ?></button>
  </form>
</div>
<main class="wrap main">
<?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>
<?php if ($isList): require __DIR__ . '/list.php'; else: echo $content; endif; ?>
</main>
</body>
</html>
