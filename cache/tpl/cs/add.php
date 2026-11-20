<?php
$type = (string)($_GET['type'] ?? '');
$types = [
    'event' => ['title' => L('add-event'), 'heading' => L('add-event-heading'), 'description' => L('add-event-description')],
    'venue' => ['title' => L('add-venue'), 'heading' => L('add-venue-heading'), 'description' => L('add-venue-description')],
    'band' => ['title' => L('add-band'), 'heading' => L('add-band-heading'), 'description' => L('add-band-description')],
];
$selected = $types[$type] ?? null;
$countries = $genres = $venues = [];
if ($selected) {
    $label = lang_code() === 'en' ? 'en' : 'cs';
    if ($type === 'band') {
        $countries = db()->query("SELECT iso2, {$label} AS name FROM " . tbl('countries') . " ORDER BY name")->fetchAll();
        $genres = db()->query("SELECT id, name FROM " . tbl('genres') . " ORDER BY name")->fetchAll();
    } elseif ($type === 'event') {
        $venues = db()->query("SELECT v.id, v.name, c.{$label} AS city FROM " . tbl('venues') . " v JOIN " . tbl('cities') . " c ON c.id=v.city_id ORDER BY v.name")->fetchAll();
    }
}

// Koncept klubu v session: buď náhled (?preview=1), nebo předvyplněný formulář (?edit=1).
$draft = $_SESSION['add_draft'] ?? null;
$draft = ($type === 'venue' && is_array($draft) && ($draft['type'] ?? '') === 'venue') ? $draft : null;
$isPreview = $draft !== null && isset($_GET['preview']) && !empty($draft['payload']) && !empty($draft['fields']);
$isEdit = $draft !== null && !$isPreview && isset($_GET['edit']);
$old = $isEdit ? (array)($draft['form'] ?? []) : [];
$keptFiles = $isEdit ? array_values(array_filter((array)($draft['files'] ?? []), 'is_string')) : [];
$val = static fn(string $key): string => (string)($old[$key] ?? '');
$tmpBase = $draft !== null ? rtrim(cfg('app.base_url'), '/') . '/img/tmp/' . $draft['token'] . '/' : '';

$error = (string)($_SESSION['add_error'] ?? '');
$success = (string)($_SESSION['add_success'] ?? '');
unset($_SESSION['add_error'], $_SESSION['add_success']);

