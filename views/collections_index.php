<?php /** @var array $collections @var array $mine */ $me = current_user(); ?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1>Travel lists</h1>
      <p class="muted">Places and cities somebody grouped for a reason, with the reason attached.</p>
    </div>
    <a class="btn btn-accent btn-sm" href="<?= e(url($me ? 'collection/new' : 'register')) ?>">Start a list</a>
  </div>

  <?php /* Your own lists first, drafts included. This is where somebody comes back to a list they
           started, and until now the page showed them everybody else's published ones instead. */ ?>
  <?php if (!empty($mine)): ?>
    <h2 style="margin:0 0 12px;font-size:1.1rem">Your lists</h2>
    <div class="grid g-3" style="margin-bottom:34px">
      <?php foreach ($mine as $c): ?>
        <article class="card"><div class="card-body">
          <h3 style="font-size:1.05rem;margin:0">
            <a href="<?= e(url('c/'.$c['slug'])) ?>"><?= e((string) $c['title']) ?></a>
            <?php if (($c['status'] ?? '') !== 'published'): ?>
              <span class="chip" style="background:#fef3c7;color:#92400e"><?= e((string) $c['status']) ?></span>
            <?php endif; ?>
          </h3>
          <p class="muted" style="margin:.3rem 0 0;font-size:.93rem">
            <?= (int) $c['item_count'] ?> <?= (int) $c['item_count'] === 1 ? 'item' : 'items' ?>
            &middot; <a href="<?= e(url('collection/'.(int)$c['id'].'/edit')) ?>">Edit</a>
          </p>
        </div></article>
      <?php endforeach; ?>
    </div>
    <h2 style="margin:0 0 12px;font-size:1.1rem">From other travelers</h2>
  <?php endif; ?>

  <?php if (!$collections): ?>
    <div class="empty-cta" style="margin-bottom:24px">
      <h3>No travel lists yet.</h3>
      <p class="muted" style="margin:0">Curate a list — best beaches for solo travelers, cities that surprised you, whatever ties a few destinations together.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url($me ? 'collection/new' : 'register')) ?>">Start a list</a></p>
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
