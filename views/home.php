<?php /** @var array $trending @var array $stories @var array $reviews @var array $meetups @var array $guides @var int $stat_destinations @var int $stat_community_reviews @var int $stat_editorial_reviews @var ?array $taxPost @var array $latestPosts @var array $goingSoon @var array $liveCities */ ?>
<?php if (!empty($refUser)): ?>
  <?php /* The one line that turns a forwarded link into a signup: who sent it, by name. */ ?>
  <div class="wrap" style="margin-top:14px"><div class="card"><div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <img class="avatar" style="width:40px;height:40px" src="<?= e(avatar_url($refUser['avatar_url'] ?? null)) ?>" alt="">
    <div style="flex:1;min-width:200px"><b>@<?= e((string) $refUser['username']) ?></b> invited you to RuinMyTrip.
      <span class="muted">Join free, follow them, and say what went wrong on your last trip.</span></div>
    <a class="btn btn-accent" href="<?= e(url('register')) ?>">Join</a>
  </div></div></div>
<?php endif; ?>
<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= e(url('media/4667ce3c70aadb7989e73b6fb6eb8c5e.jpg')) ?>')"></div>
  <div class="hero-inner">
    <?php /* The front door said "here is our research on ticket prices", which is what every travel
             page on the internet says and is not what this is. This site's one thing is the people:
             who is going where you are going, and whether you can meet them. That is the sentence
             a stranger has to read first, because it is the only one they cannot get elsewhere. */ ?>
    <p class="eyebrow" style="color:#7dd3c8">A travel community, not a guidebook</p>
    <h1>Find the people going where you are going.</h1>
    <p>Post your dates and see whose overlap. Meet up in public. Ask travelers who have actually been, and read reviews written by them rather than by us.</p>
    <form class="hero-search" action="<?= e(url('explore')) ?>" method="get">
      <input type="search" name="q" placeholder="Which city? Try Lisbon, Tokyo, Mexico City…" aria-label="Search destinations">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <p style="margin:18px 0 0;display:flex;gap:10px;flex-wrap:wrap">
      <?php if (!current_user()): ?>
        <a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free</a>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('going')) ?>">Post your dates</a>
      <?php endif; ?>
      <a class="btn btn-ghost btn-on-dark" href="<?= e(url('travelers')) ?>">See who is going</a>
      <?php /* The hero answered four of the five questions a first-time visitor has -- what this is,
               how it differs from a travel blog, what to read, how to search -- and not the fifth:
               that they can contribute. The button it replaces said "Founding Traveler", which is
               the name of our launch programme and means nothing to somebody who arrived a minute
               ago. The programme is still explained on /founding and linked from signup, so
               nothing is orphaned. */ ?>
      <a class="btn btn-ghost btn-on-dark" data-review-cta="home" href="<?= e(url('contribute')) ?>"
        >Been somewhere? Review it</a>
    </p>
    <?php /* People first, and every number is a live COUNT(*) of something real.

             A zero is dropped rather than printed. "0 Traveler reviews" in the first screenful is
             not honesty, it is an advertisement for an empty room, and it sat directly under the
             Join button the same visitor is being asked to press. Nothing is padded or invented to
             replace it: the row carries the counts that have something behind them, and grows back
             to three the moment the third one does. */ ?>
    <?php
      $heroStats = [];
      if ((int)($stat_travelers ?? 0) > 0)
          $heroStats[] = [(int)$stat_travelers, (int)$stat_travelers === 1 ? 'Traveler' : 'Travelers'];
      if ((int)$stat_community_reviews > 0)
          $heroStats[] = [(int)$stat_community_reviews, (int)$stat_community_reviews === 1 ? 'Traveler review' : 'Traveler reviews'];
      if ((int)$stat_destinations > 0)
          $heroStats[] = [(int)$stat_destinations, (int)$stat_destinations === 1 ? 'City' : 'Cities'];
    ?>
    <?php if ($heroStats): ?>
    <div class="hero-stats">
      <?php foreach ($heroStats as $hs): ?>
      <div><b><?= (int)$hs[0] ?></b><span><?= e($hs[1]) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php /* Who is here, before anything we wrote. A visitor deciding whether to join is deciding
         whether there are people, and no amount of research answers that question. When there is
         nobody yet the section says so and offers the empty chair, which is the only version of
         this that has ever recruited anybody. */ ?>
