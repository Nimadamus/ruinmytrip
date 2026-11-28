<?php
/**
 * The redesigned city page (app/design_v2.php, surface "city"). Same controller, title,
 * description, canonical and structured data as views/destination.php. A photograph across the
 * page, the city's place categories as chips, the actions a visitor can take, the strongest places,
 * then everything the current page carries (views/destination.php, without its own hero).
 *
 * @var array $d @var ?array $me @var int $placeCount @var array $categoryPages @var list<array> $cityTop
 */
$heroSet = rmt_media_srcset($d['hero_url'] ?? null);
?>
<section class="v2-city-hero"<?= empty($d['hero_url']) ? ' style="min-height:280px;background:linear-gradient(135deg,#0b1f2a,#0f766e)"' : '' ?>>
  <?php if (!empty($d['hero_url'])): ?><img src="<?= e(abs_url($d['hero_url'])) ?>"<?php if ($heroSet !== ''): ?> srcset="<?= e($heroSet) ?>" sizes="100vw"<?php endif; ?> fetchpriority="high" alt="<?= e($d['name'] . ', ' . $d['country']) ?>"><?php endif; ?>
  <div class="v2-city-hero-in wrap">
    <p class="v2-crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Explore</a> / <a href="<?= e(url(rmt_country_path((string) $d['country']))) ?>"><?= e($d['country']) ?></a></p>
    <h1 class="v2-city-name"><?= e($d['name']) ?><span>, <?= e($d['country']) ?></span></h1>
    <p class="v2-city-sum"><?= e($d['summary']) ?></p>
    <div class="v2-city-acts">
      <?php if (rmt_phase1_on()): /* Phase 1: the checklist and the trap report lead; social doors only where switched on */ ?>
      <form method="post" action="<?= e(url('checklist/save')) ?>" class="p1-inline" data-p1-save data-dest="<?= e($d['name']) ?>" data-full="1"><?= csrf_field() ?>
        <input type="hidden" name="dest" value="<?= e($d['slug']) ?>"><input type="hidden" name="key" value="*">
        <button class="v2-btn v2-btn-primary p1-save-full" type="submit" data-cta="city_save_full">Save Full City Checklist</button>
      </form>
      <a class="v2-btn v2-btn-glass" data-cta="city_report" href="#report">Report a trap</a>
      <?php if (rmt_social_on('going', (string) $d['slug'])): ?>
      <a class="v2-btn v2-btn-glass" data-cta="cta_travelers" href="<?= e(url('d/' . $d['slug'] . '/travelers')) ?>">Who is going</a>
      <?php endif; ?>
      <?php else: ?>
      <a class="v2-btn v2-btn-accent" data-cta="city_rate" href="<?= e(url('review/new?destination=' . (int) $d['id'] . '&src=destination')) ?>">Rate a place in <?= e($d['name']) ?></a>
      <a class="v2-btn v2-btn-glass" data-cta="cta_dates" href="<?= e($me ? url('trip/new?destination_id=' . (int) $d['id']) : url('plan?cta=cta_dates&d=' . rawurlencode($d['slug']))) ?>">Post your dates</a>
      <a class="v2-btn v2-btn-glass" data-cta="cta_travelers" href="<?= e(url('d/' . $d['slug'] . '/travelers')) ?>">Who is going</a>
      <?php $beenCode = rmt_map_country_code($d['country'] ?? null);
            if (!$me && $beenCode !== null): /* see views/destination.php: no account, the map opens with this country in */ ?>
        <a class="v2-btn v2-btn-glass" rel="nofollow" data-cta="dest_been_map" href="<?= e(url('map?c=' . $beenCode)) ?>">Been to <?= e($d['country']) ?>? Color it in</a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<div class="wrap"><?= rmt_photo_credit_html($d) ?></div>

<div class="wrap v2-city-body">
  <?php if ($placeCount > 0 || $categoryPages): ?>
    <nav class="v2-chips v2-city-chips" aria-label="<?= e($d['name']) ?> by category">
      <a class="v2-chip on" href="<?= e(url('d/' . $d['slug'] . '/places')) ?>">All places<?php if ($placeCount > 0): ?> <span><?= (int) $placeCount ?></span><?php endif; ?></a>
      <?php foreach ($categoryPages as $cp): ?>
        <a class="v2-chip" href="<?= e(url('d/' . $d['slug'] . '/' . $cp['slug'])) ?>"><?= e(ucfirst(preg_replace('/ in .*$/', '', (string) $cp['label']))) ?> <span><?= (int) $cp['n'] ?></span></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if (!empty($cityTop)) include __DIR__ . '/_city_top_places.php'; ?>
</div>
<?php $v2Body = true; include __DIR__ . '/destination.php'; ?>
