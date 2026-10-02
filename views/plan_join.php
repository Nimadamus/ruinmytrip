<?php
/** @var array $draft @var array $errors @var ?array $summary @var array $overlap @var ?array $question
 *
 * The account step, with what they already wrote shown back to them. The question this page has
 * to answer is "why should I make an account", and the only honest answer is the thing on it: your
 * trip, and the real number of travelers it would put you in front of. Zero is never printed as a
 * number; it is said as what it is, which is that they would be first.
 */
$ov = $overlap;
$people = (int) $ov['travelers'] + (int) $ov['buddies'];
$review = $review ?? null;
$destName = $summary['dest']['name'] ?? ($question['dest']['name'] ?? ($review['dest']['name'] ?? ''));
$what = $review ? 'review' : ($question ? 'question' : 'trip');
?>
<div class="wrap"><div class="form-card pf">
  <?php if ($review): ?>
    <p class="eyebrow" style="margin:0 0 6px">Your review is ready</p>
    <div class="pf-held">
      <b><?= e($review['title'] !== '' ? $review['title'] : $review['subject']) ?></b>
      <span class="hint"><?= e($review['subject']) ?><?= $destName !== '' ? ', ' . e($destName) : '' ?><?= $review['rating'] > 0 ? ' · ' . $review['rating'] . ' of 5' : '' ?></span>
    </div>
    <h1 style="margin:10px 0 6px;font-size:1.5rem">Make a free account to publish it</h1>
    <p class="muted" style="margin:0 0 14px">It goes up under your name, where travelers planning<?= $destName !== '' ? ' ' . e($destName) : ' this trip' ?> will read it. Nothing you wrote is lost.</p>
  <?php elseif ($question): ?>
    <p class="eyebrow" style="margin:0 0 6px">Your question is ready</p>
    <blockquote class="pf-held">“<?= e(excerpt((string) $question['body'], 280)) ?>”
      <?php if ($destName !== ''): ?><span class="hint">Posting to the <?= e($destName) ?> community</span><?php endif; ?></blockquote>
    <h1 style="margin:10px 0 6px;font-size:1.5rem">Make a free account to post it</h1>
    <p class="muted" style="margin:0 0 14px">Travelers who have been<?= $destName !== '' ? ' to ' . e($destName) : '' ?> or are going soon can answer, and you get a notification when they do.</p>
  <?php else: ?>
    <p class="eyebrow" style="margin:0 0 6px">Your trip is ready</p>
    <div class="pf-held">
      <b><?= e((string) ($summary['lines'][0] ?? '')) ?></b>
      <?php if (count($summary['lines'] ?? []) > 1): ?><span class="hint"><?= e(implode(' · ', array_slice($summary['lines'], 1))) ?></span><?php endif; ?>
      <a class="hint" href="<?= e(url('plan?edit=1')) ?>">Change it</a>
    </div>
    <?php if ($people > 0): ?>
      <p style="margin:12px 0 4px;font-size:1.05rem"><b><?= $people ?> <?= $people === 1 ? 'traveler is' : 'travelers are' ?> in <?= e($destName) ?> on your dates.</b></p>
      <p class="muted" style="margin:0 0 14px">Make an account to see who, and they will see you too.</p>
    <?php else: ?>
      <p style="margin:12px 0 4px;font-size:1.05rem"><b>You would be the first traveler with these dates in <?= e($destName) ?>.</b></p>
      <p class="muted" style="margin:0 0 14px">Anyone who posts overlapping dates later is shown your trip, and you get a notification the moment they do.</p>
    <?php endif; ?>
    <?php if ((int) $ov['locals'] > 0): ?>
      <p class="hint" style="margin:-6px 0 14px"><?= (int) $ov['locals'] ?> <?= (int) $ov['locals'] === 1 ? 'local has' : 'locals have' ?> said they are happy to meet travelers.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('plan/join')) ?>"><?= csrf_field() ?>
    <?php $qjReturn = ''; include __DIR__ . '/auth/_quick_join_fields.php'; ?>
    <p class="hint" style="margin-top:4px">With email we send one link to confirm the address; your <?= $what ?> goes live when
      you click it. With Google it goes live straight away.</p>
    <div style="margin-top:12px"><button class="btn btn-primary btn-block"><?= $review ? 'Create account and publish' : ($question ? 'Create account and post' : 'Create account and post my trip') ?></button></div>
  </form>
  <p class="muted" style="margin-top:14px">Already a member? <a href="<?= e(url('login?return=' . rawurlencode('/plan/join'))) ?>">Sign in</a> and it is posted for you.</p>
</div></div>
