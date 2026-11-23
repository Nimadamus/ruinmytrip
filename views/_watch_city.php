<?php
/**
 * The one sign up ask on tax notes, ticket notes and warnings pages (rmt_cta_focus).
 *
 * 2026-11-23: the "Watch {city}" card asked for an email in return for nothing the reader did not
 * already have, and got no signups. Now it offers what a reader at this moment wants to keep: this
 * page's answer in their inbox now, a reminder 14 and 3 days before their trip, and one email if
 * the official rules change. Email only, no password, and the page itself stays free.
 * Same byg_signups row and double opt in (POST /updates); the trip date is optional.
 *
 * @var array $byg ['topic' => 'tax'|'scams', 'city' => key, 'name' => city name, 'source' => string, 'return' => path]
 */
$wWhat = RMT_BYG_TOPICS[$byg['topic']] ?? 'updates';
$wName = (string) ($byg['name'] ?? '');
$wMe = function_exists('current_user') ? current_user() : null;
$wReturn = (string) ($byg['return'] ?? '/');
$wPage = (string) preg_replace('/#.*$/', '', $wReturn);
$wToday = date('Y-m-d');
$wMax = date('Y-m-d', strtotime('+730 days'));
?>
<section class="card byg watch-city" id="byg" style="margin:22px 0;border-left:4px solid #0f766e"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px"><?= $wName !== '' ? 'Going to ' . e($wName) . '? Take this with you' : 'Take this with you' ?></h2>
  <p class="hint" style="margin:0 0 10px">We email you this page's answer now. Add your trip date and we remind you 14 days and 3 days before you leave, with anything that changed in the <?= e($wWhat) ?>. No password, no newsletter, one click to stop.</p>
  <form method="post" action="<?= e(url('updates')) ?>" class="ma-form">
    <?= csrf_field() ?>
    <input type="hidden" name="topic" value="<?= e((string) $byg['topic']) ?>">
    <input type="hidden" name="city" value="<?= e((string) $byg['city']) ?>">
    <input type="hidden" name="source" value="<?= e((string) $byg['source']) ?>">
    <input type="hidden" name="return" value="<?= e($wReturn) ?>">
    <input type="hidden" name="page" value="<?= e($wPage) ?>">
    <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="ma-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));max-width:520px">
      <label class="ma-email">Email<input type="email" name="email" required autocomplete="email" maxlength="254" placeholder="you@example.com" value="<?= e((string) ($wMe['email'] ?? '')) ?>"></label>
      <label>Trip starts (optional)<input type="date" name="travel_date" min="<?= e($wToday) ?>" max="<?= e($wMax) ?>"></label>
    </div>
    <button class="btn btn-accent btn-sm" type="submit">Email me this</button>
  </form>
</div></section>
