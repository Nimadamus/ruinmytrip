<?php
/**
 * One local, on the hub or a city page. Only what the member published: their city, what they
 * offer, when, their languages. Never where they live inside the city.
 *
 * @var array  $lc      a row from rmt_hl_locals()
 * @var ?array $me
 * @var array  $myLocal to_user_id => state of the viewer's own requests
 * @var string $back    where the button returns to
 */
$lcState = $myLocal[(int) $lc['user_id']] ?? '';
$lcName = (string) ($lc['display_name'] ?: '@' . $lc['username']);
$lcOffers = array_map(static fn(string $k) => RMT_LOCAL_OFFERS[$k], rmt_local_offers_for($lc['local_offers'] ?? null));
$lcLangs = rmt_language_labels(rmt_languages_for($lc['languages'] ?? null));
?>
<article class="hl-person">
  <div class="hl-person-top">
    <a href="<?= e(url('u/' . $lc['username'])) ?>"><img class="avatar" src="<?= e(avatar_url($lc['avatar_url'] ?? null)) ?>" alt="" loading="lazy"></a>
    <div>
      <a href="<?= e(url('u/' . $lc['username'])) ?>"><b><?= e($lcName) ?></b></a>
      <span class="hint">Local in <?= e((string) $lc['dest_name']) ?></span>
    </div>
  </div>
  <div class="hl-badges">
    <span class="hl-badge hl-badge-role">Local</span>
    <?php if (!empty($lc['email_verified_at'])): ?><span class="hl-badge hl-badge-ok">Email confirmed</span><?php endif; ?>
    <?php foreach (array_slice($lcLangs, 0, 3) as $l): ?><span class="hl-badge"><?= e($l) ?></span><?php endforeach; ?>
  </div>
  <?php if ($lcOffers): ?><p><b>Happy to:</b> <?= e(mb_strtolower(implode(', ', array_slice($lcOffers, 0, 4)))) ?></p><?php endif; ?>
  <?php if (($lc['local_availability'] ?? '') !== ''): ?><p class="hint">Usually free: <?= e((string) $lc['local_availability']) ?></p><?php endif; ?>
  <?php if (($lc['bio'] ?? '') !== ''): ?><p class="muted"><?= e(mb_strimwidth((string) $lc['bio'], 0, 160, '…')) ?></p><?php endif; ?>
  <div class="hl-person-acts">
    <?php if (!$me): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(url('register?return=' . rawurlencode($back))) ?>">Join to ask</a>
    <?php elseif ($lcState === 'interested'): ?>
      <span class="chip">Asked, waiting</span>
    <?php elseif ($lcState === 'accepted'): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $lc['username'])) ?>">Message</a>
    <?php elseif ($lcState === 'declined'): ?>
      <span class="chip">Answered</span>
    <?php else: ?>
      <form method="post" action="<?= e(url('buddies/local/' . (int) $lc['user_id'] . '/connect')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($back) ?>">
        <button class="btn btn-primary btn-sm">Ask to meet</button></form>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('u/' . $lc['username'])) ?>">Profile</a>
  </div>
</article>
