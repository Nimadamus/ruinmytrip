<?php
/**
 * A member's own trust page: their numbers, how the numbers are counted, their state, the
 * history of every moderator decision about them, an appeal if they are restricted, and the
 * travelers they can vouch for or flag.
 *
 * @var array $me
 * @var array $t     rmt_trust_summary()
 * @var array $open  rmt_trust_reviewable()
 */
$restricted = $t['trust_state'] === 'restricted';
$score = (int) $t['trust_score'];
$appeal = $t['appeal'];
?>
<div class="wrap" style="max-width:760px;padding-bottom:50px">
  <h1 style="margin-bottom:4px">Your trust</h1>
  <p class="hint" style="margin-top:0">These are your numbers. Only you and our moderators see them. They are not shown on your profile and nothing on the site is ranked by them.</p>

  <?php if ($restricted): ?>
    <div class="card trust-restricted" style="margin:18px 0;border-left:4px solid #b45309"><div class="card-body">
      <h2 style="margin:0 0 6px;font-size:1.15rem">Your account is restricted</h2>
      <p style="margin:0 0 8px">A moderator reviewed flags from travelers you met and paused your messaging<?= $t['trust_state_at'] ? ' on ' . e(date('M j, Y', strtotime((string) $t['trust_state_at']))) : '' ?>. You cannot send private messages or post in city rooms and trip chats for now. You can still sign in, read your messages, and use the rest of the site.</p>
      <?php if (!empty($t['reason'])): ?>
        <p style="margin:0 0 8px"><b>The reason given:</b> <?= e((string) $t['reason']) ?></p>
      <?php endif; ?>

      <?php if ($appeal && $appeal['status'] === 'open'): ?>
        <p style="margin:0"><b>Your appeal is with a moderator</b> since <?= e(date('M j, Y', strtotime((string) $appeal['created_at']))) ?>. You will be told the answer here and by email.</p>
      <?php elseif ($t['can_appeal']): ?>
        <?php if ($appeal && $appeal['status'] === 'upheld'): ?>
          <p style="margin:0 0 8px" class="hint">Your last appeal was answered on <?= e(date('M j, Y', strtotime((string) $appeal['decided_at']))) ?><?= !empty($appeal['decision_note']) ? ': ' . e((string) $appeal['decision_note']) : '.' ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('trust/appeal')) ?>" id="appeal"><?= csrf_field() ?>
          <label for="appeal-body"><b>Appeal this decision</b></label>
          <p class="hint" style="margin:2px 0 6px">Tell us what happened from your side. A different look at the same flags, by a person, is what you get.</p>
          <textarea id="appeal-body" name="body" rows="4" minlength="<?= RMT_TRUST_APPEAL_MIN ?>" maxlength="<?= RMT_TRUST_APPEAL_MAX ?>" required style="width:100%"></textarea>
          <button class="btn btn-primary btn-sm" style="margin-top:10px">Send appeal</button>
        </form>
      <?php elseif ($appeal && $appeal['status'] === 'upheld'): ?>
        <p style="margin:0"><b>Your appeal was answered</b> on <?= e(date('M j, Y', strtotime((string) $appeal['decided_at']))) ?><?= !empty($appeal['decision_note']) ? ': ' . e((string) $appeal['decision_note']) : '.' ?>
          <span class="hint">You can appeal again <?= RMT_TRUST_APPEAL_AGAIN_DAYS ?> days after that date.</span></p>
      <?php endif; ?>
    </div></div>
  <?php endif; ?>

  <div class="card" style="margin:18px 0"><div class="card-body">
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:120px;text-align:center;padding:10px 6px">
        <div style="font-size:2rem;font-weight:700;line-height:1.1" id="trust-score"><?= $score ?></div>
        <div class="hint">Trust score</div>
      </div>
      <div style="flex:1;min-width:120px;text-align:center;padding:10px 6px">
        <div style="font-size:2rem;font-weight:700;line-height:1.1" id="trust-vouches"><?= (int) $t['vouch_count'] ?></div>
        <div class="hint"><?= (int) $t['vouch_count'] === 1 ? 'Traveler vouched for you' : 'Travelers vouched for you' ?></div>
      </div>
      <div style="flex:1;min-width:120px;text-align:center;padding:10px 6px">
        <div style="font-size:2rem;font-weight:700;line-height:1.1" id="trust-flags"><?= (int) $t['flag_count'] ?></div>
        <div class="hint"><?= (int) $t['flag_count'] === 1 ? 'Traveler flagged you' : 'Travelers flagged you' ?></div>
      </div>
    </div>
    <p style="margin:12px 0 0;text-align:center"><b>Status:</b> <?= $restricted ? 'Restricted, messaging paused' : 'In good standing' ?></p>
  </div></div>

  <h2>How this is counted</h2>
  <ul>
    <li>After a trip you shared with somebody is over, or after you were both checked in to the same city and both wrote in its room, each of you can leave the other one vouch or one flag. You have <?= RMT_TRUST_REVIEW_DAYS ?> days.</li>
    <li>Each traveler who vouched for you adds 1. Each traveler who flagged you takes away <?= RMT_TRUST_FLAG_WEIGHT ?>. A traveler counts once, however many times you met.</li>
    <li>Your score right now: <?= (int) $t['vouch_count'] ?> minus <?= RMT_TRUST_FLAG_WEIGHT ?> times <?= (int) $t['flag_count'] ?> is <?= $score ?>.</li>
    <li>Flags never restrict an account by themselves, and neither does a low score. When <?= RMT_TRUST_REVIEW_FLAGS ?> or more travelers have flagged the same member, a moderator reads the flags and decides.</li>
    <li>If a moderator restricts your account you are told the same minute, here and by email, with the reason, and you can appeal on this page.</li>
    <li>Who vouched or flagged, and anything they wrote, stays private. Only moderators read it.</li>
  </ul>

  <h2>Travelers you met</h2>
  <?php if (!$open): ?>
    <p class="muted">Nobody to vouch for right now. When a trip you shared ends, or a check-in where you talked with other travelers runs out, they show up here.</p>
  <?php else: ?>
    <p class="hint">Vouch for somebody you would travel with again. Flag somebody who made you feel unsafe, lied about who they are, or asked for money. Your answer is private and cannot be changed.</p>
    <?php foreach ($open as $i => $o): ?>
      <div class="card" style="margin:12px 0"><div class="card-body">
        <p style="margin:0 0 8px;display:flex;align-items:center;gap:10px">
          <img class="avatar" style="width:36px;height:36px" src="<?= e(avatar_url($o['avatar_url'] ?? null)) ?>" alt="">
          <span><a href="<?= e(url('u/' . $o['username'])) ?>"><b><?= e((string) ($o['display_name'] ?: $o['username'])) ?></b></a>
            <span class="hint" style="display:block"><?= e($o['label']) ?></span></span>
        </p>
        <form method="post" action="<?= e(url('trust/review')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="subject_id" value="<?= (int) $o['subject_id'] ?>">
          <input type="hidden" name="context_key" value="<?= e($o['context_key']) ?>">
          <label for="trust-note-<?= (int) $i ?>" class="hint">Anything a moderator should know (optional, private)</label>
          <textarea id="trust-note-<?= (int) $i ?>" name="note" rows="2" maxlength="<?= RMT_TRUST_NOTE_MAX ?>" style="width:100%"></textarea>
          <p style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 0">
            <button class="btn btn-primary btn-sm" name="answer" value="vouch">Vouch</button>
            <button class="btn btn-ghost btn-sm" name="answer" value="flag">Flag</button>
          </p>
        </form>
      </div></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($t['events']): ?>
    <h2>History</h2>
    <ul>
      <?php foreach ($t['events'] as $ev): ?>
        <li><?= e(date('M j, Y', strtotime((string) $ev['created_at']))) ?>:
          <?= e(rmt_trust_reason_label((string) $ev['reason'])) ?>.
          <?php if (!empty($ev['note'])): ?><?= e((string) $ev['note']) ?><?php endif; ?>
          <span class="hint">(<?= (int) $ev['vouch_count'] ?> vouched, <?= (int) $ev['flag_count'] ?> flagged, score <?= (int) $ev['trust_score'] ?>)</span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
