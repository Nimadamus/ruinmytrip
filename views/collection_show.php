<?php /** @var array $c @var ?array $me @var array $items @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved @var bool $canEdit @var array $tags
     @var bool $isCommunity @var array $members @var int $memberCount @var ?string $myRole @var bool $canAdd @var string $joinState @var ?array $invite @var ?string $inviteToken */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('collections')) ?>">Collections</a> / <?= e($c['title']) ?></p></div>
<div class="wrap prose">
  <h1><?= e($c['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$c['author']['username'])) ?>">@<?= e($c['author']['username']) ?></a> · <?= e(ago($c['created_at'])) ?> · <?= count($items) ?> <?= count($items) === 1 ? 'destination' : 'destinations' ?></p>
  <?php if ($c['summary']): ?><p style="font-size:1.15rem;color:var(--muted)"><?= e($c['summary']) ?></p><?php endif; ?>

  <?php /* The join box. A stranger seeing a community needs three facts before anything else: who
           is in it, whether they can come in, and what it is for. The door goes above the content
           for that reason, and the content stays visible either way -- asking someone to join a
           room they cannot see into is how communities stay empty. */ ?>
  <?php if ($isCommunity): ?>
    <div class="card" style="padding:14px 16px;margin:18px 0;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <b><?= $memberCount ?> <?= $memberCount === 1 ? 'member' : 'members' ?></b>
        <span class="muted"> &middot; <?= $c['join_policy'] === 'open' ? 'anyone can join' : 'invite only' ?></span>
        <?php if ($members): ?>
          <div class="muted" style="margin-top:.35rem">
            <?php foreach (array_slice($members, 0, 8) as $m): ?>
              <a href="<?= e(url('u/'.$m['username'])) ?>">@<?= e((string) $m['username']) ?></a><?php if ($m['role'] === 'owner'): ?> <span class="chip">founder</span><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($memberCount > 8): ?><span>and <?= $memberCount - 8 ?> more</span><?php endif; ?>
            &middot; <a href="<?= e(url('c/'.$c['slug'].'/members')) ?>">all members</a>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($joinState === 'can_join'): ?>
          <form method="post" action="<?= e(url('c/'.$c['slug'].'/join')) ?>"><?= csrf_field() ?>
            <?php if ($inviteToken): ?><input type="hidden" name="invite" value="<?= e($inviteToken) ?>"><?php endif; ?>
            <button class="btn">Join this community</button>
          </form>
        <?php elseif ($joinState === 'sign_in_required'): ?>
          <a class="btn" href="<?= e(url('login?return='.urlencode('/c/'.$c['slug']))) ?>">Sign in to join</a>
        <?php elseif ($joinState === 'invite_required'): ?>
          <span class="muted">Invite only. Ask a member for a link.</span>
        <?php elseif ($joinState === 'removed'): ?>
          <span class="muted">You cannot rejoin this community.</span>
        <?php elseif ($myRole === 'member'): ?>
          <form method="post" action="<?= e(url('c/'.$c['slug'].'/leave')) ?>"><?= csrf_field() ?>
            <button class="btn btn-ghost">Leave</button>
          </form>
        <?php elseif ($myRole === 'owner'): ?>
          <span class="muted">You founded this community.</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($canEdit && $invite): ?>
      <p class="muted" style="margin:-6px 0 18px">Invite link:
        <code><?= e(abs_url('/join/'.$invite['token'])) ?></code></p>
    <?php endif; ?>

    <?php if ($canAdd && $myRole === 'member'): ?>
      <p class="muted" style="margin:-6px 0 18px">You can add places and cities to this community from any
        place page, or from <a href="<?= e(url('contribute')) ?>">contribute</a>.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if (!$items): ?>
    <p class="muted">Nothing added to this collection yet.</p>
  <?php endif; ?>
  <?php /* An item is a city or a venue. A city keeps its hero image; a venue has no image of its
           own here and renders as a titled row rather than a grey box where a photograph should
           be. Both are ordinary links, which is how a list of places gets crawled. */ ?>
  <?php foreach ($items as $i => $it): ?>
    <?php $isPlace = !empty($it['place_id']); ?>
    <div class="card" style="margin-bottom:14px">
      <a href="<?= e(url($isPlace ? 'p/'.$it['place_slug'] : 'd/'.$it['dest_slug'])) ?>"
         style="display:flex;gap:14px;color:inherit;text-decoration:none">
        <?php if (!$isPlace): ?>
          <img class="card-media" loading="lazy" style="width:160px;height:120px;flex-shrink:0"
               src="<?= e(abs_url($it['dest_hero'])) ?>" alt="<?= e((string) $it['dest_name']) ?>">
        <?php endif; ?>
        <div class="card-body" style="padding:12px 16px">
          <span class="muted"><?= $i+1 ?>.</span>
          <?php if ($isPlace): ?>
            <b style="font-size:1.1rem"><?= e((string) $it['place_name']) ?></b>
            <span class="muted" style="text-transform:capitalize"> &middot; <?= e(rmt_place_type_label((string) $it['place_type'])) ?></span>
            <span class="muted"> &middot; <?= e((string) $it['place_dest_name']) ?><?php
              if (!empty($it['place_area'])): ?>, <?= e((string) $it['place_area']) ?><?php endif; ?></span>
          <?php else: ?>
            <b style="font-size:1.1rem"><?= e((string) $it['dest_name']) ?>, <?= e((string) $it['dest_country']) ?></b>
          <?php endif; ?>
          <?php if ($it['note']): ?><p style="margin:.4rem 0 0"><?= e((string) $it['note']) ?></p><?php endif; ?>
          <?php /* Attribution only where it means something: on a personal list every row was
                   added by the one person whose name is already at the top of the page. */ ?>
          <?php if ($isCommunity && !empty($it['contributor']) && (int) $it['added_by'] !== (int) $c['user_id']): ?>
            <p class="muted" style="margin:.35rem 0 0;font-size:.9rem">added by @<?= e((string) $it['contributor']['username']) ?></p>
          <?php endif; ?>
        </div>
      </a>
      <?php if ($isCommunity && $canEdit): ?>
        <div style="display:flex;gap:8px;padding:0 16px 12px">
          <form method="post" action="<?= e(url('collection/'.(int)$c['id'].'/items/'.(int)$it['id'].'/pin')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="pinned" value="<?= empty($it['pinned']) ? '1' : '0' ?>">
            <button class="btn btn-ghost btn-sm"><?= empty($it['pinned']) ? 'Pin to top' : 'Unpin' ?></button>
          </form>
        </div>
      <?php elseif ($isCommunity && !empty($it['pinned'])): ?>
        <div style="padding:0 16px 12px"><span class="chip">pinned</span></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:30px 0 20px">
    <a class="btn btn-ghost" href="<?= e(url($isCommunity ? 'communities' : 'collections')) ?>">← <?= $isCommunity ? 'All communities' : 'All collections' ?></a>
    <?php if ($canEdit): ?>
      <a class="btn btn-ghost" href="<?= e(url('collection/'.(int)$c['id'].'/edit')) ?>">Edit</a>
    <?php endif; ?>
  </div>

  <?php
    $targetType = 'collection'; $targetId = (int)$c['id']; $ownerId = (int)$c['user_id'];
    $returnUrl = url('c/'.$c['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
