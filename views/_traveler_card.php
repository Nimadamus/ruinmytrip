<?php
/**
 * One traveler, on a page about meeting them.
 *
 * The rule the card is built around: it says only what that person actually put on this site. A
 * missing travel style is a missing line, not "Traveler", and a profile with no interests shows
 * no interest row rather than a filled in guess. On a network this young the temptation to make a
 * thin card look full is exactly the temptation that would make every card worthless.
 *
 * It never carries a hotel, an address, a flight, a live position or a contact detail, because
 * none of those are in the row it is drawn from. A trip is a city and two dates that somebody
 * chose to publish, and that is the whole of what is shown.
 *
 * Expects:
 *   $tc        the row: user_id, username, display_name, avatar_url, home_city, travel_style,
 *              their_from, their_to, dest_name, dest_slug, and either overlap_days or gap_days+side
 *   $tcBack    where the follow button should return to
 *   $tcInterests  array<int,list<string>> keyed by user id
 *   $tcFollowing  array<int,true> the reader already follows these
 *   $tcConnects   array<int,array> the reader's own connect rows, keyed by trip id
 */
$tcId    = (int) ($tc['user_id'] ?? 0);
$tcName  = trim((string) ($tc['display_name'] ?? ''));
$tcUser  = (string) ($tc['username'] ?? '');
$tcInt   = array_slice($tcInterests[$tcId] ?? [], 0, 3);
$tcStyle = (string) ($tc['travel_style'] ?? '');
$tcIsFollowing = !empty($tcFollowing[$tcId]);
?>
<div class="card tcard"><div class="card-body tcard-body">
  <a href="<?= e(url('u/' . $tcUser)) ?>" class="tcard-av">
    <img class="avatar" src="<?= e(avatar_url($tc['avatar_url'] ?? null)) ?>" alt="@<?= e($tcUser) ?>"></a>

  <div class="tcard-main">
    <p class="tcard-name">
      <b><a href="<?= e(url('u/' . $tcUser)) ?>"><?= e($tcName !== '' ? $tcName : '@' . $tcUser) ?></a></b>
      <?php if ($tcName !== ''): ?><span class="hint">@<?= e($tcUser) ?></span><?php endif; ?>
    </p>

    <p class="tcard-where">
      <?php if (!empty($tc['dest_slug'])): ?>
        <a href="<?= e(url('d/' . $tc['dest_slug'])) ?>"><?= e((string) $tc['dest_name']) ?></a> &middot;
      <?php endif; ?>
      <?= e(date('M j', strtotime((string) $tc['their_from']))) ?>
      to <?= e(date('M j', strtotime((string) $tc['their_to']))) ?>
    </p>

    <?php /* The one line that says why this person is on the page at all. Overlap or near miss,
             never both, and always counted in days a reader can act on. */ ?>
    <?php if (!empty($tc['overlap_days'])): ?>
      <p class="tcard-overlap"><?= (int) $tc['overlap_days'] ?>
        <?= (int) $tc['overlap_days'] === 1 ? 'day' : 'days' ?> overlap
        <?php if (!empty($tc['other_overlaps'])): ?>
          <span class="hint">and <?= (int) $tc['other_overlaps'] ?> other <?= (int) $tc['other_overlaps'] === 1 ? 'trip' : 'trips' ?></span>
        <?php endif; ?>
      </p>
    <?php elseif (!empty($tc['gap_days'])): ?>
      <p class="tcard-near"><?= (int) $tc['gap_days'] ?> <?= (int) $tc['gap_days'] === 1 ? 'day' : 'days' ?>
        <?= (string) ($tc['side'] ?? '') === 'before' ? 'before you arrive' : 'after you leave' ?></p>
    <?php endif; ?>

    <?php if ($tcStyle !== '' && isset(RMT_TRAVEL_STYLES[$tcStyle])): ?>
      <p class="hint" style="margin:.15rem 0 0"><?= e(RMT_TRAVEL_STYLES[$tcStyle]) ?></p>
    <?php endif; ?>

    <?php if ($tcInt): ?>
      <p class="tcard-int">
        <?php foreach ($tcInt as $i => $k): ?><?= $i ? ' &middot; ' : '' ?><?= e(RMT_INTERESTS[$k] ?? $k) ?><?php endforeach; ?>
      </p>
    <?php endif; ?>

    <div class="tcard-acts">
      <a class="btn btn-ghost btn-sm" href="<?= e(url('u/' . $tcUser)) ?>">View profile</a>
      <?php /* The small deliberate signal. It carries no words, shares nothing either of them has
               not already published, and enrols nobody in anything: the other traveler decides.
               Drawn only where there is a trip to meet on, and only where they have not already
               answered. */ ?>
      <?php $tcTrip = (int) ($tc['their_trip_id'] ?? 0); $tcCon = $tcConnects[$tcTrip] ?? null; ?>
      <?php if ($tcTrip > 0 && !$tcCon): ?>
        <form method="post" action="<?= e(url('connect')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="trip_id" value="<?= $tcTrip ?>">
          <input type="hidden" name="return" value="<?= e((string) $tcBack) ?>">
          <button class="btn btn-ghost btn-sm">Interested in meeting</button>
        </form>
      <?php elseif ($tcCon && (string) $tcCon['state'] === 'interested'): ?>
        <span class="chip">Waiting on them</span>
        <form method="post" action="<?= e(url('connect/' . (int) $tcCon['id'] . '/withdraw')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= e((string) $tcBack) ?>">
          <button class="btn btn-ghost btn-sm">Take it back</button>
        </form>
      <?php elseif ($tcCon && (string) $tcCon['state'] === 'accepted'): ?>
        <span class="chip">You both said yes</span>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('messages/' . $tcUser)) ?>">Message</a>
      <?php elseif ($tcCon && (string) $tcCon['state'] === 'declined'): ?>
        <span class="chip">Answered</span>
      <?php endif; ?>
      <?php if ($tcIsFollowing): ?>
        <span class="chip">Following</span>
      <?php else: ?>
        <form method="post" action="<?= e(url('follow')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= $tcId ?>">
          <input type="hidden" name="return" value="<?= e((string) $tcBack) ?>">
          <button class="btn btn-primary btn-sm">Follow</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div></div>
