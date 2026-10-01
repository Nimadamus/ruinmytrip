<?php
/** @var array $alert @var string $label @var array $over @var bool $confirmed
 *
 * After the signed out alert form, and again after its confirm button. The two next steps that
 * matter: make the account (the trip is already held), and send the card to the people who might
 * be the other half of the match.
 */
$n = (int) $over['total'];
?>
<div class="wrap" style="max-width:720px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($confirmed): ?>
      <p class="eyebrow" style="margin:0">Your alert is on</p>
      <h1 style="margin:4px 0 10px"><?= e($label) ?></h1>
      <p>We will email you when somebody real posts dates that overlap yours. At most one email a day, and every one has an off switch.</p>
    <?php else: ?>
      <p class="eyebrow" style="margin:0">One more step</p>
      <h1 style="margin:4px 0 10px">Check your inbox</h1>
      <p>We sent a link to switch on your alert for <b><?= e($label) ?></b>. Nothing else is sent until you click it.</p>
    <?php endif; ?>
    <?php if ($n > 0): ?>
      <p class="callout"><b><?= $n === 1 ? 'One traveler already overlaps' : $n . ' travelers already overlap' ?> your dates.</b>
        Post your trip to see who, and to say hello.</p>
    <?php endif; ?>
  </div></section>

  <div class="grid g-2" style="gap:18px;align-items:start">
    <section class="card"><div class="card-body">
      <h2 style="margin:0 0 6px">Be findable too</h2>
      <p class="hint">An alert listens. A trip is seen: the travelers you overlap get told about you, and you can say hello. Your dates are already filled in.</p>
      <a class="btn btn-accent" data-cta="alert_join" href="<?= e(rmt_alert_join_url($alert)) ?>">Post my trip</a>
    </div></section>
    <section class="card"><div class="card-body">
      <h2 style="margin:0 0 6px">Bring the others</h2>
      <p class="hint">Somebody in your group chats is going too. Send them an "I'm going" card; if they say me too, you have your first match.</p>
      <form method="post" action="<?= e(url('im-going')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" data-cta="alert_share">Make my I'm going card</button>
      </form>
    </div></section>
  </div>
  <p style="margin:18px 0"><a href="<?= e(rmt_alert_page($alert)) ?>">Back to <?= e(explode(',', $label)[0]) ?></a></p>
</div>
