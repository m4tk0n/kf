<?php
// templates/layout.php
/** @var array|null $view  výpis (index.php); u stránek z page() je $content */
$req = $view['req'] ?? ['s' => (string)($section ?? 'events'), 'q' => '', 'p' => 1, 'd' => '', 'k' => 0, 'fav' => kf_get('fav') === '1' ? 1 : 0];
$favOn = !empty($req['fav']);
$user = $user ?? auth_resolve();
$menu = $menu ?? menu_items($user);
$flash = $flash ?? flash_text();
$isList = isset($view);
$sessionReplaced = !empty(auth_store()['replaced']);
$debugEnabled = kf_debug_enabled($user);
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(isset($title) && $title !== '' ? $title . ' – ' . (string)cfg('app.name') : (string)cfg('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(kf_asset('css/app.css')) ?>">
<script src="<?= e(kf_asset('js/app.js')) ?>" defer></script>
<?php if ($debugEnabled): kf_debug_assets(); endif; ?>
<?php if ($isList): ?>
<script src="<?= e(kf_asset('js/list.js')) ?>" defer></script>
<script src="<?= e(kf_asset('js/calendar.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($user !== null && ($isList || !empty($hasFavorites))): ?>
<script src="<?= e(kf_asset('js/favorites.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($user !== null): ?>
<script src="<?= e(kf_asset('js/menu.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($hasLightbox)): ?>
<script src="<?= e(kf_asset('js/lightbox.js')) ?>" defer></script>
<?php endif; ?>
</head>
<body>
<div class="head">
  <header class="wrap top">
    <a class="brand" href="<?= e(url('')) ?>">K&amp;F</a>
    <?php if ($user === null): ?>
      <a class="btn" href="<?= e(url('login')) ?>"><?= e(kf_t('login')) ?></a>
    <?php else: ?>
      <div class="top-right">
      <a data-favtop data-label-on="<?= e(t('fav.toggle_on')) ?>" data-label-off="<?= e(t('fav.toggle_off')) ?>" class="fav favtop<?= $favOn ? ' on' : '' ?>" href="<?= e(kf_url($req, ['fav' => $favOn ? 0 : 1, 'p' => 1, 'k' => 0])) ?>" aria-pressed="<?= $favOn ? 'true' : 'false' ?>" aria-label="<?= e(t($favOn ? 'fav.toggle_on' : 'fav.toggle_off')) ?>" title="<?= e(t($favOn ? 'fav.toggle_on' : 'fav.toggle_off')) ?>">★</a>
      <details class="umenu">
        <summary aria-label="<?= e((string)$user['display_name']) ?>"><?= avatar_html($user) ?></summary>
        <nav class="umenu-list">
          <?php foreach ($menu as $it): ?>
            <a href="<?= e((string)$it['href']) ?>"><?= e((string)$it['label']) ?></a>
          <?php endforeach; ?>
          <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit"><?= e(t('menu.logout')) ?></button></form>
        </nav>
      </details>
      </div>
    <?php endif; ?>
  </header>
  <form class="wrap bar" method="get" action="<?= e(url('')) ?>">
    <?php if ($favOn && $user !== null): ?><input type="hidden" name="fav" value="1"><?php endif; ?>
    <select name="s" aria-label="<?= e(kf_t('nav.label')) ?>">
      <?php foreach (['events', 'bands', 'venues'] as $v): ?>
        <option value="<?= e($v) ?>"<?= $req['s'] === $v ? ' selected' : '' ?>><?= e(kf_t('nav.' . $v)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e((string)$req['q']) ?>" maxlength="100" data-min-chars="<?= max(1, (int)cfg('paging.autocomplete_min_chars')) ?>" placeholder="<?= e(kf_t('search.placeholder')) ?>" aria-label="<?= e(kf_t('search.label')) ?>" autocomplete="off">
    <button class="btn" type="button" disabled><?= e(kf_t('filters')) ?></button>
  </form>
</div>
<main class="wrap main">
<?php if ($flash && !$sessionReplaced): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?>
<?php if ($sessionReplaced): ?>
<div class="modal-backdrop" id="session-replaced-modal" role="presentation">
  <section class="modal" role="alertdialog" aria-modal="true" aria-labelledby="session-replaced-title" aria-describedby="session-replaced-message">
    <h2 id="session-replaced-title"><?= e(t('flash.session_replaced_title')) ?></h2>
    <p id="session-replaced-message"><?= e($flash ?? t('flash.session_replaced')) ?></p>
    <button class="btn" type="button" data-modal-close><?= e(t('common.understood')) ?></button>
  </section>
</div>
<?php endif; ?>
<?php if ($isList): ?><div id="view" data-fav="<?= $favOn && $user !== null ? 1 : 0 ?>"><?php require __DIR__ . '/list.php'; ?></div><?php else: echo $content; endif; ?>
</main>
<?php if ($debugEnabled): kf_debug_render(); endif; ?>
</body>
</html>
