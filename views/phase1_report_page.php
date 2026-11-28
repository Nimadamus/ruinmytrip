<?php
/**
 * GET /report-a-trap: the header's "Report a Trap". After a guest sends one, the same page thanks
 * them and offers an account to claim the Founding Traveler badge; the report waits in the editor
 * queue either way.
 * @var ?array $d @var list<array> $dests @var bool $done
 */
$p1RepDest = null; $p1Dests = $dests;
?>
<div class="wrap p1-page">
  <?php if ($done): ?>
  <div class="card p1-done">
    <h1>Thank you. Your report is in.</h1>
    <p>An editor checks it before it appears<?= $d ? ' on the ' . e((string) $d['name']) . ' page' : '' ?>. Nothing is published until then.</p>
    <?php if (!is_logged_in()): ?>
    <h2>Claim your Founding Traveler badge</h2>
    <p>Add an email so this report is yours, see when it goes live, and track how many travelers it helps. Free, takes a minute.</p>
    <p class="p1-acts"><a class="btn btn-primary" data-cta="p1_report_claim" href="<?= e(url('register?return=' . rawurlencode('/checklist/claim'))) ?>">Create free account</a>
      <a class="btn btn-ghost" href="<?= e(url('login?return=' . rawurlencode('/checklist/claim'))) ?>">Log in</a></p>
    <p class="p1-muted"><a href="<?= e(url('founding')) ?>">What is a Founding Traveler?</a></p>
    <?php endif; ?>
    <?php if ($d): ?><p><a href="<?= e(url('d/' . $d['slug'])) ?>">Back to <?= e((string) $d['name']) ?></a></p><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="card p1-report p1-report-page">
    <h1>Report a trap</h1>
    <p class="p1-muted">A surprise fee, a scam, a spot that was not worth the queue, a local rule nobody told you about. Warn the next traveler in three quick steps.</p>
    <?php include __DIR__ . '/_p1_report_form.php'; ?>
  </div>
  <?php endif; ?>
</div>
