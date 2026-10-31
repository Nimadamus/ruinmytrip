<?php
/**
 * /hosts-locals/me: one member's roles, in one place.
 *
 * @var array $me @var array $profile @var array $roles @var ?array $host @var array $dests @var array $incoming
 */
$offers = rmt_local_offers_for($profile['local_offers'] ?? null);
$adult = can_host_meetups($me);
$verified = !empty($me['email_verified_at']);
?>
<div class="wrap hl-sec" style="max-width:860px">
  <p class="crumbs"><a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / Your roles</p>
  <h1 class="hl-h2" style="font-size:clamp(1.7rem,4vw,2.3rem)">Your Hosts &amp; Locals profile</h1>
  <p class="hl-sub">One account. Your photo, bio, languages and interests live on <a href="<?= e(url('u/' . $me['username'] . '/edit')) ?>">your profile</a>
    and show on every role.</p>
  <div class="hl-badges" style="margin-bottom:20px">
    <span class="hl-badge hl-badge-role">Traveler</span>
    <?php if ($roles['local']): ?><span class="hl-badge hl-badge-role">Local in <?= e((string) $roles['home']['name']) ?></span><?php endif; ?>
    <?php if ($roles['host']): ?><span class="hl-badge hl-badge-role">Host</span><?php endif; ?>
    <?php if ($verified): ?><span class="hl-badge hl-badge-ok">Email confirmed</span><?php else: ?><span class="hl-badge hl-badge-no">Email not confirmed</span><?php endif; ?>
  </div>

  <?php if (!$verified || !$adult): ?>
    <p class="hl-callout">Meeting travelers and hosting are for members 18 and over with a confirmed email.
      <?php if (!$verified): ?><a href="<?= e(url('verify-email')) ?>">Confirm your email</a>.<?php endif; ?>
      <?php if (!$adult): ?><a href="<?= e(url('account/age?return=%2Fhosts-locals%2Fme')) ?>">Add your date of birth</a> (it stays private).<?php endif; ?></p>
  <?php endif; ?>

  <?php if ($incoming): ?>
  <section class="hl-form" style="margin-bottom:20px" aria-labelledby="hl-req">
    <h2 class="hl-h2" id="hl-req" style="font-size:1.3rem">Travelers asking to meet you</h2>
    <?php foreach ($incoming as $r): ?>
      <div class="hl-ref"><div class="hl-ref-head">
        <img class="avatar" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt="">
        <a href="<?= e(url('u/' . $r['username'])) ?>"><b><?= e((string) ($r['display_name'] ?: '@' . $r['username'])) ?></b></a>
        <span class="hint"><?= e(ago((string) $r['created_at'])) ?></span>
        <form method="post" action="<?= e(url('buddies/local/' . (int) $r['from_user_id'] . '/decide')) ?>" style="margin-left:auto;display:flex;gap:6px"><?= csrf_field() ?>
          <input type="hidden" name="return" value="/hosts-locals/me">
          <button class="btn btn-primary btn-sm" name="answer" value="accepted">Yes</button>
          <button class="btn btn-ghost btn-sm" name="answer" value="declined">No thanks</button>
        </form>
      </div></div>
    <?php endforeach; ?>
    <p class="hl-hint">Say yes and you can message each other. Say no and they are not told.</p>
  </section>
  <?php endif; ?>

  <form class="hl-form" id="local" method="post" action="<?= e(url('hosts-locals/local')) ?>" style="margin-bottom:20px">
    <?= csrf_field() ?>
    <h2 class="hl-h2" style="font-size:1.3rem">Meet a Local: <?= $roles['local'] ? 'you are listed' : 'be a local' ?></h2>
    <p class="hl-hint">Travelers see your city, what you offer and when you are usually free. Never where you live. Nobody can message you until you accept.</p>
    <label for="l-dest">The city you live in</label>
    <select id="l-dest" name="destination_id" required>
      <option value="">Pick your city</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) ($profile['home_destination_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name'] . ', ' . $d['country']) ?></option><?php endforeach; ?>
    </select>
    <fieldset style="margin-top:14px"><legend>What you would be happy to do</legend>
      <div class="hl-checks">
        <?php foreach (RMT_LOCAL_OFFERS as $k => $l): ?>
          <label><input type="checkbox" name="offers[]" value="<?= $k ?>"<?= in_array($k, $offers, true) ? ' checked' : '' ?>> <?= e($l) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="hl-hint">Being a local never means offering a place to stay. That is hosting, below.</p>
    </fieldset>
    <label for="l-avail">When you are usually free <span class="hint">(optional)</span></label>
    <input id="l-avail" type="text" name="availability" maxlength="160" value="<?= e((string) ($profile['local_availability'] ?? '')) ?>" placeholder="Weekday evenings and Sunday mornings">
    <p style="margin:14px 0 0;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit"<?= (!$verified || !$adult) ? ' disabled' : '' ?>><?= $roles['local'] ? 'Save' : 'List me as a local' ?></button>
    </p>
  </form>
  <?php if ($roles['local']): ?>
    <form method="post" action="<?= e(url('hosts-locals/local')) ?>" style="margin:-8px 0 20px"><?= csrf_field() ?>
      <input type="hidden" name="off" value="1"><button class="btn btn-ghost btn-sm">Stop being listed as a local</button></form>
  <?php endif; ?>

  <section class="hl-form" id="host" aria-labelledby="hl-host">
    <h2 class="hl-h2" id="hl-host" style="font-size:1.3rem">Stay With a Local: hosting</h2>
    <?php if (!$host): ?>
      <p>Have a spare room or couch? Apply and a moderator reviews it. Then travelers can ask to stay, and you decide.</p>
      <a class="btn btn-primary" href="<?= e(url('hosts-locals/become-a-host')) ?>">Apply to host</a>
    <?php else: ?>
      <p><b>Status: <?= e(ucfirst((string) $host['status'])) ?></b> in <?= e((string) ($host['dest_name'] ?? '')) ?>.
        <?php if ($host['status'] === 'rejected' && ($host['review_note'] ?? '') !== ''): ?><br><span class="hint">Moderator note: <?= e((string) $host['review_note']) ?></span><?php endif; ?></p>
      <p style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('hosts-locals/become-a-host')) ?>">Edit host profile</a>
        <?php foreach ([['approved', 'paused', 'Pause hosting'], ['paused', 'approved', 'Resume hosting']] as [$from, $to, $label]): if ($host['status'] === $from): ?>
          <form method="post" action="<?= e(url('hosts-locals/host/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $to ?>"><button class="btn btn-primary btn-sm"><?= $label ?></button></form>
        <?php endif; endforeach; ?>
        <?php if (in_array($host['status'], ['pending', 'approved', 'paused'], true)): ?>
          <form method="post" action="<?= e(url('hosts-locals/host/status')) ?>" data-confirm-submit="Withdraw your host profile?"><?= csrf_field() ?><input type="hidden" name="status" value="withdrawn"><button class="btn btn-ghost btn-sm">Withdraw</button></form>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </section>

  <?php /* Growth: a member brings in the people they know who live somewhere travelers go. The
           link is the existing invite link (?ref=, app/invites.php) pointed at this page. */
    $hlInvite = url('hosts-locals?ref=' . rawurlencode((string) $me['username']));
    $hlInviteText = 'Travelers come to your city and want to meet somebody who lives there. Be a local on RuinMyTrip, free: ' . $hlInvite;
    $hlProfile = url('u/' . $me['username']); ?>
  <section class="hl-form" id="invite" style="margin-top:20px" aria-labelledby="hl-inv">
    <h2 class="hl-h2" id="hl-inv" style="font-size:1.3rem">Invite a local or a host</h2>
    <p class="hl-hint">Know somebody who lives where travelers go? Send them your link. You will see when they join.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0">
      <label class="sr-only" for="hl-invite-link">Your invite link</label>
      <input id="hl-invite-link" type="text" readonly value="<?= e($hlInvite) ?>" style="flex:1;min-width:220px;min-height:44px" data-select-on-click>
      <button type="button" class="btn btn-primary" data-copy="<?= e($hlInvite) ?>">Copy link</button>
    </div>
    <?php $shareUrl = $hlInvite; $shareText = $hlInviteText; include __DIR__ . '/_share.php'; ?>
    <p class="hl-hint">Your public profile to share: <a href="<?= e($hlProfile) ?>"><?= e($hlProfile) ?></a>
      <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($hlProfile) ?>">Copy</button></p>
  </section>

  <p style="margin-top:20px"><a href="<?= e(url('u/' . $me['username'] . '/references')) ?>">Your references</a> &middot;
    <a href="<?= e(url('stays')) ?>">Your stays</a> &middot; <a href="<?= e(url('buddies/mine')) ?>">Your travel buddy posts</a> &middot; <a href="<?= e(url('messages')) ?>">Messages</a> &middot;
    <a href="<?= e(url('hosts-locals/safety')) ?>">Safety</a></p>
</div>
