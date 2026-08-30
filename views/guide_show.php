<?php /** @var array $mentions @var array $g @var ?array $me @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('guides')) ?>">Guides</a> / <?= e($g['title']) ?></p></div>
<div class="wrap prose">
  <?php $isEd = rmt_is_editorial($g); ?>
  <?php if($g['dest_name']):?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif;?>
  <?php if($isEd):?><?= rmt_editorial_badge() ?><?php endif;?>
  <h1><?= e($g['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$g['author']['username'])) ?>"><?= $isEd ? e(rmt_editorial_name()) : '@'.e($g['author']['username']) ?></a> · <?= e(ago($g['created_at'])) ?></p>
  <?php if($isEd):?><div class="callout"><?= rmt_editorial_disclosure() ?></div><?php endif;?>
  <?php if ($g['cover_url']): ?><img class="article-hero" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>"><?php endif; ?>
  <p style="font-size:1.15rem;color:var(--muted)"><?= e($g['summary']) ?></p>
  <?php if ($g['premium']): ?><div class="callout warn"><b>Premium guide.</b> A preview is shown. Full booking-ready detail unlocks with a creator subscription (coming soon).</div><?php endif; ?>
  <?php /* Editorial guides are seeded/edited by our own team and trusted with rich HTML. Traveler
           guides are untrusted user input -- rendering them raw would be stored XSS, so they get
           the same escape-then-nl2br treatment as trip and review bodies. */ ?>
  <div style="white-space:<?= $isEd ? 'normal' : 'pre-wrap' ?>"><?= $isEd ? $g['body'] : rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($g['body'])))) ?></div>
  <?php if (!$isEd && !empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>
  <?php /* What this guide actually names, matched against our own entities in this destination and
           nothing else. Eighty guides linked to their city and to nothing they spent a paragraph
           on: a reader told the Louvre put its prices up had no way to reach the Louvre page, and
           the Louvre page had no way back. The matching is deliberately narrow -- same destination,
           whole words, long names only -- because a guide that links every occurrence of "museum"
           is keyword spam that happens to be internal. */ ?>
  <?php if (!empty($mentions['places']) || !empty($mentions['areas'])): ?>
    <section style="margin:34px 0 0">
      <h2 style="font-size:1.1rem;margin:0 0 10px">Places this guide mentions</h2>
      <div class="chip-row">
        <?php foreach ($mentions['places'] as $mp): ?>
          <a class="chip" href="<?= e(url('p/' . $mp['slug'])) ?>"><?= e((string) $mp['name']) ?>
            <span class="chip-count" style="text-transform:capitalize"><?= e(rmt_place_type_label((string) $mp['type'])) ?></span></a>
        <?php endforeach; ?>
        <?php foreach ($mentions['areas'] as $ma): ?>
          <a class="chip" href="<?= e(url('d/' . $ma['dest_slug'] . '/n/' . $ma['slug'])) ?>"><?= e((string) $ma['name']) ?>
            <span class="chip-count">Area</span></a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if($isEd):?>
    <div class="empty-cta" style="margin-top:30px">
      <h2 style="margin:0 0 6px;font-size:1.2rem">Been there? Correct us.</h2>
      <p class="muted" style="margin:0 0 14px">Prices move, routes close, places go downhill. A first-hand review is worth more than this guide and will be shown alongside it.</p>
      <a class="btn btn-accent" href="<?= e(url('review/new'.($g['destination_id'] ? '?destination='.(int)$g['destination_id'] : ''))) ?>">Share your experience</a>
    </div>
  <?php endif;?>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:30px 0 20px">
    <a class="btn btn-ghost" href="<?php if($g['dest_slug']):?><?= e(url('d/'.$g['dest_slug'])) ?><?php else:?><?= e(url('guides')) ?><?php endif;?>">← More about this destination</a>
    <?php if (rmt_guide_can_edit($g, $me)): ?>
      <a class="btn btn-ghost" href="<?= e(url('guide/'.(int)$g['id'].'/edit')) ?>">Edit</a>
    <?php endif; ?>
  </div>

  <?php
    // showActionsBar defaults true: renders Like/Save + Report (Edit is handled above instead).
    $targetType = 'guide'; $targetId = (int)$g['id']; $ownerId = (int)$g['user_id'];
    $returnUrl = url('g/'.$g['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
