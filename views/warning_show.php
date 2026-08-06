<?php
/**
 * A single warning.
 *
 * The layout is built around a legal and ethical constraint as much as a design one: this page
 * may name a business, so it must never read as RuinMyTrip asserting a fact about that business.
 * The verification state is stated in words next to the title, an explicit "what this is" note
 * sits under the byline, and the business's own response — if it filed one — renders inline at
 * the same visual weight as the report.
 *
 * @var array $w @var array $photos @var array $responses @var ?string $myVote @var array $related
 * @var ?array $me @var array $contributor @var array $modLog
 */
$sev = (int) $w['severity'];
$ver = (string) $w['verification'];
$selfUrl = url(ltrim(rmt_warning_path($w), '/'));
?>
<div class="wrap prose" style="max-width:820px">
  <p class="crumbs">
    <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('d/' . $w['dest_slug'])) ?>"><?= e($w['dest_name']) ?></a> /
    <a href="<?= e(url('d/' . $w['dest_slug'] . '/warnings')) ?>">Warnings</a>
  </p>

  <?php if ($w['status'] !== 'approved'): ?>
    <div class="callout warn">
      <b>This warning is <?= e(str_replace('_', ' ', (string) $w['status'])) ?>.</b>
      Only you and our moderators can see it.
      <?php if (!empty($w['moderation_note'])): ?>
        <p style="margin:.5rem 0 0"><b>Moderator note:</b> <?= e((string) $w['moderation_note']) ?></p>
      <?php endif; ?>
      <?php if ($w['status'] === 'needs_revision'): ?>
        <p style="margin:.5rem 0 0"><a class="btn btn-accent btn-sm" href="<?= e(url('warning/' . (int) $w['id'] . '/edit')) ?>">Edit and resubmit</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="warn-meta" style="margin-bottom:.6rem">
    <span class="sev <?= e(rmt_severity_class($sev)) ?>"><?= e(rmt_severity_label($sev)) ?></span>
    <span class="chip chip-cat"><?= rmt_warning_category_icon($w['category']) ?> <?= e(rmt_warning_category_label($w['category'])) ?></span>
    <span class="trust trust-<?= e($ver) ?>"><?= e(ucfirst($ver)) ?></span>
    <a href="<?= e(url('d/' . $w['dest_slug'])) ?>"><?= e($w['dest_name']) ?>, <?= e($w['dest_country']) ?></a>
  </div>

  <h1><?= e($w['title']) ?></h1>

  <p class="muted" style="margin:.2rem 0 0">
    by <a href="<?= e(url('u/' . $w['username'])) ?>">@<?= e($w['username']) ?></a>
    <?php if ($contributor['approved'] > 1): ?>
      <span class="chip chip-muted"><?= (int) $contributor['approved'] ?> published warnings<?php if ($contributor['helpful'] > 0): ?> · <?= (int) $contributor['helpful'] ?> helpful votes<?php endif; ?></span>
    <?php else: ?>
      <span class="chip chip-muted">First published warning</span>
    <?php endif; ?>
  </p>
  <p class="muted" style="margin:.3rem 0 0;font-size:.9rem">
    <?php if (!empty($w['date_experienced'])): ?><b>Experienced:</b> <?= e(rmt_experienced_label($w['date_experienced'])) ?> · <?php endif; ?>
    <b>Submitted:</b> <?= e(date('F j, Y', strtotime((string) $w['created_at']))) ?>
    <?php if (!empty($w['updated_at']) && substr((string) $w['updated_at'], 0, 10) !== substr((string) $w['created_at'], 0, 10)): ?>
      · <b>Updated:</b> <?= e(date('F j, Y', strtotime((string) $w['updated_at']))) ?>
    <?php endif; ?>
  </p>

  <?php /* What this is, in one sentence, immediately under the byline. Nothing on this page may
           be mistaken for a verified statement of fact unless a moderator has said it is. */ ?>
  <div class="callout" style="margin-top:16px">
    <?php if ($ver === 'verified'): ?>
      <b>Verified report.</b> A moderator checked this account against an independent source
      (an official notice, a receipt, or corroborating reports). It remains one traveler's experience.
    <?php elseif ($ver === 'disputed'): ?>
      <b>Disputed report.</b> This account has been contested — by the business named, or by other
      travelers whose experience differs. Read it alongside any response below.
    <?php else: ?>
      <b>Unverified traveler report.</b> This is one person's first-hand account, reviewed by a moderator
      for good faith and clarity but not independently confirmed. Treat it as a warning to check, not as
      an established fact.
    <?php endif; ?>
  </div>

  <?php if (rmt_warning_is_stale($w)): ?>
    <div class="callout warn"><b>Over a year old.</b> Prices, routes, rules and closures change fast.
      Verify anything time-sensitive before you rely on it.</div>
  <?php endif; ?>

  <h2>What happened</h2>
  <div style="white-space:pre-wrap"><?= nl2br(e((string) $w['body'])) ?></div>

  <?php if (!empty($w['advice'])): ?>
    <div class="warn-advice" style="margin-top:20px">
      <b>How to avoid it</b>
      <div style="white-space:pre-wrap;margin-top:.3rem"><?= nl2br(e((string) $w['advice'])) ?></div>
    </div>
  <?php endif; ?>

  <?php if ($photos): ?>
    <div class="grid g-2" style="margin:20px 0">
      <?php foreach ($photos as $ph): ?>
        <figure style="margin:0">
          <img style="border-radius:12px" loading="lazy" decoding="async" src="<?= e($ph['url']) ?>"
               width="<?= (int) ($ph['width'] ?: 800) ?>" height="<?= (int) ($ph['height'] ?: 600) ?>"
               alt="<?= e($ph['caption'] ?: ('Photo submitted with this warning about ' . $w['dest_name'])) ?>">
          <?php if (!empty($ph['caption'])): ?><figcaption class="hint"><?= e($ph['caption']) ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- The structured detail -->
  <table class="tbl" style="margin:24px 0">
    <tbody>
      <tr><th style="width:200px">Destination</th><td><a href="<?= e(url('d/' . $w['dest_slug'])) ?>"><?= e($w['dest_name']) ?>, <?= e($w['dest_country']) ?></a></td></tr>
      <tr><th>Category</th><td><a href="<?= e(url('warnings/' . $w['category'])) ?>"><?= e(rmt_warning_category_label($w['category'])) ?></a></td></tr>
      <tr><th>Severity</th><td><?= e(rmt_severity_label($sev)) ?> — <?= e(RMT_WARNING_SEVERITIES[$sev]['desc'] ?? '') ?></td></tr>
      <?php if (!empty($w['location_detail'])): ?><tr><th>Where exactly</th><td><?= e($w['location_detail']) ?></td></tr><?php endif; ?>
      <?php if ($w['cost_impact_usd'] !== null && $w['cost_impact_usd'] !== ''): ?>
        <tr><th>Estimated cost</th><td class="warn-cost">About $<?= number_format((int) $w['cost_impact_usd']) ?> (the traveler's own estimate)</td></tr>
      <?php endif; ?>
      <?php if (!empty($w['provider_name'])): ?>
        <tr><th>Business involved</th><td><?= e($w['provider_name']) ?><?php if (!empty($w['provider_type'])): ?>
          <span class="muted">(<?= e(RMT_PROVIDER_TYPES[$w['provider_type']] ?? $w['provider_type']) ?>)</span><?php endif; ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($w['traveler_type'])): ?><tr><th>Traveler type</th><td><?= e(rmt_traveler_type_label($w['traveler_type'])) ?></td></tr><?php endif; ?>
      <?php if (!empty($w['season_month'])): ?><tr><th>Season</th><td><a href="<?= e(url('d/' . $w['dest_slug'] . '/warnings?month=' . (int) $w['season_month'])) ?>"><?= e(date('F', mktime(0, 0, 0, (int) $w['season_month'], 1))) ?></a></td></tr><?php endif; ?>
    </tbody>
  </table>

  <!-- Helpfulness -->
  <div class="warn-foot" style="border-top:0">
    <?php if ($me && (int) $me['id'] !== (int) $w['user_id'] && $w['status'] === 'approved'): ?>
      <form method="post" action="<?= e(url('warning/' . (int) $w['id'] . '/helpful')) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="return" value="<?= e(rmt_warning_path($w)) ?>">
        <button class="btn <?= $myVote === 'helpful' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" name="vote" value="helpful">
          👍 Helpful (<?= (int) $w['helpful_count'] ?>)
        </button>
      </form>
      <form method="post" action="<?= e(url('warning/' . (int) $w['id'] . '/helpful')) ?>" class="inline-form">
        <?= csrf_field() ?><input type="hidden" name="return" value="<?= e(rmt_warning_path($w)) ?>">
        <button class="btn <?= $myVote === 'not_helpful' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" name="vote" value="not_helpful">
          Not useful
        </button>
      </form>
    <?php else: ?>
      <span class="muted"><?= (int) $w['helpful_count'] ?> travelers found this helpful</span>
      <?php if (!$me): ?><a href="<?= e(url('login?return=' . rawurlencode(rmt_warning_path($w)))) ?>">Sign in to vote</a><?php endif; ?>
    <?php endif; ?>

    <?php if (rmt_warning_can_edit($w, $me)): ?>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('warning/' . (int) $w['id'] . '/edit')) ?>">Edit</a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('report?type=warning&id=' . (int) $w['id'])) ?>">Report abuse</a>
    <?php $outdatedTarget = 'warning'; $outdatedId = (int) $w['id']; $outdatedReturn = rmt_warning_path($w);
          include __DIR__ . '/_outdated_button.php'; ?>
  </div>

  <!-- Right of reply -->
  <section style="margin-top:34px">
    <h2>Responses</h2>
    <?php if ($responses): ?>
      <?php foreach ($responses as $r): ?>
        <div class="card" style="margin-bottom:12px;border-left:4px solid var(--brand)"><div class="card-body">
          <p style="margin:0"><b><?= e($r['responder_name']) ?></b>
            <?php if (!empty($r['responder_role'])): ?><span class="muted">· <?= e($r['responder_role']) ?></span><?php endif; ?>
            <span class="chip chip-cat">Business response</span></p>
          <p class="hint" style="margin:.2rem 0 .6rem">Verified contact, posted <?= e(ago((string) $r['created_at'])) ?>. RuinMyTrip does not endorse either account.</p>
          <div style="white-space:pre-wrap"><?= nl2br(e((string) $r['body'])) ?></div>
        </div></div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="muted">No response has been filed.</p>
    <?php endif; ?>
    <?php if (!empty($w['provider_name'])): ?>
      <p><a class="btn btn-ghost btn-sm" href="<?= e(url('w/' . (int) $w['id'] . '/respond')) ?>">Is this your business? Post a response</a></p>
    <?php endif; ?>
  </section>

  <?php if ($modLog): ?>
    <section style="margin-top:30px">
      <h2 style="font-size:1.1rem">Moderation history <span class="chip chip-muted">Moderators only</span></h2>
      <div class="table-scroll"><table class="tbl">
        <thead><tr><th>When</th><th>Field</th><th>Change</th><th>By</th><th>Note</th></tr></thead>
        <tbody>
          <?php foreach ($modLog as $l): ?>
            <tr><td><?= e(ago((string) $l['created_at'])) ?></td><td><?= e($l['field']) ?></td>
              <td><?= e((string) $l['from_value']) ?> → <b><?= e((string) $l['to_value']) ?></b></td>
              <td>@<?= e((string) $l['actor_username']) ?></td><td><?= e((string) $l['note']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  <?php endif; ?>

  <?php if ($related): ?>
    <section style="margin-top:34px">
      <h2>More <?= e(mb_strtolower(rmt_warning_category_label($w['category']))) ?> warnings in <?= e($w['dest_name']) ?></h2>
      <?php foreach ($related as $r2) { $w2 = $w; $w = $r2; $showDest = false; include __DIR__ . '/_warning_card.php'; $w = $w2; } ?>
    </section>
  <?php endif; ?>

  <div class="empty-cta" style="margin-top:30px;text-align:left">
    <h3 style="font-size:1.15rem">Been to <?= e($w['dest_name']) ?>?</h3>
    <p class="muted">If something went wrong for you too — or if this is out of date now — say so. Corroboration
      is how an unverified report becomes a verified one.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
      <a class="btn btn-accent" href="<?= e(url('warning/new?destination=' . (int) $w['destination_id'] . '&category=' . $w['category'])) ?>">Add your warning</a>
      <a class="btn btn-ghost" href="<?= e(url('d/' . $w['dest_slug'])) ?>">Full <?= e($w['dest_name']) ?> risk report</a>
    </div>
  </div>
  <div style="height:40px"></div>
</div>
