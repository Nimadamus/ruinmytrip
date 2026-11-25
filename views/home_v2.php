<?php
/**
 * The redesigned home page (app/design_v2.php, surface "home"). Same controller, title,
 * description and structured data as views/home.php; only the body differs.
 *
 * The hero says why the site exists and offers the three first actions (warn, explore, find people);
 * then official reviews, places to rate, cities, the travel map, and the questions travelers are
 * asking. Every count is a count of rows; an empty one is not shown.
 *
 * @var list<array> $v2Places @var list<array> $v2Cities @var int $v2CityCount @var int $stat_places
 * @var int $stat_community_reviews @var list<array> $v2Reviews @var list<array> $goingSoon
 */
?>
<?php
/* 2026-11-25 hero (Nima's approved mockup): the promise, one search, four doors (warnings,
   destinations, reviews, travel buddies), the popular cities with their own photographs, and what
   the site stands for, all inside the first desktop screen over the Santorini photograph. Every
   number shown is a count of rows; a city with nothing counted yet shows no number at all.
   The three first actions that lived in the hero (warn in one sentence, official scam chips, find
   people) moved to the band right under it, unchanged. */
$h3Scams = rmt_city_scams_all();
$h3ScamSlugs = array_keys($h3Scams);
$h3Scam = $h3ScamSlugs ? q_all('SELECT slug, name FROM destinations WHERE slug IN (' . implode(',', array_fill(0, count($h3ScamSlugs), '?')) . ') ORDER BY name', $h3ScamSlugs) : [];
$hxFirst = ['santorini-greece', 'bangkok-thailand', 'lisbon-portugal', 'barcelona-spain', 'cancun-mexico', 'rome-italy'];
$hxRows = q_all("SELECT d.slug, d.name, d.country, d.hero_url,
                        (SELECT COUNT(*) FROM places p WHERE p.destination_id = d.id AND p.status = 'active') places
                   FROM destinations d WHERE d.hero_url IS NOT NULL AND d.hero_url <> ''
               ORDER BY CASE WHEN d.slug IN ('santorini-greece','bangkok-thailand','lisbon-portugal','barcelona-spain','cancun-mexico','rome-italy') THEN 0 ELSE 1 END,
                        places DESC, d.name LIMIT 40");
usort($hxRows, static function (array $x, array $y) use ($hxFirst): int {
    $ix = array_search($x['slug'], $hxFirst, true); $iy = array_search($y['slug'], $hxFirst, true);
    $ix = $ix === false ? 99 : $ix; $iy = $iy === false ? 99 : $iy;
    return $ix <=> $iy ?: ((int) $y['places'] <=> (int) $x['places']);
});
$hxDests = array_slice($hxRows, 0, 12);
$hxIco = [
  'warn'   => '<path d="M12 3.5 2.5 20h19L12 3.5z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/>',
  'pin'    => '<circle cx="12" cy="10" r="3"/><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11z"/>',
  'star'   => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z"/>',
  'people' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5.4 6-5.4s5.4 2 6 5.4"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14.6c2.3.3 4 2 4.5 5"/>',
  'arrow'  => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
  'shield' => '<path d="M12 3 19 6v6c0 4.4-2.9 7.4-7 9-4.1-1.6-7-4.6-7-9V6l7-3z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
  'check'  => '<circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.7 2.7L16.3 9.4"/>',
  'bolt'   => '<path d="M13 2.5 4.5 13.5H12l-1 8 8.5-11H12l1-8z"/>',
  'plane'  => '<path d="M21 4.5c-.9-.9-2.3-.9-3.2 0L14 8.3 5.6 5.8 4 7.4l6.6 4.1-3.4 3.4-2.6-.4L3.4 15.7l3.4 1.5 1.5 3.4 1.2-1.2-.4-2.6 3.4-3.4 4.1 6.6 1.6-1.6-2.5-8.4 3.8-3.8c.9-.9.9-2.3 0-3.2z"/>',
];
$hxSvg = static fn(string $k, int $s = 24): string => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $hxIco[$k] . '</svg>';
?>
<section class="v2-hx" aria-labelledby="hx-title">
  <div class="wrap v2-hx-in">
    <div class="v2-hx-top">
      <p class="v2-hx-eyebrow">Honest travel, before you book</p>
      <h1 class="v2-hx-h1" id="hx-title">Find out what could ruin <br class="v2-hx-br">your trip, <span class="v2-hx-accent">before it does.<svg class="v2-hx-swoosh" viewBox="0 0 300 18" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M4 13.5C70 5 175 2.5 296 8.5" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/></svg></span></h1>
      <p class="v2-hx-lede">The tourist traps, hidden fees and travel scams travelers wish somebody had warned them about. Read real reviews, real warnings and real experiences so you can travel smarter, safer and better prepared.</p>
      <form class="v2-hx-search" action="<?= e(url('search')) ?>" method="get" role="search"
            data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
        <svg class="v2-hx-search-ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/></svg>
        <label class="sr-only" for="v2-home-q">Search a city, country or place</label>
        <input type="search" name="q" id="v2-home-q" placeholder="Search a city, country or place" autocomplete="off">
        <button class="v2-hx-search-btn" type="submit">Search <?= $hxSvg('arrow', 20) ?></button>
      </form>
    </div>

    <ul class="v2-hx-cards">
      <li><a class="v2-hx-card v2-hx-card-warn" href="<?= e(url('ruined')) ?>" data-cta="home_card_warn">
        <span class="v2-hx-card-ico"><?= $hxSvg('warn', 40) ?></span>
        <span class="v2-hx-card-txt"><b>Travel Warnings</b><span>Real traveler alerts, scams and things to avoid.</span></span>
        <span class="v2-hx-card-go"><?= $hxSvg('arrow', 14) ?></span></a></li>
      <li><a class="v2-hx-card v2-hx-card-dest" href="<?= e(url('explore')) ?>" data-cta="home_explore">
        <span class="v2-hx-card-ico"><?= $hxSvg('pin', 40) ?></span>
        <span class="v2-hx-card-txt"><b>Explore Destinations</b><span><?php if ($stat_places > 0): ?><?= number_format($stat_places) ?> places with costs, reviews, tips and advice.<?php else: ?>Cities with costs, reviews, tips and advice.<?php endif; ?></span></span>
        <span class="v2-hx-card-go"><?= $hxSvg('arrow', 14) ?></span></a></li>
      <li><a class="v2-hx-card v2-hx-card-rev" href="<?= e(url('reviews')) ?>" data-cta="home_card_reviews">
        <span class="v2-hx-card-ico"><?= $hxSvg('star', 40) ?></span>
        <span class="v2-hx-card-txt"><b>Read Reviews</b><span>Honest, unfiltered experiences from real travelers.</span></span>
        <span class="v2-hx-card-go"><?= $hxSvg('arrow', 14) ?></span></a></li>
      <li><a class="v2-hx-card v2-hx-card-bud" href="<?= e(url('buddies')) ?>" data-cta="home_card_buddies">
        <span class="v2-hx-card-ico"><?= $hxSvg('people', 40) ?></span>
        <span class="v2-hx-card-txt"><b>Find Travel Buddies</b><span>Connect with like-minded travelers for your next trip.</span></span>
        <span class="v2-hx-card-go"><?= $hxSvg('arrow', 14) ?></span></a></li>
    </ul>

    <?php if ($hxDests): ?>
    <div class="v2-hx-pop">
      <div class="v2-hx-pop-head">
        <h2 id="hx-pop"><span class="v2-hx-plane"><?= $hxSvg('plane', 26) ?></span>Popular Destinations</h2>
        <a href="<?= e(url('explore')) ?>" data-cta="home_dest_all">Explore All Destinations <?= $hxSvg('arrow', 16) ?></a>
      </div>
      <div class="v2-hx-car">
        <button type="button" class="v2-hx-car-btn v2-hx-car-prev" aria-label="Previous destinations" aria-controls="hx-track" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 5-7 7 7 7"/></svg></button>
        <ul class="v2-hx-track" id="hx-track" aria-labelledby="hx-pop">
          <?php foreach ($hxDests as $i => $d):
            $dSet = function_exists('rmt_media_srcset') ? rmt_media_srcset((string) $d['hero_url']) : '';
            $dSrc = $dSet !== '' ? (string) strtok($dSet, ' ') : abs_url((string) $d['hero_url']);
            $dScams = isset($h3Scams[$d['slug']]) ? count($h3Scams[$d['slug']]['items']) : 0;
            $dMeta = [];
            if ((int) $d['places'] > 0) $dMeta[] = number_format((int) $d['places']) . ' ' . ((int) $d['places'] === 1 ? 'place' : 'places');
            if ($dScams > 0) $dMeta[] = $dScams . ' scam ' . ($dScams === 1 ? 'alert' : 'alerts'); ?>
          <li><a class="v2-hx-dest" href="<?= e(url('d/' . $d['slug'])) ?>" data-cta="home_city">
            <img src="<?= e($dSrc) ?>"<?php if ($dSet !== ''): ?> srcset="<?= e($dSet) ?>" sizes="(max-width: 700px) 46vw, 240px"<?php endif; ?> alt="" width="240" height="180" loading="<?= $i < 6 ? 'eager' : 'lazy' ?>" decoding="async">
            <span class="v2-hx-dest-txt"><b><?= e((string) $d['name']) ?></b><?php if ((string) $d['country'] !== ''): ?><span><?= e((string) $d['country']) ?></span><?php endif; ?><?php if ($dMeta): ?><small><?= e(implode(' · ', $dMeta)) ?></small><?php endif; ?></span></a></li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="v2-hx-car-btn v2-hx-car-next" aria-label="More destinations" aria-controls="hx-track" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 5 7 7-7 7"/></svg></button>
      </div>
    </div>
    <?php endif; ?>

    <ul class="v2-hx-benefits" aria-label="Why RuinMyTrip">
      <li><?= $hxSvg('shield', 30) ?>Real Traveler Insights</li>
      <li><?= $hxSvg('check', 30) ?>Scam &amp; Fee Alerts</li>
      <li><?= $hxSvg('people', 30) ?>A Global Travel Community</li>
      <li><?= $hxSvg('bolt', 30) ?>Travel Smarter, Not Sorrier</li>
    </ul>
  </div>
</section>
<script src="<?= e(rmt_asset('assets/js/home-hero.js')) ?>" defer></script>

<section class="v2-hero v2-home3-acts" aria-label="Warn, explore, find people">
  <div class="wrap">
    <div class="v2-act3">
      <section class="v2-act v2-act-warn" aria-labelledby="act-warn">
        <span class="v2-act-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 2.5 20h19L12 3.5z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/></svg></span>
        <h2 id="act-warn">Warn the next traveler</h2>
        <p>What nearly ruined your trip? One sentence is enough to save somebody else the same mistake.</p>
        <form method="get" action="<?= e(url('review/new')) ?>" class="v2-act-form">
          <input type="hidden" name="src" value="home_warn">
          <label class="sr-only" for="act-warn-text">What nearly ruined your trip</label>
          <textarea id="act-warn-text" name="ruined" rows="2" maxlength="280" required placeholder="The taxi that charged triple. The hotel that lied about the pool."></textarea>
          <button class="v2-btn v2-btn-accent" type="submit" data-cta="home_warn">Warn the next traveler</button>
        </form>
        <p class="v2-act-note">No account needed to start. <a href="<?= e(url('ruined')) ?>">Read the warnings</a></p>
      </section>

      <section class="v2-act" aria-labelledby="act-explore">
        <span class="v2-act-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11z"/></svg></span>
        <h2 id="act-explore">Explore a destination</h2>
        <p><?php if ($stat_places > 0): ?><?= number_format($stat_places) ?> places in <?= (int) $v2CityCount ?> cities, with costs, honest reviews<?php else: ?>Cities with costs, honest reviews<?php endif; ?> and, for these cities, the scams official travel advice warns about:</p>
        <?php if ($h3Scam): ?>
          <div class="v2-act-chips"><?php foreach (array_slice($h3Scam, 0, 8) as $c): ?><a href="<?= e(url('d/' . $c['slug']) . '#scams') ?>" data-cta="home_scams"><?= e((string) $c['name']) ?></a><?php endforeach; ?></div>
        <?php endif; ?>
        <a class="v2-btn v2-btn-glass v2-act-btn" href="<?= e(url('explore')) ?>" data-cta="home_explore">See every city</a>
      </section>

      <section class="v2-act" aria-labelledby="act-find">
        <span class="v2-act-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5.4 6-5.4s5.4 2 6 5.4"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14.6c2.3.3 4 2 4.5 5"/></svg></span>
        <h2 id="act-find">Find travel companions</h2>
        <p>See who is going where you are going, on your dates. Nobody can message you until you both say yes.</p>
        <form method="get" action="<?= e(url('buddies')) ?>" class="v2-act-form v2-act-form-row">
          <label class="sr-only" for="act-find-where">Where are you going</label>
          <input id="act-find-where" type="text" name="where" placeholder="City or country" autocomplete="off">
          <button class="v2-btn v2-btn-primary" type="submit" data-cta="home_find">Find people</button>
        </form>
        <p class="v2-act-note">Going somewhere? <a href="<?= e(url('plan?cta=home_plan')) ?>" data-cta="home_plan">Post your dates</a> and travelers on the same dates can find you.</p>
      </section>
    </div>

    <ul class="v2-why v2-why-row" aria-label="What we promise">
      <li><span class="v2-why-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5 2.5 20h19L12 3.5z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/></svg></span><span><b>What went wrong is the point</b>A one star review that explains itself is as welcome as a rave. Nothing is removed for being critical.</span></li>
      <li><span class="v2-why-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/></svg></span><span><b>Nobody can buy a rating</b>No business can pay for a better score, a higher spot or a deleted review.</span></li>
      <li><span class="v2-why-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 19 6v6c0 4.4-2.9 7.4-7 9-4.1-1.6-7-4.6-7-9V6l7-3z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg></span><span><b>Ours and yours, kept apart</b>Our Official Reviews are always labelled and never counted in traveler ratings.</span></li>
    </ul>
    <?php if (!current_user()): ?>
      <p class="v2-hero-join">A free account keeps your warnings, reviews, trips and travel map together. <a href="<?= e(url('register?src=home_hero')) ?>" data-cta="home_join">Join free</a></p>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($v2Official)): ?>
