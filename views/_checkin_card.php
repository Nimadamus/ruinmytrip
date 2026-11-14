<?php
/**
 * The check-in card: "I am in this city right now".
 *
 * Checked in, it says where, for how much longer, how many travelers are there, and links to the
 * city's room. Not checked in, it is the form. Shown to signed in members only; the caller decides
 * that.
 *
 * @var ?array $ckDest  a destination row to check in to (a city's own page), or null to offer a
 *                      choice from $dests
 * @var ?array $dests   destinations for the choice, when $ckDest is null
 * @var ?string $ckBack where to come back to after checking out
 * @var ?int $ckPick    a destination id to preselect in the choice
 */
$ckMe = current_user();
if (!$ckMe) return;
$ckDest = $ckDest ?? null;
$ckLive = rmt_checkin_live((int) $ckMe['id']);
$ckBack = $ckBack ?? (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/buddies'), PHP_URL_PATH) ?: '/buddies');
?>
<div class="card checkin-card" id="checkin"><div class="card-body">
  <?php if ($ckLive): $ckN = rmt_city_room_count((int) $ckLive['destination_id']); ?>
    <h3>You are checked in to <?= e((string) $ckLive['dest_name']) ?></h3>
    <p class="hint">For another <?= e(rmt_checkin_left((string) $ckLive['expires_at'])) ?>.
      <?= $ckN === 1 ? 'You are the only traveler checked in so far.' : (int) $ckN . ' travelers are checked in right now.' ?>
      When your time is up you leave the room on your own.</p>
    <p style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 0">
      <a class="btn btn-primary btn-sm" href="<?= e(url('city-room/' . (int) $ckLive['destination_id'])) ?>">Open the <?= e((string) $ckLive['dest_name']) ?> room</a>
    </p>
    <form method="post" action="<?= e(url('checkin/end')) ?>" style="margin-top:8px"><?= csrf_field() ?>
      <input type="hidden" name="return" value="<?= e($ckBack) ?>">
      <button class="btn btn-ghost btn-sm">Check out</button>
    </form>
    <?php if ($ckDest && (int) $ckDest['id'] !== (int) $ckLive['destination_id']): ?>
      <form method="post" action="<?= e(url('checkin')) ?>" style="margin-top:8px"><?= csrf_field() ?>
        <input type="hidden" name="destination_id" value="<?= (int) $ckDest['id'] ?>">
        <button class="btn btn-ghost btn-sm">Move my check-in to <?= e((string) $ckDest['name']) ?></button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <h3><?= $ckDest ? 'In ' . e((string) $ckDest['name']) . ' right now?' : 'In town right now?' ?></h3>
    <p class="hint">Check in to join the city's room and talk to the other travelers who are there today. You also show up for travelers searching this city. Only your city and dates show, never where you are staying, and you drop out of the room by yourself when your time is up.</p>
    <form method="post" action="<?= e(url('checkin')) ?>"><?= csrf_field() ?>
      <?php if ($ckDest): ?>
        <input type="hidden" name="destination_id" value="<?= (int) $ckDest['id'] ?>">
      <?php else: ?>
        <label for="ck-dest">I am in</label>
        <select id="ck-dest" name="destination_id" required><option value="">Pick a city</option>
          <?php foreach (($dests ?? []) as $ckD): ?><option value="<?= (int) $ckD['id'] ?>"<?= (int) ($ckPick ?? 0) === (int) $ckD['id'] ? ' selected' : '' ?>><?= e((string) $ckD['name']) ?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <label for="ck-hours">For</label>
      <select id="ck-hours" name="hours">
        <?php foreach (RMT_CHECKIN_CHOICES as $ckH => $ckLabel): ?>
          <option value="<?= (int) $ckH ?>"<?= $ckH === RMT_CHECKIN_DEFAULT_HOURS ? ' selected' : '' ?>><?= e($ckLabel) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" style="margin-top:10px">Check in</button>
    </form>
  <?php endif; ?>
</div></div>
