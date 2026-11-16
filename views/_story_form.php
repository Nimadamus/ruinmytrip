<?php
/**
 * "Did something go wrong here? Tell us" (app/before_you_book.php story_submit). Goes to the
 * feedback queue only; a person checks every story before anything from it is published.
 *
 * @var array $story ['topic' => 'tax:amsterdam'|'warnings:amsterdam-netherlands', 'name' => city name, 'return' => path]
 */
$storyName = (string) ($story['name'] ?? '');
?>
<section class="card story-form" id="story" style="margin:18px 0;border-left:4px solid #b45309"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px">Did something go wrong<?= $storyName !== '' ? ' in ' . e($storyName) : ' here' ?>? Tell us</h2>
  <p class="hint" style="margin:0 0 10px">A surprise charge, a scam, a rule nobody mentioned. We read every story and check it before anything from it is published. No account needed; your email is optional and only so we can reply.</p>
  <form method="post" action="<?= e(url('story')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="topic" value="<?= e((string) ($story['topic'] ?? '')) ?>">
    <input type="hidden" name="return" value="<?= e((string) ($story['return'] ?? '/')) ?>">
    <div class="hp-field" aria-hidden="true"><label>Leave this empty <input type="text" name="hp_note" value="" tabindex="-1" autocomplete="off"></label></div>
    <label style="display:block;margin:0 0 8px">What happened
      <textarea name="message" required minlength="20" maxlength="3000" rows="4" style="width:100%" placeholder="What happened, where, and what it cost you"></textarea></label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 10px">
      <label style="flex:1 1 160px">When (optional)<input type="text" name="when" maxlength="60" placeholder="e.g. May 2026" style="width:100%"></label>
      <label style="flex:2 1 220px">Email (optional)<input type="email" name="contact_email" maxlength="190" autocomplete="email" style="width:100%"></label>
    </div>
    <button class="btn btn-accent btn-sm" type="submit">Send my story</button>
  </form>
</div></section>
