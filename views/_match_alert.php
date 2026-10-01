<?php
/**
 * The signed out match alert (app/match_alerts.php): an email, the dates, how flexible. The city
 * and the occasion ride along hidden. Shown only to somebody with no account; a member gets the
 * trip form instead, because a member's trip already alerts them.
 *
 * @var array $ma ['dest' => [id, name], 'occ' => slug|'', 'from' => Y-m-d|'', 'to' => Y-m-d|'', 'source' => string,
 *                 'return' => path, 'heading' => ?string]
 */
$maDest = $ma['dest'];
$maMin = date('Y-m-d');
?>
<section class="match-alert card" id="match-alert">
  <div class="card-body">
    <h2><?= e($ma['heading'] ?? 'Going to ' . $maDest['name'] . '? Get told when another traveler overlaps your dates.') ?></h2>
    <p class="hint" style="margin:0 0 12px">No account needed. One email to confirm, then we only write when somebody real posts dates that cross yours.</p>
    <form method="post" action="<?= e(url('alerts')) ?>" class="ma-form">
      <?= csrf_field() ?>
      <input type="hidden" name="destination_id" value="<?= (int) $maDest['id'] ?>">
      <input type="hidden" name="occasion" value="<?= e((string) ($ma['occ'] ?? '')) ?>">
      <input type="hidden" name="source" value="<?= e((string) ($ma['source'] ?? 'destination')) ?>">
      <input type="hidden" name="return" value="<?= e((string) ($ma['return'] ?? '/')) ?>">
      <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="ma-row">
        <label class="ma-email">Email<input type="email" name="email" required autocomplete="email" maxlength="254" placeholder="you@example.com"></label>
        <label>Arrive<input type="date" name="date_from" required min="<?= e($maMin) ?>" value="<?= e((string) ($ma['from'] ?? '')) ?>"></label>
        <label>Leave<input type="date" name="date_to" required min="<?= e($maMin) ?>" value="<?= e((string) ($ma['to'] ?? '')) ?>"></label>
        <label>Flexible?<select name="flex_days">
          <?php foreach (RMT_ALERT_FLEX as $k => $lbl): ?><option value="<?= (int) $k ?>"><?= e($lbl) ?></option><?php endforeach; ?>
        </select></label>
      </div>
      <button class="btn btn-accent" type="submit">Alert me</button>
      <p class="hint" style="margin:8px 0 0">Make an account later and these dates become your trip, so the travelers you overlap can find you too. Turn the alert off from any email.</p>
    </form>
  </div>
</section>
