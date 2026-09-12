<?php /** @var array $board @var int $days @var array $steps @var array $byAuth @var array $bySource @var array $failures @var array $counts @var array $signup @var array $growth @var array $inventory @var array $overlap */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Contribution funnel</p>
  <h1 style="margin:.2rem 0 .4rem">Signup and contribution funnels</h1>
  <p class="hint" style="margin:0 0 6px">
    Attempts, not people. This table holds no user id, no address and no review text: the
    questions it answers do not need any of them. An attempt is one journey through the flow, so
    somebody clicking a button three times before the page loads counts once.
  </p>
  <p class="hint" style="margin:0 0 20px">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 0 => 'All time'] as $d => $lbl): ?>
      <a href="<?= e(url('admin/funnel') . '?days=' . $d) ?>"<?= $d === $days ? ' style="font-weight:700"' : '' ?>><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </p>

  <?php
  /* Members, counted from the product's own rows. This block is first because it is the only one
     on the page that cannot be wrong about itself: every number is a COUNT over the thing it
     claims, so there is no event that can be missing, double fired or left behind by a refactor.
     The event table below answers the one question rows cannot, which is how many people arrived. */
  $bar = static function (int $n, int $top): string {
      $w = $top > 0 ? max(0, min(100, (int) round($n * 100 / $top))) : 0;
      return '<div style="background:#eef2f6;height:12px;border-radius:6px;overflow:hidden">'
           . '<div style="width:' . $w . '%;height:100%;background:#2f6fed"></div></div>';
  };
  $spineTop = max(1, (int) $growth['spine'][0]['n'], (int) $growth['spine'][1]['n']);
  ?>
  <h2 style="margin:6px 0 4px">Members</h2>
  <p class="hint" style="margin:0 0 10px">Counted from trips, saves, plans and confirmations
    themselves, not from anything recorded about anybody. Each percentage is a share of the line
    above it.
    <?php if (!empty($growth['arrivals_since']) && empty($growth['arrivals_cover'])): ?>
      Arrivals have only been counted since <?= e(substr((string) $growth['arrivals_since'], 0, 10)) ?>,
      so the signup rate is left blank rather than computed against a longer window.
    <?php elseif (empty($growth['arrivals_since'])): ?>
      No arrivals have been counted yet.
    <?php endif; ?>
  </p>
  <table class="table" style="margin:0 0 18px">
    <tbody>
    <?php foreach ($growth['spine'] as $row): ?>
      <tr>
        <td style="width:18rem"><?= e((string) $row['label']) ?></td>
        <td style="width:5rem;text-align:right"><b><?= (int) $row['n'] ?></b></td>
        <td style="width:5rem;text-align:right" class="muted"><?= $row['of'] === null ? '' : e((string) $row['of']) . '%' ?></td>
        <td><?= $bar((int) $row['n'], $spineTop) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2 style="margin:6px 0 4px">First actions</h2>
  <p class="hint" style="margin:0 0 10px">Share of members who have done each thing at least once.
    Not a sequence: nobody does these in this order, so a fall down the list is not a drop-off.</p>
  <table class="table" style="margin:0 0 18px">
    <tbody>
    <?php foreach ($growth['firsts'] as $row): ?>
      <tr>
        <td style="width:18rem"><?= e((string) $row['label']) ?></td>
        <td style="width:5rem;text-align:right"><b><?= (int) $row['n'] ?></b></td>
        <td style="width:5rem;text-align:right" class="muted"><?= $row['of'] === null ? '' : e((string) $row['of']) . '%' ?></td>
        <td><?= $bar((int) $row['n'], max(1, (int) $growth['members'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php
  /* What there is to arrive for. Cities with two or more travelers is the number that says whether
     the social half of this product does anything at all, so it is stated rather than buried. */
  $inv = $inventory;
  ?>
  <?php
  /* The two numbers that say whether the social half of this product does anything at all, and
     the only place on this page where a zero is the most important thing on the screen.

     Network activation is a member with a trip that overlaps another member's trip in the same
     city on the same days: somebody they could actually see. Social activation is one of those
     members who then followed, messaged or asked to join something.

     Counts of members, never pairs. Who overlaps with whom is exactly the fact a traveler would
     not want kept, and a count cannot hold it. Neither is tracked: both are recomputed from trip
     rows every time this page is opened. */
  $ov = $overlap;
  $dated = max(1, (int) $ov['dated_upcoming_trips']);
  ?>
  <h2 style="margin:6px 0 4px">Is the network working</h2>
  <?php if ((int) $ov['cities_with_overlap'] === 0): ?>
    <p style="margin:0 0 6px"><b>No.</b> Nobody currently has a trip that overlaps anybody else's.</p>
    <p class="hint" style="margin:0 0 22px">
      <?= (int) $ov['dated_upcoming_trips'] ?> upcoming trips carry real dates, and no two of them
      are in the same city at the same time. Until that changes, every social surface on the site
      is honestly empty, and concentrating the next members into one city and one window matters
      more than any number above. The first result worth having is this line reading one city.
    </p>
  <?php else: ?>
    <table class="table" style="margin:0 0 6px">
      <tbody>
        <tr><td style="width:22rem">Cities where somebody could meet somebody</td>
            <td style="width:5rem;text-align:right"><b><?= (int) $ov['cities_with_overlap'] ?></b></td><td></td></tr>
        <tr><td>Upcoming dated trips that overlap another traveler</td>
            <td style="text-align:right"><b><?= (int) $ov['trips_with_overlap'] ?></b></td>
            <td class="muted"><?= (int) round((int) $ov['trips_with_overlap'] * 100 / $dated) ?>% of dated upcoming trips</td></tr>
        <tr><td>Network activation: members who could see a real traveler</td>
            <td style="text-align:right"><b><?= (int) $ov['network_activated'] ?></b></td><td></td></tr>
        <tr><td>Social activation: of those, members who then followed, messaged or asked to join</td>
            <td style="text-align:right"><b><?= (int) $ov['social_activated'] ?></b></td>
            <td class="muted"><?= (int) $ov['network_activated'] > 0
                ? (int) round((int) $ov['social_activated'] * 100 / (int) $ov['network_activated']) . '% of activated'
                : '' ?></td></tr>
      </tbody>
    </table>
    <p class="hint" style="margin:0 0 22px">Only upcoming trips count. Two people who were in the
      same city last March did not meet and cannot now.</p>
  <?php endif; ?>

  <h2 style="margin:6px 0 4px">What is here</h2>
  <p style="margin:0 0 6px">
    <?= (int) $inv['cities'] ?> cities, <?= (int) $inv['places'] ?> places,
    <?= (int) $inv['public_trips'] ?> public trips
    (<?= (int) $inv['upcoming_trips'] ?> still upcoming), <?= (int) $inv['open_plans'] ?> plans.
  </p>
  <p class="hint" style="margin:0 0 22px">
    <?php if ((int) $inv['cities_with_overlap'] > 0): ?>
      <?= (int) $inv['cities_with_overlap'] ?> cities have more than one traveler with upcoming
      dates. Those are the only cities where meeting somebody is currently possible.
    <?php else: ?>
      No city yet has two travelers with overlapping upcoming dates, so nobody can currently be
      matched with anybody. Until that changes, the social half of the site has nothing to show and
      density in one city matters more than any number above.
    <?php endif; ?>
  </p>

  <?php
  /* The join funnel, first, because the site's problem is members rather than throughput. Each row
     is attempts, counted by journey, so one person reloading the form is one person. */
  $sv = $signup['steps'];
  $joinRows = [
      ['Saw the join form', (int) $sv['join_view']],
      ['Pressed create account', (int) $sv['join_submit']],
      ['Account created', (int) $sv['join_created']],
      ['Email confirmed', (int) $sv['join_confirmed']],
      ['Answered a question in the welcome flow', (int) $sv['join_first_action']],
  ];
  $joinTop = max(1, $joinRows[0][1]);
  ?>
  <h2 style="margin:6px 0 4px">Joining</h2>
  <p class="hint" style="margin:0 0 10px">Attempts at the form itself. The last row counts only
    the welcome flow's own questions, so it is not an activation rate: what people actually did
    is the Members table above, which reads the rows rather than the events. Crawler requests
    stopped being recorded on 2026 09 11, so counts from before then are inflated by robots.</p>
  <?php if (!array_sum(array_column($joinRows, 1))): ?>
    <p class="muted" style="margin:0 0 20px">Nobody has reached the join form in this window.</p>
  <?php else: ?>
    <table class="table" style="margin:0 0 10px">
      <tbody>
      <?php foreach ($joinRows as $i => [$label, $n]): $pct = (int) round($n * 100 / $joinTop); ?>
        <tr>
          <td style="width:16rem"><?= e($label) ?></td>
          <td style="width:5rem;text-align:right"><b><?= $n ?></b></td>
          <td>
            <div style="background:#eef2f6;height:12px;border-radius:6px;overflow:hidden">
              <div style="background:#0f766e;height:12px;width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td style="width:9rem;text-align:right" class="hint">
            <?php $prev = $i > 0 ? $joinRows[$i - 1][1] : 0; ?>
            <?php if ($i > 0 && $prev > 0): ?><?= (int) round($n * 100 / $prev) ?>% of the step above<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ((int) $sv['join_failure'] > 0): ?>
      <p class="hint" style="margin:0 0 14px"><?= (int) $sv['join_failure'] ?> refused: a validation error or the
        per connection rate limit. A number that climbs here is a form problem, not a demand problem.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($signup['by_source'])): ?>
    <h3 style="margin:16px 0 8px">Which page recruits</h3>
    <table class="table" style="margin:0 0 26px">
      <thead><tr><th>Page they came from</th><th style="text-align:right">Saw the form</th>
        <th style="text-align:right">Joined</th><th style="text-align:right">Rate</th></tr></thead>
      <tbody>
      <?php foreach ($signup['by_source'] as $src => $row): ?>
        <tr>
          <td><?= e($src) ?></td>
          <td style="text-align:right"><?= (int) $row['views'] ?></td>
          <td style="text-align:right"><b><?= (int) $row['created'] ?></b></td>
          <td style="text-align:right" class="hint"><?= $row['views'] > 0 ? (int) round($row['created'] * 100 / $row['views']) . '%' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2 style="margin:22px 0 10px">Contributing</h2>

  <?php
  /* The scoreboard. Whether this is a review site yet is one number, and it is the first thing on
     the page rather than something to scroll for. */
  $published = (int) ($counts['review_publish_success'] ?? 0);
  $clicks    = (int) ($counts['review_cta_click'] ?? 0);
  ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Community scoreboard</h2>
    <p class="hint" style="margin:0 0 12px">
      Real rows only, editorial excluded. A zero here means zero, and the number does not move
      until a traveler writes something.
    </p>
    <div class="grid g-2" style="gap:4px 24px">
      <?php foreach ([
        'reviews'             => 'Community reviews',
        'reviewers'           => 'Unique reviewers',
        'places_reviewed'     => 'Places with a review',
        'places_rankable'     => 'Places with ' . RMT_TOP_MIN_REVIEWS . '+ reviews',
        'destinations_active' => 'Destinations with activity',
        'photos'              => 'Community photos',
        'reviews_7d'          => 'Reviews, last 7 days',
        'reviews_30d'         => 'Reviews, last 30 days',
      ] as $k => $label): ?>
        <p style="margin:2px 0;font-size:.94rem">
          <span class="muted"><?= e($label) ?></span>
          <strong style="float:right"><?= (int) $board[$k] ?></strong>
        </p>
      <?php endforeach; ?>
    </div>
    <p style="margin:12px 0 0;font-size:.94rem">
      <span class="muted">CTA to published</span>
      <strong style="float:right">
        <?= $clicks > 0 ? (int) round($published * 100 / $clicks) . '%' : 'none' ?>
      </strong>
    </p>
    <?php if (!empty($board['last_community_review'])): ?>
      <p class="hint" style="margin:8px 0 0">Last community review: <?= e(substr((string) $board['last_community_review'], 0, 16)) ?>.</p>
    <?php endif; ?>
  </div></section>

  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Who could be told</h2>
    <p class="hint" style="margin:0 0 10px">
      Counts only. Nothing here sends anything and nothing here reads an address; it exists so an
      acquisition decision is made against a real number rather than a guess.
    </p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Registered accounts</span>
      <strong style="float:right"><?= (int) $board['users'] ?></strong></p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Active</span>
      <strong style="float:right"><?= (int) $board['users_active'] ?></strong></p>
    <p style="margin:2px 0;font-size:.94rem"><span class="muted">Email confirmed</span>
      <strong style="float:right"><?= (int) $board['users_verified'] ?></strong></p>
  </div></section>

  <?php $top = max(1, (int) ($steps[0]['count'] ?? 0)); ?>
  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 10px;font-size:1.05rem">The path</h2>
    <?php if (!array_sum(array_column($steps, 'count'))): ?>
      <p class="muted" style="margin:0">
        Nothing recorded in this window yet. That is the honest state: no traveler has started a
        review here.
      </p>
    <?php else: ?>
      <?php foreach ($steps as $i => $st): $pct = (int) round($st['count'] * 100 / $top); ?>
        <div style="display:flex;align-items:center;gap:10px;margin:5px 0">
          <span class="muted" style="width:14rem;font-size:.9rem">
            <?= e($st['label']) ?>
            <?php /* A branch step applies only to some attempts, so reading it as a straight-line
                     loss would be wrong -- most people do not need to sign up. */ ?>
            <?php if ($st['branch']): ?><span class="hint">(some)</span><?php endif; ?>
          </span>
          <span style="flex:1;height:8px;background:#e9e9ee;border-radius:99px;overflow:hidden">
            <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--ink)"></span>
          </span>
          <strong style="width:3.5rem;text-align:right;font-size:.9rem"><?= (int) $st['count'] ?></strong>
          <?php $prev = $i > 0 ? (int) $steps[$i - 1]['count'] : 0; ?>
          <span class="hint" style="width:5.5rem;text-align:right">
            <?php if ($i > 0 && $prev > 0 && !$st['branch'] && !$steps[$i - 1]['branch']): ?>
              <?= (int) round($st['count'] * 100 / $prev) ?>% of prev
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div></section>

  <section class="card" style="margin:0 0 20px"><div class="card-body">
    <h2 style="margin:0 0 4px;font-size:1.05rem">Signed in before starting, or not</h2>
    <p class="hint" style="margin:0 0 10px">
      An anonymous attempt has two extra steps in front of it. Lumping the two together hides
      whichever one is broken.
    </p>
    <?php foreach (['authed' => 'Already signed in', 'anonymous' => 'Not signed in'] as $k => $lbl):
      $row = $byAuth[$k]; $rate = $row['started'] > 0 ? round($row['published'] * 100 / $row['started']) : 0; ?>
      <p style="margin:3px 0;font-size:.94rem">
        <?= e($lbl) ?>:
        <strong><?= (int) $row['published'] ?></strong> published of
        <?= (int) $row['started'] ?> attempts
        <?php if ($row['started'] > 0): ?><span class="muted">(<?= $rate ?>%)</span><?php endif; ?>
      </p>
    <?php endforeach; ?>
  </div></section>

  <?php if ($bySource): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Where the attempt started</h2>
      <p class="hint" style="margin:0 0 10px">
        Which surface produces reviews, which is not the same question as which one gets clicked.
      </p>
      <?php foreach ($bySource as $r): ?>
        <p style="margin:3px 0;font-size:.94rem">
          <?= e((string) $r['source']) ?>:
          <strong><?= (int) $r['published'] ?></strong> published of <?= (int) $r['attempts'] ?> attempts
        </p>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <?php if ($failures): ?>
    <section class="card" style="margin:0 0 20px"><div class="card-body">
      <h2 style="margin:0 0 4px;font-size:1.05rem">Why a publish did not happen</h2>
      <p class="hint" style="margin:0 0 10px">Operational reasons only. Never the content that failed.</p>
      <?php foreach ($failures as $f): ?>
        <p style="margin:3px 0;font-size:.94rem"><?= e((string) $f['reason']) ?>: <strong><?= (int) $f['n'] ?></strong></p>
      <?php endforeach; ?>
    </div></section>
  <?php endif; ?>

  <section class="card" style="margin:0 0 30px"><div class="card-body">
    <h2 style="margin:0 0 10px;font-size:1.05rem">Every event</h2>
    <div class="grid g-2" style="gap:2px 24px">
      <?php foreach ($counts as $event => $n): ?>
        <p style="margin:2px 0;font-size:.9rem">
          <span class="muted"><?= e(str_replace('_', ' ', (string) $event)) ?></span>
          <strong style="float:right"><?= (int) $n ?></strong>
        </p>
      <?php endforeach; ?>
    </div>
  </div></section>
</div>
