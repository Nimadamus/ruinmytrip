<?php
/**
 * @var string $snippet
 * @var string $citySnippet
 * @var array<string,array> $cities
 */
?>
<div class="wrap tt">
  <header class="tt-head">
    <p class="tt-kicker">Free for travel sites</p>
    <h1>Put the tourist tax calculator on your site</h1>
    <p class="muted">Your readers work out what the tourist tax adds to their stay without leaving your page. <?= count($cities) ?> cities, the same sourced rates as <a href="<?= e(url('tourist-tax-calculator')) ?>">our calculator</a>, updated when a city changes its rate. Free, no sign up, no cookies, no tracking.</p>
  </header>

  <section class="card tt-embed-code">
    <h2>Paste these two lines where you want it</h2>
    <pre><code><?= e($snippet) ?></code></pre>
    <p class="muted">To open on one city, add its name to the box. For example Amsterdam:</p>
    <pre><code><?= e($citySnippet) ?></code></pre>
    <p class="muted">City names you can use: <?= e(implode(', ', array_keys($cities))) ?>.</p>
    <p class="muted">The calculator ends with a small credit link to RuinMyTrip. Please keep it: it is how readers find the sources for every rate.</p>
  </section>

  <section class="tt-embed-preview">
    <h2>What your readers see</h2>
    <div data-rmt-tax-calc data-city="amsterdam"></div>
  </section>
</div>
<script src="<?= e(url('embed/tourist-tax.js')) ?>" nonce="<?= e(rmt_csp_nonce()) ?>" async></script>
