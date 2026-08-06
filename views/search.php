<?php
/**
 * Search results.
 *
 * Warnings come first because that is what this site is for. Destinations follow, then the named
 * subjects people warn about (hotels, attractions, transport operators, neighbourhoods), then the
 * editorial guides, then the older community content.
 *
 * @var string $qs @var string $raw @var bool $isPhrase
 * @var array $dests @var array $trips @var array $guides @var array $reviews @var array $people
 * @var array $posts @var array $collections @var array $warnings @var array $pages @var array $subjects
 * @var array $suggestions @var array $categoryHits
 */
$nothing = !$dests && !$trips && !$reviews && !$guides && !$posts && !$collections && !$people
        && !$warnings && !$pages && !$subjects && !$categoryHits;
?>
<div class="wrap" style="min-height:50vh">
  <h1 style="margin-top:24px">Search</h1>

  <form class="ac-wrap" action="<?= e(url('search')) ?>" method="get" style="display:flex;gap:10px;margin:14px 0 10px">
    <input type="search" name="q" value="<?= e($raw) ?>" autocomplete="off" data-suggest style="flex:1"
           placeholder="A city, an airport, a hotel, a neighbourhood, or what went wrong…">
    <button class="btn btn-primary">Search</button>
    <div class="ac-list" role="listbox" aria-label="Suggestions"></div>
  </form>
  <p class="hint" style="margin-bottom:26px">
    Wrap a phrase in double quotes for an exact match — <code>"resort fee"</code> rather than every page
    containing “resort”.
    <?php if ($isPhrase): ?><b>Exact-phrase search is on.</b><?php endif; ?>
  </p>

  <?php if ($qs === ''): ?>
    <p class="muted">Search destinations, airports, hotels, neighbourhoods, transport operators and traveler
      warnings. Or start from <a href="<?= e(url('warnings')) ?>">every warning</a> or
      <a href="<?= e(url('explore')) ?>">every destination</a>.</p>
  <?php else: ?>

    <?php if ($nothing): ?>
      <p class="muted">No results for “<?= e($qs) ?>”.</p>
      <?php if ($suggestions): ?>
        <p>Did you mean
          <?php foreach ($suggestions as $i => $s): ?>
            <?= $i ? ' or ' : '' ?><a href="<?= e(url('d/' . $s['slug'])) ?>"><b><?= e($s['name']) ?></b></a>
          <?php endforeach; ?>?
        </p>
      <?php endif; ?>
    <?php elseif ($suggestions): ?>
      <p class="muted">Also try
        <?php foreach ($suggestions as $i => $s): ?>
          <?= $i ? ', ' : '' ?><a href="<?= e(url('d/' . $s['slug'])) ?>"><?= e($s['name']) ?></a>
        <?php endforeach; ?>.
      </p>
    <?php endif; ?>

    <?php if ($categoryHits): ?>
      <div class="filter-chips">
        <?php foreach ($categoryHits as $ck => $cc): ?>
          <a href="<?= e(url('warnings/' . $ck)) ?>"><?= $cc['icon'] ?> All <?= e($cc['label']) ?> warnings</a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($warnings): ?>
      <h2>Traveler warnings</h2>
      <?php foreach ($warnings as $w) { include __DIR__ . '/_warning_card.php'; } ?>
      <p><a href="<?= e(url('warnings?q=' . rawurlencode($qs))) ?>">Filter all matching warnings →</a></p>
    <?php endif; ?>

    <?php if ($dests): ?>
      <h2 style="margin-top:24px">Destinations</h2>
      <div class="grid g-3">
        <?php foreach ($dests as $d): ?>
          <article class="card"><a href="<?= e(url('d/' . $d['slug'])) ?>">
            <?php if (!empty($d['hero_url'])): ?>
              <img class="card-media" loading="lazy" decoding="async" width="380" height="238" src="<?= e($d['hero_url']) ?>" alt="">
            <?php endif; ?>
            <div class="card-body">
              <h3 style="font-size:1.05rem"><?= e($d['name']) ?></h3>
              <p class="muted" style="margin:0;font-size:.86rem"><?= e((string) ($d['country'] ?? '')) ?>
                <?php if (!empty($d['airport_codes'])): ?> · <?= e((string) $d['airport_codes']) ?><?php endif; ?></p>
            </div>
          </a></article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($subjects): ?>
      <h2 style="margin-top:24px">Hotels, attractions, operators and neighbourhoods</h2>
      <ul class="list-plain">
        <?php foreach ($subjects as $s): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)">
            <b><?= e((string) $s['name']) ?></b>
            <span class="chip chip-cat"><?= e((string) $s['kind']) ?></span>
            <span class="muted">in <a href="<?= e(url('d/' . $s['dest_slug'])) ?>"><?= e((string) $s['dest_name']) ?></a>
              · <?= (int) $s['c'] ?> mention<?= (int) $s['c'] === 1 ? '' : 's' ?></span>
            <a href="<?= e(url('d/' . $s['dest_slug'] . '/warnings?q=' . rawurlencode((string) $s['name']))) ?>">See reports →</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($pages): ?>
      <h2 style="margin-top:24px">Warning guides</h2>
      <ul class="list-plain">
        <?php foreach ($pages as $p): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)">
            <a href="<?= e(url($p['slug'])) ?>"><?= e($p['h1']) ?></a>
            <?php if (!empty($p['dest_name'])): ?><span class="muted"> · <?= e((string) $p['dest_name']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($reviews): ?>
      <h2 style="margin-top:24px">Reviews</h2>
      <ul class="list-plain">
        <?php foreach ($reviews as $r): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)">
            <a href="<?= e(url(ltrim(rmt_review_path($r), '/'))) ?>"><?= e($r['title'] ?: $r['subject_name']) ?></a>
            <?php if (!empty($r['dest_name'])): ?><span class="muted"> · <?= e($r['dest_name']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($trips): ?>
      <h2 style="margin-top:24px">Trip stories</h2>
      <ul class="list-plain">
        <?php foreach ($trips as $t): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('trip/' . $t['id'] . '/' . $t['slug'])) ?>"><?= e($t['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($guides): ?>
      <h2 style="margin-top:24px">Traveler guides</h2>
      <ul class="list-plain">
        <?php foreach ($guides as $g): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('g/' . $g['slug'])) ?>"><?= e($g['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($posts): ?>
      <h2 style="margin-top:24px">Blog</h2>
      <ul class="list-plain">
        <?php foreach ($posts as $p): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('blog/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($collections): ?>
      <h2 style="margin-top:24px">Collections</h2>
      <ul class="list-plain">
        <?php foreach ($collections as $c): ?>
          <li style="padding:8px 0;border-bottom:1px solid var(--line)"><a href="<?= e(url('c/' . $c['slug'])) ?>"><?= e($c['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($people): ?>
      <h2 style="margin-top:24px">Travelers</h2>
      <div class="grid" style="gap:10px">
        <?php foreach ($people as $p): ?>
          <article class="card"><div class="card-body" style="display:flex;gap:10px;align-items:center">
            <?php if (!empty($p['avatar_url'])): ?><img class="avatar" style="width:36px;height:36px" src="<?= e(avatar_url($p['avatar_url'])) ?>" alt=""><?php endif; ?>
            <a href="<?= e(url('u/' . $p['username'])) ?>"><?= e($p['display_name'] ?: $p['username']) ?></a>
            <span class="muted">@<?= e($p['username']) ?></span>
          </div></article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
