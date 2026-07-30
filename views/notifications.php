<?php /** @var array $items */ ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Notifications</h1>
  <?php if(!$items):?>
    <div class="empty-cta" style="margin-top:16px">
      <h3>Nothing yet.</h3>
      <p class="muted" style="margin:0">Follow travelers and join meetups to see activity here.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
    </div>
  <?php endif;?>
  <ul class="list-plain">
    <?php foreach ($items as $n): ?>
      <li class="card" style="margin-bottom:8px"><div class="card-body" style="padding:12px 16px">
        <?php if ($n['type']==='follow' && $n['actor']): ?>
          <a href="<?= e(url('u/'.$n['actor'])) ?>"><b>@<?= e($n['actor']) ?></b> started following you.</a>
        <?php elseif ($n['type']==='follow'): ?>
          <b>Someone</b> started following you, then deleted their account.
        <?php elseif ($n['type']==='compliment' && $n['actor']): ?>
          <a href="<?= e(url('u/'.$n['actor'])) ?>"><b>@<?= e($n['actor']) ?></b> sent you a compliment.</a>
        <?php elseif ($n['type']==='compliment'): ?>
          <b>Someone</b> sent you a compliment, then deleted their account.
        <?php elseif ($n['type']==='comment' || $n['type']==='mention'):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $verb = $n['type']==='comment' ? 'commented on your' : 'mentioned you in a';
          $noun = ['trip'=>'trip story','review'=>'review','guide'=>'guide','blog_post'=>'blog post','meetup'=>'meetup'][$n['target_type']] ?? 'post';
          $href = rmt_notification_target_url((string)$n['target_type'], (int)$n['target_id']);
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($who) ?></b> <?= e($verb) ?> <?= e($noun) ?>.</a>
          <?php else: ?>
            <b><?= e($who) ?></b> <?= e($verb) ?> <?= e($noun) ?> that is no longer available.
          <?php endif; ?>
        <?php else: ?>
          <b><?= e($n['type']) ?></b> from @<?= e($n['actor']) ?>
        <?php endif; ?>
        <span class="hint"> · <?= e(ago($n['created_at'])) ?></span>
      </div></li>
    <?php endforeach; ?>
  </ul>
  <div style="height:40px"></div>
</div>
