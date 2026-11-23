<?php /** @var array $p @var ?array $me @var array $comments @var int $likeCount @var int $saveCount @var bool $liked @var bool $saved */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('blog')) ?>">Blog</a> / <?= e($p['title']) ?></p></div>
<div class="wrap prose">
  <?php $isEd = rmt_is_editorial($p); ?>
  <?php /* Utility notes (tax and ticket notes) get one sign up ask, the Watch card (rmt_cta_focus). */
        $utilTt = null;
        if ($isEd) { foreach (rmt_tt_cities() as $uK => $uC) { if ($uC['note'] === ($p['slug'] ?? '')) { $utilTt = $uK; break; } } }
        $utilTicket = $isEd && str_contains((string) ($p['slug'] ?? ''), '-tickets-') && !empty($blogDest)
                      && isset(rmt_byg_cities('scams')[(string) $blogDest['slug']]);
        $util = $isEd && rmt_cta_focus() && ($utilTt !== null || $utilTicket); ?>
  <span class="chip chip-cap"><?= e(ucfirst($p['category'])) ?></span>
  <?php if ($isEd): ?><?= rmt_editorial_badge() ?><?php endif; ?>
  <?php /* Arrived from a post that asked them (rmt_outreach_arrival): the question comes before the title. */ ?>
  <?php $askFirst = !empty($blogDest) && rmt_outreach_arrival(); ?>
  <?php if ($askFirst): ?>
    <?php $rateDest = $blogDest; $rateSrc = 'outreach_rate'; $rateQ = 'Been to ' . $blogDest['name'] . ' this year? How was it?';
          include __DIR__ . '/_rate_dest.php'; ?>
  <?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <p class="muted">by <a href="<?= e(url('u/'.$p['author']['username'])) ?>"><?= $isEd ? e(rmt_editorial_name()) : '@'.e($p['author']['username']) ?></a> · <?= e(ago($p['created_at'])) ?></p>
  <?php /* On the first screen (2026-10-17): under the summary it started 1.2 screens down on a laptop
           and 1.8 on a phone, past where a 2 to 10 second visit ever scrolls. */ ?>
  <?php if (!empty($blogDest) && !$askFirst && !$util): ?>
    <?php $rateDest = $blogDest; $rateSrc = 'blog_rate'; $rateQ = null; include __DIR__ . '/_rate_dest.php'; ?>
  <?php endif; ?>
  <?php if ($isEd): ?><div class="callout"><?= rmt_editorial_disclosure() ?></div><?php endif; ?>
  <?php if ($p['cover_url']): ?><?php $heroSet = rmt_media_srcset((string) $p['cover_url']); /* a phone got the full original: 218 KB where 23 KB would do (crawl 2026-11-07) */ ?><img class="article-hero" src="<?= e(abs_url($p['cover_url'])) ?>"<?php if ($heroSet): ?> srcset="<?= e($heroSet) ?>" sizes="(max-width: 760px) 100vw, 760px"<?php endif; ?> width="1280" height="720" fetchpriority="high" alt="<?= e($p['title']) ?>"><?php endif; ?>
  <p style="font-size:1.15rem;color:var(--muted)"><?= e($p['summary']) ?></p>
  <?php /* These posts are what search lands on, and the city strip used to sit under the whole
           article. On 2026-09-29 every engaged search visit of the week was one blog page read
           for 2 to 10 seconds and left: nobody reached the bottom. So the people going to the
           city now sit under the summary, in the short form the place pages use. */ ?>
  <?php if (!empty($blogDest) && !$util): ?>
    <?php $destSlug = (string) $blogDest['slug']; $destName = (string) $blogDest['name']; $dsCompact = true;
          include __DIR__ . '/_meet_travelers.php'; $dsCompact = false; ?>
  <?php endif; ?>
  <div style="white-space:<?= $isEd ? 'normal' : 'pre-wrap' ?>"><?= $isEd ? $p['body'] : rmt_linkify_mentions(rmt_linkify_tags(nl2br(e($p['body'])))) ?></div>
  <?php /* A partner link only on our own utility notes, only once a program is joined (app/partners.php). */
        if ($isEd) echo rmt_partner_block_for_post((string) $p['slug']); ?>
  <?php $tplace = $isEd ? rmt_place_for_ticket_note((string) $p['slug']) : null;
        $tplaceRow = $tplace !== null ? q_one("SELECT slug, name FROM places WHERE slug = ? AND status = 'active'", [$tplace]) : null;
        if ($tplaceRow): ?>
    <p style="margin:18px 0 0">Opening hours, map and what it is like inside: <a href="<?= e(url('p/' . $tplaceRow['slug'])) ?>"><?= e((string) $tplaceRow['name']) ?></a></p>
  <?php endif; ?>
  <?php if ($util && $utilTicket && $utilTt === null):
        $byg = ['topic' => 'scams', 'city' => (string) $blogDest['slug'], 'name' => (string) $blogDest['name'],
                'source' => 'ticket_note', 'return' => '/blog/' . (string) $p['slug'] . '#byg'];
        include __DIR__ . '/_watch_city.php'; endif; ?>
  <?php if ($isEd && ($p['slug'] ?? '') === 'tourist-taxes-2026'): $taxIdx = rmt_tax_note_index(); if ($taxIdx): ?>
    <h2 id="every-city" style="margin:26px 0 8px">Every city we cover</h2>
    <ul class="list-plain">
      <?php foreach ($taxIdx as $ti): ?><li style="margin:0 0 6px"><a href="<?= e(url('blog/' . $ti['slug'])) ?>"><?= e($ti['title']) ?></a></li><?php endforeach; ?>
    </ul>
  <?php endif; endif; ?>
  <?php if (!empty($tags)): ?>
    <div class="tag-row"><?php foreach ($tags as $tg): ?><a class="chip" href="<?= e(url('tag/'.$tg['name'])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div>
  <?php endif; ?>

  <?php $ttCity = null; foreach (rmt_tt_cities() as $ttK => $ttC) { if ($ttC['note'] === ($p['slug'] ?? '')) { $ttCity = $ttK; break; } }
  if ($ttCity !== null || ($p['slug'] ?? '') === 'tourist-taxes-2026'): ?>
    <?php $calcHref = url('tourist-tax-calculator' . ($ttCity !== null ? '?city=' . $ttCity : '')); ?>
    <?php if ($ttCity !== null) { $tsTopics = rmt_story_topics_for_dest((string) rmt_tt_cities()[$ttCity]['dest']); $tsCity = (string) rmt_tt_cities()[$ttCity]['name']; include __DIR__ . '/_traveler_stories.php'; } ?>
    <?php if ($util && $ttCity !== null):
          $byg = ['topic' => 'tax', 'city' => (string) $ttCity, 'name' => (string) (rmt_tt_cities()[$ttCity]['name'] ?? ''),
                  'source' => 'tax_note', 'return' => '/blog/' . (string) $p['slug'] . '#byg'];
          include __DIR__ . '/_watch_city.php'; ?>
    <?php else: ?>
    <p style="margin:22px 0 0"><a class="btn btn-accent btn-sm" href="<?= e($calcHref) ?>">Work out the tax on your stay</a></p>
    <?php endif; ?>
    <?php $tt27 = null; foreach (rmt_tt27_rows() as $tt27R) { if ($ttCity !== null && $tt27R['key'] === $ttCity) { $tt27 = $tt27R; break; } } ?>
    <p style="margin:14px 0 0"><?php if ($tt27): ?>Changing in 2027: <?= e($tt27['rate_2027']) ?><?= $tt27['date'] !== '' ? ' from ' . e(date('j F Y', (int) strtotime($tt27['date']))) : '' ?>. <?php endif; ?><a href="<?= e(url('tourist-tax-2027' . ($tt27 ? '#' . $tt27['key'] : ''))) ?>">Every European tourist tax change for 2027, ranked</a></p>
    <?php if ($ttCity !== null): ?>
      <p class="muted" style="margin:14px 0 0">Tourist tax in other cities:
        <?php foreach (rmt_tt_neighbours($ttCity, 6) as $i => $ttN): ?><?= $i ? ', ' : ' ' ?><a href="<?= e(url('blog/' . $ttN['note'])) ?>"><?= e($ttN['name']) ?></a><?php endforeach; ?>.</p>
    <?php endif; ?>
    <?php if ($ttCity !== null): $ttDest = (string) rmt_tt_cities()[$ttCity]['dest']; $ttByb = rmt_byb($ttDest); ?>
      <?php if ($ttByb !== null): ?>
        <p style="margin:14px 0 0"><a href="<?= e(url('warnings/' . $ttDest . '#before-you-book')) ?>"><?= count($ttByb['items']) ?> more things to check before you book in <?= e((string) rmt_tt_cities()[$ttCity]['name']) ?></a>, each from an official source.</p>
      <?php endif; ?>
      <?php if ($util): ?><details class="more-trip" style="margin:18px 0"><summary>More for your trip: work out the tax, your checklist, tell us what went wrong</summary>
        <p style="margin:14px 0 0"><a class="btn btn-ghost btn-sm" href="<?= e($calcHref) ?>">Work out the tax on your stay</a></p><?php endif; ?>
      <?php $clDest = (string) rmt_tt_cities()[$ttCity]['dest']; $clName = (string) rmt_tt_cities()[$ttCity]['name']; include __DIR__ . '/_checklist_cta.php'; ?>
      <?php $story = ['topic' => 'tax:' . $ttCity, 'name' => (string) rmt_tt_cities()[$ttCity]['name'], 'return' => '/blog/' . (string) $p['slug'] . '#story'];
            include __DIR__ . '/_story_form.php'; ?>
      <?php if ($util): ?></details><?php endif; ?>
    <?php endif; ?>
    <?php if (!$util) { $byg = ['topic' => 'tax', 'city' => (string) ($ttCity ?? ''), 'name' => $ttCity !== null ? (string) (rmt_tt_cities()[$ttCity]['name'] ?? '') : '',
                  'source' => 'tax_note', 'return' => '/blog/' . (string) $p['slug'] . '#byg'];
          include __DIR__ . '/_byg_form.php'; } ?>
  <?php endif; ?>
  <?php $bm = $blogMore ?? []; if (!empty($blogDest) && (!empty($bm['posts']) || !empty($bm['guide']) || !empty($bm['buddies']))): ?>
    <div class="card" style="margin:26px 0 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 8px">More on <?= e((string) $blogDest['name']) ?></h2>
      <ul class="list-plain">
        <li style="margin:0 0 6px"><a href="<?= e(url('d/' . $blogDest['slug'])) ?>"><?= e((string) $blogDest['name']) ?>: places, honest reviews and who is going</a></li>
        <?php if (function_exists('rmt_city_scams') && rmt_city_scams((string) $blogDest['slug'])): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('d/' . $blogDest['slug']) . '#scams') ?>">Scams and traps in <?= e((string) $blogDest['name']) ?>, from official travel advice</a></li>
        <?php endif; ?>
        <?php if (!empty($bm['guide'])): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('g/' . $bm['guide']['slug'])) ?>"><?= e((string) $bm['guide']['title']) ?></a></li>
        <?php endif; ?>
        <?php foreach (array_slice($bm['posts'] ?? [], 0, 4) as $op): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('blog/' . $op['slug'])) ?>"><?= e((string) $op['title']) ?></a></li>
        <?php endforeach; ?>
        <?php if (!empty($bm['buddies'])): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url(rmt_buddy_landing_path($bm['buddies']['slug']))) ?>">Travel buddies in <?= e((string) $bm['buddies']['name']) ?></a></li>
        <?php else: ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('d/' . $blogDest['slug'] . '/travelers')) ?>">Travelers going to <?= e((string) $blogDest['name']) ?></a></li>
        <?php endif; ?>
      </ul>
    </div></div>
  <?php endif; ?>
  <?php if (!empty($blogRelated)): ?>
    <div class="card" style="margin:18px 0 0"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 8px"><?= ['tax' => 'More 2026 tourist taxes and fees', 'ticket' => 'More 2026 ticket prices'][rmt_blog_topic((string) $p['slug'])] ?? 'More to read before you go' ?></h2>
      <ul class="list-plain">
        <?php foreach ($blogRelated as $rp): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('blog/' . $rp['slug'])) ?>"><?= e((string) $rp['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div></div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:30px 0 20px">
    <a class="btn btn-ghost" href="<?= e(url('blog')) ?>">← Back to blog</a>
  </div>

  <?php /* A reader who came for a price has a trip behind them. Ask for the sentence, here, while
           the page is still open: it becomes their first review. */ ?>
  <?php if (!$util): ?>
  <div class="card" style="margin:22px 0"><div class="card-body">
    <?php $dests = $askDests ?? []; $askVariant = 'card'; include __DIR__ . '/_ruined_ask.php'; ?>
  </div></div>
  <?php endif; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (rmt_blog_can_edit($p, $me)): ?>
      <a class="btn btn-ghost" href="<?= e(url('blog/'.(int)$p['id'].'/edit')) ?>">Edit</a>
    <?php endif; ?>
  </div>

  <?php
    $targetType = 'blog_post'; $targetId = (int)$p['id']; $ownerId = (int)$p['user_id'];
    $returnUrl = url('blog/'.$p['slug']);
    include __DIR__ . '/_engagement.php';
  ?>
</div>
