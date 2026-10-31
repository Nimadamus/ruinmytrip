<?php
/**
 * Shared review form fields — used by both /review/new and /review/{id}/edit so the two can
 * never drift apart.
 * @var array $dests  @var ?array $r  previous input or the existing row
 * @var ?array $boundPlace  set when the review was started from a place page
 */
$val = static fn(string $k, $d = '') => e((string) ($r[$k] ?? $d));
$sel = static fn(string $k, $v) => (string) ($r[$k] ?? '') === (string) $v ? ' selected' : '';
$bp  = $boundPlace ?? null;
?>
<?php if ($bp): /* Bound to one place: what is being reviewed is settled, so it is stated rather than
                   asked. The three identity fields ride along as hidden inputs and the server still
                   re-checks them against the place id (rmt_place_bound_id) before trusting either. */ ?>
<div class="card" style="margin:0 0 18px"><div class="card-body">
  <p class="muted" style="margin:0 0 4px;font-size:.9rem">You are reviewing</p>
  <p style="margin:0;font-size:1.15rem;font-weight:600"><?= e($bp['name']) ?></p>
  <p class="muted" style="margin:2px 0 0;font-size:.92rem">
    <?= e(rmt_place_type_label($bp['type'])) ?> in <?= e($bp['dest_name']) ?>, <?= e($bp['dest_country']) ?>
    &middot; <a href="<?= e(url(ltrim(rmt_place_path($bp), '/'))) ?>">see its page</a>
    &middot; <a href="<?= e(url('review/new')) ?>">review something else</a>
  </p>
</div></div>
<input type="hidden" name="place_id" value="<?= (int) $bp['id'] ?>">
<input type="hidden" name="destination_id" value="<?= (int) $bp['destination_id'] ?>">
<input type="hidden" name="subject_type" value="<?= e($bp['type']) ?>">
<input type="hidden" name="subject_name" value="<?= e($bp['name']) ?>">
<?php elseif (!empty($boundDest) && ($r['subject_type'] ?? '') === 'destination'): /* The city itself, from its
   "Been to X?" stars (2026-10-17): stated, like a place, so the stars and the box are on a phone's
   first screen instead of under three boxes that are already filled in. */ ?>
<p class="muted" style="margin:0 0 14px;font-size:.92rem">You are reviewing
  <strong style="color:var(--text)"><?= e((string) $boundDest['name']) ?><?= !empty($boundDest['country']) ? ', ' . e((string) $boundDest['country']) : '' ?></strong>
  as a whole &middot; <a href="<?= e(url('review/new?destination=' . (int) $boundDest['id'])) ?>">a place there instead</a></p>
<input type="hidden" name="destination_id" value="<?= (int) $boundDest['id'] ?>">
<input type="hidden" name="subject_type" value="destination">
<input type="hidden" name="subject_name" value="<?= e((string) $boundDest['name']) ?>">
<?php else: /* Unbound: where the review is about. On the quick form this block is printed after the
   stars and the box (2026-10-30), so a visitor starts by writing rather than by filling in three
   lookup fields; the edit form keeps it first. */ ob_start(); ?>
<h2 class="review-where-h" style="font-size:1.05rem;margin:18px 0 2px">Where was this?</h2>
<label for="destination_id">Destination</label>
<?php /* datalist gives type-ahead search over destinations with no JS and no extra request. */ ?>
<input list="dest-options" id="dest-search" aria-label="Destination" placeholder="Start typing: Kyoto, Lisbon, Banff…"
       autocomplete="off" value="<?php
         $cur = null;
         foreach ($dests as $d) if ((int)$d['id'] === (int)($r['destination_id'] ?? 0)) $cur = $d;
         echo $cur ? e($cur['name'].', '.$cur['country']) : '';
       ?>">
<datalist id="dest-options">
  <?php foreach ($dests as $d): ?>
    <option data-id="<?= (int)$d['id'] ?>" value="<?= e($d['name'].', '.$d['country']) ?>"></option>
  <?php endforeach; ?>
</datalist>
<select id="destination_id" name="destination_id" aria-label="Destination">
  <option value="">Select a destination</option>
  <?php foreach ($dests as $d): ?>
    <option value="<?= (int)$d['id'] ?>"<?= $sel('destination_id', $d['id']) ?>><?= e($d['name'].', '.$d['country']) ?></option>
  <?php endforeach; ?>
</select>