<section class="v2-section v2-section-proof">
  <div class="wrap">
    <div class="v2-sec-head">
      <div>
        <h2 class="v2-h2">Read before you go</h2>
        <p class="v2-sub">A few of our Official Reviews, written from official sources and labelled as ours. Traveler reviews are only just starting, so yours could be the first on a place.</p>
      </div>
      <a class="v2-link" href="<?= e(url('editorial-policy')) ?>">How we write these →</a>
    </div>
    <div class="v2-proof-grid">
      <?php foreach ($v2Official as $or): ?>
        <a class="v2-proof" href="<?= e(url('p/' . $or['slug'])) ?>" data-cta="home_official">
          <span class="v2-proof-top">
            <span class="ed-badge">Official Review</span>
            <span class="v2-proof-stars" aria-label="<?= (int) $or['rating'] ?> of 5"><?= str_repeat('★', (int) $or['rating']) ?><span class="v2-dim"><?= str_repeat('★', 5 - (int) $or['rating']) ?></span></span>
          </span>
          <span class="v2-proof-meta"><?= e(rmt_place_type_label((string) $or['type'])) ?> · <?= e((string) $or['dest_name']) ?></span>
          <b class="v2-proof-name"><?= e((string) $or['name']) ?></b>
          <span class="v2-proof-title"><?= e((string) $or['title']) ?></span>
          <span class="v2-proof-foot">Editorial, not counted in traveler ratings</span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($v2Notes)): ?>
      <p class="v2-sub" style="margin:18px 0 0">New 2026 notes:
        <?php foreach ($v2Notes as $ni => $vn): ?><?= $ni ? ' · ' : ' ' ?><a href="<?= e(url('blog/' . $vn['slug'])) ?>" data-cta="home_note"><?= e((string) $vn['title']) ?></a><?php endforeach; ?>
        · <a class="v2-link" href="<?= e(url('blog')) ?>">All 2026 notes →</a>
      </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($v2Places): ?>
