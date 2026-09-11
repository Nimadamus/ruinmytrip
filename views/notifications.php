<?php /** @var array $items @var array $me @var array $unreadIds */ $unreadIds = $unreadIds ?? []; ?>
<div class="wrap" style="max-width:680px;min-height:50vh">
  <h1 style="margin-top:24px">Notifications</h1>
  <?php if (rmt_push_enabled()): ?>
    <?php /* Shown only where the browser can do it; push.js hides it again once a device is on. */ ?>
    <div id="push-cta" hidden class="card" style="margin:12px 0"><div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:220px"><b>Get these on your phone.</b>
        <span class="muted">Replies, mentions, messages and matches, the moment they happen.</span></div>
      <button type="button" class="btn btn-primary js-push-on">Turn on</button>
    </div></div>
    <p id="push-state" class="hint" hidden></p>
  <?php endif; ?>
  <?php if(!$items):?>
    <?php $emptyTitle = 'Nothing yet';
          $emptyWhy = 'This fills with replies to what you post, people following you, and travelers whose dates land on yours. Following somebody is the fastest way to start it.';
          $emptyCtaText = 'Find travelers'; $emptyCtaUrl = url('travelers');
          include __DIR__ . '/_nothing_yet.php'; ?>
  <?php endif;?>
  <ul class="list-plain">
    <?php foreach ($items as $n): ?>
      <?php $rmt_new = !empty($unreadIds[(int) $n['id']]); ?>
      <li class="note<?= $rmt_new ? ' note-new' : '' ?>">
        <img class="avatar" src="<?= e(avatar_url($n['actor_avatar'] ?? null)) ?>" alt="">
        <div class="note-body">
        <?php if ($n['type']==='follow' && $n['actor']): ?>
          <a href="<?= e(url('u/'.$n['actor'])) ?>"><b>@<?= e($n['actor']) ?></b> started following you.</a>
        <?php elseif ($n['type']==='follow'): ?>
          <b>Someone</b> started following you, then deleted their account.
        <?php elseif ($n['type']==='compliment' && $n['actor']): ?>
          <a href="<?= e(url('u/'.$n['actor'])) ?>"><b>@<?= e($n['actor']) ?></b> sent you a compliment.</a>
        <?php elseif ($n['type']==='compliment'): ?>
          <b>Someone</b> sent you a compliment, then deleted their account.
        <?php elseif (in_array($n['type'], ['activity_join','activity_request','activity_accepted',
                                            'activity_declined','activity_removed','activity_cancelled',
                                            'activity_changed'], true)):
          /* Everything that happens around a plan somebody else may be coming to. The activity is
             named, because "your request was accepted" with no subject is a riddle. */
          $ac = q_one("SELECT a.id, a.title, a.day, a.cancelled_at, d.name dest_name
                         FROM trip_activities a
                    LEFT JOIN destinations d ON d.id = a.destination_id
                        WHERE a.id = ? AND a.status = 'published'", [(int) $n['target_id']]);
          $who = $n['actor'] ? '@' . $n['actor'] : 'Somebody';
          $what = $ac ? (string) $ac['title'] : 'a plan';
          $href = $ac ? url('activity/' . (int) $ac['id']) : null;
          $line = [
            'activity_join'      => $who . ' is coming to ' . $what . '.',
            'activity_request'   => $who . ' asked to join ' . $what . '.',
            'activity_accepted'  => 'You are in: ' . $what . '.',
            'activity_declined'  => $who . ' said no to your ask for ' . $what . '.',
            'activity_removed'   => $who . ' removed you from ' . $what . '.',
            'activity_cancelled' => 'Cancelled: ' . $what . '.',
            'activity_changed'   => 'The time or the meeting place changed: ' . $what . '.',
          ][$n['type']] ?? $what;
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($line) ?></b></a>
            <?php if ($n['type'] === 'activity_request'): ?>
              <span class="hint">Accept or decline on the plan.</span>
            <?php elseif ($n['type'] === 'activity_accepted'): ?>
              <span class="hint">The meeting point is on the plan, if there is one.</span>
            <?php endif; ?>
          <?php else: ?>
            <b><?= e($line) ?></b>
          <?php endif; ?>
        <?php elseif ($n['type'] === 'save'):
          /* No actor, ever: who bookmarked something is their business. The number is the news. */
          $saves = function_exists('rmt_save_count') ? rmt_save_count((string) $n['target_type'], (int) $n['target_id']) : 0;
          $href = rmt_notification_target_url((string) $n['target_type'], (int) $n['target_id']);
          $noun = ['trip' => 'trip', 'review' => 'review', 'guide' => 'guide', 'post' => 'post',
                   'trip_photo' => 'photo', 'review_photo' => 'photo', 'meetup' => 'meetup',
                   'collection' => 'list'][$n['target_type']] ?? 'post';
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= $saves > 1 ? $saves . ' people have saved' : 'Somebody saved' ?> your <?= e($noun) ?>.</b></a>
            <span class="hint">Saves are private, so this does not say who.</span>
          <?php else: ?>
            <b>Something of yours was saved, and is no longer there.</b>
          <?php endif; ?>
        <?php elseif ($n['type'] === 'trip_soon' || $n['type'] === 'trip_over'):
          /* The member's own trip, which is why there is no actor and no "@somebody did X". */
          $trip = q_one("SELECT t.id, t.slug, t.date_from, t.date_to, d.name dest_name, d.slug dest_slug
                           FROM trips t LEFT JOIN destinations d ON d.id = t.destination_id
                          WHERE t.id = ? AND t.status = 'published'", [(int) $n['target_id']]);
          $where = $trip ? (string) ($trip['dest_name'] ?: 'your trip') : 'your trip';
          $href = $trip ? url('trip/' . (int) $trip['id'] . '/' . (string) $trip['slug']) : null;
        ?>
          <?php if (!$trip): ?>
            <b>A trip you had posted is no longer there.</b>
          <?php elseif ($n['type'] === 'trip_soon'): ?>
            <?php $days = max(0, (int) ceil((strtotime((string) $trip['date_from']) - time()) / 86400));
                  $company = function_exists('rmt_lifecycle_company') ? rmt_lifecycle_company((int) $trip['id']) : 0; ?>
            <a href="<?= e($href) ?>"><b><?= e($where) ?><?= $days === 0 ? ' starts today' : ($days === 1 ? ' starts tomorrow' : ' starts in ' . $days . ' days') ?>.</b></a>
            <?php if ($company > 0): ?>
              <?= $company === 1 ? 'One other traveler will' : $company . ' other travelers will' ?> be there while you are.
              <?php if (!empty($trip['dest_slug'])): ?>
                <a href="<?= e(url('d/'.$trip['dest_slug'].'/travelers')) ?>">See who</a>.
              <?php endif; ?>
            <?php elseif (!empty($trip['dest_slug'])): ?>
              <a href="<?= e(url('d/'.$trip['dest_slug'].'/travelers')) ?>">See who else is going</a>.
            <?php endif; ?>
          <?php else: ?>
            <a href="<?= e($href) ?>"><b>How was <?= e($where) ?>?</b></a>
            Add your photos and write what nearly ruined it. That is the thing the next person reads.
          <?php endif; ?>
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
            'meetup_comment'   => $who . ' posted on ' . ($title ? $what : 'a meetup you are going to') . '.',
          ][$n['type']];
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($line) ?></b></a>
          <?php else: ?>
            <b><?= e($line) ?></b>
          <?php endif; ?>
        <?php elseif (in_array($n['type'], RMT_CITY_NOTIFY_TYPES, true)):
          $who = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $city = null;
          if ($n['type'] === 'city_going') {
              $city = q_one('SELECT d.name, d.slug FROM trips t JOIN destinations d ON d.id=t.destination_id WHERE t.id=?',
                            [(int)$n['target_id']])
                   ?: q_one('SELECT d.name, d.slug FROM going g JOIN destinations d ON d.id=g.destination_id WHERE g.id=?',
                            [(int)$n['target_id']]);
          } elseif ($n['type'] === 'city_review') {
              $city = q_one('SELECT d.name, d.slug FROM reviews r JOIN destinations d ON d.id=r.destination_id WHERE r.id=?',
                            [(int)$n['target_id']]);
          } else {
              $city = q_one('SELECT d.name, d.slug FROM meetups m JOIN destinations d ON d.id=m.destination_id WHERE m.id=?',
                            [(int)$n['target_id']]);
          }
          /* The city is the reason this notification exists, so it is in the sentence and it is the
             link: the useful next move is that city's people page, not a generic feed. */
          $href = $city ? url('d/'.$city['slug'].'/travelers')
                        : rmt_notification_target_url((string)$n['target_type'], (int)$n['target_id']);
          $where = $city ? $city['name'] : 'a city you saved';
          $line = [
              'city_going'  => $who . ' posted dates for ' . $where . '.',
              'city_meetup' => $who . ' is hosting a meetup in ' . $where . '.',
              'city_review' => $who . ' reviewed something in ' . $where . '.',
          ][$n['type']];
          // A review links to itself; the other two are best answered by the city's people page.
          if ($n['type'] === 'city_review') {
              $href = rmt_notification_target_url('review', (int) $n['target_id']) ?: $href;
          }
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($line) ?></b></a>
          <?php else: ?>
            <b><?= e($line) ?></b>
          <?php endif; ?>
        <?php elseif ($n['type']==='trip_update'):
          $who = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $where = q_one('SELECT d.name FROM posts p LEFT JOIN destinations d ON d.id=p.destination_id
                           WHERE p.id=?', [(int)$n['target_id']])['name'] ?? null;
          $href = rmt_notification_target_url('post', (int)$n['target_id']);
          $line = $who . ' posted an update from ' . ($where ?: 'a trip on your dates') . '.';
        ?>
          <?php if ($href): ?>
            <a href="<?= e($href) ?>"><b><?= e($line) ?></b></a>
          <?php else: ?>
            <b><?= e($line) ?></b>
          <?php endif; ?>
        <?php elseif ($n['type']==='going_too'):
          $who = $n['actor'] ? '@'.$n['actor'] : 'Someone';
          $trip = q_one('SELECT t.id, t.slug, d.name dest_name FROM trips t
                         LEFT JOIN destinations d ON d.id=t.destination_id WHERE t.id=?', [(int)$n['target_id']]);
          $href = $trip ? url('trip/'.(int)$trip['id'].'/'.$trip['slug']) : null;
          $line = $who . ' is going to ' . ($trip && $trip['dest_name'] ? $trip['dest_name'] : 'the same place')
                . ' on your dates.';
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
          <span class="note-when"><?= e(ago($n['created_at'])) ?></span>
        </div>
        <?php if ($rmt_new): ?><span class="note-dot" aria-label="New"></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <div style="height:40px"></div>
</div>
