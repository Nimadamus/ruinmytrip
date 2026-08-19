<?php /** @var array $m @var array $rsvps @var ?array $me @var bool $mine @var bool $isHost @var int $going @var bool $isFull @var bool $isPast */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('meetups')) ?>">Meetups</a> / <?= e($m['title']) ?></p></div>
<div class="wrap" style="max-width:820px">
  <span class="chip"><?= e($m['dest_name']) ?></span>
  <h1><?= e($m['title']) ?></h1>
  <p class="muted"><?= e(date('l, M j, Y · g:ia', strtotime((string)$m['date_start']))) ?>
    <?php if($m['date_end']):?>– <?= e(date('g:ia', strtotime((string)$m['date_end']))) ?><?php endif;?>
    · Hosted by <a href="<?= e(url('u/'.$m['host']['username'])) ?>">@<?= e($m['host']['username']) ?></a></p>

  <?php if ($m['status'] === 'cancelled'): ?>
    <?php /* Said first and said plainly. Somebody arriving from a link they were sent has to learn
             this before they read the plan, not after. */ ?>
    <div class="callout warn" style="margin:14px 0"><b>This meetup was cancelled by the host.</b>
      It is not happening. The page stays up so everyone who RSVPed can see why their plan changed.</div>
  <?php elseif ($isPast): ?>
    <div class="callout" style="margin:14px 0"><b>This meetup has already happened.</b></div>
  <?php endif; ?>

  <div class="callout"><b>How location works:</b> this meetup is tied to the destination only. If the host names a specific meeting spot, it's in the description below, visible to everyone — RuinMyTrip has no separate private-location feature. We never post anyone's precise or live location.</div>

  <p style="font-size:1.1rem"><?= nl2br(e($m['description'])) ?></p>

  <div class="callout warn"><b>Meetup safety:</b> meet in public, tell someone your plans, trust your instincts, and use <a href="<?= e(url('report')) ?>">report</a> or block if anything feels off. You must be 18+ to attend. <a href="<?= e(url('safety')) ?>">Full safety guide →</a></div>

  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:20px 0">
    <?php /* Withdrawing is always offered, including from something cancelled or past: being stuck
             on the going list of a meetup you are not attending is the annoying direction of this.
             Joining is what the state gates, and the server gates it again -- the button is not
             the door. */ ?>
    <?php $closed = $m['status'] === 'cancelled' || $isPast; ?>
    <?php if ($me && ($mine || !$closed)): ?>
      <?php if ($mine || !$isFull): ?>
        <form method="post" action="<?= e(url('meetup/'.$m['id'].'/rsvp')) ?>" style="margin:0"><?= csrf_field() ?>
          <button class="btn <?= $mine?'btn-ghost':'btn-primary' ?>"><?= $mine?'Cancel RSVP':'RSVP — I\'m going' ?></button></form>
      <?php else: ?>
        <span class="chip">Full</span>
      <?php endif; ?>
    <?php elseif (!$me && !$closed): ?>
      <a class="btn btn-primary" href="<?= e(url('login?return=' . rawurlencode('/meetup/'.(int)$m['id']))) ?>">Sign in to RSVP</a>
    <?php endif; ?>
    <?php if ($isHost && $m['status'] !== 'cancelled'): ?>
      <a class="btn btn-ghost" href="<?= e(url('meetup/'.(int)$m['id'].'/edit')) ?>">Edit</a>
      <form method="post" action="<?= e(url('meetup/'.(int)$m['id'].'/cancel')) ?>" style="margin:0"
            onsubmit="return confirm('Cancel this meetup? Everyone who RSVPed will see it is off.')">
        <?= csrf_field() ?><button class="btn btn-ghost">Cancel meetup</button>
      </form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('report?target_type=meetup&target_id='.$m['id'])) ?>">⚑ Report</a>
  </div>

  <h2>Going (<?= $going ?><?php if ((int)$m['capacity'] > 0): ?> of <?= (int)$m['capacity'] ?><?php endif; ?>)</h2>
  <div class="tag-list">
    <?php foreach ($rsvps as $r): ?>
      <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.3rem .7rem" href="<?= e(url('u/'.$r['username'])) ?>">
        <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($r['avatar_url']??null)) ?>" alt="">@<?= e($r['username']) ?></a>
    <?php endforeach; ?>
    <?php if(!$rsvps):?><span class="muted">Be the first to RSVP.</span><?php endif;?>
  </div>
  <div style="height:50px"></div>
</div>
