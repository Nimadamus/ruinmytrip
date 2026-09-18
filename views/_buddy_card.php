<?php
/**
 * One person on the travel buddies page: a buddy post, a dated trip, or a local.
 *
 * Built only from what the member published: a city or a ship, a date range, a trip type, their own
 * interests and languages. There is no hotel, cabin, address, live position or contact detail in
 * the row, so there is none on the card. The action is always a request the other side can say no
 * to; messaging opens only after they say yes.
 *
 * @var array   $bc      a card from rmt_buddy_search()
 * @var ?array  $me
 * @var string  $bcBack  where a button should return to
 */
$bcState = (string) ($bc['viewer_state']['state'] ?? '');
$bcName  = $bc['name'] !== '' ? $bc['name'] : '@' . $bc['username'];
$bcDates = '';
if ($bc['from'] !== '') {
    $sameYear = substr($bc['from'], 0, 4) === substr($bc['to'], 0, 4);
    $bcDates = date($sameYear ? 'M j' : 'M j, Y', strtotime($bc['from'])) . ' to ' . date('M j, Y', strtotime($bc['to']));
}
$bcLogin = url('register?return=' . rawurlencode(parse_url($bcBack, PHP_URL_PATH) . (parse_url($bcBack, PHP_URL_QUERY) ? '?' . parse_url($bcBack, PHP_URL_QUERY) : '')));
?>
<?php $bcEx = !empty($bc['example']); ?>
<article class="bcard<?= $bc['here_now'] ? ' bcard-here' : '' ?><?= $bc['kind'] === 'local' ? ' bcard-local' : '' ?><?= $bcEx ? ' bcard-example' : '' ?>">
  <header class="bcard-head">
    <?php if ($bcEx): ?>
      <span class="bcard-av bcard-av-ex" aria-hidden="true"><?= $bc['type'] === 'cruise' ? '&#9875;' : ($bc['kind'] === 'local' ? '&#8962;' : '&#9992;') ?></span>
    <?php else: ?>
      <a href="<?= e(url('u/' . $bc['username'])) ?>" class="bcard-av"><img class="avatar" src="<?= e(avatar_url($bc['avatar_url'])) ?>" alt="" loading="lazy"></a>
    <?php endif; ?>
    <div class="bcard-who">
      <?php if ($bcEx): ?>
      <span class="bcard-name">Example traveler</span>
      <span class="hint">Sample, not a real member</span>
      <?php else: ?>
      <a class="bcard-name" href="<?= e(url('u/' . $bc['username'])) ?>"><?= e($bcName) ?></a>
      <span class="hint"><?php if (mb_strtolower($bc['name']) !== mb_strtolower($bc['username']) && $bc['name'] !== ''): ?>@<?= e($bc['username']) ?><?php if ($bc['verified']): ?> &middot; <?php endif; ?><?php endif; ?><?php if ($bc['verified']): ?><span class="bcard-ok" title="This member confirmed their email address">Email confirmed</span><?php endif; ?></span>
      <?php endif; ?>
    </div>
    <span class="bcard-tag<?= $bcEx ? ' bcard-tag-ex' : '' ?>"><?= $bcEx ? 'Example' : ($bc['kind'] === 'local' ? 'Local' : ($bc['here_now'] ? 'There now' : e(RMT_BUDDY_TYPES[$bc['type']] ?? 'Trip'))) ?></span>
  </header>

  <?php if ($bc['kind'] === 'post' && $bc['title'] !== ''): ?>
    <h3 class="bcard-title"><?php if ($bcEx): ?><?= e($bc['title']) ?><?php else: ?><a href="<?= e($bc['url']) ?>"><?= e($bc['title']) ?></a><?php endif; ?></h3>
  <?php endif; ?>
  <p class="bcard-where">
    <?php if ($bc['kind'] === 'local'): ?>
      Lives in <a href="<?= e(url('d/' . $bc['dest_slug'])) ?>"><?= e($bc['dest_name']) ?></a><span class="bcard-dates">Open to meeting travelers</span>
    <?php elseif ($bc['type'] === 'cruise' && $bc['ship'] !== ''): ?>
      <b><?= e($bc['ship']) ?></b>
      <span class="bcard-dates">Sails <?= e($bcDates) ?><?= $bc['flexible'] ? ' (flexible)' : '' ?></span>
    <?php else: ?>
      <b><?php if ($bc['dest_slug'] !== '' && $bc['kind'] === 'trip'): ?><a href="<?= e(url('d/' . $bc['dest_slug'])) ?>"><?= e($bc['dest_name']) ?></a><?php else: ?><?= e($bc['where']) ?><?php endif; ?></b>
      <span class="bcard-dates"><?= e($bcDates) ?><?= $bc['flexible'] ? ' (flexible)' : '' ?></span>
    <?php endif; ?>
  </p>

  <?php if ($bc['type'] === 'cruise' && ($bc['cruise_line'] !== '' || $bc['departure_port'] !== '')): ?>
    <p class="bcard-cruise"><?= e(implode(' · ', array_filter([$bc['cruise_line'], $bc['departure_port'] !== '' ? 'from ' . $bc['departure_port'] : '', $bc['nights'] ? $bc['nights'] . ' nights' : '']))) ?></p>
    <?php if (($bc['itinerary'] ?? '') !== ''): ?><p class="bcard-itin"><?= e($bc['itinerary']) ?></p><?php endif; ?>
    <?php if (($bc['on_sailing'] ?? 0) > 0): ?><p class="bcard-sailing"><?= (int) $bc['on_sailing'] + 1 ?> travelers on this exact sailing</p><?php endif; ?>
  <?php endif; ?>

  <?php if ($bc['overlap_days'] > 0): ?>
    <p class="bcard-overlap"><?= (int) $bc['overlap_days'] ?> <?= $bc['overlap_days'] === 1 ? 'day' : 'days' ?> overlap with your dates</p>
  <?php endif; ?>

  <?php $bcSum = trim(preg_replace('/\s+/', ' ', (string) $bc['summary'])); if ($bcSum !== ''): ?>
    <p class="bcard-sum"><?= e(mb_strimwidth($bcSum, 0, 150, '…')) ?></p>
  <?php endif; ?>

  <div class="bcard-chips">
    <?php if ($bc['party'] !== '' && isset(RMT_BUDDY_PARTIES[$bc['party']])): ?><span class="chip"><?= e(RMT_BUDDY_PARTIES[$bc['party']]) ?></span><?php endif; ?>
    <?php foreach ($bc['interests'] ?? [] as $ik): ?><span class="chip chip-soft"><?= e(RMT_INTERESTS[$ik] ?? $ik) ?></span><?php endforeach; ?>
  </div>
  <?php $bcMeta = array_filter([
      $bc['languages'] ? 'Speaks ' . implode(', ', $bc['languages']) : '',
      ($bc['spots'] ?? 0) > 0 ? 'Looking for ' . (int) $bc['spots'] : '',
      (($bc['age_min'] ?? 0) || ($bc['age_max'] ?? 0)) ? 'Hoping for ages ' . ((int) $bc['age_min'] ?: 18) . ' to ' . ((int) $bc['age_max'] ?: 99) : '',
    ]); ?>
  <?php if ($bcMeta): ?><p class="hint bcard-lang"><?= e(implode(' · ', $bcMeta)) ?></p><?php endif; ?>

  <div class="bcard-acts">
    <?php if ($bcEx): ?>
      <a class="btn btn-primary btn-sm" href="<?= e($me ? $bc['url'] : url('register?return=' . rawurlencode((string) parse_url($bc['url'], PHP_URL_PATH) . '?' . (string) parse_url($bc['url'], PHP_URL_QUERY)))) ?>"><?= $bc['kind'] === 'local' ? 'Post your own trip' : 'Post a trip like this' ?></a>
    <?php elseif (!$me): ?>
      <a class="btn btn-primary btn-sm" href="<?= e($bcLogin) ?>">Join to connect</a>
    <?php elseif ($bc['kind'] === 'post'): ?>
      <?php if ($bcState === 'accepted'): ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $bc['username'])) ?>">Message</a>
      <?php elseif ($bcState === 'interested'): ?>
        <span class="chip">Request sent</span>
      <?php elseif ($bcState === 'declined'): ?>
        <span class="chip">Answered</span>
      <?php else: ?>
        <form method="post" action="<?= e(url('buddy/' . $bc['id'] . '/interest')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= e($bcBack) ?>">
          <button class="btn btn-primary btn-sm">I'm interested</button></form>
      <?php endif; ?>
    <?php elseif ($bc['kind'] === 'trip'): ?>
      <?php if ($bcState === 'accepted'): ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $bc['username'])) ?>">Message</a>
      <?php elseif ($bcState === 'interested'): ?>
        <span class="chip">Request sent</span>
      <?php elseif ($bcState === 'declined'): ?>
        <span class="chip">Answered</span>
      <?php else: ?>
        <form method="post" action="<?= e(url('connect')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="trip_id" value="<?= (int) $bc['id'] ?>">
          <input type="hidden" name="return" value="<?= e($bcBack) ?>">
          <button class="btn btn-primary btn-sm">I'm interested</button></form>
      <?php endif; ?>
    <?php else: ?>
      <?php if ($bcState === 'accepted'): ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $bc['username'])) ?>">Message</a>
      <?php elseif ($bcState === 'interested'): ?>
        <span class="chip">Request sent</span>
      <?php elseif ($bcState === 'declined'): ?>
        <span class="chip">Answered</span>
      <?php else: ?>
        <form method="post" action="<?= e(url('buddies/local/' . $bc['id'] . '/connect')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= e($bcBack) ?>">
          <button class="btn btn-primary btn-sm">Ask to meet</button></form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!$bcEx): ?><a class="btn btn-ghost btn-sm" href="<?= e($bc['url']) ?>"><?= $bc['kind'] === 'local' ? 'Profile' : 'Details' ?></a><?php endif; ?>
    <?php if ($me && !$bcEx && $bc['kind'] !== 'local'): ?>
      <form method="post" action="<?= e(url('react')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="kind" value="save"><input type="hidden" name="target_type" value="<?= $bc['kind'] === 'post' ? 'buddy' : 'trip' ?>">
        <input type="hidden" name="target_id" value="<?= (int) $bc['id'] ?>"><input type="hidden" name="return" value="<?= e($bcBack) ?>">
        <button class="btn btn-ghost btn-sm" aria-pressed="<?= $bc['saved'] ? 'true' : 'false' ?>"><?= $bc['saved'] ? 'Saved' : 'Save' ?></button></form>
    <?php endif; ?>
  </div>
</article>
