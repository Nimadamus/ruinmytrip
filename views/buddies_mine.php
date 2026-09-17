<?php /** @var array $posts @var array $trips @var array $received @var array $sent @var array $buddies @var array $saved @var array $matches @var array $profile @var array $dests @var array $me */
$back = '/buddies/mine';
$range = static fn($a, $b): string => date('M j', strtotime((string) $a)) . ' to ' . date('M j, Y', strtotime((string) $b));
$stateLabel = ['interested' => 'Waiting for an answer', 'accepted' => 'Accepted', 'declined' => 'Not this time', 'withdrawn' => 'Withdrawn'];
?>
<div class="wrap bdash" style="max-width:920px;padding-bottom:50px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('buddies')) ?>">Travel buddies</a> / Yours</p>
  <div class="section-head">
    <div><h1 style="margin:0">Your travel buddies</h1>
      <p class="hint" style="margin:.3rem 0 0">Your upcoming trips, who wants to come along, and the people you have connected with.</p></div>
    <a class="btn btn-accent btn-sm" href="<?= e(url('buddies/new')) ?>">Post a trip</a>
  </div>

  <h2>Requests waiting on you <?php if ($received): ?><span class="chip"><?= count($received) ?></span><?php endif; ?></h2>
  <?php if (!$received): ?>
    <p class="muted">Nobody is waiting on an answer.</p>
  <?php else: foreach ($received as $r): ?>
    <div class="bdash-row">
      <img class="avatar" src="<?= e(avatar_url($r['avatar_url'])) ?>" alt="">
      <div class="bdash-main">
        <b><a href="<?= e(url('u/' . $r['username'])) ?>">@<?= e($r['username']) ?></a></b>
        <?php if ($r['kind'] === 'local'): ?>would like to meet you as a local.
        <?php else: ?>wants to join <a href="<?= e(url(($r['kind'] === 'post' ? 'buddy/' : 'trip/') . (int) $r['target_id'])) ?>"><?= e((string) $r['target_title']) ?></a>.<?php endif; ?>
        <?php if (!empty($r['note'])): ?><p style="margin:.3rem 0 0"><?= nl2br(e((string) $r['note'])) ?></p><?php endif; ?>
      </div>
      <div class="bdash-acts">
        <?php
          if ($r['kind'] === 'post') { $act = url('buddy/' . (int) $r['target_id'] . '/decide/' . (int) $r['from_id']); $yes = 'accepted'; $no = 'declined'; }
          elseif ($r['kind'] === 'trip') { $act = url('connect/' . (int) $r['connect_id'] . '/decide'); $yes = 'accept'; $no = 'decline'; }
          else { $act = url('buddies/local/' . (int) $r['from_id'] . '/decide'); $yes = 'accepted'; $no = 'declined'; }
        ?>
        <form method="post" action="<?= e($act) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>">
          <input type="hidden" name="answer" value="<?= e($yes) ?>"><button class="btn btn-primary btn-sm">Accept</button></form>
        <form method="post" action="<?= e($act) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>">
          <input type="hidden" name="answer" value="<?= e($no) ?>"><button class="btn btn-ghost btn-sm">Pass</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <h2>Your travel buddies</h2>
  <?php if (!$buddies): ?>
    <p class="muted">Once you accept somebody, or they accept you, they show up here and you can message each other.</p>
  <?php else: foreach ($buddies as $bu): ?>
    <div class="bdash-row">
      <img class="avatar" src="<?= e(avatar_url($bu['avatar_url'])) ?>" alt="">
      <div class="bdash-main"><b><a href="<?= e(url('u/' . $bu['username'])) ?>"><?= e(trim((string) $bu['display_name']) !== '' ? $bu['display_name'] : '@' . $bu['username']) ?></a></b>
        <br><span class="hint"><?= e((string) $bu['what']) ?></span></div>
      <div class="bdash-acts"><a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $bu['username'])) ?>">Message</a></div>
    </div>
  <?php endforeach; endif; ?>

  <h2>Your upcoming trips</h2>
  <?php if (!$posts && !$trips): ?>
    <p class="muted">Nothing coming up. <a href="<?= e(url('buddies/new')) ?>">Post a trip</a> and travelers heading the same way can find you.</p>
  <?php endif; ?>
  <?php foreach ($posts as $p): $m = $matches['post:' . $p['id']] ?? null; ?>
    <div class="bdash-row">
      <div class="bdash-main">
        <span class="chip"><?= e(RMT_BUDDY_TYPES[$p['trip_type']] ?? 'Trip') ?></span><?php if ($p['status'] === 'closed'): ?> <span class="chip">Closed</span><?php endif; ?>
        <br><b><a href="<?= e(url('buddy/' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a></b>
        <br><span class="hint"><?= e($p['where_text']) ?> &middot; <?= e($range($p['date_from'], $p['date_to'])) ?></span>
        <br><span class="hint"><?= (int) $p['waiting'] ?> waiting &middot; <?= (int) $p['accepted'] ?> accepted<?php if ($m): ?> &middot; <a href="<?= e(url($p['trip_type'] === 'cruise' ? 'buddies/cruise' : 'buddies') . $m['query']) ?>"><?= (int) $m['count'] ?> <?= $m['count'] === 1 ? 'traveler lines up' : 'travelers line up' ?></a><?php endif; ?></span>
      </div>
      <div class="bdash-acts">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('buddy/' . (int) $p['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('buddy/' . (int) $p['id'] . '/status')) ?>" onsubmit="return confirm('Cancel this trip and remove the post?')"><?= csrf_field() ?>
          <input type="hidden" name="status" value="removed"><button class="btn btn-ghost btn-sm">Cancel</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php foreach ($trips as $t): $m = $matches['trip:' . $t['id']] ?? null; ?>
    <div class="bdash-row">
      <div class="bdash-main">
        <span class="chip">Trip</span><?php if (($t['visibility'] ?? 'public') !== 'public'): ?> <span class="chip"><?= e(ucfirst((string) $t['visibility'])) ?></span><?php endif; ?>
        <br><b><a href="<?= e(url('trip/' . (int) $t['id'] . '/' . $t['slug'])) ?>"><?= e($t['title']) ?></a></b>
        <br><span class="hint"><?= e((string) ($t['dest_name'] ?? '')) ?> &middot; <?= e($range($t['date_from'], $t['date_to'])) ?></span>
        <?php if ($m): ?><br><span class="hint"><a href="<?= e(url('buddies') . $m['query']) ?>"><?= (int) $m['count'] ?> <?= $m['count'] === 1 ? 'traveler lines up' : 'travelers line up' ?></a></span><?php endif; ?>
      </div>
      <div class="bdash-acts">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('trip/' . (int) $t['id'] . '/edit')) ?>">Edit</a>
      </div>
    </div>
  <?php endforeach; ?>

  <h2>Requests you sent</h2>
  <?php if (!$sent): ?>
    <p class="muted">None yet. <a href="<?= e(url('buddies')) ?>">Find travelers heading your way</a>.</p>
  <?php else: foreach ($sent as $s): ?>
    <div class="bdash-row">
      <div class="bdash-main">
        <?php if ($s['kind'] === 'local'): ?>To meet <a href="<?= e(url('u/' . $s['username'])) ?>">@<?= e($s['username']) ?></a>, a local
        <?php else: ?><a href="<?= e(url(($s['kind'] === 'post' ? 'buddy/' : 'trip/') . (int) $s['target_id'])) ?>"><?= e((string) $s['target_title']) ?></a> <span class="hint">by @<?= e($s['username']) ?></span><?php endif; ?>
        <br><span class="chip"><?= e($stateLabel[$s['state']] ?? ucfirst((string) $s['state'])) ?></span>
      </div>
      <div class="bdash-acts">
        <?php if ($s['state'] === 'accepted'): ?><a class="btn btn-primary btn-sm" href="<?= e(url('messages/' . $s['username'])) ?>">Message</a>
        <?php elseif ($s['state'] === 'interested'): ?>
          <?php if ($s['kind'] === 'post'): ?>
            <form method="post" action="<?= e(url('buddy/' . (int) $s['target_id'] . '/interest')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>"><button class="btn btn-ghost btn-sm">Withdraw</button></form>
          <?php elseif ($s['kind'] === 'trip'): ?>
            <form method="post" action="<?= e(url('connect/' . (int) $s['connect_id'] . '/withdraw')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>"><button class="btn btn-ghost btn-sm">Withdraw</button></form>
          <?php else: ?>
            <form method="post" action="<?= e(url('buddies/local/' . (int) $s['to_id'] . '/connect')) ?>"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>"><input type="hidden" name="withdraw" value="1"><button class="btn btn-ghost btn-sm">Withdraw</button></form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <h2>Saved trips</h2>
  <?php if (!$saved): ?>
    <p class="muted">Tap Save on any trip to keep it here while you decide.</p>
  <?php else: foreach ($saved as $s): ?>
    <p class="bdash-row" style="margin:0"><span class="bdash-main"><a href="<?= e(url('buddy/' . (int) $s['id'])) ?>"><?= e($s['title']) ?></a>
      <br><span class="hint"><?= e($s['where_text']) ?> &middot; <?= e($range($s['date_from'], $s['date_to'])) ?> &middot; @<?= e($s['username']) ?><?= $s['status'] === 'closed' ? ' · closed' : '' ?></span></span></p>
  <?php endforeach; endif; ?>
  <p class="hint">Saved city trips are on your <a href="<?= e(url('saved')) ?>">Saved</a> page.</p>

  <h2>Meeting travelers where you live</h2>
  <?php if (!empty($profile['open_to_meeting']) && !empty($profile['home_name'])): ?>
    <p>You are listed as a local in <a href="<?= e(url('buddies?dest=' . rawurlencode((string) $profile['home_slug']) . '&show=locals')) ?>"><?= e($profile['home_name']) ?></a> who is open to meeting travelers. Only the city shows.</p>
    <form method="post" action="<?= e(url('buddies/local')) ?>"><?= csrf_field() ?><input type="hidden" name="off" value="1"><input type="hidden" name="return" value="<?= e($back) ?>">
      <button class="btn btn-ghost btn-sm">Stop listing me</button></form>
  <?php else: ?>
    <form method="post" action="<?= e(url('buddies/local')) ?>" style="max-width:420px"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($back) ?>">
      <label for="mine-local">I live in</label>
      <select id="mine-local" name="destination_id" required><option value="">Pick a city</option>
        <?php foreach ($dests as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) ($profile['home_destination_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
      <button class="btn btn-ghost btn-sm" style="margin-top:10px">List me as open to meeting travelers</button>
    </form>
  <?php endif; ?>
</div>
