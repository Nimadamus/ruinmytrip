<?php
/**
 * The warnings page for one city, or for one kind of warning in it (app/hubs.php).
 *
 * Everything on it is something an official travel advisory says, in plain words, with the
 * sentence it came from and a link to the page. Nothing here calls a city safe or unsafe, and no
 * business is named: it is what to watch for and how to avoid it.
 *
 * @var array   $w          rmt_warnings_city()
 * @var array   $d          the destination
 * @var ?string $type       a warning type key when this is a one-kind page
 * @var array   $wm         title, h1, description (not $meta: view() uses that name)
 * @var array   $items      the warnings shown
 * @var int     $partners   travelers looking for company there in the next 30 days
 * @var bool    $cityListed false while the city itself is below the content threshold
 */
$city = (string) $d['name'];
$cityUrl = url('d/' . $d['slug']);
$warnUrl = url('warnings/' . $d['slug']);
$checked = $w['checked'] !== '' ? date('j M Y', strtotime($w['checked']) ?: time()) : '';
$typeShort = static fn(string $t): string => trim((string) preg_replace('/:.*$/', '', (string) (RMT_SCAM_TYPES[$t] ?? $t)));
?>
<div class="wrap" style="max-width:820px;padding-bottom:50px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('ruined')) ?>">Travel warnings</a> /
    <?php if ($type !== null): ?><a href="<?= e($warnUrl) ?>"><?= e($city) ?></a> / <?= e($typeShort($type)) ?><?php else: ?><?= e($city) ?><?php endif; ?></p>

  <h1 style="margin:8px 0 6px"><?= e($wm['h1']) ?></h1>
  <p class="muted" style="max-width:66ch;margin:0 0 6px">
    <?= count($items) ?> <?= count($items) === 1 ? 'thing' : 'things' ?> official travel advice warns visitors to <?= e($city) ?> about<?= $type !== null ? ' under ' . e(lcfirst($typeShort($type))) : '' ?>, in plain words.
    Each one quotes the advisory it comes from and links to it<?= $checked !== '' ? '. Last checked ' . e($checked) : '' ?>.
  </p>
  <p class="hint" style="margin:0 0 18px"><?= rmt_editorial_badge() ?> Our own research, not somebody's trip. We do not rate a city safe or unsafe: these are the specific things to watch for.</p>

  <?php if ($type === null && count($w['types']) > 1): ?>
    <p class="hint" style="margin:0 0 16px">In this list:
      <?php foreach ($w['types'] as $t => $n): ?>
        <?php if (in_array($t, $w['type_pages'], true)): ?>
          <a class="chip" href="<?= e($warnUrl . '/' . RMT_WARNING_TYPE_SLUGS[$t]) ?>"><?= e($typeShort($t)) ?> (<?= (int) $n ?>)</a>
        <?php else: ?>
          <span class="chip chip-soft"><?= e($typeShort($t)) ?> (<?= (int) $n ?>)</span>
        <?php endif; ?>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php foreach ($items as $i => $it): ?>
    <div class="card" style="margin:0 0 14px"><div class="card-body">
      <h2 class="h-card" style="margin:0 0 6px"><?= (int) ($i + 1) ?>. <?= e($it['name']) ?></h2>
      <p style="margin:0 0 8px"><?= e($it['what']) ?></p>
      <?php if ($it['avoid'] !== ''): ?><p style="margin:0 0 10px"><b>How to avoid it:</b> <?= e($it['avoid']) ?></p><?php endif; ?>
      <?php foreach ($it['sources'] as $s): ?>
        <blockquote style="margin:0 0 8px;padding:6px 12px;border-left:3px solid #cbd5e1" cite="<?= e($s['url']) ?>">
          <span class="muted">&ldquo;<?= e($s['quote']) ?>&rdquo;</span>
          <span class="hint" style="display:block"><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['publisher']) ?></a></span>
        </blockquote>
      <?php endforeach; ?>
      <p class="hint" style="margin:0">
        <?php foreach ($it['types'] as $t): ?><span class="chip chip-soft"><?= e($typeShort($t)) ?></span> <?php endforeach; ?>
        <?php if ($it['checked'] !== ''): ?>Checked <?= e(date('j M Y', strtotime($it['checked']) ?: time())) ?><?php endif; ?>
      </p>
    </div></div>
  <?php endforeach; ?>

  <?php if ($w['official_links']): ?>
    <p class="hint" style="margin:6px 0 20px">Read the full official advice:
      <?php foreach ($w['official_links'] as $i => $l): ?><?= $i ? ', ' : '' ?><a href="<?= e($l['url']) ?>" rel="noopener" target="_blank"><?= e($l['label'] !== '' ? $l['label'] : (string) parse_url($l['url'], PHP_URL_HOST)) ?></a><?php endforeach; ?>.
    </p>
  <?php endif; ?>

  <?php $bybTax = rmt_tt_key_for_dest((string) $d['slug']); $bybTaxCity = $bybTax !== null ? rmt_tt_cities()[$bybTax] : null; ?>
  <?php if ($bybTaxCity !== null): ?>
    <p class="hint" style="margin:0 0 16px"><b>Tourist tax in <?= e($city) ?>:</b> <?= e((string) $bybTaxCity['rate']) ?>.
      <a href="<?= e(url('blog/' . $bybTaxCity['note'])) ?>">How it works</a> &middot;
      <a href="<?= e(url('tourist-tax-calculator?city=' . rawurlencode($bybTax))) ?>">Work out yours</a></p>
  <?php endif; ?>

  <?php if ($type === null && ($byb = rmt_byb((string) $d['slug'])) !== null): $bybCity = $city; include __DIR__ . '/_byb_checklist.php'; endif; ?>

  <?php $clDest = (string) $d['slug']; $clName = $city; include __DIR__ . '/_checklist_cta.php'; ?>

  <?php $story = ['topic' => 'warnings:' . (string) $d['slug'], 'name' => $city, 'return' => '/warnings/' . (string) $d['slug'] . '#story'];
        include __DIR__ . '/_story_form.php'; ?>

  <?php /* The way from reading about what goes wrong to the people going. The count is real or it
           is not shown: with nobody there yet the invitation is to be the first, said plainly. */ ?>
  <?php $byg = ['topic' => 'scams', 'city' => (string) $d['slug'], 'name' => $city, 'source' => 'warnings',
                'return' => '/warnings/' . (string) $d['slug'] . '#byg'];
        include __DIR__ . '/_byg_form.php'; ?>

  <div class="card warnings-cta" style="margin:18px 0;border-left:4px solid #0f766e"><div class="card-body">
    <?php if ($partners > 0): ?>
      <h2 class="h-card" style="margin:0 0 6px">Do not get caught out in <?= e($city) ?> alone</h2>
      <p style="margin:0 0 10px"><?= (int) $partners ?> <?= $partners === 1 ? 'traveler is' : 'travelers are' ?> looking for company in <?= e($city) ?> this month. Two people are harder to distract, overcharge or separate from a bag than one.</p>
      <a class="btn btn-primary btn-sm" href="<?= e(url('buddies?dest=' . rawurlencode((string) $d['slug']))) ?>">See who is going to <?= e($city) ?></a>
    <?php else: ?>
      <h2 class="h-card" style="margin:0 0 6px">Going to <?= e($city) ?>?</h2>
      <p style="margin:0 0 10px">Post your dates and find company before you land. Two people are harder to distract, overcharge or separate from a bag than one. Nobody can message you until you say yes.</p>
      <a class="btn btn-primary btn-sm" href="<?= e(url('buddies/new?dest=' . rawurlencode((string) $d['slug']))) ?>">Post your dates for <?= e($city) ?></a>
    <?php endif; ?>
  </div></div>

  <p style="display:flex;gap:8px;flex-wrap:wrap;margin:0">
    <?php if ($type !== null): ?><a class="btn btn-ghost btn-sm" href="<?= e($warnUrl) ?>">All warnings for <?= e($city) ?></a><?php endif; ?>
    <?php if ($cityListed): ?><a class="btn btn-ghost btn-sm" href="<?= e($cityUrl) ?>"><?= e($city) ?>: places, costs and travelers</a><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('ruined?d=' . $d['slug'])) ?>">What travelers say went wrong in <?= e($city) ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('safety')) ?>">Meeting people safely</a>
  </p>
</div>
