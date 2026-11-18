<section class="login-page">
  <div class="login-card">
    <h1><?= e(L('login-title')) ?></h1>
    <div class="social-login-list">
      <button class="social-login social-google" type="button" disabled aria-disabled="true">
        <span class="social-brand social-google-mark" aria-hidden="true">G</span>
        <span><?= e(L('login-google')) ?></span>
      </button>
      <button class="social-login social-facebook" type="button" disabled aria-disabled="true">
        <span class="social-brand social-facebook-mark" aria-hidden="true">f</span>
        <span><?= e(L('login-facebook')) ?></span>
      </button>
    </div>
    <p class="login-setup-note"><?= e(L('login-setup-note')) ?></p>
    <a class="login-back" href="<?= e(url()) ?>"><?= e(L('login-back')) ?></a>
  </div>
</section>
