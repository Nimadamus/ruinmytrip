<?php /** @var array $m @var array $rsvps @var ?array $me @var bool $mine */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('meetups')) ?>">Meetups</a> / <?= e($m['title']) ?></p></div>
<div class="wrap" style="max-width:820px">
  <span class="chip"><?= e($m['dest_name']) ?></span>
  <h1><?= e($m['title']) ?></h1>
  <p class="muted"><?= e(date('l, M j, Y · g:ia', strtotime((string)$m['date_start']))) ?>
    <?php if($m['date_end']):?>– <?= e(date('g:ia', strtotime((string)$m['date_end']))) ?><?php endif;?>
    · Hosted by <a href="<?= e(url('u/'.$m['host']['username'])) ?>">@<?= e($m['host']['username']) ?></a></p>

  <div class="callout"><b>How location works:</b> this meetup is tied to the destination only. The exact meeting spot is a public place shared with confirmed attendees in-app. We never post your precise or live location.</div>

  <p style="font-size:1.1rem"><?= nl2br(e($m['description'])) ?></p>

  <div class="callout warn"><b>Meetup safety:</b> meet in public, tell someone your plans, trust your instincts, and use <a href="<?= e(url('report')) ?>">report</a> or block if anything feels off. You must be 18+ to attend. <a href="<?= e(url('safety')) ?>">Full safety guide →</a></div>

  <div style="display:flex;gap:10px;align-items:center;margin:20px 0">
    <?php if ($me): ?>
      <form method="post" action="<?= e(url('meetup/'.$m['id'].'/rsvp')) ?>"><?= csrf_field() ?>
        <button class="btn <?= $mine?'btn-ghost':'btn-primary' ?>"><?= $mine?'Cancel RSVP':'RSVP — I\'m going' ?></button></form>
    <?php else: ?><a class="btn btn-primary" href="<?= e(url('login')) ?>">Sign in to RSVP</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('report?target_type=meetup&target_id='.$m['id'])) ?>">⚑ Report</a>
  </div>

  <h2>Going (<?= count($rsvps) ?>)</h2>
  <div class="tag-list">
    <?php foreach ($rsvps as $r): ?>
      <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.3rem .7rem" href="<?= e(url('u/'.$r['username'])) ?>">
        <img class="avatar" style="width:22px;height:22px" src="<?= e($r['avatar_url']??'') ?>" alt="">@<?= e($r['username']) ?></a>
    <?php endforeach; ?>
    <?php if(!$rsvps):?><span class="muted">Be the first to RSVP.</span><?php endif;?>
  </div>
  <div style="height:50px"></div>
</div>
