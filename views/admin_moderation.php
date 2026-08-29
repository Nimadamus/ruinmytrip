<?php /** @var array $queue @var array $history */ ?>
<div class="wrap">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Moderation</a> / Queue</p>
  <h1 style="margin:.2rem 0 .4rem">Moderation queue</h1>
  <p class="muted" style="margin:0 0 6px"><?= count($queue) ?> item<?= count($queue) === 1 ? '' : 's' ?> waiting.</p>
  <p class="hint" style="margin:0 0 20px">
    A report is not a verdict. Nothing here is hidden by report volume, and the count is shown so
    you can see it, not so it decides anything. <strong>A negative review is not a violation</strong> &mdash;
    "terrible service, would not return" is an ordinary traveler opinion. Moderate spam, abuse,
    fraud, personal information and off-topic content; never criticism.
  </p>

  <?php if (!$queue): ?>
    <p class="muted">Nothing reported. That is the current state, not an empty page.</p>
  <?php else: ?>
    <?php foreach ($queue as $item): $c = $item['context']; ?>
      <section class="card" style="margin:0 0 16px"><div class="card-body">
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:baseline">
          <h2 style="margin:0;font-size:1.02rem">
            <?php if (!empty($c['url'])): ?>
              <a href="<?= e((string) $c['url']) ?>"><?= e((string) ($c['title'] ?: 'Untitled')) ?></a>
            <?php else: ?>
              <?= e((string) ($c['title'] ?: 'Untitled')) ?>
            <?php endif; ?>
          </h2>
          <span class="muted" style="font-size:.88rem">
            <?= (int) $item['reports'] ?> report<?= (int) $item['reports'] === 1 ? '' : 's' ?>
            &middot; <?= e(implode(', ', array_map(static fn($r) => str_replace('_', ' ', (string) $r), $item['reasons']))) ?>
            &middot; <?= e(substr((string) $item['last_reported'], 0, 16)) ?>
          </span>
        </div>

        <p class="muted" style="margin:.3rem 0 .2rem;font-size:.9rem">
          <?= e(str_replace('_', ' ', (string) $item['target_type'])) ?>
          <?php if (!empty($c['author'])): ?> by <a href="<?= e(url('u/'.$c['author'])) ?>">@<?= e((string) $c['author']) ?></a><?php endif; ?>
          <?php if (!empty($c['where'])): ?> &middot; <?= e((string) $c['where']) ?><?php endif; ?>
          <?php if ($c['rating'] !== null): ?>
            &middot; <span class="stars"><?= stars((int) $c['rating']) ?></span>
            <span class="hint">(shown for context; a rating is never a reason)</span>
          <?php endif; ?>
          <?php if (!empty($c['status'])): ?> &middot; currently <strong><?= e((string) $c['status']) ?></strong><?php endif; ?>
        </p>

        <?php /* The text itself. Deciding from the report's summary rather than the content is
                 exactly how legitimate criticism gets removed. */ ?>
        <?php if (!empty($c['excerpt'])): ?>
          <blockquote style="margin:.5rem 0;padding:8px 12px;border-left:3px solid var(--line);font-size:.94rem">
            <?= e((string) $c['excerpt']) ?>
          </blockquote>
        <?php endif; ?>

        <?php if (!empty($item['history'])): ?>
          <p class="hint" style="margin:.2rem 0 .5rem">
            Previously:
            <?php foreach ($item['history'] as $h): ?>
              <?= e((string) $h['action']) ?><?= $h['to_status'] ? ' &rarr; ' . e((string) $h['to_status']) : '' ?>
              (<?= e(substr((string) $h['created_at'], 0, 10)) ?>)
            <?php endforeach; ?>
          </p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/resolve')) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:8px 0 0">
          <?= csrf_field() ?>
          <input type="hidden" name="report_id" value="<?= (int) $item['first_report_id'] ?>">
          <input type="text" name="note" placeholder="Why (recorded in the log)" maxlength="500"
                 style="flex:1;min-width:220px">
          <button class="btn btn-ghost" name="action" value="dismiss">Dismiss</button>
          <button class="btn btn-ghost" name="action" value="hide">Hide</button>
          <button class="btn btn-ghost" name="action" value="remove">Remove</button>
          <button class="btn btn-ghost" name="action" value="restore">Restore</button>
        </form>
      </div></section>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($history): ?>
    <h2 style="font-size:1.05rem;margin:30px 0 8px">Recent decisions</h2>
    <p class="hint" style="margin:0 0 10px">
      Who acted, on what, and why. Content is never physically deleted &mdash; hidden and removed are
      statuses, so the history survives the decision.
    </p>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <thead><tr style="text-align:left;border-bottom:1px solid #e9e9ee">
          <th style="padding:6px 10px 6px 0">When</th><th style="padding:6px 8px">Who</th>
          <th style="padding:6px 8px">What</th><th style="padding:6px 8px">Action</th>
          <th style="padding:6px 8px">Status</th><th style="padding:6px 8px">Note</th>
        </tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr style="border-bottom:1px solid #f1f1f5">
              <td style="padding:6px 10px 6px 0" class="muted"><?= e(substr((string) $h['created_at'], 0, 16)) ?></td>
              <td style="padding:6px 8px"><?= $h['actor'] ? '@' . e((string) $h['actor']) : '<span class="hint">system</span>' ?></td>
              <td style="padding:6px 8px" class="muted"><?= e((string) $h['target_type']) ?> #<?= (int) $h['target_id'] ?></td>
              <td style="padding:6px 8px"><?= e((string) $h['action']) ?></td>
              <td style="padding:6px 8px" class="muted">
                <?= $h['from_status'] ? e((string) $h['from_status']) . ' &rarr; ' . e((string) $h['to_status']) : '&mdash;' ?>
              </td>
              <td style="padding:6px 8px" class="muted"><?= e((string) ($h['note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <div style="height:40px"></div>
</div>
