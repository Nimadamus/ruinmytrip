<?php
/**
 * One person, in a row, with the reason they are being shown and one thing to do about it.
 *
 * @var array  $person   needs user_id (or id) and username; avatar_url, display_name optional
 * @var string $because  the reason, said plainly. Never a score, never "recommended for you"
 * @var string $backTo   where a follow should return to
 *
 * The reason matters more than the card does. A list of strangers with a Follow button is a
 * directory; a list where each row says "four days with you in Lisbon" is an introduction.
 */
$pUser = (string) ($person['username'] ?? '');
$pId = (int) ($person['user_id'] ?? $person['id'] ?? 0);
$pName = trim((string) ($person['display_name'] ?? '')) !== ''
    ? (string) $person['display_name'] : '@' . $pUser;
$because = trim((string) ($because ?? ''));
$backTo = (string) ($backTo ?? '/travelers');
$meNow = current_user();
if ($pUser === '') return;
?>
<div class="person">
  <a class="person-face" href="<?= e(url('u/'.$pUser)) ?>">
    <img class="avatar" src="<?= e(avatar_url($person['avatar_url'] ?? null)) ?>" alt=""></a>
  <div class="person-who">
    <a href="<?= e(url('u/'.$pUser)) ?>"><b><?= e($pName) ?></b></a>
    <span class="hint">@<?= e($pUser) ?><?php
      if (!empty($person['home_city'])): ?> &middot; lives in <?= e((string) $person['home_city']) ?><?php endif; ?></span>
    <?php if ($because !== ''): ?><span class="person-why"><?= e($because) ?></span><?php endif; ?>
  </div>
  <div class="person-do">
    <?php if ($meNow && $pId > 0 && $pId !== (int) $meNow['id']): ?>
      <form method="post" action="<?= e(url('follow')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= $pId ?>">
        <input type="hidden" name="return" value="<?= e($backTo) ?>">
        <button class="btn btn-ghost btn-sm">Follow</button>
      </form>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('messages/'.$pUser)) ?>">Message</a>
    <?php elseif (!$meNow): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('register?return=' . rawurlencode($backTo))) ?>">Follow</a>
    <?php endif; ?>
  </div>
</div>