<label for="subject_type">What are you reviewing?</label>
<select id="subject_type" name="subject_type">
  <?php foreach (RMT_REVIEW_CATEGORIES as $c): ?>
    <option value="<?= e($c) ?>"<?= $sel('subject_type', $c) ?>><?= e(ucfirst($c)) ?></option>
  <?php endforeach; ?>
</select>

<label for="subject_name">Name of the place or experience</label>
<?php /* Suggestions are places somebody has already reviewed in the chosen destination. Picking one
         is not required -- the name is resolved to a place on the server either way (see
         rmt_place_resolve) -- but a near-miss spelling would create a second page for the same
         hotel, and offering the existing name is the cheapest way to prevent that. */ ?>
<input type="text" id="subject_name" name="subject_name" maxlength="200" list="place-options"
       autocomplete="off" placeholder="e.g. Skyline Gondola, Queenstown" value="<?= $val('subject_name') ?>">
<datalist id="place-options">
  <?php foreach (($placeOptions ?? []) as $po): ?>
    <option data-dest="<?= (int)$po['destination_id'] ?>" value="<?= e($po['name']) ?>"></option>
  <?php endforeach; ?>
</datalist>
<p class="hint" id="place-hint" style="margin:4px 0 0"></p>
<?php $whereBlock = ob_get_clean(); endif; ?>

<?php
/* The quick form (2026-10-11). A new review asks for two things up front: how many stars and a few
   honest sentences. Everything else is optional and sits in "Add more detail", which opens by
   itself when any of it already has a value (a restored draft, a validation re-render, or the
   front door's "what ruined it" sentence). The edit form keeps every field on show, labelled. */
$quick = !isset($quickForm) || $quickForm;
if (isset($whereBlock) && !$quick) { echo str_replace('<h2 class="review-where-h"', '<h2 hidden class="review-where-h"', $whereBlock); $whereBlock = null; }
$ratingNow = (int) ($r['rating'] ?? 0);
$bodyMin = defined('RMT_REVIEW_BODY_MIN') ? RMT_REVIEW_BODY_MIN : 40;
$opt = '<span class="muted" style="font-weight:400">(optional)</span>';
$extraFields = static function () use ($val, $opt): void { ?>
<label for="title">Headline <?= $opt ?></label>
<input type="text" id="title" name="title" maxlength="140"
       placeholder="Touristy but the view earns it" value="<?= $val('title') ?>">
<label for="visited_on">When were you there? <?= $opt ?></label>
<input type="date" id="visited_on" name="visited_on" max="<?= date('Y-m-d') ?>" value="<?= $val('visited_on') ?>">
<label for="what_great">What was great? <?= $opt ?></label>
<textarea id="what_great" name="what_great" rows="3" maxlength="2000"
          placeholder="The part worth the trip."><?= $val('what_great') ?></textarea>
<label for="what_ruined">What nearly ruined the trip? <?= $opt ?></label>
<textarea id="what_ruined" name="what_ruined" rows="3" maxlength="2000"
          placeholder="The thing you wish someone had warned you about."><?= $val('what_ruined') ?></textarea>
<?php }; ?>

<fieldset class="star-rate">
  <legend>Your rating</legend>
  <div class="star-rate-row">
    <span class="stars" role="radiogroup" aria-label="Rating, 1 to 5 stars">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" class="sr-only" id="rate-<?= $i ?>" name="rating" value="<?= $i ?>"<?= $ratingNow === $i ? ' checked' : '' ?>>
        <label for="rate-<?= $i ?>" title="<?= $i ?> of 5"><span aria-hidden="true">★</span><span class="sr-only"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span></label>
      <?php endfor; ?>
    </span>
    <span class="star-rate-word" id="star-word" aria-live="polite"><?= $ratingNow ? e(RMT_STAR_WORDS[$ratingNow] ?? '') : 'Tap a star' ?></span>
  </div>
</fieldset>

<?php if (!$quick): ?>
<label for="title">Headline <?= $opt ?></label>
<input type="text" id="title" name="title" maxlength="140"
       placeholder="Touristy but the view earns it" value="<?= $val('title') ?>">
<?php endif; ?>

<label for="body">Your review</label>
<textarea id="body" name="body" rows="<?= $quick ? 5 : 8 ?>" minlength="<?= (int) $bodyMin ?>" maxlength="<?= (int) (defined('RMT_REVIEW_BODY_MAX') ? RMT_REVIEW_BODY_MAX : 10000) ?>"
          aria-describedby="body-count"
          placeholder="What was it actually like? What it cost, what surprised you, what you would tell a friend."><?= $val('body') ?></textarea>
