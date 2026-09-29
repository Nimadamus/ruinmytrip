<?php
/**
 * @var list<string> $codes   the countries shown
 * @var list<string> $saved   the viewer's own saved countries
 * @var ?array $owner         set on /u/{name}/map
 * @var bool $isOwnerView
 * @var ?string $who
 * @var ?array $me
 * @var array<string,string> $names
 */
$readOnly = $owner && !$isOwnerView;
$n = count($codes);
$returnTo = '/map' . ($codes ? '?c=' . rmt_map_key($codes) : '');
?>
<div class="wrap tm" data-tm-readonly="<?= $readOnly ? '1' : '0' ?>" data-tm-codes="<?= e(rmt_map_key($codes)) ?>"
     data-tm-geo="<?= e(rmt_asset('assets/data/world-map.json')) ?>" data-tm-base="<?= e(url('map')) ?>"
     data-tm-card="<?= e(url('card/map')) ?>">
  <header class="tm-head">
    <?php if ($owner): ?>
      <p class="tm-kicker"><a href="<?= e(url('u/' . $owner['username'])) ?>"><?= e((string) $who) ?></a>'s travel map</p>
      <h1><span data-tm-count><?= e(rmt_map_count_label($n)) ?></span></h1>
    <?php else: ?>
      <p class="tm-kicker">Travel map</p>
      <h1 data-tm-title><?= $n > 0 ? "I've been to <span data-tm-count>" . e(rmt_map_count_label($n)) . '</span>' : 'Where have you been?' ?></h1>
      <p class="muted tm-sub">Tap every country you have been to. Your map is a link you can share, no account needed.</p>
    <?php endif; ?>
  </header>

  <?php if (!$readOnly): ?>
  <div class="tm-search">
    <input type="search" data-tm-find placeholder="Type a country, then Enter" autocomplete="off" aria-label="Add a country" list="tm-countries">
    <datalist id="tm-countries"><?php foreach ($names as $code => $name): ?><option value="<?= e($name) ?>"></option><?php endforeach; ?></datalist>
  </div>
  <?php endif; ?>

  <div class="tm-map card" data-tm-map aria-label="World map"><p class="muted tm-loading">Loading the map…</p></div>

  <div class="tm-actions">
    <?php if ($readOnly): ?>
      <a class="btn btn-primary" href="<?= e(url('map')) ?>" data-cta="map_make">Make your own travel map</a>
    <?php else: ?>
      <button type="button" class="btn btn-primary" data-tm-share data-cta="map_share">Share my map</button>
      <a class="btn btn-ghost" data-tm-download data-cta="map_download" href="<?= e(rmt_map_card_url($codes)) ?>" download="my-travel-map.png">Download image</a>
      <form method="post" action="<?= e(url('map/save')) ?>" class="tm-save" data-tm-save-form>
        <?= csrf_field() ?>
        <input type="hidden" name="c" value="<?= e(rmt_map_key($codes)) ?>" data-tm-field>
        <input type="hidden" name="return" value="<?= e($returnTo) ?>" data-tm-return>
        <button type="submit" class="btn btn-accent" data-cta="map_save"><?= $me ? 'Save to my profile' : 'Keep it on a free profile' ?></button>
      </form>
    <?php endif; ?>
  </div>
  <p class="muted tm-note" data-tm-status aria-live="polite"><?php
    if ($me && !$owner && $codes && $codes !== $saved) echo 'Not saved yet. Save it to keep it on your profile.';
  ?></p>

  <section class="tm-list">
    <h2 class="tm-h2">Countries</h2>
    <div class="tag-row" data-tm-chips><?php foreach ($codes as $c): ?><span class="chip"><?= e($names[$c] ?? $c) ?></span><?php endforeach; ?></div>
  </section>

  <section class="card tm-next"><div class="card-body">
    <p style="margin:0 0 6px"><b>Where are you going next?</b></p>
    <p class="muted" style="margin:0 0 12px">Add your dates and see who else is going there the same week. Travelers with overlapping dates find each other here.</p>
    <a class="btn btn-primary btn-sm" href="<?= e(url('plan')) ?>" data-cta="map_make">Add my next trip</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('buddies')) ?>">Find a travel buddy</a>
  </div></section>
</div>
<style>
.tm-head{margin:18px 0 10px}.tm-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:.8rem;color:var(--brand);margin:0 0 4px;font-weight:700}
.tm-head h1{margin:0 0 6px;font-size:clamp(1.8rem,5vw,2.6rem)}.tm-sub{margin:0}
.tm-search input{width:100%;max-width:420px;padding:12px 14px;border:1px solid var(--line);border-radius:12px;font-size:1rem;background:var(--card);color:var(--text)}
.tm-map{margin:14px 0;padding:6px;overflow:hidden;background:#0f1b2d}.tm-map svg{display:block;width:100%;height:auto}
.tm-map path{fill:#2c3e58;stroke:#1e2d44;stroke-width:.5;cursor:pointer;transition:fill .15s}
.tm-map path.on,.tm-map circle.on{fill:#14b8a6}.tm-map path:hover{fill:#3f5a80}.tm-map path.on:hover{fill:#2dd4bf}
.tm-map circle{fill:transparent;cursor:pointer}.tm-map circle.on{stroke:#0f1b2d;stroke-width:1}
.tm-map[data-readonly] path{cursor:default}.tm-loading{color:#aab8c8;padding:40px;text-align:center;margin:0}
.tm-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.tm-save{margin:0}
.tm-note{min-height:1.2em;margin:8px 0 0}.tm-h2{font-size:1.1rem;margin:22px 0 8px}.tm-next{margin:26px 0}
.tm-tip{position:fixed;pointer-events:none;background:#0f1b2d;color:#fff;padding:4px 8px;border-radius:6px;font-size:.85rem;z-index:50}
</style>
<script src="<?= e(rmt_asset('assets/js/travel-map.js')) ?>" defer></script>
