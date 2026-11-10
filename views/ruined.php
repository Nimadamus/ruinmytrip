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

  <p class="hint" style="margin:0 0 10px">Chasing money back for a cancelled flight, a lost bag or a holiday that was not as sold?
    <a href="<?= e(url('blog/trip-went-wrong-what-you-can-claim')) ?>">What you can claim, and from whom</a>.</p>
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

  <?php /* Every city with a sourced scam section, so the warnings page is the hub for them (2026-11-07). */
    if (!$dest && function_exists('rmt_city_scams_all') && ($rmtScamAll = rmt_city_scams_all())):
      $rmtScamNames = [];
      foreach ($dests as $dd) if (isset($rmtScamAll[$dd['slug']])) $rmtScamNames[$dd['slug']] = (string) $dd['name'];
      asort($rmtScamNames); ?>
  <section aria-labelledby="ruined-scams" style="margin:0 0 34px">
    <h2 id="ruined-scams" style="margin:0 0 6px">Known scams by city</h2>
    <p class="hint" style="margin:0 0 12px;max-width:70ch">What official travel advice warns visitors about, city by city, each point linked to the government or official page that says it.</p>
    <div class="chips" style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($rmtScamNames as $sl => $nm): ?>
        <a class="chip" href="<?= e(url('d/' . $sl) . '#scams') ?>"><?= e($nm) ?> <span class="muted">(<?= count($rmtScamAll[$sl]['items']) ?>)</span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php $rmtTypeRows = function_exists('rmt_scam_type_counts') ? rmt_scam_type_counts() : []; if ($rmtTypeRows): ?>
  <section aria-labelledby="ruined-types" id="scam-types" style="margin:0 0 34px">
    <h2 id="ruined-types" style="margin:0 0 6px">The scams official advice warns about most</h2>
    <p class="hint" style="margin:0 0 12px;max-width:70ch">How many of these <?= count($rmtScamNames) ?> cities have official advice naming each kind of scam. We count a city once per kind, only where the UK, Canadian or a city or official page says it for that city. It shows what the advice talks about, not how often scams happen.</p>
    <div class="tt-scroll"><table style="width:100%;border-collapse:collapse">
      <thead><tr><th style="text-align:left">Scam</th><th style="text-align:right">Cities</th><th style="text-align:left">Where</th></tr></thead>
      <tbody>
        <?php foreach ($rmtTypeRows as $tr): ?>
          <tr style="border-top:1px solid var(--line, #ddd)"><td style="padding:8px 8px 8px 0;vertical-align:top"><?= e($tr['label']) ?></td>
            <td style="padding:8px;text-align:right;vertical-align:top"><?= count($tr['cities']) ?></td>
            <td style="padding:8px 0;vertical-align:top"><?php $links = []; foreach ($tr['cities'] as $sl) if (isset($rmtScamNames[$sl])) $links[] = '<a href="' . e(url('d/' . $sl) . '#scams') . '">' . e($rmtScamNames[$sl]) . '</a>'; sort($links); echo implode(', ', $links); ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
  <?php endif; ?>

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
