<?php
// templates/layout.php
/** @var array|null $view  výpis (index.php); u stránek z page() je $content */
$req = $view['req'] ?? ['s' => (string)($section ?? 'events'), 'q' => (string)kf_get('q'), 'p' => max(1, (int)kf_get('p')), 'd' => (string)kf_get('d'), 'k' => max(0, (int)kf_get('k')), 'fav' => kf_get('fav') === '1' ? 1 : 0];
$favOn = !empty($req['fav']);
$user = $user ?? auth_resolve();
$menu = $menu ?? menu_items($user);
$flash = $flash ?? flash_text();
$isList = isset($view);
$sessionReplaced = !empty(auth_store()['replaced']);
$debugEnabled = kf_debug_enabled($user);
$filterIdentity = $user !== null ? 'user:' . (int)$user['id'] : 'guest';
$clearFilterSession = (string)($_GET['clear_filter_session'] ?? '') === '1';
?>
<!doctype html>
<html lang="<?= e(current_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e(isset($title) && $title !== '' ? $title . ' – ' . (string)cfg('app.name') : (string)cfg('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php if ($isList): ?><link rel="stylesheet" href="<?= e(asset('css/pager.css')) ?>"><?php endif; ?>
<?php if ($debugEnabled): kf_debug_assets(); endif; ?>
<script src="<?= e(asset('js/filters.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if ($isList): ?>
<script src="<?= e(asset('js/list.js')) ?>" defer></script>
<script src="<?= e(asset('js/pager.js')) ?>" defer></script>
<script src="<?= e(asset('js/calendar.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($user !== null && ($isList || !empty($hasFavorites))): ?>
<script src="<?= e(asset('js/favorites.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($user !== null): ?>
<script src="<?= e(asset('js/menu.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($hasQuickFilters)): ?>
<script src="<?= e(asset('js/quick-filters.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($hasLightbox)): ?>
<script src="<?= e(asset('js/lightbox.js')) ?>" defer></script>
<?php endif; ?>
</head>
<body data-home-url="<?= e(url('')) ?>" data-back-label="<?= e(t('common.back')) ?>" data-filter-identity="<?= e($filterIdentity) ?>"<?= $clearFilterSession ? ' data-clear-filter-session="1"' : '' ?><?= $flash && !$sessionReplaced ? ' data-flash-text="' . e($flash) . '"' : '' ?>>
<div class="head">
  <header class="wrap top">
    <a class="brand" href="<?= e(url('')) ?>"><span class="brand-letter">F</span><span class="brand-amp">&amp;</span><span class="brand-letter">K</span></a>
    <?php if ($user === null): ?>
      <a class="btn" href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a>
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
  <div class="wrap bar">
    <form class="bar-search" method="get" action="<?= e(url('')) ?>">
      <?php if ($favOn && $user !== null): ?><input type="hidden" name="fav" value="1"><?php endif; ?>
      <select name="s" aria-label="<?= e(t('nav.label')) ?>">
        <?php foreach (['events', 'bands', 'venues'] as $v): ?>
          <option value="<?= e($v) ?>"<?= $req['s'] === $v ? ' selected' : '' ?>><?= e(t('nav.' . $v)) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" name="q" value="<?= e((string)$req['q']) ?>" maxlength="100" data-min-chars="<?= max(1, (int)cfg('paging.autocomplete_min_chars')) ?>" placeholder="<?= e(t('search.placeholder')) ?>" aria-label="<?= e(t('search.label')) ?>" autocomplete="off">
    </form>
    <?php $quickSection = (string)($_GET['s'] ?? $req['s'] ?? 'events'); if (!in_array($quickSection, ['events', 'bands', 'venues'], true)) $quickSection = 'events'; $quickParams = ['s' => $quickSection, 'q' => (string)($_GET['q'] ?? $req['q']), 'p' => max(1, (int)($_GET['p'] ?? $req['p'])), 'd' => (string)($_GET['d'] ?? $req['d']), 'k' => max(0, (int)($_GET['k'] ?? $req['k']))]; if ($favOn && $user !== null) $quickParams['fav'] = 1; ?>
    <a class="btn bar-filter-toggle" href="<?= e(url('quick-filters') . '?' . http_build_query($quickParams)) ?>"><?= e(t('filters')) ?></a>
  </div>
</div>
<main class="wrap main">
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
