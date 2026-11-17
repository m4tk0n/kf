<section class="page-heading">
  <div>
    <p class="eyebrow">PUNK • [home-eyebrow]</p>
    <h1>[home-heading]</h1>
    <p class="muted">[home-intro]</p>
  </div>
  <a class="filter-link" href="#filtry">[home-filters]</a>
</section>

<section class="date-panel" aria-label="[date-picker-label]">
  <button class="date-arrow" type="button" aria-label="[date-previous-period]">‹‹</button>
  <button class="date-arrow" type="button" aria-label="[date-previous-day]">‹</button>
  <button class="date-current" type="button">[date-today] <span><?= e(date(lang_code() === 'cs' ? 'j. n. Y' : 'M j, Y')) ?></span></button>
  <button class="date-arrow" type="button" aria-label="[date-next-day]">›</button>
  <button class="date-arrow" type="button" aria-label="[date-next-period]">››</button>
</section>

<section class="event-section" id="akce">
  <div class="section-heading">
    <h2>[events-upcoming]</h2>
    <span class="count">0 [events-count-many]</span>
  </div>
  <div class="empty-state">
    <div class="empty-icon" aria-hidden="true">[img]</div>
    <strong>[events-empty-title]</strong>
    <p>[events-empty-description]</p>
  </div>
</section>

<section class="filters" id="filtry">
  <h2>[search-heading]</h2>
  <div class="filter-grid">
    <label>[search-label]<input type="search" placeholder="[search-placeholder]"></label>
    <label>[country-label]<select><option>[country-all]</option><option>CZ</option></select></label>
    <label>[event-type-label]<select><option>[event-type-all]</option><option>[event-type-concert]</option><option>[event-type-festival]</option><option>[event-type-other]</option></select></label>
  </div>
</section>