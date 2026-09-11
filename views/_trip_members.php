<?php
/**
 * Who is planning this trip.
 *
 * @var array $t        the trip
 * @var array $members  active members, owner first
 * @var array $invited  people asked and not yet answered (owner only)
 * @var bool  $isOwner
 * @var bool  $myInvite whether the reader is the one being asked
 * @var ?array $me
 *
 * Drawn only when there is something true to say: a trip somebody is planning on their own shows
 * nothing but the invite box, and only to them. A row of one face labelled "1 traveler" is the
 * kind of thing that makes a site feel empty.
 */
$members = $members ?? [];
$invited = $invited ?? [];
$myInvite = $myInvite ?? false;
$backUrl = '/trip/' . (int) $t['id'] . '/' . (string) $t['slug'];
$shared = count($members) > 1;
?>

<?php /* The question itself is drawn at the top of the trip page, where a question addressed to
         the reader belongs. Repeating it here would ask the same thing twice on one screen. */ ?>

<?php if ($shared || $isOwner): ?>
  <section id="who" class="trip-who">
    <?php if ($shared): ?>
      <h2 class="rail-h" style="margin:0 0 8px">Planning this trip</h2>
      <ul class="trip-who-list">
        <?php foreach ($members as $m): ?>
          <li>
            <a href="<?= e(url('u/'.$m['username'])) ?>">
              <img class="avatar" src="<?= e(avatar_url($m['avatar_url'] ?? null)) ?>" alt="">
              <span><b><?= e((string) ($m['display_name'] ?: $m['username'])) ?></b>
                <span class="hint"><?= $m['role'] === 'owner' ? 'whose trip it is' : 'helping plan' ?></span></span>
            </a>
            <?php if ($isOwner && $m['role'] !== 'owner'): ?>
              <form method="post" action="<?= e(url('trip/'.(int) $t['id'].'/member/remove')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                <button class="btn btn-ghost btn-sm">Remove</button>
              </form>
            <?php elseif ($me && !$isOwner && (int) $m['user_id'] === (int) $me['id']): ?>
              <form method="post" action="<?= e(url('trip/'.(int) $t['id'].'/member/remove')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $me['id'] ?>">
                <button class="btn btn-ghost btn-sm">Leave</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($isOwner): ?>
      <?php if ($invited): ?>
        <p class="hint" style="margin:8px 0 0">Asked, and not answered yet:
          <?php foreach ($invited as $i => $iv): ?><?= $i ? ', ' : '' ?>@<?= e((string) $iv['username']) ?><?php endforeach; ?>.</p>
      <?php endif; ?>
      <form method="post" action="<?= e(url('trip/'.(int) $t['id'].'/invite')) ?>" class="trip-invite">
        <?= csrf_field() ?>
        <label class="sr-only" for="invite-username">Username to invite</label>
        <input type="text" id="invite-username" name="username" placeholder="Invite a traveler by @username"
               maxlength="40" autocomplete="off">
        <button class="btn btn-ghost btn-sm">Invite</button>
      </form>
      <p class="hint" style="margin:6px 0 0">They can add plans, places and photographs. Only you can
        change who sees this trip, or delete it.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
