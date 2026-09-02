<?php /** @var array $posts @var string $cat */ $me = current_user(); ?>
<section class="block"><div class="wrap">
  <div class="section-head">
    <div>
      <h1>Blog</h1>
      <p class="muted">2026 tourist taxes, ticket prices and travel rules, researched from official sources, plus stories from travelers who actually went.</p>
    </div>
    <a class="btn btn-accent btn-sm" href="<?= e(url($me ? 'blog/new' : 'register')) ?>">Write a post</a>
  </div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
    <a class="chip" href="<?= e(url('blog')) ?>" style="<?= $cat==='' ? 'background:var(--ink);color:#fff' : '' ?>">All</a>
    <?php foreach (RMT_BLOG_CATEGORIES as $c): ?>
      <a class="chip" href="<?= e(url('blog?category='.$c)) ?>"
         style="<?= $cat===$c ? 'background:var(--ink);color:#fff' : '' ?>"><?= e(ucfirst($c)) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (($pages ?? 1) > 1): ?>
    <p style="display:flex;gap:10px;justify-content:center;margin:18px 0 40px">
      <?php if ($page > 1): ?><a class="btn btn-ghost" rel="prev" href="<?= e(url('blog?' . $qs . 'page=' . ($page - 1))) ?>">← Newer</a><?php endif; ?>
      <span class="hint" style="align-self:center">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-ghost" rel="next" href="<?= e(url('blog?' . $qs . 'page=' . ($page + 1))) ?>">Older →</a><?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (!$posts): ?>
    <div class="empty-cta" style="margin-bottom:24px">
      <h3>No posts in this category yet.</h3>
      <p class="muted" style="margin:0">Got a real story, a safety tip, or a budget breakdown worth sharing? Write it up.</p>
      <p style="margin:16px 0 0"><a class="btn btn-accent" href="<?= e(url($me ? 'blog/new' : 'register')) ?>">Write a post</a></p>
    </div>
  <?php endif; ?>

  <div class="grid g-3" style="padding-bottom:50px">
    <?php foreach ($posts as $p): ?>
      <article class="card"><a href="<?= e(url('blog/'.$p['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($p['cover_url'] ?: url('assets/img/og-default.svg'))) ?>" alt="<?= e($p['title']) ?>">
        <div class="card-body">
          <span class="chip"><?= e(ucfirst($p['category'])) ?></span>
          <h3><?= e($p['title']) ?></h3>
          <p class="muted"><?= e($p['summary']) ?></p>
          <div class="meta-row">by @<?= e($p['author']['username']??'') ?> · <?= e(ago($p['created_at'])) ?></div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div></section>
