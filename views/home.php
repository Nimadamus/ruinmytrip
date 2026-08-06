<?php
/**
 * The homepage. One promise, answered in the first screen.
 *
 * Section order is the reading order a person planning a trip actually needs: what is this,
 * where am I going, what is going wrong right now, what kinds of things go wrong, how does this
 * work, what did travelers just report, and how do I get told before I leave.
 *
 * The social features this site already has (meetups, who's going, top reviewers, collections)
 * are still live and linked from the nav and footer, but they are deliberately not on this page
 * until there is enough activity for them to be worth a first-time visitor's attention.
 *
 * @var array  $dests    popular destinations (featured first)
 * @var array  $topCats  destination_id => [category keys]
 * @var array  $trending timely warnings
 * @var array  $latest   newest approved warnings
 * @var int    $stat_destinations @var int $stat_warnings @var int $stat_covered
 * @var string $homeIntro  owner-editable supporting line
 */
$me = current_user();
?>

<!-- A. HERO -->
<section class="hero">
  <div class="hero-bg" style="background-image:url('<?= e($dests[0]['hero_url'] ?? url('assets/img/og-default.svg')) ?>')"></div>
  <div class="hero-inner">
    <p class="eyebrow" style="color:#7dd3fc">Travel risk reports &amp; traveler warnings</p>
    <h1>Know What Could Ruin Your Trip Before You Book It</h1>
    <p>
      <?= $homeIntro !== '' ? e($homeIntro) : 'Tourist scams. Hidden fees. The neighbourhood that looked central. '
        . 'The month everything is closed. RuinMyTrip collects the practical problems that wreck real trips — '
        . 'researched destination risk reports plus honest warnings from travelers who hit them first.' ?>
    </p>

    <form class="hero-search ac-wrap" action="<?= e(url('search')) ?>" method="get" role="search">
      <input type="search" name="q" autocomplete="off" data-suggest
             placeholder="Where are you going? Paris, Cancun, Tokyo…" aria-label="Check a destination">
      <button class="btn btn-accent" type="submit">Check a Destination</button>
      <div class="ac-list" role="listbox" aria-label="Suggestions"></div>
    </form>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
      <a class="btn btn-primary" href="<?= e(url('warning/new')) ?>">Share a Warning</a>
      <a class="btn btn-ghost" style="color:#fff;border-color:rgba(255,255,255,.4)" href="<?= e(url('explore')) ?>">Browse all destinations</a>
    </div>

    <?php /* Real counts only. A number that would embarrass the site is simply not printed —
             this block never invents one and never shows a zero as a headline stat. */ ?>
    <div class="hero-stats">
      <div><b><?= number_format($stat_destinations) ?></b><span>destinations covered</span></div>
      <?php if ($stat_covered > 0): ?>
        <div><b><?= number_format($stat_covered) ?></b><span>with a full risk report</span></div>
      <?php endif; ?>
      <?php if ($stat_warnings > 0): ?>
        <div><b><?= number_format($stat_warnings) ?></b><span>traveler warnings published</span></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- B. POPULAR DESTINATIONS -->