<section class="v2-section" id="rate">
  <div class="wrap">
    <div class="v2-sec-head">
      <div>
        <h2 class="v2-h2">Been somewhere lately?</h2>
        <p class="v2-sub">Tap the stars. No account needed to start, and nothing is lost if you join halfway.</p>
      </div>
      <a class="v2-link" href="<?= e(url('review/new?src=home')) ?>" data-cta="home_rate">Rate any place →</a>
    </div>
    <div class="v2-rate-grid">
      <?php foreach ($v2Places as $pl): $plId = (int) $pl['id']; $plN = (int) $pl['traveler_reviews']; ?>
        <article class="v2-rate-card">
          <a class="v2-rate-media" href="<?= e(url('p/' . $pl['slug'])) ?>" data-cta="home_place" tabindex="-1" aria-hidden="true"
             style="background-image:url('<?php $plSet = function_exists('rmt_media_srcset') ? rmt_media_srcset((string) $pl['hero_url']) : ''; /* the 480px copy: the card is never wider */
                echo e($plSet !== '' ? (string) strtok($plSet, ' ') : abs_url((string) $pl['hero_url'])); ?>')">
            <span class="v2-rate-glyph"><?= rmt_design_type_glyph((string) $pl['type']) ?></span>
          </a>
          <div class="v2-rate-body">
            <p class="v2-rate-meta"><?= e(rmt_place_type_label((string) $pl['type'])) ?> · <?= e((string) $pl['dest_name']) ?></p>
            <h3 class="v2-rate-name"><a href="<?= e(url('p/' . $pl['slug'])) ?>" data-cta="home_place"><?= e((string) $pl['name']) ?></a></h3>
            <div class="v2-stars" role="group" aria-label="Rate <?= e((string) $pl['name']) ?>">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <a href="<?= e(url('review/new?place=' . $plId . '&rating=' . $s . '&src=home')) ?>" data-cta="home_rate"
                   rel="nofollow" aria-label="<?= $s ?> <?= $s === 1 ? 'star' : 'stars' ?>">★</a>
              <?php endfor; ?>
            </div>
            <p class="v2-rate-note"><?php if ($plN > 0): ?><?= number_format((float) $pl['traveler_avg'], 1) ?> from <?= $plN ?> <?= $plN === 1 ? 'traveler' : 'travelers' ?><?php else: ?>Be the first traveler to review it<?php endif; ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="v2-section v2-section-tint">
  <div class="wrap">
    <div class="v2-sec-head">
      <div>
        <h2 class="v2-h2">Cities to explore</h2>
        <?php if ($stat_places > 0): ?><p class="v2-sub"><?= number_format($stat_places) ?> places in <?= (int) $v2CityCount ?> cities, every one on the map with directions.</p><?php endif; ?>
      </div>
      <a class="v2-link" href="<?= e(url('explore')) ?>">All <?= (int) $v2CityCount ?> cities →</a>
    </div>
    <div class="v2-city-grid">
      <?php foreach ($v2Cities as $i => $c): ?>
        <a class="v2-city<?= $i === 0 ? ' v2-city-lg' : '' ?>" href="<?= e(url('d/' . $c['slug'])) ?>" data-cta="home_city">
          <?php $cSet = function_exists('rmt_media_srcset') ? rmt_media_srcset((string) $c['hero_url']) : ''; ?>
          <img loading="lazy" src="<?= e(abs_url((string) $c['hero_url'])) ?>"<?php if ($cSet !== ''): ?> srcset="<?= e($cSet) ?>" sizes="<?= $i === 0 ? '(max-width: 900px) 100vw, 50vw' : '(max-width: 900px) 50vw, 25vw' ?>"<?php endif; ?> alt="">
          <span class="v2-city-txt">
            <b><?= e((string) $c['name']) ?></b>
            <small><?= e((string) $c['country']) ?><?php if ((int) $c['places'] > 0): ?> · <?= (int) $c['places'] ?> places<?php endif; ?></small>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php /* The city and travelers pages the current home page links, so none of them loses its home link. */
      $v2Shown = array_column($v2Cities, 'slug');
      $v2More = array_values(array_filter($featuredCities ?? [], static fn(array $c): bool => !in_array($c['slug'], $v2Shown, true))); ?>
    <?php if ($v2More): ?>
      <div class="v2-chips v2-chips-wrap v2-home-chips"><?php foreach ($v2More as $c): ?><a class="v2-chip v2-chip-sm" href="<?= e(url('d/' . $c['slug'])) ?>" data-cta="home_city"><?= e((string) $c['name']) ?></a><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="v2-hero v2-map-band" aria-labelledby="map-band-h">
  <div class="wrap">
    <div class="v2-map-band-grid">
      <div>
        <p class="v2-kicker">Free, no account</p>
        <h2 class="v2-h2 v2-on-dark" id="map-band-h">Color in everywhere you have been</h2>
        <p class="v2-lede">Tap the countries on the map, then save it and share the picture. Every country you color in is a place you could warn somebody about.</p>
      </div>
      <div class="v2-hero-map v2-hero-map-xl tm" data-tm-embed data-tm-readonly="0" data-tm-codes=""
         data-tm-geo="<?= e(rmt_asset('assets/data/world-map.json')) ?>" data-tm-base="<?= e(url('map')) ?>"
         data-tm-card="<?= e(url('card/map')) ?>">
      <div class="v2-map-head">
        <p class="v2-map-title">Your travel map <span class="v2-map-free">free, no account</span></p>
        <span class="v2-map-count" data-tm-count>0 countries</span>
      </div>
      <div class="tm-map" data-tm-map aria-label="World map: tap the countries you have been to">
        <p class="tm-loading">Tap the countries you have been to</p>
      </div>
      <div class="v2-map-foot">
        <span class="v2-map-hint" data-tm-status aria-live="polite">Tap a country to color it in.</span>
        <a class="v2-btn v2-btn-primary v2-btn-sm" data-tm-open data-cta="home_map" href="<?= e(url('map')) ?>">Save and share my map</a>
      </div>
    </div>
    </div>
  </div>
