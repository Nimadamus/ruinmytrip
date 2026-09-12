<?php /** @var array $act @var ?array $me @var bool $isOwner @var ?string $myState
        @var array $going @var array $interested @var array $requests @var array $photos
        @var array $comments @var bool $showPoint @var bool $isPrivate @var string $when */
$cancelled = !empty($act['cancelled_at']);
$cap = (int) ($act['capacity'] ?? 0);
$backTo = '/activity/' . (int) $act['id'];
?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> /
    <?php if (!empty($act['dest_slug'])): ?><a href="<?= e(url('d/'.$act['dest_slug'])) ?>"><?= e((string) $act['dest_name']) ?></a> / <?php endif; ?>
    <a href="<?= e(url('trip/'.(int) $act['trip_id'].'/'.(string) $act['trip_slug'])) ?>"><?= e((string) $act['trip_title']) ?></a></p>

  <?php if ($cancelled): ?>
    <div class="callout warn" style="margin:0 0 16px"><b>This plan was cancelled.</b>
      Everybody who had said they were coming has been told.</div>
  <?php endif; ?>

  <h1 style="margin-bottom:.2rem"><?= e((string) $act['title']) ?></h1>
  <p class="muted" style="margin:0 0 14px">
    <?php if ($when !== ''): ?><b><?= e($when) ?></b><?php if (!empty($act['end_time'])): ?> to <?= e((string) $act['end_time']) ?><?php endif; ?><?php endif; ?>
    <?php if (!empty($act['dest_name'])): ?><?= $when !== '' ? ' &middot; ' : '' ?><a href="<?= e(url('d/'.$act['dest_slug'])) ?>"><?= e((string) $act['dest_name']) ?></a><?php endif; ?>
    <?php if (($act['category'] ?? 'other') !== 'other'): ?> &middot; <?= e(RMT_ACTIVITY_CATEGORIES[$act['category']]) ?><?php endif; ?>
    <?php if (($act['visibility'] ?? 'trip') === 'private'): ?> &middot; <span class="chip">Only you</span><?php endif; ?>
  </p>

  <div class="photo-by" style="margin:0 0 16px">
    <a href="<?= e(url('u/'.$act['username'])) ?>"><img class="avatar" src="<?= e(avatar_url(rmt_profile_avatar((int) $act['user_id']))) ?>" alt=""></a>
    <span>
      <a href="<?= e(url('u/'.$act['username'])) ?>"><b>@<?= e((string) $act['username']) ?></b></a>
      <span class="hint">Planned this, on
        <a href="<?= e(url('trip/'.(int) $act['trip_id'].'/'.(string) $act['trip_slug'])) ?>"><?= e((string) $act['trip_title']) ?></a></span>
    </span>
  </div>

  <?php if (!empty($act['notes'])): ?><p style="white-space:pre-wrap"><?= e((string) $act['notes']) ?></p><?php endif; ?>
  <?php if (!empty($act['location_text']) || !empty($act['link'])): ?>
    <p class="muted">
      <?php if (!empty($act['location_text'])): ?><?= e((string) $act['location_text']) ?><?php endif; ?>
      <?php if (!empty($act['link'])): ?> <a href="<?= e((string) $act['link']) ?>" rel="noopener nofollow" target="_blank">Link</a><?php endif; ?>
    </p>
  <?php endif; ?>

  <?php /* The meeting point is the one part of a plan that is not for everybody: "by the fountain
           at the top of the steps at eight" is exactly what the people coming need and exactly
           what a stranger should not have about a small group. Owner and accepted attendees. */ ?>
  <?php if ($showPoint): ?>
    <div class="callout" style="margin:14px 0"><b>Meeting point</b><br><?= e((string) $act['meeting_point']) ?>
      <span class="hint" style="display:block;margin-top:4px">Only shown to the people coming.</span></div>
  <?php elseif (!empty($act['meeting_point'])): ?>
    <p class="hint">A meeting point is set. It is shown to people who are coming.</p>
  <?php endif; ?>

  <?php /* Who the person organising this is, in public facts. It sits next to the join button
           because that is the moment the question is actually being asked. */ ?>
  <?php if ($me && (int) $act['user_id'] !== (int) $me['id']
            && in_array((string) $act['join_mode'], ['ask','open'], true)): ?>
    <?php $trustUserId = (int) $act['user_id']; $trustUsername = (string) $act['username'];
          include __DIR__ . '/_trust.php'; ?>
  <?php endif; ?>

  <?php /* Said once, where somebody is deciding whether to meet a stranger, and not repeated on
           every screen afterwards. A warning nobody reads is not a safety feature. */ ?>
  <?php if (in_array((string) $act['join_mode'], ['ask','open'], true) && empty($act['cancelled_at'])): ?>
    <p class="hint" style="margin:10px 0 0">Meeting people you do not know: somewhere public, tell
      somebody where you are going, leave whenever you want.
      <a href="<?= e(url('safety')) ?>">Safety guidance</a>.</p>
  <?php endif; ?>

  <?php if ($photos): ?>
    <?php $gridPhotos = array_map(static fn(array $ph) => [
            'url' => (string) $ph['url'], 'caption' => (string) ($ph['caption'] ?? ''), 'href' => ''], $photos);
          $gridLead = count($photos) > 2;
          include __DIR__ . '/_photo_grid.php'; ?>
  <?php endif; ?>

  <?php /* Can I come. The whole reason this page exists for anybody but the person who wrote it. */ ?>
  <?php if (!$isOwner && !$cancelled && ($act['join_mode'] ?? 'no') !== 'no'): ?>
    <div class="join-box">
      <?php if ($myState === 'going'): ?>
        <p><b>You are going.</b><?php if (!$showPoint): ?> The meeting point appears here if the traveler sets one.<?php endif; ?></p>
      <?php elseif ($myState === 'requested'): ?>
        <p><b>You asked to join.</b> <span class="hint">@<?= e((string) $act['username']) ?> has not answered yet.</span></p>
      <?php elseif ($myState === 'declined'): ?>
        <p class="muted">You asked, and the traveler said no.</p>
      <?php elseif ($myState === 'interested'): ?>
        <p><b>You said you are interested.</b></p>
      <?php endif; ?>

      <?php if ($me && $myState !== 'declined'): ?>
        <div class="join-acts">
          <?php if ($myState === null): ?>
            <?php $full = ($act['join_mode'] === 'open') && !rmt_activity_has_room($act); ?>
            <?php if ($full): ?>
              <p class="muted" style="margin:0">This one is full<?= $cap > 0 ? ' (' . $cap . ' ' . ($cap === 1 ? 'person' : 'people') . ')' : '' ?>.</p>
            <?php else: ?>
              <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/join')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="state" value="<?= $act['join_mode'] === 'open' ? 'going' : 'requested' ?>">
                <button class="btn btn-primary"><?= $act['join_mode'] === 'open' ? 'I am going too' : 'Ask to join' ?></button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/cancel-request')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-ghost btn-sm"><?= $myState === 'requested' ? 'Withdraw the ask' : 'Change my mind' ?></button>
            </form>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('report?target_type=activity&target_id='.(int) $act['id'])) ?>">Report this plan</a>
        </div>
      <?php elseif (!$me): ?>
        <p style="margin:0"><a class="btn btn-accent" href="<?= e(url('register?return=' . rawurlencode($backTo))) ?>">Join RuinMyTrip to come along</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php /* Who is coming. Counted, named, and the pending asks are the owner's business alone. */ ?>
  <?php if ($going || $interested): ?>
    <section class="find-block">
      <h2 class="find-h">
        <?= count($going) ?> <?= count($going) === 1 ? 'person is' : 'people are' ?> going<?php
          if ($cap > 0): ?> <span class="hint">of <?= $cap ?></span><?php endif; ?>
      </h2>
      <?php foreach ($going as $g): ?>
        <?php $person = $g; $because = ''; include __DIR__ . '/_person_card.php'; ?>
      <?php endforeach; ?>
      <?php if ($interested): ?>
        <p class="hint" style="margin:10px 0 0"><?= count($interested) ?> also interested.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($isOwner && $requests): ?>
    <section class="find-block">
      <h2 class="find-h"><?= count($requests) ?> <?= count($requests) === 1 ? 'person wants' : 'people want' ?> to join</h2>
      <?php foreach ($requests as $r): ?>
        <div class="person">
          <a class="person-face" href="<?= e(url('u/'.$r['username'])) ?>">
            <img class="avatar" src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></a>
          <div class="person-who">
            <a href="<?= e(url('u/'.$r['username'])) ?>"><b>@<?= e((string) $r['username']) ?></b></a>
            <span class="hint">Asked <?= e(ago((string) $r['created_at'])) ?></span>
          </div>
          <div class="person-do">
            <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/decide')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
              <input type="hidden" name="decision" value="accept">
              <button class="btn btn-primary btn-sm">Accept</button>
            </form>
            <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/decide')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $r['user_id'] ?>">
              <input type="hidden" name="decision" value="decline">
              <button class="btn btn-ghost btn-sm">Decline</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <?php if ($isOwner): ?>
    <section class="find-block">
      <h2 class="find-h">Yours to manage</h2>
      <?php if ($going): ?>
        <p class="hint">Remove somebody if you need to. They are told, and they cannot re-ask.</p>
        <?php foreach ($going as $g): ?>
          <div class="person">
            <div class="person-who"><b>@<?= e((string) $g['username']) ?></b></div>
            <div class="person-do">
              <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/decide')) ?>"
                    onsubmit="return confirm('Remove @<?= e((string) $g['username']) ?> from this plan?');">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $g['user_id'] ?>">
                <input type="hidden" name="decision" value="remove">
                <button class="btn btn-ghost btn-sm" style="color:#b42318">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php /* Almost nobody decides on a capacity and a meeting point while typing "drinks in
               Bairro Alto". They decide once two people have said they are coming, which is why
               this is here rather than only in the composer. */ ?>
      <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/settings')) ?>" style="margin:0 0 16px">
        <?= csrf_field() ?>
        <div class="plan-more-grid">
          <div>
            <label for="s-join">Can anybody come?</label>
            <select id="s-join" name="join_mode">
              <?php foreach (RMT_ACTIVITY_JOIN_MODES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= ($act['join_mode'] ?? 'no') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="s-cap">How many, at most</label>
            <input type="number" id="s-cap" name="capacity" min="0" max="100"
                   value="<?= (int) ($act['capacity'] ?? 0) ?: '' ?>" placeholder="No limit">
          </div>
          <div>
            <label for="s-start">Starts</label>
            <input type="time" id="s-start" name="start_time" value="<?= e((string) ($act['start_time'] ?? '')) ?>">
          </div>
          <div>
            <label for="s-end">Ends</label>
            <input type="time" id="s-end" name="end_time" value="<?= e((string) ($act['end_time'] ?? '')) ?>">
          </div>
        </div>
        <label for="s-point">Meeting point <span class="hint">(only the people coming see this)</span></label>
        <input type="text" id="s-point" name="meeting_point" maxlength="300"
               value="<?= e((string) ($act['meeting_point'] ?? '')) ?>"
               placeholder="By the fountain at the top of the steps">
        <div style="margin-top:10px"><button class="btn btn-ghost btn-sm">Save these</button></div>
      </form>

      <form method="post" enctype="multipart/form-data" action="<?= e(url('activity/'.(int) $act['id'].'/photos')) ?>"
            style="margin:14px 0">
        <?= csrf_field() ?>
        <label for="act-photos">Photos of this <span class="hint">(up to six)</span></label>
        <input type="file" id="act-photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
        <div style="margin-top:8px"><button class="btn btn-ghost btn-sm">Add photos</button></div>
      </form>

      <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/cancel')) ?>"
            onsubmit="return confirm('<?= $cancelled ? 'Put this plan back on?' : 'Cancel this plan and tell everybody coming?' ?>');">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" style="color:<?= $cancelled ? 'var(--brand)' : '#b42318' ?>">
          <?= $cancelled ? 'Put it back on' : 'Cancel this plan' ?></button>
      </form>
    </section>
  <?php endif; ?>

  <?php /* Coordination, in the open, between the people it concerns. Not a second inbox: a private
           conversation is a message, and this is "where are we meeting" where everybody coming can
           read the answer once. */ ?>
  <h2 style="margin-top:26px;font-size:1.15rem">Sorting it out</h2>
  <div class="thread" style="border-top:0">
    <?php foreach ($comments as $c): ?>
      <div class="thread-line">
        <img class="avatar" style="width:26px;height:26px" src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt="">
        <span><a href="<?= e(url('u/'.$c['username'])) ?>"><b>@<?= e((string) $c['username']) ?></b></a>
          <?= rmt_linkify_mentions(e((string) $c['body'])) ?>
          <span class="hint"><?= e(ago((string) $c['created_at'])) ?></span></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$comments): ?><p class="muted" style="margin:0">Nothing said yet.</p><?php endif; ?>
  </div>
  <?php if ($me && !$cancelled): ?>
    <form class="thread-reply" method="post" action="<?= e(url('comment')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="target_type" value="activity">
      <input type="hidden" name="target_id" value="<?= (int) $act['id'] ?>">
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('comment_activity_'.(int) $act['id'])) ?>">
      <input type="hidden" name="return" value="<?= e($backTo) ?>">
      <img class="avatar" style="width:26px;height:26px" src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
      <input type="text" name="body" maxlength="2000" placeholder="Where are we meeting?">
      <button class="btn btn-ghost btn-sm">Say it</button>
    </form>
  <?php endif; ?>

  <?php if (!$isPrivate): ?>
    <?php $shareUrl = abs_url('/activity/' . (int) $act['id']);
          $shareText = (string) $act['title'] . ($when !== '' ? ', ' . $when : '');
          include __DIR__ . '/_share.php'; ?>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
