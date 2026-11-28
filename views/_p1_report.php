<?php
/** Phase 1: the question at the end of every city page, opening the three step report. @var array $d */
$p1RepDest = $d;
?>
<section class="p1-report" id="report" aria-labelledby="p1-rep-h">
  <h2 id="p1-rep-h">Have you been to <?= e((string) $d['name']) ?>? Did any surprise fee or trap catch you off guard?</h2>
  <details class="p1-rep-open"<?= input('report') === '1' ? ' open' : '' ?>>
    <summary class="v2-btn v2-btn-primary btn btn-primary" data-cta="p1_report_yes">Yes, tell the next traveler</summary>
    <?php include __DIR__ . '/_p1_report_form.php'; ?>
  </details>
</section>
