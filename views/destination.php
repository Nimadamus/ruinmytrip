<?php
/**
 * The destination risk report — the page this whole site exists to produce.
 *
 * Reading order is deliberate and is the answer to "what could ruin my trip here?":
 *   1. the overall risk read and the summary
 *   2. is it worth visiting at all
 *   3. the reviewed risk sections, one per failure mode
 *   4. what travelers have actually reported, filterable
 *   5. FAQ, related destinations, submit CTA
 *   6. only then the community layer this site already had (trips, reviews, meetups, who's going),
 *      which is kept and linked but is no longer what the page is for
 *
 * @var array $d @var array $sections @var array $faqs @var array $catCounts @var int $warnCount
 * @var array $warnings @var array $newest @var array $pages @var array $related
 * @var bool  $saved @var bool $following @var bool $watching @var int $wantCount
 * @var array $trips @var int $tripCount @var array $reviews @var array $editorial @var array $tips
 * @var array $guides @var array $meetups @var array $going @var array $avg @var array $avgByCategory
 * @var ?array $me @var array $photos @var int $photoCount
 */
$destUrl = url('d/' . $d['slug']);
$risk = (int) ($d['risk_level'] ?? 0);
$defs = rmt_risk_section_defs();
?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore')) ?>">Destinations</a> / <?= e($d['name']) ?></p>

  <div class="dest-hero">
    <img src="<?= e(abs_url($d['hero_url'])) ?>" alt="<?= e($d['name'] . ', ' . $d['country']) ?>" width="1160" height="340">
    <div class="overlay">
      <div>
        <?php if (!empty($d['category'])): ?><span class="chip"><?= e($d['category']) ?></span><?php endif; ?>
        <h1>What could ruin a trip to <?= e($d['name']) ?>?</h1>
        <p style="color:#e8eef5;margin:.3rem 0 0;max-width:62ch"><?= e($d['summary']) ?></p>
      </div>
    </div>
  </div>
  <?= rmt_photo_credit_html($d) ?>

  <!-- OVERALL TRIP-RISK SUMMARY -->
  <div class="risk-summary">
    <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start;justify-content:space-between">
      <div style="flex:1;min-width:280px">
        <p class="eyebrow">Overall trip risk</p>
        <?php if ($risk): ?>
          <h2 class="risk-<?= $risk ?>" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span class="risk-meter r<?= $risk ?>"><i></i><i></i><i></i><i></i></span>
            <?= e(rmt_risk_level_label($risk)) ?>
          </h2>
          <p class="muted" style="margin:.1rem 0 .6rem;font-size:.9rem"><?= e(RMT_RISK_LEVELS[$risk]['desc']) ?></p>
        <?php else: ?>
          <h2>Not yet rated</h2>
          <p class="muted" style="margin:.1rem 0 .6rem;font-size:.9rem">
            We only publish a risk level once the report has been researched and reviewed. Traveler warnings
            below still apply.</p>
        <?php endif; ?>

        <?php if (!empty($d['risk_summary'])): ?>
          <div class="prose" style="max-width:none"><?= rmt_rich((string) $d['risk_summary']) ?></div>
        <?php endif; ?>

        <?php if (!empty($d['best_months']) || !empty($d['worst_months'])): ?>
          <p style="margin:.8rem 0 0;font-size:.92rem">
            <?php if (!empty($d['best_months'])): ?><b>Easiest months:</b> <?= e($d['best_months']) ?><?php endif; ?>
            <?php if (!empty($d['best_months']) && !empty($d['worst_months'])): ?> · <?php endif; ?>
            <?php if (!empty($d['worst_months'])): ?><b>Hardest months:</b> <?= e($d['worst_months']) ?><?php endif; ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($d['last_reviewed_at'])): ?>
          <p class="reviewed-line">
            <span>Last reviewed <?= e(date('F j, Y', strtotime((string) $d['last_reviewed_at']))) ?></span>
            <?php $outdatedTarget = 'destination'; $outdatedId = (int) $d['id']; $outdatedReturn = $destUrl;
                  include __DIR__ . '/_outdated_button.php'; ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Save / follow / watch -->
      <div style="min-width:250px">
        <?php if ($me): ?>
          <form method="post" action="<?= e(url('watchlist/add')) ?>" style="background:#f8fafc;border:1px solid var(--line);border-radius:14px;padding:14px">
            <?= csrf_field() ?>
            <input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="return" value="<?= e($destUrl) ?>">
            <p style="margin:0 0 8px;font-weight:700;font-size:.95rem">
              <?= $watching ? 'This trip is on your watchlist' : 'Going here? Save the trip' ?>
            </p>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <label class="skip" for="wd-from">Departure date</label>
              <input id="wd-from" type="date" name="date_from" style="flex:1;min-width:130px;padding:.4rem .6rem;border:1px solid var(--line);border-radius:8px">
              <label class="skip" for="wd-to">Return date</label>
              <input id="wd-to" type="date" name="date_to" style="flex:1;min-width:130px;padding:.4rem .6rem;border:1px solid var(--line);border-radius:8px">
            </div>
            <button class="btn btn-primary btn-block btn-sm" style="margin-top:8px" type="submit">
              <?= $watching ? 'Update my trip' : 'Save this trip' ?>
            </button>
            <p class="hint" style="margin-top:6px">We will tell you if something serious is reported before you go.</p>
          </form>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <form method="post" action="<?= e(url('destination/follow')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
              <input type="hidden" name="return" value="<?= e($destUrl) ?>">
              <button class="btn <?= $following ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $following ? 'Following' : 'Follow updates' ?></button>
            </form>
            <form method="post" action="<?= e(url('destination/save')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
              <input type="hidden" name="return" value="<?= e($destUrl) ?>">
              <button class="btn <?= $saved ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $saved ? '★ Saved' : '☆ Save' ?></button>
            </form>
          </div>
        <?php else: ?>
          <div style="background:#f8fafc;border:1px solid var(--line);border-radius:14px;padding:16px">
            <p style="margin:0 0 6px;font-weight:700">Going to <?= e($d['name']) ?>?</p>
            <p class="hint" style="margin:0 0 10px">Save the destination and receive important warnings before your trip.</p>
            <a class="btn btn-primary btn-block btn-sm" href="<?= e(url('register')) ?>">Create a free account</a>
            <p class="hint" style="margin-top:8px;text-align:center">
              or <a href="<?= e(url('alerts?destination=' . $d['slug'])) ?>">just get email alerts</a>
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Category counts: what is actually reported here -->
  <?php if ($catCounts): ?>
    <div class="filter-chips" style="margin-bottom:6px">
      <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): if (empty($catCounts[$k])) continue; ?>
        <a href="<?= e($destUrl . '/warnings?category=' . $k) ?>">
          <?= $c['icon'] ?> <?= e($c['label']) ?> <b><?= (int) $catCounts[$k]['c'] ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Section nav -->
  <?php if ($sections || $faqs): ?>
    <nav class="risk-nav" aria-label="Sections of this report">
      <?php foreach ($sections as $key => $s): ?>
        <a href="#s-<?= e($key) ?>"><?= e($s['heading']) ?></a>
      <?php endforeach; ?>
      <?php if ($warnCount): ?><a href="#traveler-warnings">Traveler warnings</a><?php endif; ?>
      <?php if ($faqs): ?><a href="#faq">FAQ</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<div class="wrap" style="display:grid;grid-template-columns:minmax(0,1fr);gap:0">

  <!-- THE RISK SECTIONS -->
  <?php if ($sections): ?>
    <?php foreach ($sections as $key => $s):
        $type = (string) ($s['content_type'] ?: ($defs[$key]['type'] ?? 'editorial'));
        $cat  = rmt_section_to_category($key);
        $srcs = rmt_sources($s['sources_json'] ?? null);
    ?>
      <section class="risk-section" id="s-<?= e($key) ?>">
        <h2>
          <?= e($s['heading']) ?>
          <span class="trust trust-<?= e($type) ?>"><?= e(['fact' => 'Checked facts', 'editorial' => 'Our guidance', 'alert' => 'Time-sensitive'][$type] ?? 'Our guidance') ?></span>
          <?php if (!empty($s['severity'])): ?>
            <span class="sev <?= e(rmt_severity_class((int) $s['severity'])) ?>"><?= e(rmt_severity_label((int) $s['severity'])) ?></span>
          <?php endif; ?>
        </h2>
        <div class="prose" style="max-width:none"><?= rmt_rich((string) $s['body']) ?></div>

        <?php if ($srcs): ?>
          <div class="sources">
            <b>Sources</b>
            <ol>
              <?php foreach ($srcs as $src): ?>
                <li><?php if (!empty($src['url'])): ?><a href="<?= e($src['url']) ?>" rel="nofollow noopener" target="_blank"><?= e($src['title'] ?: $src['url']) ?></a><?php else: ?><?= e($src['title']) ?><?php endif; ?></li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endif; ?>

        <p class="reviewed-line">
          <?php if (!empty($s['last_reviewed_at'])): ?>
            <span>Last reviewed <?= e(date('F j, Y', strtotime((string) $s['last_reviewed_at']))) ?></span>
          <?php endif; ?>
          <?php if ($cat && !empty($catCounts[$cat])): ?>
            <a href="<?= e($destUrl . '/warnings?category=' . $cat) ?>"><?= (int) $catCounts[$cat]['c'] ?> traveler <?= $catCounts[$cat]['c'] === 1 ? 'report' : 'reports' ?> on this →</a>
          <?php elseif ($cat): ?>
            <a href="<?= e(url('warning/new?destination=' . (int) $d['id'] . '&category=' . $cat)) ?>">Report something in this category →</a>
          <?php endif; ?>
          <?php $outdatedTarget = 'risk_section'; $outdatedId = (int) $s['id']; $outdatedReturn = $destUrl . '#s-' . $key;
                include __DIR__ . '/_outdated_button.php'; ?>
        </p>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-cta">
      <h2 style="font-size:1.3rem">The risk report for <?= e($d['name']) ?> is not written yet</h2>
      <p class="muted">We publish a destination report only once it has been researched and reviewed — an
        auto-generated page restating a database row would be worse than nothing. Traveler warnings for
        <?= e($d['name']) ?> still appear below as soon as they are submitted and approved.</p>
      <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new?destination=' . (int) $d['id'])) ?>">Share what you learned here</a>
    </div>
  <?php endif; ?>

  <!-- WHAT TRAVELERS REPORTED -->
  <section id="traveler-warnings" style="margin-top:30px;scroll-margin-top:110px">
    <div class="section-head">
      <div>
        <p class="eyebrow">First-hand</p>
        <h2>Traveler warnings <?php if ($warnCount): ?><span class="muted" style="font-weight:600;font-size:1rem">(<?= number_format($warnCount) ?>)</span><?php endif; ?></h2>
      </div>
      <?php if ($warnCount > count($warnings)): ?>
        <a href="<?= e($destUrl . '/warnings') ?>">Filter all <?= number_format($warnCount) ?> →</a>
      <?php endif; ?>
    </div>

    <?php if ($warnings): ?>
      <p class="muted" style="margin:-10px 0 16px;font-size:.9rem">
        These are first-hand accounts from travelers, reviewed by a moderator before publication.
        Reports marked <span class="trust trust-unverified">Unverified</span> have not been independently
        confirmed. <a href="<?= e($destUrl . '/warnings') ?>">Filter by category, severity, season or traveler type →</a>
      </p>
      <?php foreach ($warnings as $w) { $showDest = false; include __DIR__ . '/_warning_card.php'; } ?>
      <p><a class="btn btn-ghost" href="<?= e($destUrl . '/warnings') ?>">See every <?= e($d['name']) ?> warning</a></p>
    <?php else: ?>
      <div class="empty-cta">
        <h3>No traveler warnings for <?= e($d['name']) ?> yet</h3>
        <p class="muted">Nobody has filed one here. That is not the same as "nothing goes wrong" — it means we
          have not heard about it. If something cost you money or a day of your trip, write it down while it is fresh.</p>
        <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new?destination=' . (int) $d['id'])) ?>">Share a warning</a>
      </div>
    <?php endif; ?>

    <div style="margin:18px 0">
      <a class="btn btn-accent" href="<?= e(url('warning/new?destination=' . (int) $d['id'])) ?>">Submit a warning for <?= e($d['name']) ?></a>
    </div>
  </section>

  <?= rmt_affiliate_block((int) $d['id'], null, 'Booking ' . $d['name'] . '?') ?>

  <!-- FAQ -->
  <?php if ($faqs): ?>
    <section class="risk-section" id="faq" style="margin-top:24px">
      <h2>Frequently asked questions</h2>
      <?php foreach ($faqs as $f): ?>
        <details style="border-bottom:1px solid var(--line);padding:.7rem 0">
          <summary style="cursor:pointer;font-weight:700"><?= e($f['question']) ?></summary>
          <div class="prose" style="max-width:none;margin-top:.5rem"><?= rmt_rich((string) $f['answer']) ?></div>
        </details>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <!-- Guide pages for this destination -->
  <?php if ($pages): ?>
    <section style="margin-top:24px">
      <div class="section-head"><div><p class="eyebrow">Go deeper</p><h2><?= e($d['name']) ?> warning guides</h2></div></div>
      <div class="grid g-3">
        <?php foreach ($pages as $p): ?>
          <a class="cat-tile" href="<?= e(url($p['slug'])) ?>">
            <b><?= e($p['h1']) ?></b>
            <span><?= e(rmt_landing_template_label((string) $p['template'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Related destinations -->
  <?php if ($related): ?>
    <section style="margin-top:34px">
      <div class="section-head"><div><p class="eyebrow">Nearby</p><h2>Related destination guides</h2></div></div>
      <div class="grid g-3">
        <?php foreach ($related as $r): ?>
          <article class="card">
            <a href="<?= e(url('d/' . $r['slug'])) ?>">
              <img class="card-media" loading="lazy" decoding="async" width="380" height="238"
                   src="<?= e($r['hero_url'] ?: url('assets/img/og-default.svg')) ?>" alt="<?= e($r['name']) ?>">
            </a>
            <div class="card-body">
              <h3><a href="<?= e(url('d/' . $r['slug'])) ?>"><?= e($r['name']) ?></a></h3>
              <p class="muted" style="font-size:.86rem;margin:0"><?= e($r['country']) ?><?php if ((int) $r['sections'] > 0): ?> · full risk report<?php endif; ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ======================================================================
       The community layer this site already had. Kept, still linked, and
       deliberately below the risk report rather than removed.
       ====================================================================== -->
  <?php $hasCommunity = $tips || $editorial || $reviews || $trips || $guides || $photos || $meetups || $going; ?>
  <?php if ($hasCommunity): ?>
    <hr style="border:0;border-top:1px solid var(--line);margin:44px 0 26px">
    <section>
      <div class="section-head"><div><p class="eyebrow">Also from travelers</p><h2>Reviews, stories and photos</h2></div></div>

      <?php if ($tips): ?>
        <div class="card" style="margin-bottom:16px"><div class="card-body">
          <h3 style="font-size:1.05rem">Practical tips <?= rmt_editorial_badge() ?></h3>
          <ul class="tips-list"><?php foreach ($tips as $t): ?><li><?= e($t['body']) ?></li><?php endforeach; ?></ul>
        </div></div>
      <?php endif; ?>

      <?php if ($avg['c'] > 0): ?>
        <div class="card" style="margin-bottom:16px"><div class="card-body">
          <div class="rating-split">
            <div class="rs-item"><p class="rs-label">Traveler rating</p>
              <p class="rs-value"><span class="stars"><?= stars((int) round((float) $avg['a'])) ?></span> <?= e($avg['a']) ?>/5 <span class="muted">from <?= (int) $avg['c'] ?></span></p></div>
            <?php if ($avg['safety_c'] > 0): ?>
              <div class="rs-item"><p class="rs-label">Safety</p><p class="rs-value"><?= e($avg['safety_a']) ?>/5 <span class="muted">from <?= (int) $avg['safety_c'] ?></span></p></div>
            <?php endif; ?>
            <?php if ($avg['value_c'] > 0): ?>
              <div class="rs-item"><p class="rs-label">Value</p><p class="rs-value"><?= e($avg['value_a']) ?>/5 <span class="muted">from <?= (int) $avg['value_c'] ?></span></p></div>
            <?php endif; ?>
          </div>
          <?php if ($avgByCategory): ?>
            <p class="muted" style="margin:.6rem 0 0;font-size:.88rem">
              <?php foreach ($avgByCategory as $cat): ?>
                <span class="chip chip-cat"><?= e(ucfirst((string) $cat['subject_type'])) ?> <?= e($cat['a']) ?>/5 (<?= (int) $cat['c'] ?>)</span>
              <?php endforeach; ?>
            </p>
          <?php endif; ?>
        </div></div>
      <?php endif; ?>

      <?php foreach ($editorial as $r): ?>
        <div class="card ed-panel" style="margin-bottom:12px"><div class="card-body">
          <?= rmt_editorial_badge('review') ?>
          <h3 style="font-size:1.05rem;margin-top:.4rem"><a href="<?= e(url(ltrim(rmt_review_path($r), '/'))) ?>"><?= e($r['title']) ?></a></h3>
          <p class="muted" style="margin:0"><span class="stars"><?= stars((int) $r['rating']) ?></span></p>
          <p style="margin:.4rem 0 0"><?= e(mb_strimwidth(strip_tags((string) $r['body']), 0, 260, '…')) ?></p>
          <p class="ed-note"><?= e(rmt_editorial_disclosure()) ?></p>
        </div></div>
      <?php endforeach; ?>

      <?php if ($reviews): ?>
        <div class="grid g-2" style="margin-bottom:16px">
          <?php foreach ($reviews as $r): ?>
            <div class="card"><div class="card-body">
              <h3 style="font-size:1.02rem"><a href="<?= e(url(ltrim(rmt_review_path($r), '/'))) ?>"><?= e($r['title']) ?></a></h3>
              <p class="muted" style="margin:0"><span class="stars"><?= stars((int) $r['rating']) ?></span>
                · @<?= e($r['author']['username'] ?? '') ?> · <?= e(ago((string) $r['created_at'])) ?></p>
              <p style="margin:.4rem 0 0"><?= e(mb_strimwidth(strip_tags((string) $r['body']), 0, 180, '…')) ?></p>
            </div></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($photos): ?>
        <div class="grid g-4" style="margin-bottom:16px">
          <?php foreach (array_slice($photos, 0, 8) as $ph): ?>
            <img class="card-media" style="border-radius:12px" loading="lazy" decoding="async" width="280" height="175"
                 src="<?= e($ph['url']) ?>" alt="<?= e($ph['caption'] ?: ('Traveler photo of ' . $d['name'])) ?>">
          <?php endforeach; ?>
        </div>
        <?php if ($photoCount > 8): ?><p><a href="<?= e($destUrl . '/photos') ?>">All <?= (int) $photoCount ?> traveler photos →</a></p><?php endif; ?>
      <?php endif; ?>

      <div class="filter-chips" style="margin-top:14px">
        <?php if ($tripCount): ?><a href="<?= e(url('explore')) ?>"><?= (int) $tripCount ?> trip stories</a><?php endif; ?>
        <?php foreach ($guides as $g): ?><a href="<?= e(url('g/' . $g['slug'])) ?>">Guide: <?= e($g['title']) ?></a><?php endforeach; ?>
        <?php if ($meetups): ?><a href="<?= e(url('meetups')) ?>"><?= count($meetups) ?> meetups</a><?php endif; ?>
        <?php if ($going): ?><a href="<?= e(url('going')) ?>"><?= count($going) ?> travelers going</a><?php endif; ?>
        <a href="<?= e(url('review/new?destination=' . (int) $d['id'])) ?>">Write a review</a>
      </div>
    </section>
  <?php endif; ?>

  <div style="height:50px"></div>
</div>