<section class="block" style="background:#fff;border-bottom:1px solid var(--line)"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Right now</p><h2>Travelers with dates coming up</h2></div>
    <a class="section-more" href="<?= e(url('travelers')) ?>">All travelers &rarr;</a></div>

  <?php if (!empty($goingSoon)): ?>
    <div class="tag-list" style="margin-bottom:8px">
      <?php foreach ($goingSoon as $g): ?>
        <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
           href="<?= e(url('d/'.$g['dest_slug'].'/travelers')) ?>">
          <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($g['avatar_url'] ?? null)) ?>" alt="">
          @<?= e($g['username']) ?> &middot; <?= e($g['dest_name']) ?>
          <span class="hint"><?= e(date('M j', strtotime((string)$g['date_from']))) ?> to <?= e(date('M j', strtotime((string)$g['date_to']))) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:0">Destination and date range only. Never a precise or live location.</p>
  <?php else: ?>
    <p class="muted" style="margin:0 0 12px">Nobody has posted upcoming dates yet. Whoever goes first is
      the traveler everybody arriving next month sees.</p>
    <p style="margin:0"><a class="btn btn-accent" href="<?= e(current_user() ? url('going') : url('register?return=' . rawurlencode('/going'))) ?>">Post your dates</a></p>
  <?php endif; ?>

  <?php if (!empty($meetups)): ?>
    <h3 style="margin:24px 0 10px">Meetups coming up</h3>
    <div class="grid g-3" style="gap:14px">
      <?php foreach (array_slice($meetups, 0, 3) as $m): ?>
        <div class="card"><a href="<?= e(url('meetup/'.(int)$m['id'])) ?>"><div class="card-body">
          <?php if ($m['dest_name']): ?><span class="chip"><?= e($m['dest_name']) ?></span><?php endif; ?>
          <h3 style="font-size:1.05rem;margin:.35rem 0 .2rem"><?= e($m['title']) ?></h3>
          <p class="muted" style="margin:0"><?= e(date('M j · g:ia', strtotime((string)$m['date_start']))) ?>
            &middot; <?= (int)($m['going_count'] ?? 0) === 1 ? '1 going' : (int)($m['going_count'] ?? 0) . ' going' ?></p>
        </div></a></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* The badge used to be going + meetups + talk added together under the heading "Who is
           going, by city", so a city with one question and nobody travelling read as one traveller
           going there. Three different things summed under the name of one of them is a number that
           lies. Each chip now says which signal it actually has, strongest first, and the heading
           only promises travellers when a traveller has posted dates. */ ?>
  <?php if (!empty($liveCities)): ?>
    <h3 style="margin:24px 0 10px"><?= !empty($goingSoon) ? 'Who is going, by city' : 'Cities with something happening' ?></h3>
    <div class="tag-list">
      <?php foreach ($liveCities as $c):
          $going = (int)$c['going_count']; $meets = (int)$c['meetup_count']; $talk = (int)$c['talk_count'];
          $hint = $going ? $going . ' going'
                : ($meets ? $meets . ($meets === 1 ? ' meetup' : ' meetups')
                : ($talk ? $talk . ($talk === 1 ? ' question' : ' questions') : '')); ?>
        <a class="chip" href="<?= e(url('d/'.$c['slug'].'/travelers')) ?>"><?= e($c['name']) ?><?php
          if ($hint !== ''): ?> <span class="hint"><?= e($hint) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <a class="chip" href="<?= e(url('travelers')) ?>">Every city &rarr;</a>
    </div>
  <?php endif; ?>
