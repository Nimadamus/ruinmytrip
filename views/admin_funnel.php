<?php /** @var array $cc @var array $board @var int $days @var array $steps @var array $byAuth @var array $bySource @var array $failures @var array $counts @var array $signup @var array $growth @var array $inventory @var array $overlap @var array $social @var array $socialC @var array $visitors @var array $topCities @var array $attrib @var array $acq */ ?>
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

  <?php if (!empty($scorecard)) include __DIR__ . '/_scorecard.php'; ?>

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

  <?php
  /* The social funnel, first on the page because it is the loop the product is built around:
     land on a city, touch it, join, follow, post a trip, ask something, come back.

     Everything here is counted by journey, which is one session's attempt, except the two visitor
     numbers, which are counted by browser and are floors rather than totals: clearing cookies
     makes somebody new to us, and there is no honest fix for that which does not involve
     identifying the person. Zero is printed as zero. A funnel that rounded a quiet week up would
     be worth less than no funnel at all. */
  $socialTop = max(1, (int) ($social[0]['count'] ?? 0));
  $pct = static function (int $n, int $of): string {
      return $of > 0 ? (string) round($n * 100 / $of) . '%' : '';
  };
  ?>
  <?php /* The morning line. Four classes of traffic, never folded together, then the steps past a
           visit. The question somebody has when they open this page is whether yesterday did
           anything, and a number that counts our own checks as travelers does not answer it. */ ?>
  <?php $dy = function_exists('rmt_acq_daily') ? rmt_acq_daily() : null; ?>
  <?php if ($dy): ?>
    <section class="card" style="margin:0 0 18px"><div class="card-body">
      <h2 style="margin:0 0 8px;font-size:1.05rem">Today</h2>
      <?php if (!empty($dy['contaminated_window'])): ?>
        <p class="hint" style="margin:0 0 8px;padding:8px 10px;background:#fff5e6;border-radius:6px">
          <b>This window reaches back before <?= e(RMT_ACQ_CLEAN_FROM) ?> UTC</b>, when the self check
          marker did not exist. Sessions from before then were our own verification under real
          campaign names and cannot be separated now. Read them as ours unless a post was published.
        </p>
      <?php endif; ?>
      <p style="margin:0 0 6px;font-size:1.02rem">
        <b><?= (int) $dy['traffic']['real_human']['today'] ?></b> real human visits today,
        <b><?= (int) $dy['traffic']['real_human']['week'] ?></b> this week.
      </p>
      <p class="hint" style="margin:0 0 8px">
        Self check: <?= (int) $dy['traffic']['self_check']['week'] ?> this week.
        Automated: <?= (int) $dy['traffic']['automated']['week'] ?>.
        Uncertain: <?= (int) $dy['traffic']['uncertain']['week'] ?>.
        None of those three is counted above.
      </p>
      <p style="margin:0 0 6px">
        <b><?= (int) $dy['signups']['week'] ?></b> signups,
        <b><?= (int) $dy['confirmed']['week'] ?></b> confirmed,
        <b><?= (int) $dy['trips']['week'] ?></b> trips this week.
        <span class="hint">Then: <?= (int) $dy['matches_viewed'] ?> matches seen,
          <?= (int) $dy['connection_requests'] ?> connection requests,
          <?= (int) $dy['connections_made'] ?> accepted,
          <?= (int) $dy['messages_sent'] ?> messages.</span>
      </p>
      <p class="hint" style="margin:0">
        Top real source: <b><?= e((string) ($dy['top_real_source'] ?? 'none yet')) ?></b>.
        Top real campaign: <b><?= e((string) ($dy['top_real_campaign'] ?? 'none yet')) ?></b>.
        Top landing: <b><?= e((string) ($dy['top_landing']['slug'] ?? 'not recorded yet')) ?></b>.
        Best conversion: <?= $dy['best_conversion']
            ? e((string) $dy['best_conversion']['source']) . ' ' . e((string) $dy['best_conversion']['rate']) . '%'
            : 'no channel has five human sessions yet' ?>.
        <?php if ($dy['notable_change'] !== null): ?>Change: <?= e((string) $dy['notable_change']) ?>.<?php endif; ?>
      </p>
      <?php /* The milestones, counted only from traffic we can honestly call acquisition. If that is
               zero it says zero, which is the whole reason it exists. */ ?>
      <?php $cl = $dy['clean'] ?? null; if ($cl): ?>
        <hr style="margin:12px 0">
        <?php if ((int) ($cl['direct_human_not_counted'] ?? 0) > 0): ?>
          <p class="hint" style="margin:0 0 6px"><b><?= (int) $cl['direct_human_not_counted'] ?></b>
            human sessions arrived with no channel we can name. They are not counted below: with
            nothing published there is no external link for somebody to have followed, so a direct
            session cannot be told apart from the automated floor.</p>
        <?php endif; ?>
        <p class="hint" style="margin:0 0 6px">Counted from <?= e((string) $cl['since']) ?>,
          <?= (int) $cl['days'] ?> day<?= (int) $cl['days'] === 1 ? '' : 's' ?> ago, with our own
          checks and automated traffic already out. Nothing before that date is counted here.</p>
        <?php foreach ($cl['milestones'] as $m): ?>
          <p style="margin:2px 0;font-size:.94rem"><span class="muted"><?= e((string) $m['what']) ?></span>
            <strong style="float:right"><?= (int) $m['now'] ?> of <?= (int) $m['target'] ?></strong></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>

  <?php /* What the people who got nothing said they wanted. The only thing on this page that is not
           a number we inferred: it is what somebody typed. */ ?>
  <?php $vq = function_exists('rmt_vq_summary') ? rmt_vq_summary('no_match_hoped_for') : null; ?>
  <?php if ($vq && $vq['total'] > 0): ?>
    <section class="card" style="margin:0 0 18px"><div class="card-body">
      <h2 style="margin:0 0 6px;font-size:1.05rem">What people with no matches were hoping to find</h2>
      <p class="hint" style="margin:0 0 8px"><?= (int) $vq['total'] ?> answer<?= $vq['total'] === 1 ? '' : 's' ?>.</p>
      <?php foreach (RMT_VQ_QUESTIONS['no_match_hoped_for']['answers'] as $k => $label): ?>
        <?php $n = (int) ($vq['answers'][$k] ?? 0); if (!$n) continue; ?>
        <p style="margin:2px 0;font-size:.94rem"><span class="muted"><?= e((string) $label) ?></span>
          <strong style="float:right"><?= $n ?></strong></p>
      <?php endforeach; ?>
      <?php if ($vq['notes']): ?>
        <p class="hint" style="margin:10px 0 4px">In their words:</p>
        <?php foreach ($vq['notes'] as $nt): ?>
          <p style="margin:2px 0;font-size:.92rem">&ldquo;<?= e((string) $nt['note']) ?>&rdquo;
            <span class="hint"><?= e(substr((string) $nt['at'], 0, 10)) ?></span></p>
        <?php endforeach; ?>
      <?php endif; ?>
    </div></section>
  <?php endif; ?>

  <?php /* The operating view. A thirty day table answers "did that campaign ever work"; the
           question somebody running one actually has is "is it working now", so the same channels
           are shown over a day, a week and the whole period side by side. Same counting rules, same
           human filter: this adds no new arithmetic, it just asks three times. */ ?>
  <h2 style="margin:6px 0 4px">Acquisition, now</h2>
  <?php $ccT = $cc['totals'] ?? []; ?>
  <p class="hint" style="margin:0 0 10px">Human sessions, signups, confirmations and trips, over the
    last 24 hours, the last 7 days and the whole window. Automated traffic is excluded from every
    number here and the raw session count is in brackets where it differs.</p>
  <?php if (empty($cc['rows'])): ?>
    <p class="hint" style="margin:0 0 18px">No channel has produced anything yet, in any window.</p>
  <?php else: ?>
    <table class="table" style="margin:0 0 6px">
      <thead>
        <tr><th>Channel</th>
          <th colspan="4" style="text-align:center">Last 24 hours</th>
          <th colspan="4" style="text-align:center">Last 7 days</th>
          <th colspan="4" style="text-align:center">Window</th></tr>
        <tr><th class="hint"></th>
          <?php for ($i = 0; $i < 3; $i++): ?>
            <th class="hint" style="text-align:right">Human</th><th class="hint" style="text-align:right">Join</th>
            <th class="hint" style="text-align:right">Conf</th><th class="hint" style="text-align:right">Trip</th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cc['rows'] as $r): ?>
          <tr>
            <td><b><?= e((string) $r['source']) ?></b><?php if ($r['campaign'] !== ''): ?>
              <span class="hint"><?= e((string) $r['campaign']) ?></span><?php endif; ?>
              <?php /* Ours, so it is shown and not counted. */ ?>
              <?php if (!empty($r['internal'])): ?><span class="hint">(our own check)</span><?php endif; ?></td>
            <?php foreach (['d1', 'd7', 'all'] as $w): $x = $r[$w]; ?>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $x['human'] ?><?php
                  if ((int) $x['sessions'] !== (int) $x['human']): ?><span class="hint"> (<?= (int) $x['sessions'] ?>)</span><?php endif; ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $x['signups'] ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $x['confirmed'] ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $x['trips'] ?></b></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><b>All channels</b></td>
          <?php foreach (['d1', 'd7', 'all'] as $w): $x = $ccT[$w] ?? ['human'=>0,'signups'=>0,'confirmed'=>0,'trips'=>0]; ?>
            <td style="text-align:right"><b><?= (int) $x['human'] ?></b></td>
            <td style="text-align:right"><b><?= (int) $x['signups'] ?></b></td>
            <td style="text-align:right"><b><?= (int) $x['confirmed'] ?></b></td>
            <td style="text-align:right"><b><?= (int) $x['trips'] ?></b></td>
          <?php endforeach; ?>
        </tr>
      </tbody>
    </table>
    <p class="hint" style="margin:0 0 18px">A rate needs a denominator, so the percentages stay in the
      table below rather than being printed here off one or two sessions.
      <?php $ih = (int) ($ccT['d7']['internal_human'] ?? 0); if ($ih): ?>
        <br><b><?= $ih ?></b> human session<?= $ih === 1 ? '' : 's' ?> in the last 7 days were our own
        checks and are excluded from the totals above. They are still in the table, marked.
      <?php endif; ?></p>
  <?php endif; ?>

  <?php /* Acquisition, first, because it is the question the whole site currently turns on: did
           anything we did outside this site bring a person in. A channel is one word, decided on
           first touch and held, so the post that did the work keeps the credit. Crawlers never
           reach this table, so an arrival here is a session rather than a request. */ ?>
  <h2 style="margin:6px 0 4px">Where people came from</h2>
  <?php if (empty($acq)): ?>
    <p class="hint" style="margin:0 0 18px">Nothing has arrived with a channel on it yet. A link
      carrying <code>?utm_source=reddit&amp;utm_campaign=…</code> is counted from its first click,
      and so is any visit that arrives from a site we can name.</p>
  <?php else: ?>
    <table class="table" style="margin:0 0 18px">
      <thead><tr><th>Source</th><th>Campaign</th><th style="text-align:right">Human visits</th>
        <th style="text-align:right">Signed up</th><th style="text-align:right">Confirmed</th>
        <th style="text-align:right">Trips</th><th style="text-align:right">Visit to signup</th>
        <th style="text-align:right">Signup to trip</th><th style="text-align:right">Visit to trip</th></tr></thead>
      <tbody>
        <?php foreach ($acq as $a): ?>
          <tr>
            <td><b><?= e((string) $a['source']) ?></b></td>
            <td class="hint"><?= e((string) $a['campaign']) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $a['human'] ?></b>
              <?php if ((int) $a['sessions'] !== (int) $a['human']): ?>
                <span class="hint">of <?= (int) $a['sessions'] ?></span>
              <?php endif; ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $a['signed_up'] ?></b></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $a['confirmed'] ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $a['trips'] ?></b></td>
            <td style="text-align:right"><?= $a['visit_to_signup_pct'] === null ? '' : e((string) $a['visit_to_signup_pct']) . '%' ?></td>
            <td style="text-align:right"><?= $a['signup_to_trip_pct'] === null ? '' : e((string) $a['signup_to_trip_pct']) . '%' ?></td>
            <td style="text-align:right"><?= $a['visit_to_trip_pct'] === null ? '' : e((string) $a['visit_to_trip_pct']) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2 style="margin:6px 0 4px">The social funnel</h2>
  <p class="hint" style="margin:0 0 10px">
    Landing on a destination through to coming back. Each row is sessions that reached that step,
    and the percentage is a share of the step above. "Touched it" is a follow, an ask, a reaction
    or a comment, because the page offers several first moves and which one is a later question.
  </p>
  <table class="table" style="margin:0 0 18px">
    <tbody>
      <?php $prev = null; foreach ($social as $st): ?>
        <tr>
          <td style="width:230px"><?= e($st['label']) ?>
            <?php if ($st['note'] !== ''): ?><br><span class="hint"><?= e($st['note']) ?></span><?php endif; ?>
          </td>
          <td style="width:70px;text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $st['count'] ?></b></td>
          <td style="width:64px;text-align:right" class="hint">
            <?= $prev === null ? '' : e($pct((int) $st['count'], (int) $prev)) ?>
          </td>
          <td><?= $bar((int) $st['count'], $socialTop) ?></td>
        </tr>
      <?php $prev = (int) $st['count']; endforeach; ?>
    </tbody>
  </table>

  <h3 style="margin:14px 0 4px">Counters</h3>
  <p class="hint" style="margin:0 0 10px">Distinct sessions in this window, per event. Crawlers are
    excluded before anything is written, so these are people rather than robots.</p>
  <table class="table" style="margin:0 0 18px">
    <tbody>
      <?php
      $rows = [
        ['Destination page views',   (int) $socialC['destination_page_view']],
        ['Unique visitors (floor)',  (int) $visitors['unique']],
        ['Returning visitors (floor)', (int) $visitors['returning']],
        ['Follow pressed',           (int) $socialC['destination_follow_click']],
        ['Follows that stuck',       (int) $socialC['destination_follow_success']],
        ['Questions started',        (int) $socialC['ask_question_click']],
        ['Questions posted',         (int) $socialC['question_posted']],
        ['Posts of any kind',        (int) $socialC['post_created']],
        ['Comments',                 (int) $socialC['comment_created']],
        ['Reactions',                (int) $socialC['reaction_created']],
        ['Signup form seen',         (int) $socialC['join_view']],
        ['Signup started',           (int) $socialC['join_submit']],
        ['Signup completed',         (int) $socialC['join_created']],
        ['Signed in',                (int) $socialC['login_completed']],
        ['Trip form opened',         (int) $socialC['trip_create_started']],
        ['Trips created',            (int) $socialC['trip_created']],
        ['Profiles read',            (int) $socialC['profile_viewed']],
        ['...from a discovery page', (int) $socialC['traveler_profile_clicked']],
        ['Profile editor opened',    (int) $socialC['profile_edit_started']],
        ['Profiles filled in',       (int) $socialC['profile_completed']],
        ['Overlapping travelers seen', (int) $socialC['overlapping_traveler_viewed']],
        ['First messages sent',      (int) $socialC['message_started']],
      ];
      foreach ($rows as [$label, $n]): ?>
        <tr><td><?= e($label) ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $n ?></b></td></tr>
      <?php endforeach; ?>
      <tr><td><b>Signup conversion</b><br><span class="hint">completed as a share of started</span></td>
          <td style="text-align:right"><b><?= e($pct((int) $socialC['join_created'], (int) $socialC['join_submit']) ?: 'no data') ?></b></td></tr>
      <tr><td><b>Follow conversion</b><br><span class="hint">follows that stuck, as a share of presses</span></td>
          <td style="text-align:right"><b><?= e($pct((int) $socialC['destination_follow_success'], (int) $socialC['destination_follow_click']) ?: 'no data') ?></b></td></tr>
      <tr><td><b>Ask conversion</b><br><span class="hint">questions posted, as a share of composers opened</span></td>
          <td style="text-align:right"><b><?= e($pct((int) $socialC['question_posted'], (int) $socialC['ask_question_click']) ?: 'no data') ?></b></td></tr>
    </tbody>
  </table>

  <h3 style="margin:14px 0 4px">Destination communities by activity</h3>
  <p class="hint" style="margin:0 0 10px">Views and actions in separate columns on purpose: a city
    with traffic and no follows is a different problem from a city with neither, and one score
    would hide which one we have.</p>
  <?php if (!$topCities): ?>
    <p class="muted" style="margin:0 0 18px">Nothing recorded in this window yet.</p>
  <?php else: ?>
    <table class="table" style="margin:0 0 18px">
      <thead><tr><th>City</th><th style="text-align:right">Views</th><th style="text-align:right">Follows</th><th style="text-align:right">Questions</th></tr></thead>
      <tbody>
        <?php foreach ($topCities as $tc): ?>
          <tr>
            <td><a href="<?= e(url('d/' . $tc['slug'])) ?>"><?= e($tc['name']) ?></a></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $tc['views'] ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $tc['follows'] ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int) $tc['questions'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h3 style="margin:14px 0 4px">Which city recruited them</h3>
  <p class="hint" style="margin:0 0 10px">The city that was on screen in the same session as the
    account being created. Read from the session token this table already keeps, so nothing is
    followed across sites and no referrer is stored.</p>
  <?php if (!$attrib): ?>
    <p class="muted" style="margin:0 0 22px">No signups with a city behind them in this window.</p>
  <?php else: ?>
    <table class="table" style="margin:0 0 22px">
      <tbody>
        <?php foreach ($attrib as $at): ?>
          <tr><td><a href="<?= e(url('d/' . $at['slug'])) ?>"><?= e($at['name']) ?></a></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><b><?= (int) $at['signups'] ?></b></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

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
  <?php /* The top line is people, not requests. It used to be every signed out session that
           reached a public page, which on this site was overwhelmingly automated, so the signup
           rate was being divided by crawlers. The raw number is still here beside it, with the two
           other buckets, because a bot filter that hides what it threw away is a filter nobody can
           check. */ ?>
  <p class="hint" style="margin:-4px 0 12px">
    Of <b><?= (int) ($growth['visits_raw'] ?? 0) ?></b> signed out sessions in this window:
    <b><?= (int) ($growth['visits'] ?? 0) ?></b> look like a person,
    <b><?= (int) ($growth['visits_automated'] ?? 0) ?></b> are automated,
    <b><?= (int) ($growth['visits_uncertain'] ?? 0) ?></b> could be either and are counted as
    neither. Classified by session shape and by whether the client ever gave back a cookie; no
    address, agent or referrer is kept. The traveler figures below use the human number.
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
