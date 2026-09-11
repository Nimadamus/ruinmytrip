<?php /** @var array $draft @var list<string> $errors @var list<array> $drafts */ ?>
<div class="wrap" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url('admin')) ?>">Admin</a> / Add a city</p>
  <h1 style="margin-top:6px"><?= $draft['id'] ? 'Editing ' . e((string) $draft['name']) : 'Add a city' ?></h1>

  <?php /* The honest version of what this form is. Somebody reading it should know before they
           start that the writing is the work, and that nothing here will be filled in for them. */ ?>
  <p class="muted">Every field is a fact somebody types. Nothing is generated from the name, and
    there is no starter summary: a city page with nothing on it is worse than no city page. Save as
    often as you like. It becomes a real page only when you publish it.</p>

  <?php if ($errors): ?>
    <div class="notice" style="border-color:#b42318;color:#b42318">
      <ul style="margin:0;padding-left:18px">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url($draft['id'] ? 'admin/destination/draft/' . (int) $draft['id'] : 'admin/destination/new')) ?>">
    <?= csrf_field() ?>
    <div class="grid g-2" style="gap:12px">
      <div><label for="dn">Name</label>
        <input id="dn" name="name" value="<?= e((string) ($draft['name'] ?? '')) ?>" maxlength="80" required></div>
      <div><label for="dc">Country</label>
        <input id="dc" name="country" value="<?= e((string) ($draft['country'] ?? '')) ?>" maxlength="80" required></div>
      <div><label for="dr">Region or state <span class="hint">optional</span></label>
        <input id="dr" name="region" value="<?= e((string) ($draft['region'] ?? '')) ?>" maxlength="80"></div>
      <div><label for="dcat">Category</label>
        <select id="dcat" name="category">
          <option value="">Choose one</option>
          <?php foreach (RMT_DEST_CATEGORIES as $c): ?>
            <option value="<?= e($c) ?>"<?= ($draft['category'] ?? '') === $c ? ' selected' : '' ?>><?= e(ucfirst($c)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="dlat">Latitude</label>
        <input id="dlat" name="lat" value="<?= e((string) ($draft['lat'] ?? '')) ?>" inputmode="decimal"></div>
      <div><label for="dlng">Longitude</label>
        <input id="dlng" name="lng" value="<?= e((string) ($draft['lng'] ?? '')) ?>" inputmode="decimal"></div>
    </div>

    <p class="hint" style="margin:8px 0 14px">The point the map opens on and the point the place
      importer searches around. Read it off OpenStreetMap or any map that gives decimal degrees.</p>

    <label for="dslug">Web address <span class="hint">/d/&hellip;</span></label>
    <input id="dslug" name="slug" value="<?= e((string) ($draft['slug'] ?? '')) ?>"
           placeholder="left blank, it is made from the name and country" maxlength="80">

    <label for="dsum" style="margin-top:14px">Summary</label>
    <textarea id="dsum" name="summary" rows="6" maxlength="2000"><?= e((string) ($draft['summary'] ?? '')) ?></textarea>
    <p class="hint">At least <?= (int) RMT_DEST_SUMMARY_MIN ?> characters to publish. What is
      actually true about going there, in the voice of the rest of the site.</p>

    <details style="margin-top:14px">
      <summary>A photograph <span class="hint">optional, and it carries its attribution</span></summary>
      <label for="dh" style="margin-top:10px">Image address</label>
      <input id="dh" name="hero_url" value="<?= e((string) ($draft['hero_url'] ?? '')) ?>" placeholder="https://">
      <div class="grid g-2" style="gap:12px;margin-top:10px">
        <div><label for="dhc">Credit</label>
          <input id="dhc" name="hero_credit" value="<?= e((string) ($draft['hero_credit'] ?? '')) ?>"></div>
        <div><label for="dhl">Licence</label>
          <input id="dhl" name="hero_license" value="<?= e((string) ($draft['hero_license'] ?? '')) ?>"></div>
      </div>
      <label for="dhs" style="margin-top:10px">The page it came from</label>
      <input id="dhs" name="hero_source_url" value="<?= e((string) ($draft['hero_source_url'] ?? '')) ?>" placeholder="https://">
      <p class="hint">All three, or none. A photograph without them is one we are not entitled to publish.</p>
    </details>

    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary">Save draft</button>
      <?php if ($draft['id']): ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/destinations/drafts')) ?>">All drafts</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($draft['id']): ?>
    <form method="post" action="<?= e(url('admin/destination/draft/' . (int) $draft['id'] . '/publish')) ?>"
          style="margin-top:14px" onsubmit="return confirm('Publish this city? It becomes a public page.');">
      <?= csrf_field() ?>
      <button class="btn">Publish it</button>
      <span class="hint">Refused until the name, country, coordinates, category and summary are all there.</span>
    </form>
  <?php endif; ?>

  <?php if ($drafts): ?>
    <h2 style="margin-top:30px">Cities being written</h2>
    <ul class="list-plain">
      <?php foreach ($drafts as $d): ?>
        <li style="padding:8px 0;border-bottom:1px solid var(--line)">
          <a href="<?= e(url('admin/destination/draft/' . (int) $d['id'])) ?>"><?= e((string) $d['name']) ?></a>
          <span class="muted"> &middot; <?= e((string) $d['country']) ?>
            <?php if (($d['lat'] ?? null) === null): ?> &middot; no coordinates yet<?php endif; ?>
            <?php if (mb_strlen((string) ($d['summary'] ?? '')) < RMT_DEST_SUMMARY_MIN): ?> &middot; summary unfinished<?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <div style="height:50px"></div>
</div>
