<?php
/**
 * @var array $dests
 * @var array $errors
 * @var array $m         existing row when editing, the raw POST when a submit bounced, [] when new
 * @var bool  $isEdit
 * @var string $action
 *
 * Shared by /meetup/new and /meetup/{id}/edit so the two forms cannot drift apart. A field that
 * validates on one and not the other is the classic way an edit screen quietly loses a rule.
 */
$val = static function (string $k, string $default = '') use ($m) {
    // A bounced submit re-renders with $_POST merged over the row, so input() wins where it has a
    // value and the stored row fills the rest. On a fresh form both are empty.
    $posted = input($k);
    if ($posted !== '') return (string) $posted;
    return (string) ($m[$k] ?? $default);
};
// datetime-local wants 'Y-m-dTH:i'; the database holds 'Y-m-d H:i:s'.
$dt = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
$capacity = $val('capacity', '0');
$ackChecked = $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty(input('safety_ack')) : $isEdit;
?>
<?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <?php if (!$isEdit): ?><input type="hidden" name="_submit" value="<?= e(rmt_submit_token('meetup_new')) ?>"><?php endif; ?>

  <label for="title">Title</label>
  <input type="text" id="title" name="title" maxlength="140" required
         value="<?= e($val('title')) ?>" placeholder="Sunday morning coffee and a walk along the canals">

  <label for="destination_id">Destination</label>
  <select id="destination_id" name="destination_id" required>
    <option value="">— Select a destination —</option>
    <?php foreach ($dests as $d): ?>
      <option value="<?= (int)$d['id'] ?>"<?= $val('destination_id') === (string)$d['id'] ? ' selected' : '' ?>>
        <?= e($d['name'].', '.$d['country']) ?></option>
    <?php endforeach; ?>
  </select>
  <p class="hint" style="margin:.2rem 0 0">A meetup is tied to a destination and nothing finer. RuinMyTrip never publishes anyone's precise or live location.</p>

  <label for="date_start">Starts</label>
  <input type="datetime-local" id="date_start" name="date_start" required value="<?= e($dt($val('date_start'))) ?>">

  <label for="date_end">Ends <span class="hint">(optional)</span></label>
  <input type="datetime-local" id="date_end" name="date_end" value="<?= e($dt($val('date_end'))) ?>">

  <label for="capacity">Capacity <span class="hint">(0 for no limit)</span></label>
  <input type="number" id="capacity" name="capacity" min="0" max="<?= RMT_MEETUP_CAPACITY_MAX ?>" value="<?= e($capacity) ?>">
  <p class="hint" style="margin:.2rem 0 0">This is enforced. Once it is reached nobody else can RSVP, so put a number you will actually keep.<?= $isEdit ? ' You cannot set it below the number who have already RSVPed.' : '' ?></p>

  <label for="description">The plan</label>
  <textarea id="description" name="description" rows="9" required
            placeholder="Where exactly to meet, what you are doing, roughly how long, and anything people should bring or book."><?= e($val('description')) ?></textarea>
  <p class="hint" style="margin:.2rem 0 0">Whatever meeting spot you name here is visible to everyone who opens the page. There is no private-location feature, deliberately.</p>

  <div class="callout warn" style="margin-top:18px">
    <b>Hosting a meetup means:</b> it is public and open to any member 18 or over, it is not dating,
    you will meet somewhere public, and you will use <a href="<?= e(url('report')) ?>">report</a> or block if
    something is wrong. <a href="<?= e(url('safety')) ?>">Full safety guide →</a>
  </div>
  <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-weight:400">
    <input type="checkbox" name="safety_ack" value="1" style="width:auto;margin-top:.25rem" <?= $ackChecked ? 'checked' : '' ?> required>
    <span>I have read the above and I am hosting on those terms.</span>
  </label>

  <div style="margin-top:18px">
    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Publish meetup' ?></button>
    <?php if ($isEdit): ?>
      <a class="btn btn-ghost" href="<?= e(url('meetup/'.(int)$m['id'])) ?>">Cancel editing</a>
    <?php endif; ?>
  </div>
</form>
