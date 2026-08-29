<?php /** @var array $c @var ?array $me @var array $items @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved @var bool $canEdit @var array $tags */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('collections')) ?>">Collections</a> / <?= e($c['title']) ?></p></div>
<div class="wrap prose">
  <h1><?= e($c['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$c['author']['username'])) ?>">@<?= e($c['author']['username']) ?></a> · <?= e(ago($c['created_at'])) ?> · <?= count($items) ?> <?= count($items) === 1 ? 'destination' : 'destinations' ?></p>
  <?php if ($c['summary']): ?><p style="font-size:1.15rem;color:var(--muted)"><?= e($c['summary']) ?></p><?php endif; ?>
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
        </div>
      </a>
    </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:30px 0 20px">
    <a class="btn btn-ghost" href="<?= e(url('collections')) ?>">← All collections</a>
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
