<?php
/**
 * The redesigned home page (app/design_v2.php, surface "home"). Same controller, title,
 * description and structured data as views/home.php; only the body differs.
 *
 * The travel map is the hero, as the way in: it is useful to one stranger with no community at all.
 * Right under it, what the community is for (review, discover, meet), then places to rate, cities,
 * and the questions travelers are asking. Every count is a count of rows; an empty one is not shown.
 *
 * @var list<array> $v2Places @var list<array> $v2Cities @var int $v2CityCount @var int $stat_places
 * @var int $stat_community_reviews @var list<array> $v2Reviews @var list<array> $goingSoon
 */
?>
<section class="v2-hero v2-hero-mapfirst">
  <div class="wrap">
    <div class="v2-hero-top">
      <p class="v2-kicker">The honest travel community</p>
      <h1 class="v2-h1">Where have you been?</h1>
      <p class="v2-lede">Start with your travel map. Then discover where to go next, share what really happened, review the places you went and meet travelers on your dates.</p>
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
        <a class="v2-btn v2-btn-accent v2-btn-sm" data-tm-open data-cta="home_map" href="<?= e(url('map')) ?>">Save and share my map</a>
      </div>
    </div>

    <?php /* What the map is the front door to: the community the site is for. Real counts only. */ ?>
    <nav class="v2-pillars" aria-label="What you can do on RuinMyTrip">
      <a class="v2-pillar" href="<?= e(url('explore')) ?>" data-cta="home_explore">
        <span class="v2-pillar-ico" aria-hidden="true">⚲</span>
        <span class="v2-pillar-txt"><b>Discover</b><small><?php if ($stat_places > 0): ?><?= number_format($stat_places) ?> places in <?= (int) $v2CityCount ?> cities<?php else: ?>Places worth the trip<?php endif; ?></small></span>
      </a>
      <a class="v2-pillar" href="<?= e(url('talk')) ?>" data-cta="home_share">
        <span class="v2-pillar-ico" aria-hidden="true">✎</span>
        <span class="v2-pillar-txt"><b>Share</b><small>What really happened on your trip</small></span>
      </a>
      <a class="v2-pillar" href="<?= e(url('review/new?src=home')) ?>" data-cta="home_rate">
        <span class="v2-pillar-ico" aria-hidden="true">★</span>
        <span class="v2-pillar-txt"><b>Review</b><small>Stars and a few honest lines</small></span>
      </a>
      <a class="v2-pillar" href="<?= e(url('plan?cta=home_plan')) ?>" data-cta="home_plan">
        <span class="v2-pillar-ico" aria-hidden="true">✈</span>
        <span class="v2-pillar-txt"><b>Plan</b><small>Your dates, your next city</small></span>
      </a>
      <a class="v2-pillar" href="<?= e(url('travelers')) ?>" data-cta="home_buddy">
        <span class="v2-pillar-ico" aria-hidden="true">☺</span>
        <span class="v2-pillar-txt"><b>Connect</b><small>Travelers on the same dates</small></span>
      </a>
    </nav>

    <form class="v2-hero-search" action="<?= e(url('search')) ?>" method="get" role="search"
          data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
      <span class="v2-search-ico" aria-hidden="true">⌕</span>
      <input type="search" name="q" placeholder="Search a city or place" aria-label="Search a place or city">
      <button class="v2-btn v2-btn-primary" type="submit">Search</button>
    </form>
    <?php if (!current_user()): ?>
      <p class="v2-hero-join">A free account keeps your map, reviews and trips together. <a href="<?= e(url('register?src=home_hero')) ?>" data-cta="home_join">Join free</a></p>
    <?php endif; ?>
  </div>
</section>

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
        <?php if ($stat_places > 0): ?><p class="v2-sub"><?= number_format($stat_places) ?> places in <?= (int) $v2CityCount ?> cities, each with hours, an address and directions.</p><?php endif; ?>
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
