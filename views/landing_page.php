<?php
/**
 * An editorial landing page ("What can ruin a trip to Paris?", "Hidden costs in Barcelona").
 *
 * Two things keep these from being the thin SEO pages the brief forbids. First, the body is
 * hand-written and gated at 600+ characters before it can be published. Second, the page always
 * carries a LIVE layer underneath — the current traveler warnings on the same theme — so it stays
 * useful between editorial reviews instead of freezing on the day it was written.
 *
 * @var array  $p        the page row (joined with its destination)
 * @var array  $warnings live warnings on this theme
 * @var array  $related  sibling pages for the same destination
 * @var array  $sources
 * @var ?string $category
 * @var ?array $tpl
 */
$isDraft = $p['status'] !== 'published';
?>
<div class="wrap prose" style="max-width:840px">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> /
    <a href="<?= e(url('warning-guides')) ?>">Warning guides</a>
    <?php if (!empty($p['dest_slug'])): ?> / <a href="<?= e(url('d/' . $p['dest_slug'])) ?>"><?= e($p['dest_name']) ?></a><?php endif; ?>
  </p>

  <?php if ($isDraft): ?>
    <div class="callout warn"><b>Draft — moderators only.</b> This page is not published and is not in the sitemap.</div>
  <?php endif; ?>

  <div class="warn-meta" style="margin-bottom:.5rem">
    <span class="trust trust-editorial">Editorial guide</span>
    <?php if ($category): ?>
      <a class="chip chip-cat" href="<?= e(url('warnings/' . $category)) ?>">
        <?= rmt_warning_category_icon($category) ?> <?= e(rmt_warning_category_label($category)) ?>
      </a>
    <?php endif; ?>
    <?php if ($tpl): ?><span class="muted"><?= e($tpl['label']) ?></span><?php endif; ?>
  </div>

  <h1><?= e($p['h1']) ?></h1>

  <?php if (!empty($p['intro'])): ?>
    <p style="font-size:1.12rem;color:var(--muted)"><?= e($p['intro']) ?></p>
  <?php endif; ?>

  <?php if (!empty($p['hero_url'])): ?>
    <img class="article-hero" loading="lazy" decoding="async" width="840" height="420"
         src="<?= e($p['hero_url']) ?>" alt="<?= e((string) $p['dest_name']) ?>">
    <?= rmt_photo_credit_html($p) ?>
  <?php endif; ?>

  <div class="callout">
    <b>What this is.</b> Researched editorial guidance written by the RuinMyTrip team from published sources,
    not a personal trip report. Traveler warnings — first-hand and separately labelled — appear further down
    and update continuously.
  </div>

  <div><?= rmt_rich((string) $p['body']) ?></div>

  <?php if ($sources): ?>
    <div class="sources">
      <b>Sources</b>
      <ol>
        <?php foreach ($sources as $s): ?>
          <li><?php if (!empty($s['url'])): ?><a href="<?= e($s['url']) ?>" rel="nofollow noopener" target="_blank"><?= e($s['title'] ?: $s['url']) ?></a><?php else: ?><?= e($s['title']) ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endif; ?>

  <p class="reviewed-line">
    <?php if (!empty($p['last_reviewed_at'])): ?>
      <span>Last reviewed <?= e(date('F j, Y', strtotime((string) $p['last_reviewed_at']))) ?></span>
    <?php endif; ?>
    <span>Published <?= e(date('F j, Y', strtotime((string) $p['created_at']))) ?></span>
    <?php $outdatedTarget = 'landing_page'; $outdatedId = (int) $p['id']; $outdatedReturn = '/' . $p['slug'];
          include __DIR__ . '/_outdated_button.php'; ?>
  </p>

  <?= rmt_affiliate_block(!empty($p['destination_id']) ? (int) $p['destination_id'] : null, null,
                          'Booking ' . ($p['dest_name'] ?? 'this trip') . '?') ?>

  <!-- The live layer -->
  <section style="margin-top:36px">
    <h2>What travelers are reporting right now</h2>
    <?php if ($warnings): ?>
      <p class="muted">First-hand reports on this theme, reviewed by a moderator before publication. Unverified
        reports are labelled as such.</p>
      <?php foreach ($warnings as $w) { $showDest = empty($p['destination_id']); include __DIR__ . '/_warning_card.php'; } ?>
      <?php if (!empty($p['dest_slug'])): ?>
        <p><a class="btn btn-ghost" href="<?= e(url('d/' . $p['dest_slug'] . '/warnings' . ($category ? '?category=' . $category : ''))) ?>">
          All <?= e($p['dest_name']) ?> warnings</a></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-cta">
        <h3>No traveler reports on this yet</h3>
        <p class="muted">This guide is researched, but nobody has filed a first-hand report on it. If it happened
          to you, that is the part no amount of research can replace.</p>
        <a class="btn btn-accent" style="margin-top:12px"
           href="<?= e(url('warning/new' . (!empty($p['destination_id']) ? '?destination=' . (int) $p['destination_id'] : '') . ($category ? (!empty($p['destination_id']) ? '&' : '?') . 'category=' . $category : ''))) ?>">
          Share a warning</a>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($related): ?>
    <section style="margin-top:34px">
      <h2>More on <?= e((string) $p['dest_name']) ?></h2>
      <div class="grid g-2">
        <?php foreach ($related as $r): ?>
          <a class="cat-tile" href="<?= e(url($r['slug'])) ?>">
            <b><?= e($r['h1']) ?></b>
            <span><?= e(rmt_landing_template_label((string) $r['template'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($p['dest_slug'])): ?>
    <div class="empty-cta" style="margin-top:30px;text-align:left">
      <h3 style="font-size:1.15rem">Going to <?= e($p['dest_name']) ?>?</h3>
      <p class="muted">Read the full risk report, or save the trip and get told if something important changes
        before you go.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-primary" href="<?= e(url('d/' . $p['dest_slug'])) ?>"><?= e($p['dest_name']) ?> risk report</a>
        <a class="btn btn-ghost" href="<?= e(url('alerts?destination=' . $p['dest_slug'])) ?>">Get alerts</a>
      </div>
    </div>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
