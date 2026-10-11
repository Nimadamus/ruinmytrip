<?php
/**
 * The redesigned home page (app/design_v2.php, surface "home"). Same controller, title,
 * description and structured data as views/home.php; only the body differs.
 *
 * Leads with what one stranger can use today: the travel map, and a one tap rating of a place
 * search already sends people to. Every count is a count of rows; an empty one is not shown.
 *
 * @var list<array> $v2Places @var list<array> $v2Cities @var int $v2CityCount @var int $stat_places
 * @var int $stat_community_reviews @var list<array> $v2Reviews @var list<array> $goingSoon
 */
?>
<section class="v2-hero">
  <div class="wrap v2-hero-grid">
    <div class="v2-hero-copy">
      <p class="v2-kicker">Real trips, honest reviews</p>
      <h1 class="v2-h1">Honest travel, from people who went.</h1>
      <p class="v2-lede">Rate the places you have been, color in your travel map, and see who is heading where you are going.</p>
      <form class="v2-hero-search" action="<?= e(url('search')) ?>" method="get" role="search"
            data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
        <span class="v2-search-ico" aria-hidden="true">⌕</span>
        <input type="search" name="q" placeholder="Where are you going?" aria-label="Search a place or city">
        <button class="v2-btn v2-btn-primary" type="submit">Search</button>
      </form>
      <div class="v2-hero-acts">
        <a class="v2-btn v2-btn-accent" data-cta="home_map" href="<?= e(url('map')) ?>">Color in my map</a>
        <a class="v2-btn v2-btn-glass" data-cta="home_rate" href="#rate">Rate a place</a>
      </div>
      <?php if ($stat_places > 0): ?>
        <p class="v2-hero-proof"><b><?= number_format($stat_places) ?></b> places in <b><?= (int) $v2CityCount ?></b> cities, with hours, addresses and directions<?php if ($stat_community_reviews > 0): ?> · <b><?= number_format($stat_community_reviews) ?></b> traveler <?= $stat_community_reviews === 1 ? 'review' : 'reviews' ?><?php endif; ?></p>
      <?php endif; ?>
    </div>

    <div class="v2-hero-map tm" data-tm-embed data-tm-readonly="0" data-tm-codes=""
         data-tm-geo="<?= e(rmt_asset('assets/data/world-map.json')) ?>" data-tm-base="<?= e(url('map')) ?>"
         data-tm-card="<?= e(url('card/map')) ?>">
      <div class="v2-map-head">
        <div>
          <p class="v2-map-kicker">Your travel map</p>
          <p class="v2-map-title">Where have you been? <span class="v2-map-count" data-tm-count>0 countries</span></p>
        </div>
      </div>
      <div class="tm-map" data-tm-map aria-label="World map: tap the countries you have been to">
        <p class="tm-loading">Tap the countries you have been to</p>
      </div>
      <div class="v2-map-foot">
        <span class="v2-map-hint" data-tm-status aria-live="polite">No account needed. Your map is a link you can share.</span>
        <a class="v2-btn v2-btn-primary v2-btn-sm" data-tm-open data-cta="home_map" href="<?= e(url('map')) ?>">Save and share</a>
      </div>
    </div>
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
             style="background-image:url('<?= e(abs_url((string) $pl['hero_url'])) ?>')">
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
          <img loading="lazy" src="<?= e(abs_url((string) $c['hero_url'])) ?>" alt="">
          <span class="v2-city-txt">
            <b><?= e((string) $c['name']) ?></b>
            <small><?= e((string) $c['country']) ?><?php if ((int) $c['places'] > 0): ?> · <?= (int) $c['places'] ?> places<?php endif; ?></small>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="v2-section">
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
    <a href="<?= e(url('buddies/cruise')) ?>">Cruise buddies</a>
    <a href="<?= e(url('buddies/backpacking')) ?>">Backpacking</a>
    <a href="<?= e(url('buddies/road-trip')) ?>">Road trips</a>
    <?php foreach (['greece', 'thailand', 'japan', 'italy', 'bali', 'mexico', 'royal-caribbean', 'carnival', 'norwegian'] as $bl): if (!($lp = rmt_buddy_landing($bl))) continue; ?>
      <a href="<?= e(url(rmt_buddy_landing_path($bl))) ?>"><?= e($lp['kind'] === 'cruise' ? $lp['line'] . ' cruises' : $lp['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<script src="<?= e(rmt_asset('assets/js/travel-map.js')) ?>" defer></script>
