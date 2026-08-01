<?php /** @var array $collections */ $me = current_user(); ?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1>Collections</h1>
      <p class="muted">Traveler-curated destination lists, with the reasoning behind each pick.</p>
    </div>
    <a class="btn btn-accent btn-sm" href="<?= e(url($me ? 'collection/new' : 'register')) ?>">Start a collection</a>
  </div>

  <?php if (!$collections): ?>
    <div class="empty-cta" style="margin-bottom:24px">
      <h3>No collections yet.</h3>
      <p class="muted" style="margin:0">Curate a list — best beaches for solo travelers, cities that surprised you, whatever ties a few destinations together.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url($me ? 'collection/new' : 'register')) ?>">Start a collection</a></p>
    </div>
  <?php endif; ?>

  <div class="grid g-3" style="padding-bottom:50px">
    <?php foreach ($collections as $c): ?>
      <article class="card"><a href="<?= e(url('c/'.$c['slug'])) ?>">
        <div class="card-body">
          <h3><?= e($c['title']) ?></h3>
          <?php if ($c['summary']): ?><p class="muted"><?= e($c['summary']) ?></p><?php endif; ?>
          <div class="meta-row">
            by @<?= e($c['author']['username']??'') ?> · <?= (int)$c['item_count'] ?> <?= (int)$c['item_count']===1?'destination':'destinations' ?>
          </div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div></section>