<section class="wrap" style="margin-top:52px">
  <div class="section-head">
    <div>
      <p class="eyebrow">Start here</p>
      <h2>Popular destinations</h2>
    </div>
    <a href="<?= e(url('explore')) ?>">All destinations →</a>
  </div>
  <div class="grid g-4">
    <?php foreach ($dests as $d): $id = (int) $d['id']; $wc = (int) ($d['warning_count'] ?? 0); ?>
      <article class="card">
        <a href="<?= e(url('d/' . $d['slug'])) ?>">
          <img class="card-media" loading="lazy" decoding="async" width="480" height="300"
               src="<?= e($d['hero_url'] ?: url('assets/img/og-default.svg')) ?>"
               alt="<?= e($d['name'] . ', ' . $d['country']) ?>">
        </a>
        <div class="card-body">
          <h3><a href="<?= e(url('d/' . $d['slug'])) ?>"><?= e($d['name']) ?></a></h3>
          <p class="muted" style="margin:.1rem 0 .5rem;font-size:.88rem"><?= e($d['country']) ?></p>

          <?php if (!empty($d['risk_level'])): $r = (int) $d['risk_level']; ?>
            <p style="margin:0 0 .5rem;font-size:.85rem" class="risk-<?= $r ?>">
              <span class="risk-meter r<?= $r ?>"><i></i><i></i><i></i><i></i></span>
              <b style="margin-left:6px"><?= e(rmt_risk_level_label($r)) ?></b>
            </p>
          <?php endif; ?>

          <?php /* "0 warnings" is not a statistic worth printing — for a destination nobody has
                   reported yet, the honest and more useful line is the invitation. */ ?>
          <p style="margin:0 0 .5rem;font-size:.88rem">
            <?php if ($wc > 0): ?>
              <b><?= number_format($wc) ?></b> traveler <?= $wc === 1 ? 'warning' : 'warnings' ?>
            <?php elseif ((int) ($d['section_count'] ?? 0) > 0): ?>
              <span class="muted">Researched risk report · no traveler reports yet</span>
            <?php else: ?>
              <span class="muted">Be the first to warn other travelers</span>
            <?php endif; ?>
          </p>

          <?php if (!empty($topCats[$id])): ?>
            <div class="tag-row">
              <?php foreach ($topCats[$id] as $ck): ?>
                <span class="chip chip-cat"><?= rmt_warning_category_icon($ck) ?> <?= e(rmt_warning_category_label($ck)) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <p class="muted" style="font-size:.88rem;margin:.5rem 0 0">
            <?= e(mb_strimwidth(strip_tags((string) ($d['risk_summary'] ?: $d['summary'])), 0, 120, '…')) ?>
          </p>
          <p style="margin:.7rem 0 0"><a href="<?= e(url('d/' . $d['slug'])) ?>">See the risk report →</a></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- C. TRENDING TRAVEL WARNINGS -->
<section class="wrap" style="margin-top:52px">
  <div class="section-head">
    <div>
      <p class="eyebrow">Right now</p>
      <h2>Trending travel warnings</h2>
    </div>
    <a href="<?= e(url('warnings')) ?>">All warnings →</a>
  </div>
  <?php if ($trending): ?>
    <div class="grid g-2">
      <div>
        <?php foreach (array_slice($trending, 0, (int) ceil(count($trending) / 2)) as $w) { include __DIR__ . '/_warning_card.php'; } ?>
      </div>
      <div>
        <?php foreach (array_slice($trending, (int) ceil(count($trending) / 2)) as $w) { include __DIR__ . '/_warning_card.php'; } ?>
      </div>
    </div>
  <?php else: ?>
    <?php /* Professional empty state: says what will appear here and invites the first one,
             instead of rendering "0 warnings" as though that were a statistic. */ ?>
    <div class="empty-cta">
      <h3>Nothing is trending yet — and that is worth saying plainly</h3>
      <p class="muted">Timely problems appear here as travelers report them: airport disruption, a new tourist
        tax, restoration scaffolding on the thing you flew to see, a metro line closed all summer.
        In the meantime, every covered destination already has a researched risk report you can read now.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        <a class="btn btn-accent" href="<?= e(url('warning/new')) ?>">Share the first warning</a>
        <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Read a risk report</a>
      </div>
    </div>
  <?php endif; ?>
</section>

