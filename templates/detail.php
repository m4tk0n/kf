<?php
// templates/detail.php
declare(strict_types=1);
/** @var array $d @var ?array $user */
$kind = (string)$d['kind'];
$id = (int)$d['id'];
$r = $d['row'];
$x = $d['extra'];
$name = (string)$r['name'];
$preview = !empty($d['preview']);
$place = e((string)$r['city']) . ' <abbr title="' . e((string)$r['country']) . '">(' . e((string)$r['iso2']) . ')</abbr>';

$row = static fn(string $label, string $html): string => '<dt>' . e($label) . '</dt><dd>' . $html . '</dd>';

$evList = static function (array $rows): string {
    if (!$rows) {
        return '<p class="muted">' . e(t('detail.none')) . '</p>';
    }
    $out = '<ul class="evlist">';
    foreach ($rows as $ev) {
        $a = (string)$ev['date_from'];
        $b = (string)$ev['date_to'];
        $out .= '<li><a href="' . e(kf_detail_url('event', (int)$ev['id'], $ev)) . '"><time datetime="' . e($a) . '">'
            . e($a . ($b !== $a ? '–' . $b : '')) . '</time><span>' . e((string)$ev['name']) . '</span></a></li>';
    }
    return $out . '</ul>';
};
?>
<article class="card detail detail-<?= e($kind) ?>">
  <div class="detail-toolbar">
    <?php if (!$preview && $user !== null && (int)($user['user_state'] ?? 0) === (int)cfg('enums.user_state.active')): ?>
      <button type="button" class="fav<?= $d['fav'] ? ' on' : '' ?>" data-fav
        data-url="<?= e(url('fav')) ?>" data-csrf-field="<?= e((string)cfg('security.csrf_field')) ?>" data-csrf="<?= e(csrf_token()) ?>"
        data-kind="<?= e($kind) ?>" data-id="<?= $id ?>" aria-pressed="<?= $d['fav'] ? 'true' : 'false' ?>"
        data-label-on="<?= e(t('detail.fav_remove')) ?>" data-label-off="<?= e(t('detail.fav_add')) ?>"
        data-msg-limit="<?= e(t('detail.fav_limit')) ?>" data-msg-error="<?= e(t('detail.fav_error')) ?>"
        aria-label="<?= e(t($d['fav'] ? 'detail.fav_remove' : 'detail.fav_add')) ?>" title="<?= e(t($d['fav'] ? 'detail.fav_remove' : 'detail.fav_add')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3.5 2.65 5.37 5.93.86-4.29 4.18 1.01 5.9L12 17.03l-5.3 2.78 1.01-5.9-4.29-4.18 5.93-.86L12 3.5Z"></path></svg></button>
    <?php elseif (!$preview): ?>
      <span class="fav off" role="img" aria-disabled="true" aria-label="<?= e(t('detail.fav_login')) ?>" title="<?= e(t('detail.fav_login')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3.5 2.65 5.37 5.93.86-4.29 4.18 1.01 5.9L12 17.03l-5.3 2.78 1.01-5.9-4.29-4.18 5.93-.86L12 3.5Z"></path></svg></span>
    <?php endif; ?>
    <a class="btn ghost page-back" data-detail-back href="<?= e((string)$d['back']) ?>">← <?= e(t('detail.back')) ?></a>
  </div>

  <?php if ($x['large']): $img = (string)($d['preview_img'] ?? kf_img((string)$d['bucket'], $id, 'l')); ?>
    <a class="detail-img" href="<?= e($img) ?>" data-lightbox><img src="<?= e($img) ?>" alt="<?= e($name) ?>"></a>
  <?php endif; ?>

  <h1 class="detail-title"><?= e($name) ?></h1>

  <dl class="kv">
    <?php
    if ($kind === 'event') {
        $a = (string)$r['date_from'];
        $b = (string)$r['date_to'];
        echo $row(t('detail.type'), '<span class="tag t' . (int)$r['type'] . '">' . e(t('type.' . (int)$r['type'])) . '</span>');
        echo $row(t('detail.date'), e($a . ($b !== $a ? ' – ' . $b : '')));
        if ($r['time_start'] !== null) {
            echo $row(t('detail.time'), e(substr((string)$r['time_start'], 0, 5)));
        }
        echo $row(t('detail.place'), $place);
        if ($r['venue_id'] !== null && $r['venue'] !== null) {
            echo $row(t('detail.venue'), '<a href="' . e(kf_detail_url('venue', (int)$r['venue_id'], ['name' => (string)$r['venue']])) . '">' . e((string)$r['venue']) . '</a>');
        }
    } elseif ($kind === 'band') {
        if ($r['genre'] !== null) {
            echo $row(t('detail.genre'), e((string)$r['genre']));
        }
        echo $row(t('detail.place'), $place);
    } else {
        echo $row(t('detail.place'), $place);
        if ($x['address'] !== '') {
            echo $row(t('detail.address'), e($x['address']));
        }
    }
    if ($x['www'] !== null) {
        echo $row(t('detail.www'), '<a href="' . e($x['www']) . '" target="_blank" rel="noopener nofollow ugc">' . e((string)preg_replace('~^https?://~i', '', $x['www'])) . '</a>');
    }
    if ($kind === 'band' && ($x['facebook'] ?? '') !== '') {
        echo $row('Facebook', '<a href="' . e($x['facebook']) . '" target="_blank" rel="noopener nofollow ugc">Facebook</a>');
    }
    if ($kind === 'band' && ($x['bandzone'] ?? '') !== '') {
        echo $row('Bandzone', '<a href="' . e($x['bandzone']) . '" target="_blank" rel="noopener nofollow ugc">Bandzone</a>');
    }
    if ($kind === 'event' && $x['price'] !== '') {
        echo $row(t('detail.price'), e($x['price']));
    }
    if ($x['email'] !== '') {
        echo $row(t('detail.email'), '<a href="mailto:' . e($x['email']) . '">' . e($x['email']) . '</a>');
    }
    if ($x['phone'] !== '') {
        echo $row(t('detail.phone'), '<a href="tel:' . e((string)preg_replace('/[^0-9+]/', '', $x['phone'])) . '">' . e($x['phone']) . '</a>');
    }
    ?>
  </dl>

  <?php if ($kind === 'venue' && ($x['mapy_maps'] !== '' || $x['google_maps'] !== '')): ?>
    <div class="maplinks" aria-label="<?= e(t('detail.map')) ?>">
      <?php if ($x['mapy_maps'] !== ''): ?><a class="btn small ghost" target="_blank" rel="noopener" href="<?= e($x['mapy_maps']) ?>">Mapy.com</a><?php endif; ?>
      <?php if ($x['google_maps'] !== ''): ?><a class="btn small ghost" target="_blank" rel="noopener" href="<?= e($x['google_maps']) ?>">Google Maps</a><?php endif; ?>
    </div>
  <?php elseif ($kind === 'venue' && $x['geo'] !== null):
      $lat = number_format($x['geo'][0], 5, '.', '');
      $lng = number_format($x['geo'][1], 5, '.', '');
  ?>
    <div class="maplinks" aria-label="<?= e(t('detail.map')) ?>">
      <a class="btn small ghost" target="_blank" rel="noopener" href="<?= e('https://mapy.com/' . current_lang() . '/zakladni?x=' . $lng . '&y=' . $lat . '&z=17&source=coor&id=' . $lng . '%2C' . $lat) ?>">Mapy.com</a>
      <a class="btn small ghost" target="_blank" rel="noopener" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . $lat . ',' . $lng) ?>">Google Maps</a>
      <a class="btn small ghost" href="<?= e('mapsme://map?ll=' . $lat . ',' . $lng . '&n=' . rawurlencode($name)) ?>">Maps.me</a>
    </div>
  <?php endif; ?>

  <?php if ($x['desc'] !== ''): ?>
    <p class="detail-text"><?= nl2br(e($x['desc'])) ?></p>
  <?php endif; ?>

  <?php if ($kind === 'event'): ?>
    <h2><?= e(t('detail.bands')) ?></h2>
    <?php if (!$d['bands']): ?>
      <p class="muted"><?= e(t('detail.none')) ?></p>
    <?php else: ?>
      <ul class="evlist">
        <?php foreach ($d['bands'] as $b): ?>
          <li><a href="<?= e(kf_detail_url('band', (int)$b['id'], $b)) ?>">
            <span class="play"><?= $b['time_play'] !== null ? e(substr((string)$b['time_play'], 0, 5)) : '' ?></span>
            <span><?= e((string)$b['name']) ?></span>
          </a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php else: ?>
    <h2><?= e(t('detail.upcoming')) ?></h2>
    <?= $evList($d['upcoming']) ?>
    <h2><?= e(t('detail.past')) ?></h2>
    <?= $evList($d['past']) ?>
  <?php endif; ?>
</article>
