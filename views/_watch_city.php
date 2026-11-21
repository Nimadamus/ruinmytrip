<?php
/**
 * The one sign up ask on tax notes, ticket notes and warnings pages (rmt_cta_focus): watch the city,
 * by email (double opt in, POST /updates) or with a Google account (/updates/watch). Same byg_signups row.
 *
 * @var array $byg ['topic' => 'tax'|'scams', 'city' => key, 'name' => city name, 'source' => string, 'return' => path]
 */
$wWhat = RMT_BYG_TOPICS[$byg['topic']] ?? 'updates';
$wName = (string) ($byg['name'] ?? '');
$wMe = function_exists('current_user') ? current_user() : null;
$wGoogle = rmt_byg_watch_url((string) $byg['topic'], (string) $byg['city'], (string) $byg['source']);
?>
<section class="card byg watch-city" id="byg" style="margin:22px 0;border-left:4px solid #0f766e"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px">Watch <?= e($wName) ?>: one email when the <?= e($wWhat) ?> <?= $byg['topic'] === 'scams' ? 'change' : 'changes' ?></h2>
  <p class="hint" style="margin:0 0 10px">We check the official source and write only when it changes. No newsletter, and you can stop it from any email.</p>
  <?php if ($wMe && $wGoogle !== null): ?>
    <a class="btn btn-accent btn-sm" data-cta="watch_city" href="<?= e($wGoogle) ?>">Watch <?= e($wName) ?></a>
  <?php else: ?>
    <form method="post" action="<?= e(url('updates')) ?>" class="ma-form">
      <?= csrf_field() ?>
      <input type="hidden" name="topic" value="<?= e((string) $byg['topic']) ?>">
      <input type="hidden" name="city" value="<?= e((string) $byg['city']) ?>">
      <input type="hidden" name="source" value="<?= e((string) $byg['source']) ?>">
      <input type="hidden" name="return" value="<?= e((string) ($byg['return'] ?? '/')) ?>">
      <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="ma-row" style="grid-template-columns:1fr;max-width:420px">
        <label class="ma-email">Email<input type="email" name="email" required autocomplete="email" maxlength="254" placeholder="you@example.com"></label>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-accent btn-sm" type="submit">Email me if it changes</button>
        <?php if ($wGoogle !== null): ?><a class="btn btn-ghost btn-sm" data-cta="watch_city_google" href="<?= e($wGoogle) ?>">Continue with Google</a><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div></section>
