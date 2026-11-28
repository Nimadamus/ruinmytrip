<?php
/**
 * Phase 1: the signup prompt a guest sees after "Save to Trip Checklist". The save is already held
 * in the session (rmt_p1_hold_save); joining or signing in lands on /checklist/claim, which writes it.
 * Without JavaScript the same prompt is the page /checklist/join (views/phase1_join.php).
 * @var array $d
 */
if (!empty($me)) return;
$qjReturn = '/checklist/claim';
?>
<dialog class="p1-dialog" id="p1-join" aria-labelledby="p1-join-h">
  <form method="dialog" class="p1-dialog-x"><button type="submit" aria-label="Close">&#10005;</button></form>
  <h2 id="p1-join-h">Save your trip checklist</h2>
  <p class="p1-join-copy" data-p1-join-copy>Create a free account to save this warning to your trip checklist and get notified if new traps are reported for <?= e((string) $d['name']) ?>.</p>
  <form method="post" action="<?= e(url('register')) ?>" class="p1-join-form"><?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($qjReturn) ?>">
    <?php include __DIR__ . '/auth/_quick_join_fields.php'; ?>
    <button class="v2-btn v2-btn-primary v2-btn-block btn btn-primary btn-block" type="submit" data-cta="p1_join">Create free account and save</button>
  </form>
  <p class="p1-muted">Already a member? <a href="<?= e(url('login?return=' . rawurlencode($qjReturn))) ?>">Log in and save</a></p>
</dialog>
