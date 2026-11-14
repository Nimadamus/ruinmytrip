<?php
/**
 * A member's own trust page: their numbers, how the numbers are counted, their state and its
 * history, and the travelers they can vouch for or flag.
 *
 * @var array $me
 * @var array $t     rmt_trust_summary()
 * @var array $open  rmt_trust_reviewable()
 */
$restricted = $t['trust_state'] === 'restricted';
$score = (int) $t['trust_score'];
?>
<div class="wrap" style="max-width:760px;padding-bottom:50px">
  <h1 style="margin-bottom:4px">Your trust</h1>
  <p class="hint" style="margin-top:0">These are your numbers. Only you see them here. Nothing about your account is scored or ranked anywhere you cannot see.</p>

  <?php if ($restricted): ?>
    <div class="card trust-restricted" style="margin:18px 0;border-left:4px solid #b45309"><div class="card-body">
      <h2 style="margin:0 0 6px;font-size:1.15rem">Your account is restricted</h2>
      <p style="margin:0 0 8px"><?= (int) $t['flag_count'] ?> travelers you met have flagged your account, so sending private messages and posting in city rooms is paused<?= $t['trust_state_at'] ? ' since ' . e(date('M j, Y', strtotime((string) $t['trust_state_at']))) : '' ?>.</p>
      <p style="margin:0 0 8px">You can still sign in, read your messages, and use the rest of the site. A moderator has been told and looks at every restriction. If the flags do not hold up they are set aside and messaging comes back on its own.</p>
      <p style="margin:0"><a class="btn btn-ghost btn-sm" href="<?= e(url('contact')) ?>">Tell us your side</a></p>
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
    <li>If <?= RMT_TRUST_RESTRICT_FLAGS ?> or more travelers have flagged you and your score is below zero, messaging is paused and a moderator looks. You are told the same minute, here and by email.</li>
    <li>Who vouched or flagged, and anything they wrote, stays private. Only moderators read it.</li>
  </ul>

  <h2>Travelers you met</h2>
  <?php if (!$open): ?>
    <p class="muted">Nobody to vouch for right now. When a trip you shared ends, or a check-in where you talked with other travelers runs out, they show up here.</p>
  <?php else: ?>
    <p class="hint">Vouch for somebody you would travel with again. Flag somebody who made you feel unsafe, lied about who they are, or asked for money. Your answer is private and cannot be changed.</p>
    <?php foreach ($open as $o): ?>
      <div class="card" style="margin:12px 0"><div class="card-body">
        <p style="margin:0 0 8px;display:flex;align-items:center;gap:10px">
          <img class="avatar" style="width:36px;height:36px" src="<?= e(avatar_url($o['avatar_url'] ?? null)) ?>" alt="">
          <span><a href="<?= e(url('u/' . $o['username'])) ?>"><b><?= e((string) ($o['display_name'] ?: $o['username'])) ?></b></a>
            <span class="hint" style="display:block"><?= e($o['label']) ?></span></span>
        </p>
        <form method="post" action="<?= e(url('trust/review')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="subject_id" value="<?= (int) $o['subject_id'] ?>">
          <input type="hidden" name="context_key" value="<?= e($o['context_key']) ?>">
          <label for="note-<?= (int) $o['subject_id'] ?>-<?= e($o['context_key']) ?>" class="hint">Anything a moderator should know (optional, private)</label>
          <textarea id="note-<?= (int) $o['subject_id'] ?>-<?= e($o['context_key']) ?>" name="note" rows="2" maxlength="<?= RMT_TRUST_NOTE_MAX ?>" style="width:100%"></textarea>
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
          <?= e(rmt_trust_reason_label((string) $ev['reason'], (string) $ev['to_state'])) ?>.
          <?= $ev['to_state'] === 'restricted' ? 'Messaging was paused.' : 'Messaging was turned back on.' ?>
          <span class="hint">(<?= (int) $ev['vouch_count'] ?> vouched, <?= (int) $ev['flag_count'] ?> flagged, score <?= (int) $ev['trust_score'] ?>)</span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
