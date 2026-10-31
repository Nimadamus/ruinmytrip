<?php
/**
 * /hosts-locals/destinations/{slug}: everybody in one city, by role, with filters.
 *
 * @var ?array $me @var array $d @var array $flt @var array $locals @var array $hosts @var array $travelers
 * @var array $counts @var int $reviews @var array $myLocal
 */
$back = '/hosts-locals/destinations/' . $d['slug'];
$bcBack = $back;
$any = $locals || $hosts || $travelers;
$filtered = $flt['lang'] !== '' || $flt['interest'] !== '' || $flt['offer'] !== '' || $flt['role'] !== '' || $flt['from'] !== '';
?>
<section class="hl-hero">
  <?php if (!empty($d['hero_url'])): ?><img class="hl-hero-img" src="<?= e((string) $d['hero_url']) ?>" alt="" fetchpriority="high" data-hide-broken><?php endif; ?>
  <div class="wrap hl-hero-in">
    <p class="hl-crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> /
      <a href="<?= e(url('hosts-locals/destinations')) ?>">Cities</a> / <?= e((string) $d['name']) ?></p>
    <p class="hl-kicker"><?= e((string) ($d['country'] ?? '')) ?></p>
    <h1>Meet people in <?= e((string) $d['name']) ?></h1>
    <p class="hl-lede">Locals who are happy to meet a traveler, travelers who will be there when you are, and members interested in hosting.</p>
    <p class="hl-counts">
      <span><b><?= (int) $counts['locals'] ?></b> <?= $counts['locals'] === 1 ? 'local' : 'locals' ?></span>
      <span><b><?= (int) $counts['going'] ?></b> going</span>
      <span><b><?= (int) $counts['hosts'] ?></b> <?= $counts['hosts'] === 1 ? 'host' : 'hosts' ?></span>
      <?php if ($reviews): ?><span><b><?= $reviews ?></b> <?= $reviews === 1 ? 'review' : 'reviews' ?></span><?php endif; ?>
    </p>
  </div>
</section>

<div class="wrap hl-sec">
  <details class="hl-filter-box"<?= $filtered ? ' open' : '' ?>>
    <summary>Filter by who, language, interest and dates</summary>
  <form class="hl-filters" method="get" action="<?= e(url('hosts-locals/destinations/' . $d['slug'])) ?>">
    <label>Show<select name="role">
      <option value="">Everybody</option>
      <?php foreach (['locals' => 'Locals', 'travelers' => 'Travelers going', 'hosts' => 'Hosts'] as $k => $l): ?>
        <option value="<?= $k ?>"<?= $flt['role'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select></label>
    <label>Language<select name="lang"><option value="">Any</option>
      <?php foreach (RMT_LANGUAGES as $k => $l): ?><option value="<?= $k ?>"<?= $flt['lang'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
    <label>Interest<select name="interest"><option value="">Any</option>
      <?php foreach (RMT_INTERESTS as $k => $l): ?><option value="<?= $k ?>"<?= $flt['interest'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
    <label>Local offers<select name="offer"><option value="">Anything</option>
      <?php foreach (RMT_LOCAL_OFFERS as $k => $l): ?><option value="<?= $k ?>"<?= $flt['offer'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
    <label>From<input type="date" name="from" value="<?= e($flt['from']) ?>"></label>
    <label>To<input type="date" name="to" value="<?= e($flt['to']) ?>"></label>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($filtered): ?><a class="btn btn-ghost" href="<?= e(url('hosts-locals/destinations/' . $d['slug'])) ?>">Clear</a><?php endif; ?>
  </form>
  </details>

  <?php if ($flt['role'] === '' || $flt['role'] === 'locals'): ?>
  <section aria-labelledby="hl-locals" style="margin-bottom:34px">
    <h2 class="hl-h2" id="hl-locals">Locals in <?= e((string) $d['name']) ?></h2>
    <?php if ($locals): ?>
      <p class="hl-sub">They chose to be found. Ask to meet, and messages open if they say yes.</p>
      <div class="hl-people"><?php foreach ($locals as $lc) include __DIR__ . '/_hl_local.php'; ?></div>
    <?php else: ?>
      <div class="hl-empty">
        <h3><?= $filtered ? 'Nobody matches those filters yet' : 'No locals in ' . e((string) $d['name']) . ' yet' ?></h3>
        <p>Live in <?= e((string) $d['name']) ?>? Be the first local travelers can meet here. Only the city shows, and you choose who you meet.</p>
        <a class="btn btn-primary" href="<?= e(url($me ? 'hosts-locals/me#local' : 'register?return=%2Fhosts-locals%2Fme')) ?>">Become a local</a>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($flt['role'] === '' || $flt['role'] === 'travelers'): ?>
  <section aria-labelledby="hl-going" style="margin-bottom:34px">
    <h2 class="hl-h2" id="hl-going">Travelers going to <?= e((string) $d['name']) ?></h2>
    <?php if ($travelers): ?>
      <p class="hl-sub">Upcoming trips and buddy posts. Cards show the city and dates only.</p>
      <div class="buddy-grid"><?php foreach ($travelers as $bc) include __DIR__ . '/_buddy_card.php'; ?></div>
      <p><a class="v2-link" href="<?= e(url('buddies?dest=' . rawurlencode((string) $d['slug']))) ?>">Search every travel buddy in <?= e((string) $d['name']) ?> &rarr;</a></p>
    <?php else: ?>
      <div class="hl-empty">
        <h3>Nobody has posted dates for <?= e((string) $d['name']) ?> yet</h3>
        <p>Going? Post your dates and you will be told when somebody lines up with you.</p>
        <a class="btn btn-primary" href="<?= e(url('buddies/new?dest=' . rawurlencode((string) $d['slug']))) ?>">Post your trip</a>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($flt['role'] === '' || $flt['role'] === 'hosts'): ?>
  <section aria-labelledby="hl-hosts" style="margin-bottom:34px">
    <h2 class="hl-h2" id="hl-hosts">Hosts in <?= e((string) $d['name']) ?></h2>
    <p class="hint" id="hl-stays-note">Stays are free. The host decides every request. We do not check identities, so read profiles and references first.</p>
    <?php if ($hosts): ?>
      <div class="hl-people"><?php foreach ($hosts as $hc) include __DIR__ . '/_hl_host.php'; ?></div>
    <?php else: ?>
      <div class="hl-empty">
        <h3>No hosts in <?= e((string) $d['name']) ?> yet</h3>
        <p>Have a spare room or couch? Apply early. A moderator reviews every application before anybody sees it.</p>
        <a class="btn btn-primary" href="<?= e(url('hosts-locals/become-a-host')) ?>">Become a host</a>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="hl-trust" aria-label="About <?= e((string) $d['name']) ?>">
    <div><h3>Read before you go</h3><p><?= $reviews ? 'Honest reviews from travelers, bad parts included.' : 'City guide, places and 2026 costs.' ?>
      <a href="<?= e(url('d/' . $d['slug'])) ?>"><?= e((string) $d['name']) ?> city page</a>.</p></div>
    <div><h3>Meeting somebody here</h3><p>Meet in public first, tell a friend where you are going, and report anything that feels wrong.
      <a href="<?= e(url('hosts-locals/safety')) ?>">Safety guide</a>.</p></div>
  </section>
</div>
