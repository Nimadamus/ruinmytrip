<?php /** @var ?array $me @var array $places @var array $dests @var array $reading */
// Labels for the reading list. Kept here rather than in the controller: it is presentation, and a
// kind with no label would still render (falling back to the raw key) instead of vanishing.
$kindLabels = ['guide'=>'Guide', 'blog_post'=>'Post', 'collection'=>'Collection', 'trip'=>'Trip', 'review'=>'Review'];
$total = count($places) + count($dests) + count($reading);
?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1>Saved</h1>
      <p class="muted">Everything you have collected. Only you can see this page.</p>
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="empty-cta" style="margin-bottom:50px">
      <h3>Nothing saved yet.</h3>
      <p class="muted" style="margin:0">Hit Save on a place, a destination or anything worth coming back to, and it lands here.</p>
      <p style="margin:16px 0 0">
        <a class="btn btn-accent" href="<?= e(url('explore')) ?>">Explore destinations</a>
        <a class="btn btn-ghost" href="<?= e(url('discover')) ?>">Discover</a>
      </p>
    </div>
  <?php endif; ?>

  <?php if ($places): ?>
    <h2 style="font-size:1.15rem;margin:0 0 10px">Places <span class="muted" style="font-weight:400">(<?= count($places) ?>)</span></h2>
    <div class="grid g-3" style="margin-bottom:34px">
      <?php foreach ($places as $p): ?>
        <article class="card"><div class="card-body">
          <span class="eyebrow"><?= e(rmt_place_type_label((string)$p['type'])) ?></span>
          <h3 style="margin:.25rem 0 .2rem;font-size:1.02rem">
            <a href="<?= e(url('p/'.$p['slug'])) ?>"><?= e($p['name']) ?></a>
          </h3>
          <p class="muted" style="margin:0;font-size:.93rem">
            <a href="<?= e(url('d/'.$p['dest_slug'])) ?>"><?= e($p['dest_name']) ?></a>, <?= e($p['dest_country']) ?>
          </p>
          <div class="meta-row" style="justify-content:space-between;align-items:center">
            <span><?php if (!empty($p['saved_at'])): ?>Saved <?= e(ago((string)$p['saved_at'])) ?><?php endif; ?></span>
            <?php /* Unsaving happens where the item is listed -- having to open the page to undo a
                     save you can see right here is the kind of small friction that makes a list
                     stop being maintained. Returns to /saved, not to the place page. */ ?>
            <form method="post" action="<?= e(url('place/save')) ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="place_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="return" value="/saved">
              <button class="btn btn-ghost btn-sm">Remove</button>
            </form>
          </div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($dests): ?>
    <h2 style="font-size:1.15rem;margin:0 0 4px">Want to visit <span class="muted" style="font-weight:400">(<?= count($dests) ?>)</span></h2>
    <p class="hint" style="margin:0 0 10px">Destinations you marked as somewhere you want to go.</p>
    <div class="grid g-3" style="margin-bottom:34px">
      <?php foreach ($dests as $d): ?>
        <article class="card"><div class="card-body">
          <h3 style="margin:0 0 .2rem;font-size:1.02rem">
            <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($d['name']) ?></a>
          </h3>
          <p class="muted" style="margin:0;font-size:.93rem"><?= e($d['country']) ?></p>
          <div class="meta-row" style="justify-content:space-between;align-items:center">
            <span><?php if (!empty($d['saved_at'])): ?>Saved <?= e(ago((string)$d['saved_at'])) ?><?php endif; ?></span>
            <form method="post" action="<?= e(url('destination/save')) ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="destination_id" value="<?= (int)($d['id'] ?? 0) ?>">
              <input type="hidden" name="return" value="/saved">
              <button class="btn btn-ghost btn-sm">Remove</button>
            </form>
          </div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($reading): ?>
    <h2 style="font-size:1.15rem;margin:0 0 4px">Reading list <span class="muted" style="font-weight:400">(<?= count($reading) ?>)</span></h2>
    <p class="hint" style="margin:0 0 10px">Guides, posts, trips, reviews and collections you saved.</p>
    <div class="grid" style="gap:10px;margin-bottom:34px">
      <?php foreach ($reading as $r): ?>
        <article class="card"><div class="card-body" style="padding-top:12px;padding-bottom:12px">
          <span class="chip"><?= e($kindLabels[$r['kind']] ?? $r['kind']) ?></span>
          <h3 style="margin:.35rem 0 .2rem;font-size:1.02rem">
            <a href="<?= e(url(ltrim((string)$r['path'], '/'))) ?>"><?= e((string)($r['title'] ?: 'Untitled')) ?></a>
          </h3>
          <div class="meta-row">
            <span>by @<?= e($r['author']['username'] ?? 'traveler') ?><?php if (!empty($r['saved_at'])): ?> · saved <?= e(ago((string)$r['saved_at'])) ?><?php endif; ?></span>
          </div>
        </div></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="padding-bottom:50px"></div>
</div></section>
