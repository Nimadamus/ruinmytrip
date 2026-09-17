<?php /** @var array $dests @var array $errors @var array $b */
$val = static function (string $k, string $default = '') use ($b) {
    $posted = input($k);
    if ($posted !== '') return (string) $posted;
    return (string) ($b[$k] ?? $default);
}; ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Find a travel buddy</h1>
  <p class="muted">Post the cruise or trip you are taking and who you would like to go with. People put their hand up, you choose who to accept, and accepted buddies can message you.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('buddies/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('buddy_new')) ?>">

    <label for="trip_type">Kind of trip</label>
    <select id="trip_type" name="trip_type" required>
      <?php foreach (RMT_BUDDY_TYPES as $k => $v): ?>
        <option value="<?= e($k) ?>"<?= $val('trip_type', 'cruise') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="title">Title</label>
    <input type="text" id="title" name="title" maxlength="140" required value="<?= e($val('title')) ?>"
           placeholder="Looking for a cabin mate on a 7 night Western Caribbean cruise">

    <label for="where_text">Where</label>
    <input type="text" id="where_text" name="where_text" maxlength="140" required value="<?= e($val('where_text')) ?>"
           placeholder="Royal Caribbean, Miami to Cozumel and Roatan">

    <label for="destination_id">City on RuinMyTrip <span class="hint">(optional)</span></label>
    <select id="destination_id" name="destination_id">
      <option value="">None</option>
      <?php foreach ($dests as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= $val('destination_id') === (string) $d['id'] ? ' selected' : '' ?>><?= e($d['name'] . ', ' . $d['country']) ?></option>
      <?php endforeach; ?>
    </select>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:160px"><label for="date_from">Leaving</label>
        <input type="date" id="date_from" name="date_from" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_from')) ?>"></div>
      <div style="flex:1;min-width:160px"><label for="date_to">Back</label>
        <input type="date" id="date_to" name="date_to" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_to')) ?>"></div>
    </div>
    <label style="display:flex;gap:10px;align-items:center;font-weight:400">
      <input type="checkbox" name="flexible" value="1" style="width:auto" <?= $val('flexible') ? 'checked' : '' ?>> <span>Dates are flexible</span>
    </label>

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:160px"><label for="spots">How many people you are looking for</label>
        <input type="number" id="spots" name="spots" min="1" max="20" value="<?= e($val('spots', '1')) ?>"></div>
      <div style="flex:1;min-width:160px"><label for="budget">Budget</label>
        <select id="budget" name="budget">
          <?php foreach (RMT_BUDDY_BUDGETS as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $val('budget', 'any') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>

    <label for="description">About the trip and who you hope to go with</label>
    <textarea id="description" name="description" rows="8" required
              placeholder="Already booked or still deciding, splitting a cabin or just company on excursions, what you like to do, a bit about you."><?= e($val('description')) ?></textarea>
    <p class="hint" style="margin:.2rem 0 0">Everyone can read this. Leave out your phone number, cabin number, address and booking reference.</p>

    <div class="callout warn" style="margin-top:18px">
      <b>Posting means:</b> this is about company for a trip, not dating; you are 18 or over; you will get to know someone and meet in public before travelling together; and you will block or <a href="<?= e(url('report')) ?>">report</a> anyone who makes you uneasy. <a href="<?= e(url('safety')) ?>">Safety guide</a>
    </div>
    <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-weight:400">
      <input type="checkbox" name="safety_ack" value="1" style="width:auto;margin-top:.25rem" required <?= $val('safety_ack') ? 'checked' : '' ?>>
      <span>I have read the above and I am posting on those terms.</span>
    </label>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Post it</button></div>
  </form>
</div></div>
