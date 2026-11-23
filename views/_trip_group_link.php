<?php
/**
 * On the owner's own trip page: the group link (app/trip_group.php). One button makes it; then the
 * link, Copy and WhatsApp, because the group chat is where it goes.
 *
 * @var array $t the trip
 */
if (empty($isOwner)) return;
$tgCode = (string) ($t['group_code'] ?? '');
$tgUrl = $tgCode !== '' ? rmt_tg_url($tgCode) : '';
?>
<section class="card" id="group" style="margin:18px 0;border-left:4px solid #0f766e"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 6px">Plan it with your group</h2>
  <?php if ($tgUrl === ''): ?>
    <p class="hint" style="margin:0 0 10px">Make one link for your group chat. Friends tap it, put their first name, and see the plan: who is in, the before you go checklist with who is sorting what, the group's tourist tax and a vote on dates. No account needed.</p>
    <form method="post" action="<?= e(url('trip/' . (int) $t['id'] . '/group-link')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-accent btn-sm" type="submit">Make the group link</button>
    </form>
  <?php else: ?>
    <p class="hint" style="margin:0 0 10px">Send this to the group chat. Anyone with it can join with a first name.</p>
    <p style="margin:0 0 10px;word-break:break-all"><a href="<?= e($tgUrl) ?>"><?= e($tgUrl) ?></a></p>
    <p style="margin:0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button type="button" class="btn btn-accent btn-sm" data-copy="<?= e($tgUrl) ?>">Copy link</button>
      <a class="btn btn-ghost btn-sm" rel="noopener" target="_blank" href="https://wa.me/?text=<?= rawurlencode('Our trip plan: ' . $tgUrl) ?>">WhatsApp</a>
      <a class="btn btn-ghost btn-sm" href="<?= e($tgUrl) ?>">Open the group page</a>
    </p>
    <form method="post" action="<?= e(url('trip/' . (int) $t['id'] . '/group-link')) ?>" style="margin:10px 0 0">
      <?= csrf_field() ?><input type="hidden" name="reset" value="1">
      <button class="btn btn-link btn-sm hint" type="submit">Make a new link (the old one stops working)</button>
    </form>
  <?php endif; ?>
</div></section>
