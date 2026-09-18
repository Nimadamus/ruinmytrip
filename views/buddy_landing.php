<?php /** @var array $p @var ?array $me @var array $cards @var array $cities @var array $meetups
        @var string $postHref @var array $related @var string $path */
$isCruise = $p['kind'] === 'cruise';
$bcBack = '/' . $path;
$n = count($cards);
$meetOn = 'going'; include __DIR__ . '/_meet_nav.php';
?>
<section class="buddy-hero"><div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> /
    <?php if ($isCruise): ?><a href="<?= e(url('buddies/cruise')) ?>">Cruise buddies</a><?php else: ?><a href="<?= e(url('buddies')) ?>">Travel buddies</a><?php endif; ?>
    / <?= e($p['name']) ?></p>
  <p class="eyebrow"><?= $isCruise ? 'Cruise buddies' : 'Travel buddies' ?></p>
  <h1><?= $isCruise ? e($p['name']) . ' cruise buddies' : 'Travel buddies in ' . e($p['name']) ?></h1>
  <p class="buddy-lede"><?= e($p['lede']) ?></p>
  <div class="buddy-cta">
    <a class="btn btn-accent" href="<?= e($postHref) ?>"><?= $isCruise ? 'Post your sailing' : 'Post your trip' ?></a>
    <a class="btn btn-ghost-light" href="<?= e(url('travelers')) ?>">Browse all travelers</a>
  </div>
  <?php if ($isCruise): ?>
    <form class="buddy-search" method="get" action="<?= e(url('buddies/cruise')) ?>" role="search">
      <input type="hidden" name="line" value="<?= e($p['line']) ?>">
      <div class="bs-row">
        <label><span>Your ship</span><input type="text" name="ship" placeholder="Ship name" autocomplete="off"></label>
        <label><span>Sail date</span><input type="date" name="from"></label>
        <label><span>Back</span><input type="date" name="to"></label>
        <button class="btn btn-primary bs-go" type="submit">Find people on it</button>
      </div>
    </form>
  <?php else: ?>
    <form class="buddy-search" method="get" action="<?= e(url('buddies')) ?>" role="search">
      <input type="hidden" name="where" value="<?= e($p['name']) ?>">
      <div class="bs-row">
        <label><span>Where</span><input type="text" value="<?= e($p['name']) ?>" disabled></label>
        <label><span>From</span><input type="date" name="from"></label>
        <label><span>To</span><input type="date" name="to"></label>
        <button class="btn btn-primary bs-go" type="submit">Check my dates</button>
      </div>
    </form>
  <?php endif; ?>
</div></section>

