<?php
/**
 * @var array $p           the place, merged with anything just posted so a failed save keeps it
 * @var array $orig        the place as stored
 * @var array $errors
 * @var array $categories  subcategories valid for this place's type
 * @var array $grid        ['closed'=>[dow=>bool], 'slots'=>[dow=>[['opens'=>,'closes'=>], ...]]]
 * @var array $photos      rows from place_photos
 * @var array $reviewPhotos traveler photos attached to reviews of this place
 */
$v = static fn(string $k) => e((string) ($p[$k] ?? ''));
?>
<div class="wrap">
  <p class="crumbs">
    <a href="<?= e(url('admin')) ?>">Moderation</a> /
    <a href="<?= e(url('admin/places')) ?>">Places</a> /
    <?= e($orig['name']) ?>
  </p>
  <h1 style="margin:.2rem 0 .3rem"><?= e($orig['name']) ?></h1>
  <p class="muted" style="margin:0 0 6px">
    <?= e(rmt_place_type_label((string) $orig['type'])) ?> in <?= e($orig['dest_name']) ?>,
    <?= e($orig['dest_country']) ?> &middot;
    <a href="<?= e(url('p/'.$orig['slug'])) ?>">view the page</a>
  </p>
  <p class="hint" style="margin:0 0 18px">
    Place #<?= (int) $orig['id'] ?>. The id is the identity and never changes; renaming moves the
    slug and leaves a 301 behind. Leave a field blank when we do not know it — a blank renders as
    nothing, and a guess renders as a fact.
  </p>

  <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('admin/place/'.(int)$orig['id'])) ?>"><?= csrf_field() ?>

    <h2 style="font-size:1.05rem;margin:20px 0 8px">Identity</h2>
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= $v('name') ?>" maxlength="200">
    <p class="hint" style="margin:.2rem 0 12px">
      Changing this rewrites the slug and records the old one, so <?= e('/p/'.$orig['slug']) ?>
      keeps working as a permanent redirect.
    </p>

    <label for="category_id">Subcategory</label>
    <select id="category_id" name="category_id">
      <option value="0">— None —</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>"<?= (int) ($p['category_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <p class="hint" style="margin:.2rem 0 12px">Only subcategories that belong to a <?= e((string) $orig['type']) ?>.</p>

    <h2 style="font-size:1.05rem;margin:24px 0 8px">Where it is</h2>
    <label for="street_address">Street address</label>
    <input type="text" id="street_address" name="street_address" value="<?= $v('street_address') ?>" maxlength="200">

    <div class="grid g-2" style="gap:14px">
      <div>
        <label for="neighborhood">Neighborhood</label>
        <input type="text" id="neighborhood" name="neighborhood" value="<?= $v('neighborhood') ?>" maxlength="120">
      </div>
      <div>
        <label for="postal_code">Postal code</label>
        <input type="text" id="postal_code" name="postal_code" value="<?= $v('postal_code') ?>" maxlength="32">
      </div>
    </div>

    <label for="region">Region or state</label>
    <input type="text" id="region" name="region" value="<?= $v('region') ?>" maxlength="120"
           placeholder="<?= e((string) ($orig['dest_region'] ?? '')) ?>">
    <p class="hint" style="margin:.2rem 0 12px">
      City and country come from the destination and are not editable here — one truth, not two.
      Fill this in only when the place sits in a different region from its destination hub.
    </p>

    <div class="grid g-2" style="gap:14px">
      <div>
        <label for="lat">Latitude</label>
        <input type="text" id="lat" name="lat" value="<?= $v('lat') ?>" inputmode="decimal">
      </div>
      <div>
        <label for="lng">Longitude</label>
        <input type="text" id="lng" name="lng" value="<?= $v('lng') ?>" inputmode="decimal">
      </div>
    </div>
    <p class="hint" style="margin:.2rem 0 12px">
      Both or neither. (0, 0) is rejected — it is the Atlantic, and it is what a failed geocode
      writes.
    </p>

    <h2 style="font-size:1.05rem;margin:24px 0 8px">Contact and price</h2>
    <div class="grid g-2" style="gap:14px">
      <div>
        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>" maxlength="40">
      </div>
      <div>
        <label for="website_url">Website</label>
        <input type="text" id="website_url" name="website_url" value="<?= $v('website_url') ?>" maxlength="500">
      </div>
    </div>

    <div class="grid g-2" style="gap:14px">
      <div>
        <label for="price_level">Price level</label>
        <select id="price_level" name="price_level">
          <option value="">— Unknown —</option>
          <?php for ($i = 1; $i <= 4; $i++): ?>
            <option value="<?= $i ?>"<?= (int) ($p['price_level'] ?? 0) === $i ? ' selected' : '' ?>>
              <?= str_repeat('$', $i) ?> — <?= e((string) rmt_place_price_title($i)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label for="timezone">Timezone</label>
        <input type="text" id="timezone" name="timezone" value="<?= $v('timezone') ?>" list="tz-options" maxlength="64"
               placeholder="Europe/Lisbon">
        <datalist id="tz-options">
          <?php foreach (timezone_identifiers_list() as $tz): ?><option value="<?= e($tz) ?>"><?php endforeach; ?>
        </datalist>
      </div>
    </div>
    <p class="hint" style="margin:.2rem 0 12px">
      Without a timezone the page cannot say whether the place is open now, and it will say nothing
      rather than guess from the server clock.
    </p>

    <h2 style="font-size:1.05rem;margin:24px 0 8px">Opening hours</h2>
    <p class="hint" style="margin:0 0 10px">
      A day left entirely blank means "we do not know" and is simply absent from the page. Tick
      Closed to say a day is actually shut. A closing time earlier than the opening time is an
      overnight interval, which is how 21:00–02:00 is stored.
    </p>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.93rem">
        <?php foreach (RMT_DAY_NAMES as $dow => $dayName): ?>
          <tr style="border-bottom:1px solid #f1f1f5">
            <th style="text-align:left;padding:8px 10px 8px 0;width:6.5rem;font-weight:600"><?= e($dayName) ?></th>
            <td style="padding:8px 10px 8px 0;white-space:nowrap">
              <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400">
                <input type="checkbox" name="hours[closed][<?= $dow ?>]" value="1"
                       <?= !empty($grid['closed'][$dow]) ? 'checked' : '' ?>> Closed
              </label>
            </td>
            <td style="padding:8px 0">
              <?php foreach ($grid['slots'][$dow] as $i => $slot): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;margin:0 10px 4px 0">
                  <input type="time" name="hours[opens][<?= $dow ?>][<?= $i ?>]" value="<?= e($slot['opens']) ?>"
                         style="width:8.5rem" aria-label="<?= e($dayName) ?> opens">
                  <span class="muted">–</span>
                  <input type="time" name="hours[closes][<?= $dow ?>][<?= $i ?>]" value="<?= e($slot['closes']) ?>"
                         style="width:8.5rem" aria-label="<?= e($dayName) ?> closes">
                </span>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <p class="hint" style="margin:8px 0 12px">
      Three slots a day covers a lunch service, a dinner service and a late bar. Filling all three
      and saving frees nothing up — if a venue genuinely needs a fourth, say so and the grid grows.
    </p>

    <h2 style="font-size:1.05rem;margin:24px 0 8px">Where this came from</h2>
    <div class="grid g-2" style="gap:14px">
      <div>
        <label for="data_source">Source</label>
        <input type="text" id="data_source" name="data_source" value="<?= $v('data_source') ?>" maxlength="60"
               list="src-options" placeholder="official_site">
        <datalist id="src-options">
          <option value="official_site"><option value="owner"><option value="editorial">
          <option value="osm"><option value="wikidata"><option value="tourism_board">
        </datalist>
      </div>
      <div>
        <label for="data_source_url">Source URL</label>
        <input type="text" id="data_source_url" name="data_source_url" value="<?= $v('data_source_url') ?>" maxlength="500">
      </div>
    </div>
    <p class="hint" style="margin:.2rem 0 16px">
      Saving stamps the checked date automatically. We publish business facts we can point at, and
      a fact with no source is a fact we should not be printing.
      <?php if (!empty($orig['data_checked_at'])): ?>
        Last checked <?= e(substr((string) $orig['data_checked_at'], 0, 16)) ?>.
      <?php endif; ?>
    </p>

    <div style="margin:22px 0;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary">Save</button>
      <a class="btn btn-ghost" href="<?= e(url('p/'.$orig['slug'])) ?>">View the page</a>
    </div>
  </form>

  <h2 style="font-size:1.05rem;margin:28px 0 8px">Photos</h2>
  <?php if (!$photos && !$reviewPhotos): ?>
    <p class="muted" style="margin:0 0 20px">
      No photos yet, from anyone. Nothing to choose a cover from.
    </p>
  <?php endif; ?>

  <?php if ($photos): ?>
    <div class="grid g-4" style="gap:12px;margin:0 0 18px">
      <?php foreach ($photos as $ph): ?>
        <div class="card"><div class="card-body" style="padding:10px">
          <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover"
               src="<?= e(abs_url(rmt_place_photo_url($ph))) ?>" alt="<?= e((string) ($ph['alt_text'] ?? '')) ?>">
          <p class="hint" style="margin:6px 0 4px">
            <?= !empty($ph['is_cover']) ? '<strong>Cover</strong>' : 'Gallery' ?>
            <?php if (!empty($ph['review_photo_id'])): ?> &middot; from a review<?php endif; ?>
          </p>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php if (empty($ph['is_cover'])): ?>
              <form method="post" action="<?= e(url('admin/place/'.(int)$orig['id'].'/photo')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="cover">
                <input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
                <button class="btn btn-ghost" style="padding:4px 10px;font-size:.85rem">Make cover</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('admin/place/'.(int)$orig['id'].'/photo')) ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="remove">
              <input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
              <button class="btn btn-ghost" style="padding:4px 10px;font-size:.85rem">Remove</button>
            </form>
          </div>
        </div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($reviewPhotos): ?>
    <h3 style="font-size:.98rem;margin:0 0 6px">Traveler photos from reviews of this place</h3>
    <p class="hint" style="margin:0 0 10px">
      Choosing one stores a reference, not a copy: the same image file, credited to the traveler,
      and it disappears from here if their review does.
    </p>
    <div class="grid g-4" style="gap:12px;margin:0 0 26px">
      <?php foreach ($reviewPhotos as $rp): ?>
        <form method="post" action="<?= e(url('admin/place/'.(int)$orig['id'].'/photo')) ?>" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="adopt">
          <input type="hidden" name="review_photo_id" value="<?= (int) $rp['id'] ?>">
          <img class="card-media" loading="lazy" style="aspect-ratio:1;object-fit:cover"
               src="<?= e(abs_url(rmt_place_photo_url($rp))) ?>" alt="<?= e((string) ($rp['caption'] ?? '')) ?>">
          <input type="text" name="alt_text" placeholder="Alt text (optional)" maxlength="300"
                 style="margin:6px 0 4px;font-size:.85rem">
          <button class="btn btn-ghost" style="padding:4px 10px;font-size:.85rem;width:100%">Use as cover</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
