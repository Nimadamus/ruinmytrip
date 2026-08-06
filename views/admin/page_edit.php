<?php
/** @var ?array $p @var array $dests @var array $errors */
$here = 'admin/pages';
$templates = rmt_landing_templates();
$v = static fn(string $k, $d = '') => $p[$k] ?? $d;
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px"><?= $p && !empty($p['id']) ? 'Edit guide page' : 'New guide page' ?></h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <div class="callout">
    <b>Slug patterns that work.</b>
    <?php foreach ($templates as $k => $t): ?>
      <div><code><?= e($t['pattern']) ?></code> — <?= e($t['label']) ?></div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= e(url('admin/page')) ?>" class="form-card form-wide" style="max-width:900px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">

    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <p style="flex:1;min-width:220px">
        <label for="pt">Template</label>
        <select id="pt" name="template" style="width:100%" required>
          <?php foreach ($templates as $k => $t): ?>
            <option value="<?= e($k) ?>" <?= (string) $v('template') === $k ? 'selected' : '' ?>><?= e($t['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </p>
      <p style="flex:1;min-width:220px">
        <label for="pd">Destination</label>
        <select id="pd" name="destination_id" style="width:100%">
          <option value="">Not destination-specific</option>
          <?php foreach ($dests as $dd): ?>
            <option value="<?= (int) $dd['id'] ?>" <?= (int) $v('destination_id') === (int) $dd['id'] ? 'selected' : '' ?>>
              <?= e($dd['name'] . ', ' . $dd['country']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </p>
      <p style="flex:1;min-width:200px">
        <label for="pc">Warning category</label>
        <select id="pc" name="category" style="width:100%">
          <option value="">Use the template default</option>
          <?php foreach (RMT_WARNING_CATEGORIES as $ck => $cc): ?>
            <option value="<?= e($ck) ?>" <?= (string) $v('category') === $ck ? 'selected' : '' ?>><?= e($cc['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Decides which live warnings appear under the article.</span>
      </p>
    </div>

    <p>
      <label for="ps">URL slug</label>
      <input id="ps" name="slug" required maxlength="100" style="width:100%"
             placeholder="what-can-ruin-a-trip-to-paris" value="<?= e((string) $v('slug')) ?>">
      <span class="hint">Lives at the site root: <code><?= e(rtrim((string) cfg('app_url'), '/')) ?>/your-slug</code></span>
    </p>
    <p>
      <label for="ph">H1 <span class="muted">(the one on-page heading)</span></label>
      <input id="ph" name="h1" required maxlength="200" style="width:100%" value="<?= e((string) $v('h1')) ?>">
    </p>
    <p>
      <label for="pti">Title tag</label>
      <input id="pti" name="title_tag" required maxlength="200" style="width:100%" value="<?= e((string) $v('title_tag')) ?>">
      <span class="hint">Must be unique across the site. Aim for 50–60 characters.</span>
    </p>
    <p>
      <label for="pm">Meta description</label>
      <textarea id="pm" name="meta_description" required rows="2" maxlength="320" style="width:100%"><?= e((string) $v('meta_description')) ?></textarea>
      <span class="hint">Aim for 140–160 characters. Must be unique.</span>
    </p>
    <p>
      <label for="pi">Intro <span class="muted">(one paragraph, plain text)</span></label>
      <textarea id="pi" name="intro" rows="3" style="width:100%"><?= e((string) $v('intro')) ?></textarea>
    </p>
    <p>
      <label for="pb">Body</label>
      <textarea id="pb" name="body" rows="20" class="code" style="width:100%"><?= e((string) $v('body')) ?></textarea>
      <span class="hint">Blank line = paragraph. <code>- </code> = bullet. <code>**bold**</code>.
        <code>[text](https://url)</code>. Minimum 600 characters to publish.</span>
    </p>
    <p>
      <label for="pso">Sources <span class="muted">(one per line: <code>Title | https://url</code>)</span></label>
      <textarea id="pso" name="sources" rows="4" class="code" style="width:100%"><?= e(rmt_sources_to_text($v('sources_json'))) ?></textarea>
    </p>
    <p>
      <label for="pst">Status</label>
      <select id="pst" name="status" style="width:100%">
        <option value="draft" <?= (string) $v('status') === 'draft' ? 'selected' : '' ?>>Draft — 404s for the public, not in the sitemap</option>
        <option value="published" <?= (string) $v('status') === 'published' ? 'selected' : '' ?>>Published</option>
      </select>
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">Save page</button>
      <?php if ((int) $v('id', 0)): ?>
        <a class="btn btn-ghost" href="<?= e(url((string) $v('slug'))) ?>">View</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/pages')) ?>">Back</a>
    </div>
  </form>

  <?php if ((int) $v('id', 0)): ?>
    <div class="wrap" style="max-width:900px;padding:0">
      <form method="post" action="<?= e(url('admin/page/' . (int) $v('id') . '/delete')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" data-confirm="Delete this page permanently?">Delete this page</button>
      </form>
    </div>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