</section>

<?php if (!empty($live) || !empty($liveCities)): ?>
<section class="v2-section">
  <div class="wrap">
    <div class="v2-sec-head">
      <div>
        <h2 class="v2-h2">From the community</h2>
        <p class="v2-sub">Questions to answer from experience and lessons from real trips. Anything written by our team says so.</p>
      </div>
      <a class="v2-link" href="<?= e(url('talk')) ?>" data-cta="home_talk">All questions →</a>
    </div>
    <?php if (!empty($live)): ?>
    <div class="v2-ask-grid">
      <?php foreach ($live as $it): ?>
        <a class="v2-ask<?= !empty($it['editorial']) ? ' v2-ask-ours' : '' ?>" href="<?= e((string) $it['href']) ?>" data-cta="home_talk">
          <span class="v2-ask-meta"><?= e((string) $it['label']) ?></span>
          <span class="v2-ask-q"><?= e(mb_strimwidth((string) $it['title'], 0, 150, '…')) ?></span>
          <span class="v2-ask-foot"><?php if ((string) $it['meta'] !== ''): ?><?= e((string) $it['meta']) ?> · <?php endif; ?><?= e((string) $it['who']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($liveCities)): ?>
      <h3 class="v2-home-chips-h v2-home-chips-h-gap"><?= !empty($goingSoon) ? 'Who is going, by city' : 'Cities with something happening' ?></h3>
      <div class="v2-chips v2-chips-wrap v2-home-chips v2-home-live">
        <?php foreach ($liveCities as $c):
            $going = (int) $c['going_count']; $meets = (int) $c['meetup_count']; $talk = (int) $c['talk_count'];
            $hint = $going ? $going . ' going' : ($meets ? $meets . ($meets === 1 ? ' meetup' : ' meetups') : ($talk ? $talk . ($talk === 1 ? ' question' : ' questions') : '')); ?>
          <a class="v2-chip v2-chip-sm" href="<?= e(url('d/' . $c['slug'] . '/travelers')) ?>"><?= e((string) $c['name']) ?><?php if ($hint !== ''): ?> <span><?= e($hint) ?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <a class="v2-chip v2-chip-sm" href="<?= e(url('travelers')) ?>">Every city →</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="v2-section v2-section-tint">
  <div class="wrap">
    <div class="v2-sec-head"><div><h2 class="v2-h2">What RuinMyTrip is for</h2><p class="v2-sub">Honest travel, before, during and after the trip.</p></div></div>
  </div>
  <div class="wrap v2-how">
    <div class="v2-how-item">
      <span class="v2-how-ico" aria-hidden="true">★</span>
      <h3>Reviews from people who went</h3>
      <p>Traveler ratings are only ever travelers. Our own research is labelled as ours and never mixed into a traveler score.</p>
    </div>
    <div class="v2-how-item">
      <span class="v2-how-ico" aria-hidden="true">◉</span>
      <h3>A map of everywhere you have been</h3>
      <p>Tap the countries, share the picture. Thirty seconds, and it lives on your profile if you want it to.</p>
    </div>
    <div class="v2-how-item">
      <span class="v2-how-ico" aria-hidden="true">☺</span>
      <h3>Company when your dates overlap</h3>
      <p>Post where and when. We tell you only when a real traveler will be there the same days.</p>
    </div>
  </div>
