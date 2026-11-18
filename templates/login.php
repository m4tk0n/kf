<section class="login-page">
  <div class="login-card">
    <h1><?= e(L('login-title')) ?></h1>
    <div class="social-login-list">
      <a class="social-login social-google" href="<?= e(url('auth/google/start')) ?>">
        <span class="social-brand social-google-mark" aria-hidden="true">G</span>
        <span><?= e(L('login-google')) ?></span>
      </a>
      <?php if (facebook_oauth_enabled()): ?>
      <a class="social-login social-facebook" href="<?= e(url('auth/facebook/start')) ?>">
        <span class="social-brand social-facebook-mark" aria-hidden="true">f</span>
        <span><?= e(L('login-facebook')) ?></span>
      </a>
      <?php else: ?>
      <button class="social-login social-facebook" type="button" disabled aria-disabled="true">
        <span class="social-brand social-facebook-mark" aria-hidden="true">f</span>
        <span><?= e(L('login-facebook')) ?></span>
      </button>
      <?php endif; ?>
    </div>
    <a class="login-back" href="<?= e(url()) ?>"><?= e(L('login-back')) ?></a>
  </div>
</section>