<div class="wrap buddy-body">
  <section class="bl-block" aria-labelledby="bl-who">
    <h2 id="bl-who"><?= $isCruise ? 'Who is sailing ' . e($p['name']) : 'Who is going to ' . e($p['name']) ?></h2>
    <?php if ($n): ?>
      <p class="muted"><?= $n ?> <?= $n === 1 ? 'member' : 'members' ?> with upcoming plans. Nobody can message you until you accept their request.</p>
      <div class="buddy-grid">
        <?php foreach ($cards as $bc): ?><?php include __DIR__ . '/_buddy_card.php'; ?><?php endforeach; ?>
      </div>
      <p><a href="<?= e($isCruise ? url('buddies/cruise') . '?' . http_build_query(['line' => $p['line']]) : url('buddies') . '?' . http_build_query(['where' => $p['name']])) ?>">See everyone and filter by your dates</a></p>
    <?php else: ?>
      <div class="empty-cta">
        <h3><?= $isCruise ? 'Nobody has posted a ' . e($p['name']) . ' sailing yet.' : 'Nobody has posted a ' . e($p['name']) . ' trip yet.' ?></h3>
        <p class="muted">Be the first. Post <?= $isCruise ? 'your ship and sail date' : 'where you are going and when' ?>, and you will be told when somebody lines up with you. Anyone searching <?= e($p['name']) ?> will find you here.</p>
        <p><a class="btn btn-accent" href="<?= e($postHref) ?>"><?= $isCruise ? 'Post your sailing' : 'Post your trip' ?></a></p>
      </div>
    <?php endif; ?>
  </section>

  <?php foreach ($p['sections'] as [$h, $body]): ?>
    <section class="bl-block">
      <h2><?= e($h) ?></h2>
      <p class="bl-prose"><?= e($body) ?></p>
    </section>
  <?php endforeach; ?>

  <section class="bl-block">
    <h2>Getting a good match</h2>
    <ul class="bl-tips">
      <?php foreach ($p['tips'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
    </ul>
    <p class="hint">Members are 18 or over. Cards show only what a member chose to publish: never a hotel, cabin, address or live location. First meetings belong somewhere public. <a href="<?= e(url('safety')) ?>">How we keep it safe</a></p>
  </section>

  <?php if ($cities): ?>
    <section class="bl-block" aria-labelledby="bl-cities">
      <h2 id="bl-cities"><?= e($p['name']) ?> cities on RuinMyTrip</h2>
      <ul class="bl-cities">
        <?php foreach ($cities as $d): ?>
          <li>
            <a class="bl-city" href="<?= e(url('d/' . $d['slug'])) ?>"><?= e($d['name']) ?></a>
            <span class="muted"><?= $d['going'] ? $d['going'] . ' going' : 'Nobody posted yet' ?><?= $d['meetups'] ? ', ' . $d['meetups'] . ' ' . ($d['meetups'] === 1 ? 'meetup' : 'meetups') : '' ?></span>
            <a href="<?= e(url('d/' . $d['slug'] . '/travelers')) ?>">Travelers in <?= e($d['name']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (empty($p['dests'])): ?><p><a href="<?= e(url('in/' . rmt_country_slug($p['country']))) ?>"><?= e($p['country']) ?> costs, tickets and taxes</a></p><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($meetups): ?>
    <section class="bl-block" aria-labelledby="bl-meet">
      <h2 id="bl-meet">Meetups coming up</h2>
      <ul class="bl-cities">
        <?php foreach ($meetups as $m): ?>
          <li><a href="<?= e(url('meetup/' . (int) $m['id'])) ?>"><?= e($m['title']) ?></a>
            <span class="muted"><?= e($m['dest_name']) ?>, <?= e(date('j M', strtotime((string) $m['date_start']))) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p><a href="<?= e(url('meetups')) ?>">All meetups</a></p>
    </section>
  <?php endif; ?>

  <section class="bl-block" aria-labelledby="bl-faq">
    <h2 id="bl-faq">Questions people ask</h2>
    <?php foreach ($p['faq'] as [$q, $ans]): ?>
      <details class="bl-faq"><summary><?= e($q) ?></summary><p><?= e($ans) ?></p></details>
    <?php endforeach; ?>
  </section>

  <section class="bl-block empty-cta">
    <h2><?= $isCruise ? 'On a ' . e($p['name']) . ' cruise soon?' : 'Going to ' . e($p['name']) . '?' ?></h2>
    <p class="muted">It takes a minute, it is free, and nobody can message you until you say yes.</p>
    <p><a class="btn btn-accent" href="<?= e($postHref) ?>"><?= $isCruise ? 'Post your sailing' : 'Post your trip' ?></a>
      <a class="btn btn-ghost" href="<?= e(url('buddies')) ?>">Find a travel buddy anywhere</a></p>
  </section>

  <nav class="bl-block" aria-labelledby="bl-more">
    <h2 id="bl-more"><?= $isCruise ? 'Other cruise lines' : 'Travel buddies in other countries' ?></h2>
    <p class="bl-links">
      <?php foreach ($related as $r): ?>
        <a class="chip" href="<?= e(url(rmt_buddy_landing_path($r['slug']))) ?>"><?= e($r['name']) ?></a>
      <?php endforeach; ?>
    </p>
    <p><a href="<?= e(url($isCruise ? 'buddies' : 'buddies/cruise')) ?>"><?= $isCruise ? 'Travel buddies by country' : 'Cruise buddies by line' ?></a></p>
  </nav>
</div>
