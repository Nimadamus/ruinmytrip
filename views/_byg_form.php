<?php
/**
 * "Before you go" updates (app/before_you_go.php): one email field, the topic and city ride along
 * hidden. Used on the tourist tax calculator, the tax notes, the warnings pages and the scams card.
 *
 * @var array $byg ['topic' => 'tax'|'scams', 'city' => key|'', 'name' => display name|'', 'source' => string, 'return' => path]
 */
$bygWhat = RMT_BYG_TOPICS[$byg['topic']] ?? 'updates';
$bygName = (string) ($byg['name'] ?? '');
$bygMe = function_exists('current_user') ? current_user() : null;
?>
<section class="card byg" id="byg" style="margin:18px 0;border-left:4px solid #0f766e"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px"><?= $bygName !== '' ? 'Going to ' . e($bygName) . '? Hear if the ' . e($bygWhat) . ($byg['topic'] === 'scams' ? ' change.' : ' changes.') : 'Hear when a ' . e($bygWhat === 'tourist tax' ? 'tourist tax' : 'warning') . ' changes' ?></h2>
  <p class="hint" style="margin:0 0 10px">One email to confirm, then we only write when <?= $bygName !== '' ? 'it' : 'one of them' ?> changes at the official source. No newsletter, and you can stop it from any email.</p>
  <form method="post" action="<?= e(url('updates')) ?>" class="ma-form">
    <?= csrf_field() ?>
    <input type="hidden" name="topic" value="<?= e((string) $byg['topic']) ?>">
    <input type="hidden" name="city" value="<?= e((string) ($byg['city'] ?? '')) ?>">
    <input type="hidden" name="source" value="<?= e((string) ($byg['source'] ?? '')) ?>">
    <input type="hidden" name="return" value="<?= e((string) ($byg['return'] ?? '/')) ?>">
    <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="ma-row" style="grid-template-columns:1fr;max-width:420px">
      <label class="ma-email">Email<input type="email" name="email" required autocomplete="email" maxlength="254" placeholder="you@example.com" value="<?= e((string) ($bygMe['email'] ?? '')) ?>"></label>
    </div>
    <button class="btn btn-accent btn-sm" type="submit">Email me if it changes</button>
  </form>
</div></section>
