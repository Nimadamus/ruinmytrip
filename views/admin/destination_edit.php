<?php
/**
 * The risk-report editor for one destination.
 *
 * Each section is its own small form so a long editing session is saved incrementally — losing
 * thirteen sections of writing to one validation error would be the fastest way to make sure the
 * reports never get written.
 *
 * @var array $d @var array $byKey @var array $faqs @var array $pages
 */
$here = 'admin/destinations';
$defs = rmt_risk_section_defs();
?>
<div class="wrap" style="min-height:60vh">
  <h1 style="margin-top:24px"><?= e($d['name']) ?>, <?= e($d['country']) ?></h1>
  <?php include __DIR__ . '/_nav.php'; ?>

  <p><a href="<?= e(url('d/' . $d['slug'])) ?>">View the public page →</a></p>

  <div class="callout">
    <b>Formatting.</b> Blank line = new paragraph. A line starting with <code>- </code> becomes a bullet.
    <code>**bold**</code> works. <code>[text](https://example.com)</code> makes a link. Everything else is
    escaped, so nothing you paste here can execute.
  </div>

  <!-- Destination-level fields -->
  <form class="form-card form-wide" method="post" action="<?= e(url('admin/destination/' . (int) $d['id'])) ?>" style="margin-top:20px">
    <?= csrf_field() ?>
    <h2 style="font-size:1.2rem;margin-top:0">Overall risk</h2>

    <p>
      <label for="ad-risk">Risk level</label>
      <select id="ad-risk" name="risk_level" style="width:100%">
        <option value="">Not yet rated (nothing is shown publicly)</option>
        <?php foreach (RMT_RISK_LEVELS as $n => $r): ?>
          <option value="<?= (int) $n ?>" <?= (int) ($d['risk_level'] ?? 0) === $n ? 'selected' : '' ?>>
            <?= e($r['label']) ?> — <?= e($r['desc']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </p>
    <p>
      <label for="ad-summary">Card summary <span class="muted">(shown on destination cards, max 600 chars)</span></label>
      <textarea id="ad-summary" name="summary" rows="2" maxlength="600" style="width:100%"><?= e((string) $d['summary']) ?></textarea>
    </p>
    <p>
      <label for="ad-risksum">Overall trip-risk summary <span class="muted">(top of the page, and the meta description)</span></label>
      <textarea id="ad-risksum" name="risk_summary" rows="4" maxlength="2000" class="code" style="width:100%"><?= e((string) ($d['risk_summary'] ?? '')) ?></textarea>
    </p>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <p style="flex:1;min-width:200px">
        <label for="ad-best">Easiest months</label>
        <input id="ad-best" name="best_months" maxlength="120" style="width:100%" placeholder="April–May, late September"
               value="<?= e((string) ($d['best_months'] ?? '')) ?>">
      </p>
      <p style="flex:1;min-width:200px">
        <label for="ad-worst">Hardest months</label>
        <input id="ad-worst" name="worst_months" maxlength="120" style="width:100%" placeholder="July–August, Golden Week"
               value="<?= e((string) ($d['worst_months'] ?? '')) ?>">
      </p>
      <p style="flex:1;min-width:160px">
        <label for="ad-air">Airport codes</label>
        <input id="ad-air" name="airport_codes" maxlength="60" style="width:100%" placeholder="CDG, ORY, BVA"
               value="<?= e((string) ($d['airport_codes'] ?? '')) ?>">
        <span class="hint">Comma separated; makes the destination findable by airport code.</span>
      </p>
    </div>
    <p>
      <label for="ad-worth">Is it worth visiting? <span class="muted">(short answer used above the sections)</span></label>
      <textarea id="ad-worth" name="worth_visiting" rows="3" maxlength="4000" class="code" style="width:100%"><?= e((string) ($d['worth_visiting'] ?? '')) ?></textarea>
    </p>
    <p><label style="font-weight:400"><input type="checkbox" name="featured" value="1" <?= !empty($d['featured']) ? 'checked' : '' ?>> Feature on the homepage</label></p>
    <button class="btn btn-primary" type="submit">Save destination</button>
    <?php if (!empty($d['last_reviewed_at'])): ?>
      <span class="muted" style="margin-left:10px">Last reviewed <?= e((string) $d['last_reviewed_at']) ?></span>
    <?php endif; ?>
  </form>

  <!-- Risk sections -->
  <h2 style="margin-top:36px;font-size:1.25rem">Risk report sections</h2>
  <p class="muted">Leave a section empty to keep it off the public page. Clearing an existing body deletes it.</p>

  <?php foreach ($defs as $key => $def): $s = $byKey[$key] ?? null; ?>
    <form class="risk-section" id="s-<?= e($key) ?>" method="post"
          action="<?= e(url('admin/destination/' . (int) $d['id'] . '/section')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="section_key" value="<?= e($key) ?>">
      <h2 style="font-size:1.1rem">
        <?= e($def['heading']) ?>
        <?php if ($s): ?><span class="chip">written</span><?php else: ?><span class="chip chip-muted">empty</span><?php endif; ?>
      </h2>

      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px">
        <div style="flex:2;min-width:220px">
          <label for="h-<?= e($key) ?>">Heading override</label>
          <input id="h-<?= e($key) ?>" name="heading" maxlength="160" style="width:100%"
                 placeholder="<?= e($def['heading']) ?>" value="<?= e((string) ($s['heading'] ?? '')) ?>">
        </div>
        <div style="flex:1;min-width:160px">
          <label for="t-<?= e($key) ?>">Trust label</label>
          <select id="t-<?= e($key) ?>" name="content_type" style="width:100%">
            <?php foreach (['fact' => 'Checked facts', 'editorial' => 'Our guidance', 'alert' => 'Time-sensitive'] as $tv => $tl): ?>
              <option value="<?= e($tv) ?>" <?= ($s['content_type'] ?? $def['type']) === $tv ? 'selected' : '' ?>><?= e($tl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1;min-width:140px">
          <label for="sv-<?= e($key) ?>">Severity chip</label>
          <select id="sv-<?= e($key) ?>" name="severity" style="width:100%">
            <option value="">None</option>
            <?php foreach (RMT_WARNING_SEVERITIES as $n => $sv): ?>
              <option value="<?= (int) $n ?>" <?= (int) ($s['severity'] ?? 0) === $n ? 'selected' : '' ?>><?= e($sv['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label for="b-<?= e($key) ?>">Body</label>
      <textarea id="b-<?= e($key) ?>" name="body" rows="8" class="code" style="width:100%"><?= e((string) ($s['body'] ?? '')) ?></textarea>

      <label for="src-<?= e($key) ?>" style="margin-top:8px;display:block">Sources <span class="muted">(one per line: <code>Title | https://url</code>)</span></label>
      <textarea id="src-<?= e($key) ?>" name="sources" rows="3" class="code" style="width:100%"><?= e(rmt_sources_to_text($s['sources_json'] ?? null)) ?></textarea>

      <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" type="submit">Save section</button>
        <?php if ($s && !empty($s['last_reviewed_at'])): ?>
          <span class="muted" style="font-size:.85rem">Last reviewed <?= e((string) $s['last_reviewed_at']) ?></span>
        <?php endif; ?>
      </div>
    </form>
  <?php endforeach; ?>

  <!-- FAQs -->
  <h2 id="faqs" style="margin-top:36px;font-size:1.25rem">Frequently asked questions</h2>
  <p class="muted">These emit FAQPage structured data. Only write questions people actually ask —
    invented Q&amp;A is exactly the structured-data spam that gets a site demoted.</p>

  <?php foreach ($faqs as $f): ?>
    <form class="risk-section" method="post" action="<?= e(url('admin/destination/' . (int) $d['id'] . '/faq')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="faq_id" value="<?= (int) $f['id'] ?>">
      <p><label>Question</label><input name="question" maxlength="300" style="width:100%" value="<?= e((string) $f['question']) ?>"></p>
      <p><label>Answer</label><textarea name="answer" rows="4" class="code" style="width:100%"><?= e((string) $f['answer']) ?></textarea></p>
      <div style="display:flex;gap:10px;align-items:center">
        <input name="sort" type="number" value="<?= (int) $f['sort'] ?>" style="width:80px;padding:.4rem" aria-label="Sort order">
        <button class="btn btn-primary btn-sm" type="submit">Save</button>
        <button class="btn btn-ghost btn-sm" name="delete" value="1" data-confirm="Delete this FAQ?">Delete</button>
      </div>
    </form>
  <?php endforeach; ?>

  <form class="risk-section" method="post" action="<?= e(url('admin/destination/' . (int) $d['id'] . '/faq')) ?>">
    <?= csrf_field() ?>
    <h2 style="font-size:1.05rem">Add a FAQ</h2>
    <p><label for="fq">Question</label><input id="fq" name="question" maxlength="300" style="width:100%"></p>
    <p><label for="fa">Answer</label><textarea id="fa" name="answer" rows="4" class="code" style="width:100%"></textarea></p>
    <div style="display:flex;gap:10px;align-items:center">
      <input name="sort" type="number" value="<?= count($faqs) ?>" style="width:80px;padding:.4rem" aria-label="Sort order">
      <button class="btn btn-primary btn-sm" type="submit">Add FAQ</button>
    </div>
  </form>

  <!-- Guide pages -->
  <h2 style="margin-top:36px;font-size:1.25rem">Guide pages for this destination</h2>
  <?php if ($pages): ?>
    <div class="table-scroll"><table class="tbl">
      <thead><tr><th>Page</th><th>Template</th><th>Status</th><th>Views</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($pages as $p): ?>
          <tr><td><a href="<?= e(url($p['slug'])) ?>">/<?= e($p['slug']) ?></a></td>
            <td><?= e(rmt_landing_template_label((string) $p['template'])) ?></td>
            <td><?= e($p['status']) ?></td><td><?= (int) $p['view_count'] ?></td>
            <td><a href="<?= e(url('admin/page/' . (int) $p['id'])) ?>">Edit</a></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="muted">None yet.</p>
  <?php endif; ?>
  <p><a class="btn btn-ghost" href="<?= e(url('admin/page/new')) ?>">New guide page</a></p>
  <div style="height:50px"></div>
</div>