</div></section>

<?php /* The one question the site is named after, asked first. A visitor who came to read leaves
         having said the thing that annoyed them, and that sentence becomes their first review. */ ?>
<section class="block" style="background:linear-gradient(120deg,var(--ink),#163a4a);color:#fff"><div class="wrap">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:28px;align-items:start">
    <div><?php $dests = $askDests ?? []; $askVariant = 'hero'; include __DIR__ . '/_ruined_ask.php'; ?></div>
    <div>
      <p class="eyebrow" style="color:#7dd3c8;margin:0 0 8px">What ruined it for others</p>
      <?php if (!empty($ruinedLines)): ?>
        <?php foreach ($ruinedLines as $rl): ?>
          <p style="margin:0 0 10px;font-size:1.02rem;line-height:1.5">“<?= e(mb_strimwidth(trim((string) $rl['what_ruined']), 0, 140, '…')) ?>”
            <span style="opacity:.75;font-size:.9rem"> · <?= e((string) ($rl['place_name'] ?: $rl['subject_name'] ?: $rl['dest_name'])) ?></span></p>
        <?php endforeach; ?>
        <p style="margin:12px 0 0"><a href="<?= e(url('ruined')) ?>" style="color:#7dd3c8">All <?= (int) ($ruinedTotal ?? 0) ?> warnings →</a></p>
      <?php else: ?>
        <p style="margin:0;opacity:.85">Nobody has said theirs yet. The first one is the one people remember.</p>
      <?php endif; ?>
    </div>
  </div>
</div></section>

<section class="block" style="background:#fff;border-bottom:1px solid var(--line)"><div class="wrap">
  <div class="section-head"><div><p class="eyebrow">Plan smarter</p><h2>2026 travel guides</h2></div>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('guides')) ?>">All guides</a></div>
  <div class="grid g-3">
    <?php foreach ($guides as $g): ?>
      <article class="card"><a href="<?= e(url('g/'.$g['slug'])) ?>">
        <img class="card-media" loading="lazy" src="<?= e(abs_url($g['cover_url'])) ?>" alt="<?= e($g['title']) ?>">
        <div class="card-body">
          <?php if ($g['dest_name']): ?><span class="chip"><?= e($g['dest_name']) ?></span><?php endif; ?>
          <?php if (rmt_is_editorial($g)): ?><?= rmt_editorial_badge('editorial', false) ?><?php endif; ?>
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
        <p class="muted">Everything below is an <b>editorial review</b>, researched and labelled as such. There are no traveler reviews yet, and we are not going to invent any. <a data-review-cta="home" href="<?= e(url('contribute')) ?>">Yours would be the first.</a></p>
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
          <p class="muted">No reviews yet. <a data-review-cta="home" href="<?= e(url('contribute')) ?>">The first honest one can be yours.</a></p>
        <?php endif; ?>
      </div>
      <p style="margin-top:16px">
        <?php /* Points at /contribute rather than the bare form: somebody arriving from the
                 homepage has a trip in mind, not a URL, and the contribution page is built for
                 exactly that. Tagged so the funnel can say whether the homepage produces reviews
                 rather than only clicks. */ ?>
        <a class="btn btn-accent" data-review-cta="home" href="<?= e(url('contribute')) ?>">Share your experience</a>
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
  <h2 style="color:#fff;font-size:2rem">Join the people, not the guidebook.</h2>
  <p style="color:#dfe9f2;max-width:52ch;margin:0 auto 20px">Post where you are going and when. See whose dates
    overlap yours, meet in public, and write the review you wish you had read. Free, and 16+.</p>
  <a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free</a>
      <a class="btn btn-ghost btn-on-dark" href="<?= e(url('travelers')) ?>">See who is going</a>
</div></section>
