<?php /** @var array $trending @var array $stories @var array $reviews @var array $meetups @var array $guides @var int $stat_destinations @var int $stat_community_reviews @var int $stat_editorial_reviews @var ?array $taxPost @var array $latestPosts */ ?>
<section class="hero">
  <div class="hero-bg" style="background-image:url('https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=1900&q=80&auto=format&fit=crop')"></div>
  <div class="hero-inner">
    <p class="eyebrow" style="color:#7dd3c8">Honest 2026 travel intel</p>
    <h1>What it actually costs. What nearly ruins it.</h1>
    <p>Tourist taxes, ticket prices, scams and new rules, researched from official sources. No fake travelers. No invented reviews.</p>
    <form class="hero-search" action="<?= e(url('explore')) ?>" method="get">
      <input type="search" name="q" placeholder="Where to next? Try Kyoto, Lisbon, Banff…" aria-label="Search destinations">
      <button class="btn btn-primary" type="submit">Explore</button>
    </form>
    <p style="margin:18px 0 0;display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-accent" href="<?= e(url('guides')) ?>">2026 city guides</a>
      <?php if ($taxPost): ?>
        <a class="btn btn-ghost" href="<?= e(url('blog/'.$taxPost['slug'])) ?>" style="color:#fff;border-color:rgba(255,255,255,.45)">2026 tourist taxes</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('founding')) ?>" style="color:#fff;border-color:rgba(255,255,255,.45)">Founding Traveler</a>
    </p>
    <div class="hero-stats">
      <div><b><?= (int)$stat_destinations ?></b><span><?= $stat_destinations === 1 ? 'Destination' : 'Destinations' ?></span></div>
      <div><b><?= (int)$stat_editorial_reviews ?></b><span>Researched reviews</span></div>
      <div><b><?= (int)($stat_travelers ?? 0) ?></b><span><?= (int)($stat_travelers ?? 0) === 1 ? 'Traveler' : 'Travelers' ?></span></div>
    </div>
  </div>
</section>

<section class="block" style="background:#fff;border-bottom:1px solid var(--line)"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Plan smarter</p><h2>2026 travel guides</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('guides')) ?>">All guides</a></div>
  <div class="grid g-3">
    <?php foreach ($guides as $g): ?>
      <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($g['cover_url'])) ?>" alt="<?= e($g['title']) ?>">
        <div class="card-body">
          <?php if ($g['dest_name']): ?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif; ?>
          <?php if (rmt_is_editorial($g)): ?><?= rmt_editorial_badge() ?><?php endif; ?>
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