$heading = $isPreview ? L('add-preview-title') : ($selected ? $selected['heading'] : L('add-title'));
$backHref = $isPreview ? url('add') . '?type=venue&edit=1' : ($selected ? url('add') : url());
?>
<section class="account-page">
  <div class="account-page-heading">
    <h1><?= e($heading) ?></h1>
    <a class="back-button" href="<?= e($backHref) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <?php if ($error !== ''): ?><p class="form-message" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success !== ''): ?><p class="form-message" role="status"><?= e($success) ?></p><?php endif; ?>
  <?php if (!$selected): ?>
    <p class="add-intro"><?= e(L('add-intro')) ?></p>
    <div class="add-options">
      <?php foreach ($types as $key => $item): ?>
        <a class="add-option" href="<?= e(url('add')) ?>?type=<?= e($key) ?>">
          <strong><?= e($item['title']) ?></strong>
          <span><?= e($item['description']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($isPreview): ?>
    <p class="add-intro"><?= e(L('add-preview-note')) ?></p>
    <?= view('venue-detail', ['venue' => venue_preview($draft)]) ?>
    <?php
    $linkUrls = [];
    foreach ((array)($draft['payload']['images'] ?? []) as $image) {
        if (($image['type'] ?? '') === 'url') $linkUrls[] = (string)$image['value'];
    }
    ?>
    <?php if ($linkUrls): ?>
      <p class="add-intro preview-urls"><?= e(L('add-preview-urls')) ?><br>
        <?= e(implode(' · ', $linkUrls)) ?></p>
    <?php endif; ?>
    <form class="preview-actions" method="post" action="<?= e(url('add')) ?>?type=venue">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="type" value="venue">
      <button type="submit" name="step" value="edit" class="back-button"><?= e(L('add-preview-edit')) ?></button>
      <button type="submit" name="step" value="confirm" class="primary-button"><?= e(L('add-preview-confirm')) ?></button>
    </form>
  <?php else: ?>
    <p class="add-intro"><?= e($selected['description']) ?></p>
    <form class="account-form add-form" method="post" action="<?= e(url('add')) ?>?type=<?= e($type) ?>" autocomplete="off"<?= $type === 'venue' ? ' enctype="multipart/form-data"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <label class="autocomplete-field"><?= e(L('add-name')) ?>
        <input name="name" maxlength="120" required value="<?= e($val('name')) ?>"<?= $type === 'venue' ? ' data-autocomplete="venues" data-min-chars="3" aria-autocomplete="list" aria-expanded="false"' : '' ?>>
        <?php if ($type === 'venue'): ?>
          <div class="autocomplete-results" role="listbox" hidden></div>
          <div class="venue-exists" role="status" hidden></div>
        <?php endif; ?>
      </label>
      <?php if ($type === 'band'): ?>
        <label><?= e(L('country-label')) ?>
          <select name="country" required><option value="">—</option>
            <?php foreach ($countries as $country): ?><option value="<?= e($country['iso2']) ?>"><?= e($country['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(L('add-genre')) ?>
          <select name="genre" required><option value="">—</option>
            <?php foreach ($genres as $genre): ?><option value="<?= (int)$genre['id'] ?>"><?= e($genre['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php elseif ($type === 'venue'): ?>
        <label class="autocomplete-field"><?= e(L('country-label')) ?>
          <input name="country_display" maxlength="100" required value="<?= e($val('country_display')) ?>" data-autocomplete="countries" data-min-chars="2" aria-autocomplete="list" aria-expanded="false" autocomplete="off">
          <input type="hidden" name="country_id" value="<?= e($val('country_id')) ?>">
          <div class="autocomplete-results" role="listbox" hidden></div>
        </label>
        <label><?= e(L('add-city')) ?>
          <input name="city" maxlength="100" required value="<?= e($val('city')) ?>">
        </label>
        <label><?= e(L('add-address')) ?>
          <input name="address" maxlength="190" required value="<?= e($val('address')) ?>">
        </label>
        <label><?= e(L('add-phone')) ?>
          <input name="phone" type="tel" maxlength="40" value="<?= e($val('phone')) ?>">
        </label>
        <label><?= e(L('add-description')) ?>
          <textarea name="description" rows="4" maxlength="3000"><?= e($val('description')) ?></textarea>
        </label>
        <div class="form-grid-two">
          <label><?= e(L('add-latitude')) ?>
            <input name="latitude" type="number" min="-90" max="90" step="any" inputmode="decimal" value="<?= e($val('latitude')) ?>">
          </label>
          <label><?= e(L('add-longitude')) ?>
            <input name="longitude" type="number" min="-180" max="180" step="any" inputmode="decimal" value="<?= e($val('longitude')) ?>">
          </label>
        </div>
        <label><?= e(L('add-image-urls')) ?>
          <textarea name="image_urls" rows="3" placeholder="https://…"><?= e($val('image_urls')) ?></textarea>
          <small><?= e(L('add-image-urls-help')) ?></small>
        </label>
        <?php if ($keptFiles): ?>
          <fieldset class="kept-images">
            <legend><?= e(L('add-kept-images')) ?></legend>
            <div class="kept-list">
              <?php foreach ($keptFiles as $file): ?>
                <label class="kept-image">
                  <img src="<?= e($tmpBase . rawurlencode($file)) ?>" alt="">
                  <span><input type="checkbox" name="remove_images[]" value="<?= e($file) ?>"> <?= e(L('add-remove-image')) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endif; ?>
        <label><?= e(L('add-image-files')) ?>
          <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
          <small><?= e(L('add-image-files-help')) ?></small>
        </label>
      <?php else: ?>
        <label><?= e(L('add-venue')) ?>
          <select name="venue_id" required><option value="">—</option>
            <?php foreach ($venues as $venue): ?><option value="<?= (int)$venue['id'] ?>"><?= e($venue['name'] . ' – ' . $venue['city']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(L('event-type-label')) ?>
          <select name="event_type" required>
            <option value="1"><?= e(L('event-type-concert')) ?></option>
            <option value="2"><?= e(L('event-type-festival')) ?></option>
            <option value="3"><?= e(L('event-type-other')) ?></option>
          </select>
        </label>
        <label><?= e(L('add-starts')) ?><input type="datetime-local" name="starts_at" required></label>
        <label><?= e(L('add-ends')) ?><input type="datetime-local" name="ends_at"></label>
      <?php endif; ?>

      <?php if ($type === 'venue'): ?>
      <script>
      (() => {
        const form = document.querySelector('.add-form');
        if (!form) return;
        const base = <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>;
        const text = <?= json_encode([
            'note' => L('add-exists-note'),
            'open' => L('add-exists-open'),
            'hint' => L('add-exists-hint'),
            'pick' => L('add-error-reference'),
        ], JSON_UNESCAPED_UNICODE) ?>;
        const notice = form.querySelector('.venue-exists');

        // Existující klub se jen ukáže s odkazem na detail; do pole se nic nepřepisuje.
        const showExisting = (item) => {
          const head = document.createElement('strong');
          const where = [item.city ? item.city + ' (' + item.cc + ')' : '', item.address].filter(Boolean).join(', ');
          head.textContent = item.value + (where ? ' – ' + where : '');
          const link = document.createElement('a');
          link.href = item.url;
          link.target = '_blank';
          link.rel = 'noopener';
          link.textContent = text.open;
          const hint = document.createElement('small');
          hint.textContent = text.hint;
          notice.replaceChildren(document.createTextNode(text.note + ' '), head, document.createElement('br'), link, document.createElement('br'), hint);
          notice.hidden = false;
        };

        form.querySelectorAll('[data-autocomplete]').forEach((input) => {
          const wrap = input.closest('.autocomplete-field');
          const list = wrap.querySelector('.autocomplete-results');
          const kind = input.dataset.autocomplete;
          const min = Number(input.dataset.minChars || 2);
          let timer = 0;
          let active = -1;
          let items = [];
          const hiddenCountry = kind === 'countries' ? form.querySelector('input[name="country_id"]') : null;
          const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
          const choose = (item) => {
            if (kind === 'countries') {
              input.value = item.iso3;
              hiddenCountry.value = String(item.id);
            } else {
              showExisting(item);
            }
            close();
          };
          const render = (rows) => {
            items = rows;
            list.replaceChildren();
            rows.forEach((item) => {
              const option = document.createElement('button');
              option.type = 'button';
              option.className = 'autocomplete-option';
              option.setAttribute('role', 'option');
              if (kind === 'countries') {
                option.textContent = item.label;
              } else {
                const title = document.createElement('span');
                title.textContent = item.value;
                const sub = document.createElement('small');
                sub.textContent = [item.city ? item.city + ' (' + item.cc + ')' : '', item.address].filter(Boolean).join(' · ');
                option.append(title, sub);
              }
              option.addEventListener('click', () => choose(item));
              list.append(option);
            });
            list.hidden = rows.length === 0;
            input.setAttribute('aria-expanded', rows.length ? 'true' : 'false');
          };
          input.addEventListener('input', () => {
            if (hiddenCountry) hiddenCountry.value = '';
            if (kind === 'venues' && notice) notice.hidden = true;
            const term = input.value.trim();
            clearTimeout(timer);
            if (term.length < min) { close(); return; }
            timer = setTimeout(async () => {
              try {
                const response = await fetch(base + 'suggest/' + (kind === 'venues' ? 'venues' : 'countries') + '?q=' + encodeURIComponent(term), {
                  headers: { 'Accept': 'application/json' },
                  credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('Autocomplete request failed');
                render(await response.json());
              } catch (_) { close(); }
            }, 180);
          });
          input.addEventListener('keydown', (event) => {
            if (list.hidden || !items.length) return;
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
              event.preventDefault();
              active = (active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
              [...list.children].forEach((node, i) => node.classList.toggle('active', i === active));
            }
            if (event.key === 'Enter' && active >= 0) {
              event.preventDefault();
              choose(items[active]);
            }
          });
          input.addEventListener('blur', () => setTimeout(close, 140));
        });
        form.addEventListener('submit', (event) => {
          const countryInput = form.querySelector('input[name="country_display"]');
          const countryId = form.querySelector('input[name="country_id"]');
          if (countryInput && !countryId.value) {
            event.preventDefault();
            countryInput.setCustomValidity(text.pick);
            countryInput.reportValidity();
            countryInput.addEventListener('input', () => countryInput.setCustomValidity(''), { once: true });
          }
        });
      })();
      </script>
      <?php endif; ?>

      <button type="submit"<?= $type === 'venue' ? ' name="step" value="preview"' : '' ?> class="primary-button"><?= e($type === 'venue' ? L('add-preview-button') : L('add-submit')) ?></button>
    </form>
  <?php endif; ?>
</section>
