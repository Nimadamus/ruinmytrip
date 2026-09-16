<?php
/**
 * One question, in the flow, with a skip that is as easy as answering.
 *
 * @var string $vqKey  a key from RMT_VQ_QUESTIONS
 *
 * Shown only to somebody signed in who has not answered it, because asking twice is a nag and asking
 * an anonymous visitor gives an answer we cannot tie to anything they did.
 */
$vqKey = (string) ($vqKey ?? '');
$fbMe = function_exists('current_user') ? current_user() : null;
if ($vqKey === '' || !isset(RMT_VQ_QUESTIONS[$vqKey]) || !$fbMe) return;
if (rmt_vq_answered((int) $fbMe['id'], $vqKey)) return;
$fbQ = RMT_VQ_QUESTIONS[$vqKey];
?>
<details class="vq-prompt card" style="margin:16px 0"><summary class="card-body" style="cursor:pointer">
  <b><?= e((string) $fbQ['question']) ?></b>
  <span class="hint" style="display:block;margin-top:4px">One tap, and it helps more than you would think.</span>
</summary>
<div class="card-body" style="padding-top:0">
  <form method="post" action="<?= e(url('answer')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="question" value="<?= e($vqKey) ?>">
    <input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/matches')) ?>">
    <p style="margin:0 0 8px;display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($fbQ['answers'] as $key => $label): ?>
        <button class="btn btn-ghost btn-sm" name="answer" value="<?= e((string) $key) ?>"><?= e((string) $label) ?></button>
      <?php endforeach; ?>
    </p>
    <p style="margin:0">
      <input type="text" name="note" maxlength="<?= (int) RMT_VQ_NOTE_MAX ?>" class="input"
             placeholder="Anything else? Optional." style="max-width:420px">
    </p>
  </form>
</div>
</details>
<?php unset($vqKey, $fbQ, $fbMe); ?>
