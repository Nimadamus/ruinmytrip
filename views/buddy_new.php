<?php /** @var array $dests @var array $errors @var array $b @var bool $isEdit */
$isEdit = $isEdit ?? false;
$val = static function (string $k, string $default = '') use ($b) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return is_array($_POST[$k] ?? null) ? '' : trim((string) ($_POST[$k] ?? ''));
    $v = $b[$k] ?? $default;
    return is_array($v) ? '' : (string) $v;
};
$ints = $_SERVER['REQUEST_METHOD'] === 'POST' ? (array) ($_POST['interests'] ?? []) : (array) ($b['interests'] ?? []);
$type = $val('trip_type', 'trip');
$action = $isEdit ? url('buddy/' . (int) $b['id'] . '/edit') : url('buddies/new');
?>
<div class="wrap"><div class="form-card form-wide">
  <h1><?= $isEdit ? 'Edit your trip' : 'Post your trip' ?></h1>
  <p class="muted">Where you are going, when, and who you would like to go with. Travelers heading the same way can find you, you choose who to accept, and accepted buddies can message you.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if (!$isEdit): ?><input type="hidden" name="_submit" value="<?= e(rmt_submit_token('buddy_new')) ?>"><?php endif; ?>

    <label for="trip_type">Kind of trip</label>
    <select id="trip_type" name="trip_type" required>
      <?php foreach (RMT_BUDDY_TYPES as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>

    <fieldset class="cruise-fields" id="cruise-fields" style="border:1px solid var(--line);border-radius:var(--radius-sm);padding:10px 14px;margin:14px 0">
      <legend style="font-weight:700;padding:0 6px">Cruise details</legend>
      <p class="hint" style="margin:0">Line and ship are how people on the same sailing find each other. Never add your cabin number.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:180px"><label for="cruise_line">Cruise line</label>
          <input type="text" id="cruise_line" name="cruise_line" maxlength="80" value="<?= e($val('cruise_line')) ?>" placeholder="Royal Caribbean"></div>
        <div style="flex:1;min-width:180px"><label for="ship">Ship</label>
          <input type="text" id="ship" name="ship" maxlength="80" value="<?= e($val('ship')) ?>" placeholder="Icon of the Seas"></div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:180px"><label for="departure_port">Departure port</label>
          <input type="text" id="departure_port" name="departure_port" maxlength="80" value="<?= e($val('departure_port')) ?>" placeholder="Miami"></div>
        <div style="flex:2;min-width:220px"><label for="itinerary">Itinerary</label>
          <input type="text" id="itinerary" name="itinerary" maxlength="200" value="<?= e($val('itinerary')) ?>" placeholder="Perfect Day at CocoCay, Cozumel, Roatan"></div>
      </div>
    </fieldset>

    <label for="title">Title <span class="hint">(optional, we will use the place and dates)</span></label>
    <input type="text" id="title" name="title" maxlength="140" value="<?= e($val('title')) ?>"
           placeholder="Two weeks in Tokyo and Kyoto, looking for people to explore with">

    <div id="city-fields">
    <label for="destination_id">City</label>
    <select id="destination_id" name="destination_id">
      <option value="">Not listed, or a cruise</option>
      <?php foreach ($dests as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= $val('destination_id') === (string) $d['id'] ? ' selected' : '' ?>><?= e($d['name'] . ', ' . $d['country']) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="hint" style="margin:.2rem 0 0">Picking a city puts you in front of everyone searching it, and tells travelers whose dates overlap.</p>
    </div>

    <label for="where_text">Where, in your words <span class="hint">(a region, a route, a country)</span></label>
    <input type="text" id="where_text" name="where_text" maxlength="140" value="<?= e($val('where_text')) ?>"
           placeholder="Thailand: Bangkok, Chiang Mai and the islands">


    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:160px"><label for="date_from">Leaving</label>
        <input type="date" id="date_from" name="date_from" required value="<?= e($val('date_from')) ?>"></div>
      <div style="flex:1;min-width:160px"><label for="date_to">Back</label>
        <input type="date" id="date_to" name="date_to" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_to')) ?>"></div>
    </div>
    <label style="display:flex;gap:10px;align-items:center;font-weight:400">
      <input type="checkbox" name="flexible" value="1" style="width:auto" <?= $val('flexible') === '1' ? 'checked' : '' ?>> <span>Dates are flexible</span>
    </label>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:160px"><label for="travel_party">Travelling</label>
        <select id="travel_party" name="travel_party"><option value="">Rather not say</option>
          <?php foreach (RMT_BUDDY_PARTIES as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('travel_party') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
      <div style="flex:1;min-width:160px"><label for="spots">How many people</label>
        <input type="number" id="spots" name="spots" min="1" max="20" value="<?= e($val('spots', '1')) ?>"></div>
      <div style="flex:1;min-width:160px"><label for="budget">Budget</label>
        <select id="budget" name="budget">
          <?php foreach (RMT_BUDDY_BUDGETS as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('budget', 'any') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    </div>

    <p style="margin:14px 0 4px;font-weight:600">What you are into on this trip</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px 16px">
      <?php foreach (RMT_INTERESTS as $k => $v): ?>
        <label style="display:flex;gap:6px;align-items:center;font-weight:400;margin:0"><input type="checkbox" name="interests[]" value="<?= e($k) ?>" style="width:auto"<?= in_array($k, $ints, true) ? ' checked' : '' ?>> <?= e($v) ?></label>
      <?php endforeach; ?>
    </div>

    <p style="margin:14px 0 4px;font-weight:600">Ages you would like to travel with <span class="hint">(optional, 18 and over)</span></p>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:120px"><label for="age_min">From</label><input type="number" id="age_min" name="age_min" min="18" max="99" value="<?= e($val('age_min')) ?>"></div>
      <div style="flex:1;min-width:120px"><label for="age_max">To</label><input type="number" id="age_max" name="age_max" min="18" max="99" value="<?= e($val('age_max')) ?>"></div>
    </div>

    <label for="description">About the trip and who you hope to go with</label>
    <textarea id="description" name="description" rows="8" required
              placeholder="Booked or still deciding, sharing costs or just company, what you like to do, a bit about you."><?= e($val('description')) ?></textarea>
    <p class="hint" style="margin:.2rem 0 0">Everyone can read this. Leave out your hotel, cabin number, address, phone number and booking reference.</p>

    <div class="callout warn" style="margin-top:18px">
      <b>Posting means:</b> you are 18 or over; you will get to know someone and meet in public before travelling together; and you will block or <a href="<?= e(url('report')) ?>">report</a> anyone who makes you uneasy. <a href="<?= e(url('safety')) ?>">Safety guide</a>
    </div>
    <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-weight:400">
      <input type="checkbox" name="safety_ack" value="1" style="width:auto;margin-top:.25rem" required <?= ($val('safety_ack') !== '' || $isEdit) ? 'checked' : '' ?>>
      <span>I have read the above and I am posting on those terms.</span>
    </label>
    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Post it' ?></button>
      <?php if ($isEdit): ?><a class="btn btn-ghost" href="<?= e(url('buddy/' . (int) $b['id'])) ?>">Cancel editing</a><?php endif; ?>
    </div>
  </form>
</div></div>
<script>
(function () {
  var sel = document.getElementById('trip_type'), box = document.getElementById('cruise-fields');
  if (!sel || !box) return;
  var city = document.getElementById('city-fields');
  function sync() { var c = sel.value === 'cruise'; box.style.display = c ? '' : 'none'; if (city) city.style.display = c ? 'none' : ''; }
  sel.addEventListener('change', sync); sync();
})();
</script>
