<?php /** @var array $items @var array $me */ ?>
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
        <?php elseif (in_array($n['type'], RMT_MEETUP_NOTIFY_TYPES, true)):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $href = rmt_notification_target_url((string)$n['target_type'], (int)$n['target_id']);
          $title = q_one('SELECT title FROM meetups WHERE id=?', [(int)$n['target_id']])['title'] ?? null;
          $what = $title ? '"' . $title . '"' : 'your meetup';
          /* Worded so the important half survives being skim-read in a list: cancelled and moved
             lead with what happened to the plan, not with who did it. */
          $line = [
            'meetup_rsvp'      => $who . ' is going to ' . ($title ? $what : 'your meetup') . '.',
            'meetup_changed'   => 'The time changed for ' . ($title ? $what : 'a meetup you are going to') . '.',
            'meetup_cancelled' => 'Cancelled: ' . ($title ? $what : 'a meetup you were going to') . '.',
            'meetup_nearby'    => $who . ' is hosting ' . ($title ? $what : 'a meetup') . ' while you are in town.',
          ][$n['type']];
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($line) ?></b></a>
          <?php else: ?>
            <b><?= e($line) ?></b>
          <?php endif; ?>
        <?php elseif ($n['type']==='invite_joined' && $n['actor']): ?>
          <a href="<?= e(url('u/'.$n['actor'])) ?>"><b>@<?= e($n['actor']) ?></b> joined from your invite link. Say hi.</a>
        <?php elseif ($n['type']==='invite_joined'): ?>
          <b>Someone</b> joined from your invite link, then left.
        <?php elseif ($n['type']==='repost'):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $href = rmt_notification_target_url('post', (int)$n['target_id']);
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($who) ?></b> reposted you.</a>
          <?php else: ?>
            <b><?= e($who) ?></b> reposted you.
          <?php endif; ?>
        <?php elseif ($n['type']==='like'):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $noun = ['trip'=>'trip story','review'=>'review','guide'=>'guide','blog_post'=>'blog post',
                   'meetup'=>'meetup','collection'=>'list','post'=>'post'][$n['target_type']] ?? 'post';
          $href = rmt_notification_target_url((string)$n['target_type'], (int)$n['target_id']);
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($who) ?></b> liked your <?= e($noun) ?>.</a>
          <?php else: ?>
            <b><?= e($who) ?></b> liked your <?= e($noun) ?>.
          <?php endif; ?>
        <?php elseif ($n['type']===RMT_MATCH_NOTIFY_TYPE):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $dest = q_one('SELECT d.name FROM going g JOIN destinations d ON d.id=g.destination_id WHERE g.id=?',
                        [(int)$n['target_id']])['name'] ?? null;
          /* Leads with the fact that matters -- the same city at the same time -- because that is
             what makes somebody open it rather than clear it. */
          $line = $dest ? $who.' will be in '.$dest.' while you are.' : $who.' has dates that overlap yours.';
        ?>
          <a href="<?= e(url('matches')) ?>"><b><?= e($line) ?></b></a>
        <?php elseif ($n['type']==='going'):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $href = rmt_notification_target_url('going', (int)$n['target_id']);
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($who) ?></b> shared upcoming travel dates.</a>
          <?php else: ?>
            <b><?= e($who) ?></b> shared upcoming travel dates.
          <?php endif; ?>
        <?php elseif ($n['type']==='message'):
          $who  = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $href = rmt_notification_target_url((string)$n['target_type'], (int)$n['target_id'], (int)$me['id']);
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($who) ?></b> sent you a message.</a>
          <?php else: ?>
            <b><?= e($who) ?></b> sent you a message.
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
