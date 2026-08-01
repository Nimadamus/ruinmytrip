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
  <?php foreach ($items as $i => $it): ?>
    <div class="card" style="margin-bottom:14px"><a href="<?= e(url('d/'.$it['dest_slug'])) ?>" style="display:flex;gap:14px;color:inherit;text-decoration:none">
      <img class="card-media" loading="lazy" style="width:160px;height:120px;flex-shrink:0" src="<?= e(abs_url($it['dest_hero'])) ?>" alt="<?= e($it['dest_name']) ?>">
      <div class="card-body" style="padding:12px 16px 12px 0">
        <span class="muted"><?= $i+1 ?>.</span> <b style="font-size:1.1rem"><?= e($it['dest_name']) ?>, <?= e($it['dest_country']) ?></b>
        <?php if ($it['note']): ?><p style="margin:.4rem 0 0"><?= e($it['note']) ?></p><?php endif; ?>
      </div>
    </a></div>
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
