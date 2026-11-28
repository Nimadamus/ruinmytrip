<?php
/**
 * Phase 1: the three step trap report. Step by step with JavaScript (public/assets/js/phase1.js),
 * one plain form without it. It posts to /report-a-trap, which files it in the editor queue
 * (feedback kind trip_story); nothing is published until an editor approves it.
 * @var ?array $p1RepDest  the city, or null to show a city picker
 * @var list<array> $p1Dests  cities for the picker (only when $p1RepDest is null)
 */
$p1RepDest = $p1RepDest ?? null;
?>
<form method="post" action="<?= e(url('report-a-trap')) ?>" class="p1-rep-form" data-p1-steps novalidate><?= csrf_field() ?>
  <div class="hp-field" aria-hidden="true"><label>Leave this empty <input type="text" name="hp_note" value="" tabindex="-1" autocomplete="off"></label></div>
  <?php if ($p1RepDest): ?>
  <input type="hidden" name="dest" value="<?= e($p1RepDest['slug']) ?>">
  <?php endif; ?>
  <ol class="p1-steps-bar" aria-hidden="true"><li class="on">Type</li><li>What happened</li><li>Cost</li></ol>
  <fieldset class="p1-step" data-step="1">
    <legend>Step 1 of 3: what kind of trap was it?</legend>
    <?php if (!$p1RepDest): ?>
    <label for="p1-rep-dest">City</label>
    <select id="p1-rep-dest" name="dest" required>
      <option value="">Choose a city</option>
      <?php foreach ($p1Dests ?? [] as $o): ?><option value="<?= e($o['slug']) ?>"<?= input('d') === $o['slug'] ? ' selected' : '' ?>><?= e($o['name'] . ((string) $o['country'] !== '' ? ', ' . $o['country'] : '')) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div class="p1-cats">
      <?php foreach (RMT_P1_REPORT_CATS as $rk => $rc): ?>
      <label class="p1-cat"><input type="radio" name="category" value="<?= e($rk) ?>" required> <span><?= e($rc['label']) ?></span></label>
      <?php endforeach; ?>
    </div>
    <button type="button" class="v2-btn v2-btn-primary btn btn-primary p1-next" data-next hidden>Next</button>
  </fieldset>
  <fieldset class="p1-step" data-step="2">
    <legend>Step 2 of 3: what happened?</legend>
    <label for="p1-rep-what" class="p1-muted">Two or three sentences: what happened, and how the next traveler can avoid it.</label>
    <textarea id="p1-rep-what" name="what" rows="4" minlength="<?= RMT_P1_REPORT_MIN ?>" maxlength="<?= RMT_P1_REPORT_MAX ?>" required></textarea>
    <div class="p1-step-nav" hidden><button type="button" class="v2-btn v2-btn-ghost btn btn-ghost" data-back>Back</button><button type="button" class="v2-btn v2-btn-primary btn btn-primary" data-next>Next</button></div>
  </fieldset>
  <fieldset class="p1-step" data-step="3">
    <legend>Step 3 of 3: roughly what did it cost you? <span class="p1-muted">(optional)</span></legend>
    <label for="p1-rep-cost" class="p1-muted">Money or time, for example "40 euros" or "a 2 hour wait".</label>
    <input type="text" id="p1-rep-cost" name="cost" maxlength="60" autocomplete="off">
    <div class="p1-step-nav"><button type="button" class="v2-btn v2-btn-ghost btn btn-ghost" data-back hidden>Back</button><button type="submit" class="v2-btn v2-btn-primary btn btn-primary" data-cta="p1_report_send">Send report</button></div>
    <p class="p1-muted p1-small">An editor checks every report before it appears. No account needed.</p>
  </fieldset>
</form>
