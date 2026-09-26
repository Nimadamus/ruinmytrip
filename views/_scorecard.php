<?php
/** @var array $scorecard  from rmt_growth_scorecard()
 *
 * The loop, first on the funnel page: visitor, member, first contribution, returning. Every number
 * is a count; a rate is printed only when its denominator is above zero.
 */
$sc = $scorecard;
$pct = static fn($v): string => $v === null ? 'n/a' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
$tile = static function (string $label, $n, string $hint = ''): string {
    return '<div class="sc-t"><b>' . e(is_int($n) ? number_format($n) : (string) $n) . '</b><span>' . e($label) . '</span>'
         . ($hint !== '' ? '<small class="hint">' . e($hint) . '</small>' : '') . '</div>';
};
$list = static function (array $rows, string $key, string $empty): string {
    if (!$rows) return '<p class="hint" style="margin:0">' . e($empty) . '</p>';
    $h = '<ol style="margin:0;padding-left:1.2rem">';
    foreach ($rows as $r) $h .= '<li><code>' . e((string) $r[$key]) . '</code> <b>' . (int) ($r['n'] ?? $r['signups'] ?? 0) . '</b></li>';
    return $h . '</ol>';
};
$v = $sc['visitors']; $m = $sc['members']; $c = $sc['content']; $p = $sc['plan']; $s = $sc['signup']; $r = $sc['rates'];
?>
<style>
.sc{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin:0 0 14px}
.sc-t{border:1px solid var(--line);border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:2px;background:var(--card)}
.sc-t b{font-size:1.4rem}.sc-t span{font-weight:600;font-size:.85rem}
.sc-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:0 0 26px}
</style>
<section style="margin:0 0 30px">
  <h2 style="margin:0 0 4px">Growth scorecard</h2>
  <p class="hint" style="margin:0 0 12px">Visitor, member, first contribution, returning. House accounts and self checks are left out.
    "Engaged" means somebody tapped, typed or scrolled<?= $v['engaged_measured_since'] ? ', measured since ' . e(date('j M Y H:i', strtotime((string) $v['engaged_measured_since']) ?: time())) . ' server time' : ', not measured yet' ?>.</p>

  <h3 style="margin:0 0 6px;font-size:1rem">Visitors</h3>
  <div class="sc">
    <?= $tile('Engaged visitors', (int) $v['engaged'], 'tapped, typed or scrolled') ?>
    <?= $tile('Likely human', (int) $v['likely_human'], 'returned a cookie') ?>
    <?= $tile('Search landings', (int) $v['search_landed'], 'referrer said a search engine') ?>
    <?= $tile('Engaged from search', (int) $v['search_engaged'], 'compare with Search Console clicks') ?>
  </div>

  <h3 style="margin:0 0 6px;font-size:1rem">Members</h3>
  <div class="sc">
    <?= $tile('Registered', (int) $m['registered']) ?>
    <?= $tile('Confirmed email', (int) $m['confirmed']) ?>
    <?= $tile('Made a first contribution', (int) $m['first_contribution'], 'trip, post, comment, review or buddy post') ?>
    <?= $tile('Came back another day', (int) $m['returned_another_day']) ?>
    <?= $tile('Active members', (int) $m['active'], 'made something in the window') ?>
  </div>

  <h3 style="margin:0 0 6px;font-size:1rem">Rates</h3>
  <div class="sc">
    <?= $tile('Visitor to member', $pct($r['visitor_to_member_pct']), 'of ' . ($r['visitor_base'] === 'engaged' ? 'engaged' : 'likely human') . ' visitors') ?>
    <?= $tile('Member to first contribution', $pct($r['member_to_contribution_pct'])) ?>
    <?= $tile('Member to returning', $pct($r['member_to_returning_pct'])) ?>
    <?= $tile('Trip form to submitted', $pct($r['plan_view_to_submit_pct'])) ?>
    <?= $tile('Submitted trip to account', $pct($r['plan_submit_to_account_pct'])) ?>
    <?= $tile('Join form to account', $pct($r['join_view_to_account_pct'])) ?>
  </div>

  <h3 style="margin:0 0 6px;font-size:1rem">Trip first funnel (attempts)</h3>
  <div class="sc">
    <?= $tile('Social CTA clicked', (int) $p['cta_clicks']) ?>
    <?= $tile('Trip form seen', (int) $p['plan_view']) ?>
    <?= $tile('Started filling it', (int) $p['plan_started']) ?>
    <?= $tile('Trip submitted', (int) $p['plan_submitted']) ?>
    <?= $tile('Questions written signed out', (int) $p['questions_held']) ?>
    <?= $tile('Account step seen', (int) $p['plan_signup_view'], 'trip or question') ?>
    <?= $tile('Create account pressed', (int) $p['join_submit']) ?>
    <?= $tile('Account created', (int) $p['join_created']) ?>
    <?= $tile('Trips published', (int) $p['trips_published'], 'all routes') ?>
    <?= $tile('Buddy posts published', (int) $p['buddy_posts'], 'all routes') ?>
  </div>

  <h3 style="margin:0 0 6px;font-size:1rem">Content by real members</h3>
  <div class="sc">
    <?= $tile('Trips created', (int) $c['trips']) ?>
    <?= $tile('Public upcoming trips', (int) $c['public_upcoming'], 'right now') ?>
    <?= $tile('Buddy listings', (int) $c['buddy_posts']) ?>
    <?= $tile('Open buddy listings', (int) $c['buddy_open'], 'right now') ?>
    <?= $tile('Posts', (int) $c['posts']) ?>
    <?= $tile('Comments', (int) $c['comments']) ?>
    <?= $tile('Reviews', (int) $c['reviews']) ?>
    <?= $tile('Join form seen', (int) $s['join_view'], 'attempts, bots included') ?>
  </div>

  <h3 style="margin:0 0 6px;font-size:1rem">By channel (browsers, first touch)</h3>
  <div style="overflow-x:auto;margin:0 0 20px"><table class="table" style="width:100%;font-size:.9rem">
    <tr><th style="text-align:left">Channel</th><th>Landed</th><th>Engaged</th><th>Trip form</th><th>Acted</th><th>Signup started</th><th>Signed up</th><th>First contribution</th><th>Came back</th><th>Engaged to member</th></tr>
    <?php foreach ($sc['by_source'] ?? [] as $bs): ?>
      <tr><td><b><?= e($bs['source'] === 'search' ? 'search (Google, Bing...)' : $bs['source']) ?></b></td>
        <?php foreach (['landed', 'engaged', 'trip_form', 'acted', 'signup_started', 'signup_completed', 'contributed', 'returned'] as $k): ?>
          <td style="text-align:center"><?= (int) $bs[$k] ?></td><?php endforeach; ?>
        <td style="text-align:center"><?= e($pct($bs['engaged_to_member_pct'])) ?></td></tr>
    <?php endforeach; ?>
  </table></div>

  <div class="sc-cols">
    <div><h3 style="margin:0 0 6px;font-size:1rem">Landing pages that produced members</h3><?= $list($sc['landing_members'], 'path', 'None yet.') ?></div>
    <div><h3 style="margin:0 0 6px;font-size:1rem">Landing pages that produced trips</h3><?= $list($sc['landing_trips'], 'path', 'None yet.') ?></div>
    <div><h3 style="margin:0 0 6px;font-size:1rem">Where engaged visitors land</h3><?= $list($sc['landing_engaged'], 'path', 'Nothing measured yet.') ?></div>
    <div><h3 style="margin:0 0 6px;font-size:1rem">Cities that produced members</h3><?= $list($sc['destinations_members'], 'name', 'None yet.') ?></div>
    <div><h3 style="margin:0 0 6px;font-size:1rem">Calls to action pressed</h3><?= $list($sc['cta'], 'cta', 'None yet.') ?></div>
  </div>
</section>
