<?php /** @var array $trending @var array $stories @var array $reviews @var array $meetups @var array $guides */ ?>
<section class="hero">
  <div class="hero-bg" style="background-image:url('https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=1900&q=80&auto=format&fit=crop')"></div>
  <div class="hero-inner">
    <p class="eyebrow" style="color:#7dd3c8">The travel community that keeps it real</p>
    <h1>Great trips. Honest reviews. People worth traveling with.</h1>
    <p>Share where you've been, review the places that earned it, follow travelers you actually trust, and find safe, optional meetups in your next destination.</p>
    <form class="hero-search" action="<?= e(url('explore')) ?>" method="get">
      <input type="search" name="q" placeholder="Where to next? Try Kyoto, Lisbon, Banff…" aria-label="Search destinations">
      <button class="btn btn-primary" type="submit">Explore</button>
    </form>
    <div class="hero-stats">
      <?php /* Real COUNT(*) from the DB — never a hardcoded or LIMIT-capped number. */ ?>
      <div><b><?= (int)$stat_destinations ?></b><span><?= $stat_destinations === 1 ? 'Destination' : 'Destinations' ?></span></div>
      <div><b>Honest</b><span>Traveler reviews</span></div>
      <div><b>Safety-first</b><span>Public meetups</span></div>
    </div>
  </div>
</section>

<section class="block"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Trending now</p><h2>Destinations travelers love</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('explore')) ?>">Explore all</a></div>
  <div class="grid g-3">
    <?php foreach ($trending as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name'].', '.$d['country']) ?>">
        <div class="card-body">
          <span class="chip"><?= e($d['category']) ?></span>
          <h3><?= e($d['name']) ?></h3>
          <p class="muted"><?= e($d['summary']) ?></p>
          <div class="meta-row"><?= (int)$d['trips'] ?> trip stories · <?= e($d['country']) ?></div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="block" style="background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Fresh from the community</p><h2>Recent traveler stories</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('explore')) ?>">More stories</a></div>
  <div class="grid g-2">
    <?php foreach ($stories as $s): ?>
      <article class="card"><a href="<?= e(url('trip/'.$s['id'].'/'.$s['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($s['cover_url']) ?>" alt="<?= e($s['title']) ?>">
        <div class="card-body">
          <?php if ($s['dest_name']): ?><span class="chip"><?= e($s['dest_name']) ?></span><?php endif; ?>
          <h3><?= e($s['title']) ?></h3>
          <div class="meta-row">
            <img class="avatar" src="<?= e($s['author']['avatar_url'] ?? '') ?>" alt="">
            <span>@<?= e($s['author']['username'] ?? 'traveler') ?> · <?= e(ago($s['created_at'])) ?></span>
            <?php if (show_verified($s)): ?><span class="verified">Verified visit</span><?php endif; ?>
          </div>
        </div></a></article>
    <?php endforeach; ?>
    <?php if (!$stories): ?>
      <p class="muted">No trip stories yet. <a href="<?= e(url('register')) ?>">Create a profile</a> and be the first to share one.</p>
    <?php endif; ?>
  </div>
</div></section>

<section class="block"><div class="wrap">
  <div class="grid g-2" style="align-items:start">
    <div>
      <p class="eyebrow">Trusted reviews</p><h2>Reviews you can actually believe</h2>
      <div class="grid" style="gap:14px">
        <?php foreach ($reviews as $r): ?>
          <div class="card"><div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <span class="stars"><?= stars((int)$r['rating']) ?></span>
              <?php if (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
            </div>
            <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem"><?= e($r['title']) ?></h3>
            <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span></p>
            <p style="margin:.5rem 0 0"><?= e(mb_strimwidth($r['body'],0,120,'…')) ?></p>
            <div class="meta-row">@<?= e($r['author']['username'] ?? 'traveler') ?></div>
          </div></div>
        <?php endforeach; ?>
        <?php if (!$reviews): ?>
          <p class="muted">No reviews yet. The first honest one can be yours.</p>
        <?php endif; ?>
      </div>
      <p style="margin-top:16px"><a class="btn btn-ghost" href="<?= e(url('reviews')) ?>">All reviews</a></p>
    </div>
    <div>
      <p class="eyebrow">Meet fellow travelers</p><h2>Upcoming public meetups</h2>
      <div class="callout">Meetups are <b>optional and public</b> — a way to meet travelers in a destination. Never dating, never precise location. <a href="<?= e(url('safety')) ?>">Read our safety approach →</a></div>
      <div class="grid" style="gap:14px">
        <?php foreach ($meetups as $m): ?>
          <div class="card"><a href="<?= e(url('meetup/'.$m['id'])) ?>"><div class="card-body">
            <span class="chip"><?= e($m['dest_name']) ?></span>
            <h3 style="font-size:1.1rem;margin:.35rem 0 .2rem"><?= e($m['title']) ?></h3>
            <p class="muted" style="margin:0"><?= e(date('M j, Y · g:ia', strtotime((string)$m['date_start']))) ?></p>
          </div></a></div>
        <?php endforeach; ?>
        <?php if (!$meetups): ?><p class="muted">No public meetups scheduled yet.</p><?php endif; ?>
      </div>
      <p style="margin-top:16px"><a class="btn btn-ghost" href="<?= e(url('meetups')) ?>">Browse meetups</a></p>
    </div>
  </div>
</div></section>

<section class="block" style="background:#fff;border-top:1px solid var(--line)"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Plan smarter</p><h2>Featured travel guides</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('guides')) ?>">All guides</a></div>
  <div class="grid g-3">
    <?php foreach ($guides as $g): ?>
      <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($g['cover_url']) ?>" alt="<?= e($g['title']) ?>">
        <div class="card-body">
          <?php if ($g['dest_name']): ?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif; ?>
          <?php if ($g['premium']): ?><span class="chip" style="background:#fef3c7;color:#92400e">Premium</span><?php endif; ?>
          <h3><?= e($g['title']) ?></h3>
          <p class="muted"><?= e(mb_strimwidth($g['summary'],0,110,'…')) ?></p>
        </div></a></article>
    <?php endforeach; ?>
    <?php if (!$guides): ?>
      <p class="muted">No guides published yet.</p>
    <?php endif; ?>
  </div>
</div></section>

<section class="block"><div class="wrap" style="text-align:center;background:linear-gradient(120deg,var(--ink),var(--brand));color:#fff;border-radius:24px;padding:56px 24px">
  <h2 style="color:#fff;font-size:2rem">Your trips are worth sharing</h2>
  <p style="color:#dfe9f2;max-width:48ch;margin:0 auto 20px">Build a traveler profile, earn credibility with honest contributions, and help people go to the right places for the right reasons.</p>
  <a class="btn btn-accent" href="<?= e(url('register')) ?>">Create your free profile</a>
</div></section>