<p class="hint body-count" id="body-count" data-min="<?= (int) $bodyMin ?>"><?= (int) $bodyMin ?> characters minimum</p>
<?php if (!empty($whereBlock)) { echo $whereBlock; $whereBlock = null; } ?>

<?php if (!$quick): ?>
<label for="visited_on">When were you there? <?= $opt ?></label>
<input type="date" id="visited_on" name="visited_on" max="<?= date('Y-m-d') ?>" value="<?= $val('visited_on') ?>">
<label for="what_great">What was great? <?= $opt ?></label>
<textarea id="what_great" name="what_great" rows="3" maxlength="2000"
          placeholder="The part worth the trip."><?= $val('what_great') ?></textarea>
<label for="what_ruined">What nearly ruined the trip? <?= $opt ?></label>
<textarea id="what_ruined" name="what_ruined" rows="3" maxlength="2000"
          placeholder="The thing you wish someone had warned you about."><?= $val('what_ruined') ?></textarea>
<?php endif; ?>

<?php
/* Optional detail: traveler type and the subratings that apply to what is being reviewed.
   Three rules hold this block together:
     1. It is optional in full. A review with a headline, a body and an overall rating publishes
        with this whole section untouched, which is what keeps the form quick.
     2. Only the aspects for the selected category are enabled. A restaurant review is never asked
        about hotel rooms. Every category's fieldset is rendered so the switch is instant, and the
        inactive ones are DISABLED, which means the browser does not submit them at all.
     3. With JavaScript off, the fieldset for the currently selected category is the enabled one and
        everything still works; the server re-checks the category against the aspects regardless.
   It starts collapsed. A wall of eleven selects above the publish button is how a form stops being
   filled in. */
$aspectValues = $aspectValues ?? [];
$currentCat = (string) ($r['subject_type'] ?? ($bp['type'] ?? 'destination'));
if (!isset(RMT_ASPECTS_BY_CATEGORY[$currentCat])) $currentCat = 'destination';
$anyDetail = $aspectValues || !empty($r['traveler_type'])
          || ($quick && (trim((string) ($r['title'] ?? '')) !== '' || !empty($r['visited_on'])
                          || trim((string) ($r['what_great'] ?? '')) !== '' || trim((string) ($r['what_ruined'] ?? '')) !== ''));
