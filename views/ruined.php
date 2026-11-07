<?php /** @var array $memberRows @var int $memberTotal @var array $researchRows @var int $researchTotal @var array $dests @var ?array $dest */ ?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if ($dest): ?><a href="<?= e(url('ruined')) ?>">What ruined it</a> / <?= e((string) $dest['name']) ?><?php else: ?>What ruined it<?php endif; ?></p></div>
<div class="wrap">
  <h1 style="margin:8px 0 6px"><?= $dest ? 'What ruined trips to ' . e((string) $dest['name']) : 'What ruined the trip' ?></h1>
  <p class="muted" style="max-width:62ch;margin:0 0 18px">The tourist trap, the surprise fee, the queue nobody mentioned. Warnings from
    travelers come first; below them are the traps our own research turned up, labelled as ours and never passed off as somebody's trip.
    Read them before you go, and add yours when you get back.</p>

  <div class="card" style="margin:0 0 26px"><div class="card-body">
    <?php $askVariant = 'card'; include __DIR__ . '/_ruined_ask.php'; ?>
  </div></div>

  <?php if ($dests && !$dest): ?>
    <p class="hint" style="margin:0 0 14px">By city:
      <?php foreach (array_slice($dests, 0, 14) as $d): ?>
        <a class="chip" href="<?= e(url('ruined?d=' . $d['slug'])) ?>"><?= e((string) $d['name']) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php $shareUrl = url($dest ? 'ruined?d=' . $dest['slug'] : 'ruined');
        $shareText = $dest ? 'What ruined trips to ' . $dest['name'] . '. Read before you go.' : 'What ruined the trip: the things travelers wish somebody had warned them about.';
        $shareLabel = 'Share these warnings';
        include __DIR__ . '/_share.php'; ?>

<?php
/* One card. $r is a row from rmt_reviews_ruined(); $research says which list it is in. */
$ruinedCard = static function (array $r, bool $research): void {
    $href = url(ltrim(rmt_review_path($r), '/'));
    $where = (string) ($r['place_name'] ?: $r['subject_name'] ?: $r['dest_name']); ?>
        <div class="card"><div class="card-body" style="display:flex;flex-direction:column;gap:8px;height:100%">
          <p style="margin:0;font-size:1.08rem;line-height:1.5;font-weight:600"><?= $research ? '' : '“' ?><?= e(mb_strimwidth(trim((string) $r['what_ruined']), 0, 220, '…')) ?><?= $research ? '' : '”' ?></p>
          <p class="hint" style="margin:0">
            <?php if (!$research): ?><span style="color:#b45309" aria-label="<?= (int) $r['rating'] ?> out of 5 stars"><?= str_repeat('★', max(0, min(5, (int) $r['rating']))) ?></span><?php endif; ?>
            <a href="<?= e($href) ?>"><?= e($where) ?></a><?php if ($r['dest_name'] && $where !== $r['dest_name']): ?>, <?= e((string) $r['dest_name']) ?><?php endif; ?>
          </p>
          <p class="hint" style="margin:auto 0 0">
            <?php if ($research): ?><span class="chip">Researched by us</span> · <a href="<?= e($href) ?>">sources and the full write up</a>
            <?php else: ?>@<a href="<?= e(url('u/' . $r['username'])) ?>"><?= e((string) $r['username']) ?></a> · <a href="<?= e($href) ?>">the whole review</a><?php endif; ?>
          </p>
        </div></div>
<?php }; ?>

  <?php if ($dest && rmt_city_scams((string) $dest['slug'])): ?>
    <p class="hint" style="margin:0 0 16px">Looking for known scams? <a href="<?= e(url('d/' . $dest['slug']) . '#scams') ?>">What official travel advice warns about in <?= e((string) $dest['name']) ?></a>, with sources.</p>
  <?php endif; ?>
  <section aria-labelledby="ruined-travelers" style="margin:6px 0 34px">
    <h2 id="ruined-travelers" style="margin:0 0 6px">From travelers<?= $dest ? ' who went to ' . e((string) $dest['name']) : '' ?></h2>
    <?php if (!$memberRows): ?>
      <div class="empty-cta" style="margin:10px 0 0">
        <h3 style="margin-top:0">No traveler warnings<?= $dest ? ' about ' . e((string) $dest['name']) : '' ?> yet</h3>
        <p class="muted" style="margin:0 0 12px">This wall only grows when somebody who went writes one line. If something nearly ruined your trip, yours is the
          first one the next traveler reads.</p>
        <a class="btn btn-accent" href="#ruined-text">Write the first warning</a>
      </div>
    <?php else: ?>
      <p class="hint" style="margin:0 0 10px"><?= (int) $memberTotal ?> <?= $memberTotal === 1 ? 'warning' : 'warnings' ?> from travelers so far.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
        <?php foreach ($memberRows as $r) $ruinedCard($r, false); ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($researchRows): ?>
  <section aria-labelledby="ruined-research" style="padding-bottom:50px">
    <h2 id="ruined-research" style="margin:0 0 6px">From our research<?= $dest ? ' on ' . e((string) $dest['name']) : '' ?></h2>
    <p class="hint" style="margin:0 0 12px;max-width:70ch">Written by the RuinMyTrip team from official and published sources, not from a visit.
      Each links to the write up with its sources. <?= (int) $researchTotal ?> so far. <a href="<?= e(url('editorial-policy')) ?>">How we research</a>.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
      <?php foreach ($researchRows as $r) $ruinedCard($r, true); ?>
    </div>
  </section>
  <?php endif; ?>
</div>