<!-- D. WHAT CAN RUIN A TRIP? -->
<section class="wrap" style="margin-top:52px">
  <div class="section-head">
    <div>
      <p class="eyebrow">The ten failure modes</p>
      <h2>What can ruin a trip?</h2>
    </div>
  </div>
  <div class="cat-grid">
    <?php foreach (RMT_WARNING_CATEGORIES as $key => $c): ?>
      <a class="cat-tile" href="<?= e(url('warnings/' . $key)) ?>">
        <span class="ico" aria-hidden="true"><?= $c['icon'] ?></span>
        <b><?= e($c['label']) ?></b>
        <span><?= e($c['blurb']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- E. HOW IT WORKS -->
<section class="wrap" style="margin-top:52px">
  <div class="section-head"><div><p class="eyebrow">How it works</p><h2>Three steps, before you book</h2></div></div>
  <div class="steps">
    <div class="step">
      <h3>Search your destination</h3>
      <p>Every covered destination has a risk report: the scams, the fees, the areas to think twice about,
         the months that go wrong, and what is closed.</p>
    </div>
    <div class="step">
      <h3>Read the current warnings</h3>
      <p>Filter traveler reports by category, severity, season and traveler type. Every report shows when it
         happened, when it was submitted, and whether anyone has verified it.</p>
    </div>
    <div class="step">
      <h3>Plan around the problems</h3>
      <p>Save the trip with your dates and we will tell you if something important changes before you go.</p>
    </div>
  </div>
</section>

<!-- F. LATEST TRAVELER REPORTS -->
<section class="wrap" style="margin-top:52px">
  <div class="section-head">
    <div><p class="eyebrow">From travelers</p><h2>Latest traveler reports</h2></div>
    <a href="<?= e(url('warnings')) ?>">See all →</a>
  </div>
  <?php if ($latest): ?>
    <?php foreach ($latest as $w) { include __DIR__ . '/_warning_card.php'; } ?>
  <?php else: ?>
    <div class="empty-cta">
      <h3>No traveler reports published yet</h3>
      <p class="muted">Every submission is read by a person before it appears, so this list starts empty rather
         than full of noise. If something cost you money or a day of your trip, that is exactly what belongs here.</p>
      <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new')) ?>">Share a warning</a>
    </div>
  <?php endif; ?>
</section>

<!-- G. EMAIL ALERT CTA -->
<section class="wrap">
  <div class="alert-band">
    <p class="eyebrow" style="color:#7dd3fc">Before you go</p>
    <h2>Get told if something changes before your trip</h2>
    <p>Pick a destination and we will email you the warnings serious enough to change your plans —
       a new tourist tax, a strike, a closure, a scam pattern that just started.</p>
    <form class="alert-form" method="post" action="<?= e(url('alerts/subscribe')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="source" value="homepage">
      <label class="skip" for="alert-dest">Destination</label>
      <select id="alert-dest" name="destination" required>
        <option value="">Choose a destination…</option>
        <?php foreach ($dests as $d): ?>
          <option value="<?= e($d['slug']) ?>"><?= e($d['name'] . ', ' . $d['country']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="skip" for="alert-email">Email address</label>
      <input id="alert-email" type="email" name="email" required placeholder="you@example.com"
             value="<?= e($me['email'] ?? '') ?>">
      <button class="btn btn-accent" type="submit">Email me warnings</button>
    </form>
    <p class="alert-note">Weekly at most, and only warnings rated Moderate or worse. One click to stop.
      We confirm your address first and never sell it. <a href="<?= e(url('alerts')) ?>">More options</a> ·
      <a href="<?= e(url('privacy')) ?>">Privacy</a></p>
  </div>
</section>

<!-- Account value proposition: benefits, not "join our community" -->
<section class="wrap" style="margin-bottom:60px">
  <?php if (!$me): ?>
    <div class="empty-cta" style="text-align:left">
      <h2 style="font-size:1.35rem">Save destinations and receive important warnings before your trip.</h2>
      <p class="muted" style="max-width:62ch">A free account gives you a trip watchlist with your travel dates,
        alerts when something serious is reported for where you are going, a preparation checklist built from the
        actual warnings for that destination, and the ability to submit and track your own reports.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">Create a free account</a>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Sign in</a>
      </div>
    </div>
  <?php else: ?>
    <div class="empty-cta" style="text-align:left">
      <h2 style="font-size:1.35rem">Your trips</h2>
      <p class="muted">Check what has changed for the destinations you are watching.</p>
      <a class="btn btn-primary" style="margin-top:12px" href="<?= e(url('dashboard')) ?>">Open your dashboard</a>
    </div>
  <?php endif; ?>
</section>
