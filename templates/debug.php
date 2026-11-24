<?php
// templates/debug.php
declare(strict_types=1);
/** @var bool $debug_enabled */
?>
<section class="card narrow">
  <h1>DEBUG</h1>
  <p>Plovoucí diagnostický panel</p>
  <p><strong>Debug aktivní:</strong> <?= $debug_enabled ? 'Ano' : 'Ne' ?></p>
  <form method="post" action="<?= e(url('debug')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="enabled" value="<?= $debug_enabled ? '0' : '1' ?>">
    <button type="submit" class="btn" aria-pressed="<?= $debug_enabled ? 'true' : 'false' ?>">
      <?= $debug_enabled ? 'Vypnout debug' : 'Zapnout debug' ?>
    </button>
  </form>
</section>
