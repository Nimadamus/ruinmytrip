<?php
/**
 * Phase 1 (app/phase1.php): the city's warnings, sorted into the owner's five tabs. Every card is a
 * row or an official source the site already publishes; a cost badge appears only when the source
 * itself gives the figure. Reading is open; "Helpful" and "Saved Me" need no account; saving to a
 * trip checklist asks a guest to join (views/_p1_join_dialog.php), and the save waits in the session.
 *
 * @var array $d @var ?array $me @var mixed $v2Body
 */
$p1W = rmt_p1_warnings((string) $d['slug']);
$p1N = rmt_p1_cat_counts($p1W);
$p1V = $p1W ? rmt_p1_vote_counts((string) $d['slug']) : [];
$p1Id = static fn(string $k): string => 'w-' . preg_replace('/[^a-z0-9]/i', '-', $k);
$p1City = (string) $d['name'];
?>
<span id="scams" class="p1-anchor" aria-hidden="true"></span>
<section class="p1-warn" id="warnings" aria-labelledby="p1-warn-h" data-p1-city="<?= e($p1City) ?>">
  <div class="p1-warn-head">
    <div>
      <h2 id="p1-warn-h">What could catch you out in <?= e($p1City) ?></h2>
      <p class="p1-muted"><?php if ($p1N['all'] > 0): ?><?= (int) $p1N['all'] ?> <?= $p1N['all'] === 1 ? 'warning' : 'warnings' ?> from official sources and travelers, each with where it comes from.<?php else: ?>No warnings published for <?= e($p1City) ?> yet.<?php endif; ?></p>
    </div>
    <?php if (empty($v2Body) && $p1W): ?>
    <form method="post" action="<?= e(url('checklist/save')) ?>" class="p1-inline" data-p1-save data-full="1"><?= csrf_field() ?>
      <input type="hidden" name="dest" value="<?= e($d['slug']) ?>"><input type="hidden" name="key" value="*">
      <button class="v2-btn v2-btn-primary btn btn-primary" type="submit" data-cta="city_save_full">Save Full City Checklist</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($p1W): ?>
  <div class="p1-tabs" role="tablist" aria-label="Warning categories">
    <button type="button" role="tab" class="p1-tab" aria-selected="true" data-p1-tab="all">All Warnings <span><?= (int) $p1N['all'] ?></span></button>
    <?php foreach (RMT_P1_CATS as $ck => $cl): ?>
    <button type="button" role="tab" class="p1-tab" aria-selected="false" data-p1-tab="<?= e($ck) ?>"><?= e($cl) ?> <span><?= (int) $p1N[$ck] ?></span></button>
    <?php endforeach; ?>
  </div>

  <div class="p1-cards">
    <?php foreach ($p1W as $w):
      $wc = (string) $w['cat'];
      $impact = RMT_P1_IMPACT[$wc] ?? 'Traveler Report';
      $votes = $p1V[$w['key']] ?? []; ?>
    <article class="p1-card p1-cat-<?= e($wc) ?>" id="<?= e($p1Id((string) $w['key'])) ?>" data-cat="<?= e($wc) ?>">
      <div class="p1-card-top">
        <span class="p1-tag"><?= e($impact) ?></span>
        <?php if (!empty($w['cost'])): ?><span class="p1-cost" title="Figure from the source"><?= e((string) $w['cost']) ?></span><?php endif; ?>
      </div>
      <h3><?= e((string) $w['title']) ?></h3>
      <p><?= e((string) $w['body']) ?></p>
      <?php if (!empty($w['source'])): ?>
      <p class="p1-src">Source: <?php if (!empty($w['url'])): ?><a href="<?= e((string) $w['url']) ?>"<?= str_starts_with((string) $w['url'], 'http') ? ' rel="noopener nofollow" target="_blank"' : '' ?>><?= e((string) $w['source']) ?></a><?php else: ?><?= e((string) $w['source']) ?><?php endif; ?></p>
      <?php endif; ?>
      <div class="p1-card-acts">
        <?php foreach (['helpful' => 'Helpful', 'saved' => 'Saved Me'] as $vk => $vl): $vn = (int) ($votes[$vk] ?? 0); ?>
        <form method="post" action="<?= e(url('d/' . $d['slug'] . '/warning-vote')) ?>" class="p1-inline" data-p1-vote><?= csrf_field() ?>
          <input type="hidden" name="key" value="<?= e((string) $w['key']) ?>"><input type="hidden" name="kind" value="<?= e($vk) ?>">
          <button type="submit" class="p1-vote" aria-pressed="false" data-vote-id="<?= e($d['slug'] . '|' . $w['key'] . '|' . $vk) ?>"><?= e($vl) ?> <span class="p1-vote-n"<?= $vn ? '' : ' hidden' ?>><?= $vn ?></span></button>
        </form>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('checklist/save')) ?>" class="p1-inline p1-save-one" data-p1-save><?= csrf_field() ?>
          <input type="hidden" name="dest" value="<?= e($d['slug']) ?>"><input type="hidden" name="key" value="<?= e((string) $w['key']) ?>">
          <button type="submit" class="p1-save">Save to Trip Checklist</button>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <button type="button" class="p1-more" data-p1-more hidden><span>Show all</span> <span data-p1-more-n></span> <span>warnings</span></button>
  <?php foreach (RMT_P1_CATS as $ck => $cl): if ($p1N[$ck] > 0) continue; ?>
  <p class="p1-empty" data-p1-empty="<?= e($ck) ?>" hidden>No <?= e(strtolower($cl)) ?> warnings for <?= e($p1City) ?> yet. Been there? <a href="#report">Report one</a>.</p>
  <?php endforeach; ?>
  <?php else: ?>
  <p class="p1-empty">Nothing here yet. If something caught you out in <?= e($p1City) ?>, <a href="#report">tell the next traveler</a>.</p>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/_p1_join_dialog.php'; ?>
