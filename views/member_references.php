<?php
/**
 * /u/{username}/references: what members who actually connected with this person say.
 *
 * @var array $u @var array $refs @var ?array $mine @var ?string $why @var array $errors @var array $old @var ?array $basis
 */
$me = current_user();
$name = (string) ($u['display_name'] ?: '@' . $u['username']);
$stars = static fn(int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
$o = static fn(string $k) => (string) ($old[$k] ?? '');
$isMe = $me && (int) $me['id'] === (int) $u['id'];
?>
<div class="wrap hl-sec" style="max-width:820px">
  <p class="crumbs"><a href="<?= e(url('u/' . $u['username'])) ?>">@<?= e((string) $u['username']) ?></a> / References</p>
  <div class="hl-person-top" style="margin-bottom:10px">
    <img class="avatar" src="<?= e(avatar_url($u['avatar_url'] ?? null)) ?>" alt="">
    <div><h1 class="hl-h2" style="font-size:1.7rem;margin:0">References for <?= e($name) ?></h1>
      <span class="hint">From members who met or traveled with them through RuinMyTrip. Separate from place and city reviews.</span></div>
  </div>

  <?php if ($refs): ?>
    <section class="hl-form" style="margin:16px 0">
      <?php foreach ($refs as $r): ?>
        <article class="hl-ref">
          <div class="hl-ref-head">
            <img class="avatar" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt="">
            <a href="<?= e(url('u/' . $r['username'])) ?>"><b><?= e((string) ($r['display_name'] ?: '@' . $r['username'])) ?></b></a>
            <span class="hl-stars" aria-label="<?= (int) $r['rating_overall'] ?> out of 5"><?= $stars((int) $r['rating_overall']) ?></span>
            <span class="hl-badge"><?= e(RMT_REF_KINDS[$r['kind']] ?? 'Connected here') ?></span>
            <span class="hint"><?= e(ago((string) $r['created_at'])) ?></span>
          </div>
          <p style="margin:8px 0 4px;white-space:pre-wrap"><?= e((string) $r['body']) ?></p>
          <p class="hint" style="margin:0">
            <?php foreach (['rating_communication' => 'Communication', 'rating_reliability' => 'Reliability', 'rating_accuracy' => 'Profile accuracy', 'rating_hospitality' => 'Hospitality'] as $k => $l):
              if (!empty($r[$k])): ?><?= $l ?> <?= (int) $r[$k] ?>/5 &middot; <?php endif; endforeach; ?>
            <?php if ($me && (int) $me['id'] !== (int) $r['from_user_id']): ?>
              <a href="<?= e(url('report?target_type=reference&target_id=' . (int) $r['id'])) ?>"><?= $isMe ? 'Dispute this reference' : 'Report' ?></a>
            <?php endif; ?></p>
        </article>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <div class="hl-empty" style="margin:16px 0"><h3>No references yet</h3>
      <p>References appear after members connect here: a local who said yes, or people who traveled together.</p></div>
  <?php endif; ?>

  <?php if ($me && !$isMe): ?>
    <?php if ($why): ?>
      <p class="hl-callout"><?= e($why) ?></p>
    <?php else: ?>
      <form class="hl-form" method="post" action="<?= e(url('u/' . $u['username'] . '/references')) ?>">
        <?= csrf_field() ?>
        <h2 class="hl-h2" style="font-size:1.3rem"><?= $mine ? 'Update your reference' : 'Write a reference' ?></h2>
        <p class="hl-hint">You connected here: <?= e(RMT_REF_KINDS[$basis['kind'] ?? ''] ?? '') ?>. Say what happened, plainly. One reference per person.</p>
        <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="hl-ratings">
          <?php $rk = ['rating_overall' => 'Overall experience', 'rating_communication' => 'Communication', 'rating_reliability' => 'Reliability', 'rating_accuracy' => 'Accuracy of their profile'];
                if (($basis['kind'] ?? '') === 'stayed') $rk['rating_hospitality'] = 'Hospitality';
                foreach ($rk as $k => $l): ?>
            <div><label for="r-<?= $k ?>"><?= $l ?><?= $k === 'rating_overall' ? '' : ' <span class="hint">(optional)</span>' ?></label>
              <select id="r-<?= $k ?>" name="<?= $k ?>"<?= $k === 'rating_overall' ? ' required' : '' ?>>
                <option value=""><?= $k === 'rating_overall' ? 'Choose' : 'Skip' ?></option>
                <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (int) $o($k) === $i ? ' selected' : '' ?>><?= $i ?> of 5</option><?php endfor; ?>
              </select></div>
          <?php endforeach; ?>
        </div>
        <label for="r-body">Your reference</label>
        <textarea id="r-body" name="body" minlength="<?= RMT_REF_BODY_MIN ?>" maxlength="<?= RMT_REF_BODY_MAX ?>" required><?= e($o('body')) ?></textarea>
        <p style="margin:14px 0 0"><button class="btn btn-primary" type="submit"><?= $mine ? 'Update' : 'Publish reference' ?></button></p>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