?>
<details class="card" id="review-detail" style="margin:6px 0 18px"<?= $anyDetail ? ' open' : '' ?>>
  <summary style="cursor:pointer;padding:12px 14px;font-weight:600">
    Add more detail <span class="muted" style="font-weight:400">(optional<?= $quick ? ': headline, dates, the best and worst part' : '' ?>)</span>
  </summary>
  <div class="card-body" style="padding-top:0">
    <?php if ($quick) $extraFields(); ?>

    <label for="traveler_type">Who were you travelling with? <span class="muted">(optional)</span></label>
    <select id="traveler_type" name="traveler_type">
      <option value="">Rather not say</option>
      <?php foreach (RMT_TRAVELER_TYPES as $tt): ?>
        <option value="<?= e($tt) ?>"<?= ((string)($r['traveler_type'] ?? '') === $tt ? ' selected' : '') ?>>
          <?= e((string) rmt_traveler_type_label($tt)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php foreach (RMT_ASPECTS_BY_CATEGORY as $cat => $aspects): ?>
      <fieldset class="aspect-group" data-category="<?= e($cat) ?>"
                style="border:0;padding:0;margin:16px 0 0<?= $cat === $currentCat ? '' : ';display:none' ?>"
                <?= $cat === $currentCat ? '' : 'disabled' ?>>
        <legend style="padding:0;font-size:.95rem;font-weight:600">Rate the details <span class="muted" style="font-weight:400">(optional)</span></legend>
        <div class="grid g-2" style="gap:14px">
          <?php foreach ($aspects as $aspect): $meta = RMT_REVIEW_ASPECTS[$aspect]; $fid = 'aspect-'.$cat.'-'.$aspect; ?>
            <div>
              <label for="<?= e($fid) ?>"><?= e($meta['label']) ?></label>
              <select id="<?= e($fid) ?>" name="aspect[<?= e($aspect) ?>]">
                <option value="">Not rated</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <option value="<?= $i ?>"<?= (($aspectValues[$aspect] ?? null) === $i ? ' selected' : '') ?>>
                    <?= $i ?> · <?= e($meta['scale'][$i]) ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>
</details>

<script>
// Show the subratings for whatever is being reviewed, and disable the rest so the browser does not
// submit them. Progressive enhancement: with this script off, the group rendered as active is the
// only enabled one and the server drops anything that does not belong to the posted category.
(function () {
  var type = document.getElementById('subject_type');
  if (!type) return;
  var groups = document.querySelectorAll('.aspect-group');
  function apply() {
    groups.forEach(function (g) {
      var on = g.getAttribute('data-category') === type.value;
      g.style.display = on ? '' : 'none';
      g.disabled = !on;
    });
  }
  type.addEventListener('change', apply);
  apply();
})();
</script>

<script>
// Map the type-ahead box back onto the real <select>. Progressive enhancement only: with JS off,
// the select itself is still there and fully usable.
(function () {
  var box = document.getElementById('dest-search'),
      sel = document.getElementById('destination_id'),
      list = document.getElementById('dest-options');
  if (!box || !sel || !list) return;
  sel.style.display = 'none';
  // The visible box is now the one to fill in, so the "Destination" label names it.
  var lab = document.querySelector('label[for="destination_id"]');
  if (lab) lab.htmlFor = 'dest-search';
  box.addEventListener('input', function () {
    var v = box.value.trim().toLowerCase();
    var hit = Array.prototype.find.call(list.options, function (o) { return o.value.toLowerCase() === v; });
    // Typing "Kyoto" alone never exactly matches an option's full "Kyoto, Japan" text, so a user
    // who types a name and hits submit without clicking the suggestion got a bare "Choose a
    // destination" error with no clue why. If the partial text uniquely identifies one
    // destination, bind it live instead of requiring the exact click.
    if (!hit && v) {
      var matches = Array.prototype.filter.call(list.options, function (o) { return o.value.toLowerCase().indexOf(v) === 0; });
      if (matches.length === 1) hit = matches[0];
    }
    sel.value = hit ? hit.dataset.id : '';
    if (window.rmtSyncPlaces) window.rmtSyncPlaces();
  });
})();

// Show only the places that belong to the destination currently chosen, and tell the writer when
// the name they typed is one that already has a page. Progressive enhancement only: with JS off the
// full suggestion list is still offered and the server resolves the name exactly the same way.
(function () {
  var sel = document.getElementById('destination_id'),
      list = document.getElementById('place-options'),
      name = document.getElementById('subject_name'),
      hint = document.getElementById('place-hint');
  if (!sel || !list || !name || !hint) return;

  var all = Array.prototype.slice.call(list.options);

  function currentDest() { return sel.value || ''; }

  window.rmtSyncPlaces = function () {
    var dest = currentDest();
    while (list.firstChild) list.removeChild(list.firstChild);
    // With no destination picked yet there is nothing to scope suggestions to, so offer none rather
    // than every place on the site -- a name from the wrong city is worse than no suggestion.
    if (!dest) { hint.textContent = ''; return; }
    all.forEach(function (o) { if (o.dataset.dest === dest) list.appendChild(o); });
    describe();
  };

  function norm(s) { return s.toLowerCase().replace(/^(the|le|la|el|il)\s+/, '').replace(/[^a-z0-9]+/g, ' ').trim(); }

  function describe() {
    var dest = currentDest(), v = norm(name.value);
    if (!dest || !v) { hint.textContent = ''; return; }
    var hit = all.some(function (o) { return o.dataset.dest === dest && norm(o.value) === v; });
    hint.textContent = hit
      ? 'This place already has a page, so your review will be added to it.'
      : 'New place. Your review will start its page.';
  }

  sel.addEventListener('change', window.rmtSyncPlaces);
  name.addEventListener('input', describe);
  window.rmtSyncPlaces();
})();
</script>

<script>
// The live count under the review box and the word beside the stars. Progressive enhancement only:
// the server enforces the same minimum and the stars are plain radio buttons without it.
(function () {
  var box = document.getElementById('body'), out = document.getElementById('body-count');
  if (box && out) {
    var min = parseInt(out.getAttribute('data-min'), 10) || 40;
    var paint = function () {
      var n = box.value.trim().length;
      out.textContent = n >= min ? n + ' characters. Good to go.' : n + ' of ' + min + ' characters minimum';
      out.classList.toggle('ok', n >= min);
    };
    box.addEventListener('input', paint); paint();
  }
  var words = <?= json_encode(RMT_STAR_WORDS) ?>, word = document.getElementById('star-word');
  document.querySelectorAll('.star-rate input[name="rating"]').forEach(function (r) {
    r.addEventListener('change', function () { if (word) word.textContent = words[r.value] || ''; });
  });
})();
</script>
