<?php
// templates/settings.php
declare(strict_types=1);
/** @var float $db_mb @var int $db_max @var int $files @var int $files_max @var array $by_role @var int $pending @var int $cron_last @var array $log */
?>
<section class="card">
  <h1><?= e(t('menu.settings')) ?></h1>
  <dl class="kv wide">
    <dt><?= e(t('settings.db')) ?></dt><dd><?= e((string)$db_mb) ?> / <?= (int)$db_max ?> MB</dd>
    <dt><?= e(t('settings.files')) ?></dt><dd><?= (int)$files ?> / <?= (int)$files_max ?></dd>
    <dt><?= e(t('settings.pending')) ?></dt><dd><?= (int)$pending ?></dd>
    <dt><?= e(t('settings.cron')) ?></dt><dd><?= $cron_last > 0 ? e(date('Y-m-d H:i:s', $cron_last)) : e(t('settings.never')) ?></dd>
    <dt><?= e(t('settings.users')) ?></dt>
    <dd><?php foreach ($by_role as $role => $n): ?><span class="chip"><?= e(t('role.' . (int)$role)) ?>: <?= (int)$n ?></span> <?php endforeach; ?></dd>
  </dl>
  <h2><?= e(t('settings.log')) ?></h2>
  <?php if (!$log): ?>
    <p class="muted"><?= e(t('settings.log_empty')) ?></p>
  <?php else: ?>
    <pre class="log"><?= e(implode("\n", $log)) ?></pre>
  <?php endif; ?>
</section>