</section>

<?php if (!empty($v2Reviews)): ?>
<section class="v2-section v2-section-tint">
  <div class="wrap">
    <div class="v2-sec-head"><h2 class="v2-h2">Latest from travelers</h2><a class="v2-link" href="<?= e(url('reviews')) ?>">All reviews →</a></div>
    <div class="v2-review-row">
      <?php foreach (array_slice($v2Reviews, 0, 3) as $rv): ?>
        <a class="v2-review" href="<?= e(url('review/' . (int) $rv['id'] . '/' . ($rv['slug'] ?: rmt_review_slug($rv)))) ?>">
          <span class="v2-review-stars" aria-label="<?= (int) $rv['rating'] ?> of 5"><?= str_repeat('★', (int) $rv['rating']) ?><span class="v2-dim"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span></span>
          <b><?= e((string) $rv['title']) ?></b>
          <small>@<?= e((string) ($rv['username'] ?? $rv['author']['username'] ?? 'traveler')) ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="v2-band">
  <div class="wrap v2-band-in">
    <div>
      <h2 class="v2-h2 v2-on-dark">Going somewhere?</h2>
      <p>Leave your dates and we email you only when a real traveler overlaps them. Nobody can message you until you say yes.</p>
    </div>
    <div class="v2-band-acts">
      <a class="v2-btn v2-btn-accent" data-cta="home_plan" href="<?= e(url('plan?cta=home_plan')) ?>">Add my dates</a>
      <a class="v2-btn v2-btn-glass" data-cta="home_buddy" href="<?= e(url('buddies')) ?>">Travel buddies</a>
    </div>
  </div>
  <?php /* The travel buddy pages the current home page links, kept so none of them loses its link. */ ?>
  <div class="wrap v2-band-links">
    <span>Popular:</span>
    <a href="<?= e(url('trip/new')) ?>">Share a trip story</a>
    <a href="<?= e(url('buddies/cruise')) ?>">Cruise buddies</a>
    <a href="<?= e(url('buddies/backpacking')) ?>">Backpacking</a>
    <a href="<?= e(url('buddies/road-trip')) ?>">Road trips</a>
    <?php foreach (['greece', 'thailand', 'japan', 'italy', 'bali', 'mexico', 'royal-caribbean', 'carnival', 'norwegian'] as $bl): if (!($lp = rmt_buddy_landing($bl))) continue; ?>
      <a href="<?= e(url(rmt_buddy_landing_path($bl))) ?>"><?= e($lp['kind'] === 'cruise' ? $lp['line'] . ' cruises' : $lp['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<script src="<?= e(rmt_asset('assets/js/travel-map.js')) ?>" defer></script>
