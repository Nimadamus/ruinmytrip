<?php
/**
 * The moderators' side of vouches and flags: accounts to review, restricted accounts, appeals.
 * This is the only place flag notes and the names of the travelers who left them are shown.
 *
 * @var array $trust  rmt_trust_mod_queue()
 */
$trustMode = rmt_trust_mode();
$trustMember = static function (array $rm): void { ?>
  <p style="margin:0 0 6px"><a href="<?= e(url('u/' . $rm['username'])) ?>"><b>@<?= e((string) $rm['username']) ?></b></a>
    <span class="muted">score <?= (int) $rm['trust_score'] ?> &middot; <?= (int) $rm['vouch_count'] ?> vouched &middot; <?= (int) $rm['flag_count'] ?> flagged<?php
      if ($rm['trust_state'] === 'restricted' && $rm['trust_state_at']): ?> &middot; restricted <?= e(ago((string) $rm['trust_state_at'])) ?><?php endif; ?></span></p>
  <?php if ($rm['signals']): ?>
    <div style="margin:0 0 8px;padding:8px 12px;border-left:4px solid #b45309;background:#fff7ed;color:#7c2d12;border-radius:6px">
      <b>Check before acting.</b>
      <ul style="margin:4px 0 0"><?php foreach ($rm['signals'] as $sig): ?><li><?= e($sig) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <ul style="margin:0 0 8px">
    <?php foreach ($rm['flags'] as $fl): ?>
      <li>@<?= e((string) ($fl['reviewer'] ?? 'deleted account')) ?> <span class="muted">(<?= e((string) $fl['context_key']) ?>, <?= e(ago((string) $fl['created_at'])) ?>)</span>:
        <?= $fl['note'] !== null && $fl['note'] !== '' ? e((string) $fl['note']) : '<span class="muted">no note</span>' ?></li>
    <?php endforeach; ?>
    <?php if (!$rm['flags']): ?><li class="muted">No counted flags.</li><?php endif; ?>
  </ul>
<?php };
?>
<section id="trust" style="margin-top:28px">
  <h2 style="margin:0 0 4px">Vouches and flags</h2>
  <p class="hint" style="margin:0 0 14px">
    Mode: <b><?= e($trustMode) ?></b>.
    <?= ['off' => 'Only staff accounts can vouch or flag. Members see none of it.',
         'monitor' => 'Members can vouch and flag. No account can be restricted.',
         'active' => 'Members can vouch and flag. You can restrict an account after a written review.'][$trustMode] ?>
    Flags never restrict anybody by themselves. Every action below needs a note, and the note is the record.
  </p>

  <h3 style="margin:14px 0 6px">Needs review (<?= count($trust['review']) ?>)</h3>
  <?php if (!$trust['review']): ?><p class="muted">Nobody has <?= RMT_TRUST_REVIEW_FLAGS ?> or more unreviewed flags.</p><?php endif; ?>
  <?php foreach ($trust['review'] as $rm): ?>
    <div class="card" style="margin:12px 0"><div class="card-body">
      <?php $trustMember($rm); ?>
      <form method="post" action="<?= e(url('admin/trust/act')) ?>" style="display:grid;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $rm['id'] ?>">
        <input type="text" name="note" required minlength="<?= RMT_TRUST_MOD_NOTE_MIN ?>" maxlength="500" placeholder="What you checked and why (internal, recorded in the log)">
        <?php if ($trustMode === 'active'): ?>
          <input type="text" name="reason" maxlength="500" placeholder="Only if restricting: the reason the member will be shown">
        <?php endif; ?>
        <span style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-ghost" name="action" value="no_action">Reviewed, no action</button>
          <button class="btn btn-ghost" name="action" value="set_aside">Set the flags aside</button>
          <?php if ($trustMode === 'active'): ?><button class="btn btn-ghost" name="action" value="restrict">Restrict messaging</button><?php endif; ?>
        </span>
      </form>
    </div></div>
  <?php endforeach; ?>

  <h3 style="margin:18px 0 6px">Restricted (<?= count($trust['restricted']) ?>)</h3>
  <?php if (!$trust['restricted']): ?><p class="muted">No account is restricted.</p><?php endif; ?>
  <?php foreach ($trust['restricted'] as $rm): ?>
    <div class="card" style="margin:12px 0"><div class="card-body">
      <?php $trustMember($rm); ?>
      <form method="post" action="<?= e(url('admin/trust/act')) ?>" style="display:grid;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int) $rm['id'] ?>">
        <input type="text" name="note" required minlength="<?= RMT_TRUST_MOD_NOTE_MIN ?>" maxlength="500" placeholder="What you checked and why (internal, recorded in the log)">
        <span style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-ghost" name="action" value="lift">Lift the restriction</button>
          <button class="btn btn-ghost" name="action" value="set_aside">Set the flags aside and lift</button>
        </span>
      </form>
    </div></div>
  <?php endforeach; ?>

  <h3 style="margin:18px 0 6px">Appeals (<?= count($trust['appeals']) ?>)</h3>
  <?php if (!$trust['appeals']): ?><p class="muted">No open appeals.</p><?php endif; ?>
  <?php foreach ($trust['appeals'] as $ap): ?>
    <div class="card" style="margin:12px 0"><div class="card-body">
      <p style="margin:0 0 6px"><a href="<?= e(url('u/' . $ap['username'])) ?>"><b>@<?= e((string) $ap['username']) ?></b></a>
        <span class="muted">appealed <?= e(ago((string) $ap['created_at'])) ?></span></p>
      <p style="margin:0 0 8px;white-space:pre-wrap"><?= e((string) $ap['body']) ?></p>
      <form method="post" action="<?= e(url('admin/trust/appeal')) ?>" style="display:grid;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="appeal_id" value="<?= (int) $ap['id'] ?>">
        <input type="text" name="note" required minlength="<?= RMT_TRUST_MOD_NOTE_MIN ?>" maxlength="500" placeholder="What you checked and why (internal, recorded in the log)">
        <input type="text" name="answer" maxlength="500" placeholder="The answer the member will be shown (required to uphold)">
        <span style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-ghost" name="decision" value="grant">Grant: lift the restriction</button>
          <button class="btn btn-ghost" name="decision" value="uphold">Uphold: it stays</button>
        </span>
      </form>
    </div></div>
  <?php endforeach; ?>
</section>
