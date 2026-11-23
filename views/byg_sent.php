<?php /** @var array $s @var string $label @var bool $confirmed */
$sWhen = function_exists('rmt_byg_when') ? rmt_byg_when($s) : '';
$sBack = !empty($s['page']) ? url(ltrim((string) $s['page'], '/')) : rmt_byg_page($s);
?>
<div class="wrap" style="max-width:620px">
  <section class="card" style="margin:28px 0"><div class="card-body">
    <?php if ($confirmed): ?>
      <p class="eyebrow" style="margin:0">Reminders on</p>
      <h1 style="margin:4px 0 10px"><?= e($label) ?></h1>
      <?php if ($sWhen !== ''): ?>
        <p>We will email you 14 days and 3 days before <b><?= e($sWhen) ?></b>, and once if the official source changes before then. Every email has an off switch.</p>
      <?php else: ?>
        <p>We will email you only when this changes at the official source. At most one email a day, and every one has an off switch.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="eyebrow" style="margin:0">Sent</p>
      <h1 style="margin:4px 0 10px">Check your inbox</h1>
      <p>We emailed you <b><?= e($label) ?></b>. Press the button in that email to turn on <?= $sWhen !== '' ? 'your reminders before ' . e($sWhen) : 'an email when it changes' ?>. Nothing more is sent until you do.</p>
    <?php endif; ?>
    <p style="margin:16px 0 0;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-primary btn-sm" href="<?= e($sBack) ?>">Back to what you were reading</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('plan')) ?>">Make a before you go checklist</a>
    </p>
  </div></section>
</div>