<?php if (!empty($latestPosts)): ?>
<section class="block"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">2026 prices</p><h2>What it costs right now</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('blog')) ?>">All notes</a></div>
  <div class="grid g-3">
    <?php foreach ($latestPosts as $bp): ?>
      <article class="card"><a href="<?= e(url('blog/'.$bp['slug'])) ?>">
        <?php if ($bp['cover_url']): ?><img class="card-media" loading="lazy" src="<?= e(abs_url($bp['cover_url'])) ?>" alt="<?= e($bp['title']) ?>"><?php endif; ?>
        <div class="card-body">
          <span class="chip"><?= e(ucfirst((string)$bp['category'])) ?></span>
          <h3><?= e($bp['title']) ?></h3>
          <p class="muted"><?= e(mb_strimwidth((string)$bp['summary'],0,120,'…')) ?></p>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="block"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Trending now</p><h2>Destinations we researched</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('explore')) ?>">Explore all</a></div>
  <div class="grid g-3">
    <?php foreach ($trending as $d): ?>
      <article class="card"><a href="<?= e(url('d/'.$d['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e($d['hero_url']) ?>" alt="<?= e($d['name'].', '.$d['country']) ?>">
        <div class="card-body">
          <span class="chip"><?= e($d['category']) ?></span>
          <h3><?= e($d['name']) ?></h3>
          <p class="muted"><?= e($d['summary']) ?></p>
          <div class="meta-row"><?= e($d['country']) ?><?php if ((int)$d['trips'] > 0): ?> · <?= (int)$d['trips'] ?> trip stories<?php endif; ?></div>
        </div></a></article>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="block" style="background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)"><div class="wrap">
  <div class="grid g-2" style="align-items:start">
    <div>
      <p class="eyebrow">Trusted reviews</p><h2>What nearly ruins the trip</h2>
      <?php if ($stat_community_reviews === 0 && $reviews): ?>
        <p class="muted">Everything below is an <b>editorial review</b>, researched and labelled as such. There are no traveler reviews yet, and we are not going to invent any. <a href="<?= e(url('review/new')) ?>">Yours would be the first.</a></p>
      <?php endif; ?>
      <div class="grid" style="gap:14px">
        <?php foreach ($reviews as $r): ?>
          <div class="card <?= rmt_is_editorial($r) ? 'ed-panel' : '' ?>"><div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
              <span class="stars"><?= stars((int)$r['rating']) ?></span>
              <?php if (rmt_is_editorial($r)): ?><?= rmt_editorial_badge('review') ?>
              <?php elseif (show_verified($r)): ?><span class="verified">Verified</span><?php endif; ?>
            </div>
            <h3 style="margin:.35rem 0 .2rem;font-size:1.05rem">
              <a href="<?= e(url('review/'.(int)$r['id'].'/'.($r['slug'] ?: rmt_review_slug($r)))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a>
            </h3>
            <p class="muted" style="margin:0"><?= e($r['subject_name']) ?> · <span style="text-transform:capitalize"><?= e($r['subject_type']) ?></span></p>
            <p style="margin:.5rem 0 0"><?= e(mb_strimwidth($r['body'],0,120,'…')) ?></p>
            <div class="meta-row"><?= rmt_is_editorial($r) ? e(rmt_editorial_name()) : '@'.e($r['author']['username'] ?? 'traveler') ?></div>
          </div></div>
        <?php endforeach; ?>
        <?php if (!$reviews): ?>
          <p class="muted">No reviews yet. The first honest one can be yours.</p>
        <?php endif; ?>
      </div>
      <p style="margin-top:16px">
        <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a>
        <a class="btn btn-ghost" href="<?= e(url('reviews')) ?>">All reviews</a>
      </p>
    </div>
    <div>
      <?php if ($stories): ?>
        <p class="eyebrow">Fresh from the community</p><h2>Recent traveler stories</h2>
        <div class="grid" style="gap:14px">
          <?php foreach ($stories as $s): ?>
            <article class="card"><a href="<?= e(url('trip/'.$s['id'].'/'.$s['slug'])) ?>"><div class="card-body">
              <?php if ($s['dest_name']): ?><span class="chip"><?= e($s['dest_name']) ?></span><?php endif; ?>
              <h3 style="font-size:1.1rem;margin:.35rem 0 .2rem"><?= e($s['title']) ?></h3>
              <div class="meta-row">@<?= e($s['author']['username'] ?? 'traveler') ?> · <?= e(ago($s['created_at'])) ?></div>
            </div></a></article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="eyebrow">Community</p><h2>Traveler stories</h2>
        <p class="muted">Nobody has posted a trip story yet. That is not a bug. RuinMyTrip opened with real destination research and zero invented travelers.</p>
        <p><a class="btn btn-primary" href="<?= e(url('trip/new')) ?>">Share a trip</a></p>
      <?php endif; ?>
      <p class="eyebrow" style="margin-top:28px">Meet fellow travelers</p><h2>Upcoming public meetups</h2>
      <div class="callout">Meetups are <b>optional and public</b>. Never dating, never precise location. <a href="<?= e(url('safety')) ?>">Safety approach →</a></div>
      <?php if ($meetups): ?>
        <div class="grid" style="gap:14px">
          <?php foreach ($meetups as $m): ?>
            <div class="card"><a href="<?= e(url('meetup/'.$m['id'])) ?>"><div class="card-body">
              <span class="chip"><?= e($m['dest_name']) ?></span>
              <h3 style="font-size:1.1rem;margin:.35rem 0 .2rem"><?= e($m['title']) ?></h3>
              <p class="muted" style="margin:0"><?= e(date('M j, Y · g:ia', strtotime((string)$m['date_start']))) ?></p>
            </div></a></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">No public meetups yet.</p>
      <?php endif; ?>
      <p style="margin-top:16px"><a class="btn btn-ghost" href="<?= e(url('meetups')) ?>">Browse meetups</a></p>
    </div>
  </div>
</div></section>

<section class="block"><div class="wrap" style="text-align:center;background:linear-gradient(120deg,var(--ink),var(--brand));color:#fff;border-radius:24px;padding:56px 24px">
  <h2 style="color:#fff;font-size:2rem">Been there? Correct us.</h2>
  <p style="color:#dfe9f2;max-width:48ch;margin:0 auto 20px">Prices move. A first-hand review is worth more than desk research, and we will show it next to ours.</p>
  <a class="btn btn-accent" href="<?= e(url('founding')) ?>">Become a Founding Traveler</a>
      <a class="btn btn-ghost" href="<?= e(url('review/new')) ?>" style="color:#fff;border-color:rgba(255,255,255,.45)">Write a review</a>
</div></section>
