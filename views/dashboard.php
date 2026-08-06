<?php
/**
 * The member dashboard — the thing an account is actually for.
 *
 * Four tabs, in the order a traveler cares about them: my trips (and what changed), my submitted
 * reports (and their moderation status), saved destinations, and email settings. The "what
 * changed" count is computed before last_seen_at is stamped, so the badge you see is the set the
 * links below it lead to.
 *
 * @var array $me @var array $trips @var array $follows @var array $saved
 * @var array $myWarnings @var array $subs @var string $tab
 */
$tabs = ['trips' => 'Trips & alerts', 'reports' => 'My reports', 'saved' => 'Saved destinations', 'alerts' => 'Email settings'];
$totalNew = 0;
foreach ($trips as $t) $totalNew += count($t['new_warnings']);
foreach ($follows as $f) $totalNew += count($f['new_warnings']);
?>
<div class="wrap" style="min-height:60vh">
  <div class="section-head" style="margin-top:24px">
    <div>
      <p class="eyebrow">Signed in as @<?= e($me['username']) ?></p>
      <h1 style="margin:0">Your travel dashboard</h1>
      <?php if ($totalNew > 0): ?>
        <p style="margin:.4rem 0 0"><span class="badge-new"><?= (int) $totalNew ?> new</span>
          <span class="muted">warnings for destinations you are watching since your last visit.</span></p>
      <?php endif; ?>
    </div>
    <a class="btn btn-accent" href="<?= e(url('warning/new')) ?>">Share a warning</a>
  </div>

  <nav class="dash-tabs" aria-label="Dashboard sections">
    <?php foreach ($tabs as $k => $label): ?>
      <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('dashboard?tab=' . $k)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php /* ------------------------------------------------ TRIPS ------- */ ?>
  <?php if ($tab === 'trips'): ?>
    <h2 style="font-size:1.25rem">Upcoming trips</h2>
    <?php if ($trips): ?>
      <?php foreach ($trips as $t): ?>
        <article class="trip-card">
          <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <div style="flex:1;min-width:260px">
              <h3><a href="<?= e(url('d/' . $t['dest_slug'])) ?>"><?= e($t['label'] ?: $t['dest_name']) ?></a>
                <?php if (count($t['new_warnings'])): ?><span class="badge-new"><?= count($t['new_warnings']) ?> new</span><?php endif; ?>
              </h3>
              <p class="muted" style="margin:0;font-size:.9rem">
                <?= e($t['dest_name']) ?>, <?= e($t['dest_country']) ?>
                <?php if (!empty($t['date_from'])): ?>
                  · <?= e(date('M j, Y', strtotime((string) $t['date_from']))) ?><?php if (!empty($t['date_to'])): ?>–<?= e(date('M j, Y', strtotime((string) $t['date_to']))) ?><?php endif; ?>
                  <?php $days = (int) floor((strtotime((string) $t['date_from']) - time()) / 86400); ?>
                  <?php if ($days > 0): ?><span class="chip chip-cat"><?= $days ?> days away</span><?php endif; ?>
                <?php else: ?>
                  · <span class="muted">no dates set</span>
                <?php endif; ?>
                · alerts <?= e($t['alert_frequency']) ?>
              </p>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(url('watchlist/' . (int) $t['id'] . '/edit')) ?>">Edit</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('d/' . $t['dest_slug'] . '/warnings')) ?>">All warnings</a>
            </div>
          </div>

          <?php if ($t['new_warnings']): ?>
            <div style="margin-top:12px">
              <p class="eyebrow" style="margin:0 0 6px">New since your last visit</p>
              <?php foreach ($t['new_warnings'] as $nw): ?>
                <p style="margin:.25rem 0;font-size:.92rem">
                  <span class="sev <?= e(rmt_severity_class((int) $nw['severity'])) ?>"><?= e(rmt_severity_label((int) $nw['severity'])) ?></span>
                  <a href="<?= e(url(ltrim(rmt_warning_path($nw), '/'))) ?>"><?= e($nw['title']) ?></a>
                  <span class="muted">· <?= e(rmt_warning_category_label($nw['category'])) ?></span>
                </p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($t['prep']): ?>
            <p class="eyebrow" style="margin:14px 0 0">Recommended preparation</p>
            <ul class="prep-list">
              <?php foreach ($t['prep'] as $p): ?>
                <li class="<?= !empty($p['urgent']) ? 'urgent' : '' ?>">
                  <?= e($p['label']) ?> <span class="muted">— <?= e($p['why']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-cta">
        <h3>No trips saved yet</h3>
        <p class="muted">Save a destination with your travel dates and we will tell you when something serious
          is reported for it before you go — plus a preparation checklist built from the actual warnings
          for that place, not a generic packing list.</p>
        <a class="btn btn-primary" style="margin-top:12px" href="<?= e(url('explore')) ?>">Find a destination</a>
      </div>
    <?php endif; ?>

    <h2 style="font-size:1.25rem;margin-top:34px">Destinations you follow</h2>
    <?php if ($follows): ?>
      <div class="grid g-3">
        <?php foreach ($follows as $f): ?>
          <div class="card"><div class="card-body">
            <h3 style="font-size:1.05rem"><a href="<?= e(url('d/' . $f['dest_slug'])) ?>"><?= e($f['dest_name']) ?></a>
              <?php if (count($f['new_warnings'])): ?><span class="badge-new"><?= count($f['new_warnings']) ?></span><?php endif; ?>
            </h3>
            <p class="muted" style="margin:0;font-size:.88rem"><?= e($f['dest_country']) ?></p>
            <?php foreach ($f['new_warnings'] as $nw): ?>
              <p style="margin:.35rem 0 0;font-size:.88rem"><a href="<?= e(url(ltrim(rmt_warning_path($nw), '/'))) ?>"><?= e($nw['title']) ?></a></p>
            <?php endforeach; ?>
          </div></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">You are not following any destinations. Follow one to hear about it without committing to dates.</p>
    <?php endif; ?>

  <?php /* ---------------------------------------------- REPORTS ------- */ ?>
  <?php elseif ($tab === 'reports'): ?>
    <h2 style="font-size:1.25rem">Warnings you have submitted</h2>
    <p class="muted" style="max-width:66ch">Every submission is read by a moderator before it appears publicly.
      A rejection or revision request always comes with a reason.</p>
    <?php if ($myWarnings): ?>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>Warning</th><th>Destination</th><th>Status</th><th>Verification</th><th>Helpful</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($myWarnings as $w): ?>
            <tr>
              <td><a href="<?= e(url(ltrim(rmt_warning_path($w), '/'))) ?>"><?= e($w['title']) ?></a>
                <?php if (!empty($w['moderation_note'])): ?>
                  <p class="hint" style="margin:.3rem 0 0"><b>Moderator:</b> <?= e((string) $w['moderation_note']) ?></p>
                <?php endif; ?>
              </td>
              <td><?= e($w['dest_name']) ?></td>
              <td>
                <?php $st = (string) $w['status']; ?>
                <span class="chip <?= $st === 'approved' ? '' : 'chip-muted' ?>"><?= e(str_replace('_', ' ', $st)) ?></span>
              </td>
              <td><span class="trust trust-<?= e((string) $w['verification']) ?>"><?= e(ucfirst((string) $w['verification'])) ?></span></td>
              <td><?= (int) $w['helpful_count'] ?></td>
              <td><?= e(date('M j, Y', strtotime((string) $w['created_at']))) ?></td>
              <td><a href="<?= e(url('warning/' . (int) $w['id'] . '/edit')) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <div class="empty-cta">
        <h3>You have not submitted a warning yet</h3>
        <p class="muted">If something on a trip cost you money, time or a whole day, writing it down here is the
          difference between the next traveler walking into it and walking around it.</p>
        <a class="btn btn-accent" style="margin-top:12px" href="<?= e(url('warning/new')) ?>">Share a warning</a>
      </div>
    <?php endif; ?>

  <?php /* ------------------------------------------------ SAVED ------- */ ?>
  <?php elseif ($tab === 'saved'): ?>
    <h2 style="font-size:1.25rem">Saved destinations</h2>
    <?php if ($saved): ?>
      <div class="grid g-4">
        <?php foreach ($saved as $d): ?>
          <article class="card">
            <a href="<?= e(url('d/' . $d['slug'])) ?>">
              <img class="card-media" loading="lazy" decoding="async" width="280" height="175"
                   src="<?= e($d['hero_url'] ?: url('assets/img/og-default.svg')) ?>" alt="<?= e($d['name']) ?>">
            </a>
            <div class="card-body">
              <h3 style="font-size:1.05rem"><a href="<?= e(url('d/' . $d['slug'])) ?>"><?= e($d['name']) ?></a></h3>
              <p class="muted" style="margin:0;font-size:.86rem"><?= e($d['country']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">Nothing saved yet. Use “Save” on any destination page to keep it here.</p>
    <?php endif; ?>

  <?php /* ----------------------------------------------- ALERTS ------- */ ?>
  <?php else: ?>
    <h2 style="font-size:1.25rem">Email settings</h2>
    <p class="muted" style="max-width:66ch">Alert frequency is set per trip, not globally, so a trip next week can
      be on immediate alerts while a trip next year stays weekly. Change it from
      <a href="<?= e(url('dashboard?tab=trips')) ?>">each trip</a>.</p>

    <?php if ($trips): ?>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>Trip</th><th>Frequency</th><th>Minimum severity</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($trips as $t): ?>
            <tr>
              <td><?= e($t['label'] ?: $t['dest_name']) ?></td>
              <td><?= e(RMT_ALERT_FREQUENCIES[$t['alert_frequency']] ?? $t['alert_frequency']) ?></td>
              <td><?= e(rmt_severity_label((int) $t['min_severity'])) ?> and above</td>
              <td><a href="<?= e(url('watchlist/' . (int) $t['id'] . '/edit')) ?>">Change</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>

    <h3 style="font-size:1.05rem;margin-top:26px">Standalone email subscriptions</h3>
    <?php if ($subs): ?>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>Destination</th><th>Frequency</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($subs as $s): ?>
            <tr>
              <td><?= e($s['dest_name'] ?: 'All destinations') ?></td>
              <td><?= e($s['frequency']) ?></td>
              <td><?= $s['confirmed_at'] ? 'Confirmed' : 'Awaiting confirmation' ?></td>
              <td><a href="<?= e(rmt_alert_unsubscribe_url($s)) ?>">Unsubscribe</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php else: ?>
      <p class="muted">No standalone email subscriptions. <a href="<?= e(url('alerts')) ?>">Add one</a>.</p>
    <?php endif; ?>
  <?php endif; ?>

  <div style="height:50px"></div>
</div>
