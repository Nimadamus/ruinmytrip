<?php
/**
 * @var list<array> $rows
 * @var string $checked
 * @var list<array> $pending
 */
$fmtDate = static fn(string $d): string => $d !== '' && ($t = strtotime($d)) ? date('j F Y', $t) : 'Date not set yet';
$newCount = count(array_filter($rows, static fn(array $r) => $r['new']));
?>
<div class="wrap tt tt27">
  <header class="tt-head">
    <p class="tt-kicker">Data study<?= $checked !== '' ? ', checked ' . e(date('j F Y', (int) strtotime($checked))) : '' ?></p>
    <h1>Every European tourist tax change for 2027, ranked</h1>
    <p class="muted"><?= count($rows) ?> European cities change what visitors pay for a night in 2027<?= $newCount ? ', ' . $newCount . ' of them with a tax that did not exist before' : '' ?>. We priced the same trip in each and ranked them by how much more it pays. Every rate links to the city or government page it comes from.</p>
    <p class="tt-links"><a href="<?= e(url('tourist-tax-2027.csv')) ?>">Download the table (CSV)</a> <a href="#method">How we worked it out</a></p>
  </header>

  <section class="tt-table" id="ranking">
    <h2>The ranking</h2>
    <p class="muted">The trip: 2 adults, 1 room, 4 nights at 200 euros or pounds a night before VAT (2,000 kroner in Norway), in a four star hotel where the city charges by category.</p>
    <div class="tt-scroll"><table>
      <thead><tr><th>#</th><th>City</th><th>Tax 2026</th><th>Tax 2027</th><th>Change</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="#<?= e($r['key']) ?>"><?= e($r['city']) ?></a><br><span class="muted"><?= e($r['country']) ?></span></td>
            <td><?= $r['now'] > 0 ? e(rmt_tt_money($r['now'], $r['cur'])) : 'No tax' ?></td>
            <td><?= e(rmt_tt_money($r['next'], $r['cur'])) ?></td>
            <td><?= $r['new'] ? 'New tax' : ($r['rise_pct'] > 0 ? '+' : '') . e((string) $r['rise_pct']) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="tt27-cities">
    <h2>City by city</h2>
    <?php foreach ($rows as $r): ?>
      <article class="card tt27-city" id="<?= e($r['key']) ?>">
        <h3><?= e($r['city']) ?>: <?= e($r['rate_2027']) ?></h3>
        <p><strong><?= e(RMT_TT27_STATUS[$r['status']]) ?></strong>, from <?= e($fmtDate($r['date'])) ?>. In 2026: <?= e($r['rate_2026'] !== '' ? $r['rate_2026'] : 'no tax') ?>.</p>
        <?php if ($r['notes'] !== ''): ?><p class="muted"><?= e($r['notes']) ?></p><?php endif; ?>
        <?php foreach ($r['sources'] as $s): ?>
          <blockquote class="tt27-quote">
            <p>&ldquo;<?= e($s['quote']) ?>&rdquo;</p>
            <?php if ($s['quote_en'] !== '' && $s['quote_en'] !== $s['quote']): ?><p class="muted">In English: &ldquo;<?= e($s['quote_en']) ?>&rdquo;</p><?php endif; ?>
            <footer><a href="<?= e($s['url']) ?>" rel="noopener" target="_blank"><?= e($s['publisher']) ?></a></footer>
          </blockquote>
        <?php endforeach; ?>
        <?php if (!$r['in_calc'] && $r['note']): ?>
          <p class="tt-links"><a href="<?= e(url('blog/' . $r['note'])) ?>">How the <?= e($r['city']) ?> levy works</a></p>
        <?php endif; ?>
        <?php if ($r['in_calc']): ?>
          <p class="tt-links"><a href="<?= e(url('tourist-tax-calculator?city=' . $r['key'])) ?>">Work out your own stay in <?= e($r['city']) ?></a>
            <?php if ($r['note']): ?><a href="<?= e(url('blog/' . $r['note'])) ?>">How <?= e($r['city']) ?> charges it now</a><?php endif; ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>

  <?php if ($pending): ?>
  <section class="tt27-cities" id="coming">
    <h2>Also coming in 2027, rates not final</h2>
    <p class="muted">These taxes are set to start in 2027 but the rate for a hotel night is not final, so they are not in the ranking yet.</p>
    <?php foreach ($pending as $r): ?>
      <article class="card tt27-city">
        <h3><?= e($r['city']) ?>: <?= e($r['rate']) ?></h3>
        <p>New tax from <?= e($fmtDate($r['date'])) ?>. <?= e($r['notes']) ?></p>
        <?php foreach ($r['sources'] as $s): ?>
          <blockquote class="tt27-quote">
            <p>&ldquo;<?= e((string) $s['quote']) ?>&rdquo;</p>
            <?php if (($s['quote_en'] ?? '') !== ''): ?><p class="muted">In English: &ldquo;<?= e((string) $s['quote_en']) ?>&rdquo;</p><?php endif; ?>
            <footer><a href="<?= e((string) $s['url']) ?>" rel="noopener" target="_blank"><?= e((string) $s['publisher']) ?></a></footer>
          </blockquote>
        <?php endforeach; ?>
      </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="tt-faq" id="method">
    <h2>How we worked it out</h2>
    <p>A city is in the table only when the 2027 change is approved by its council, written into law, or formally proposed, and the official page saying so is quoted above. Changes reported only in the news are left out until an official source confirms them. Cities with no published 2027 change are not listed.</p>
    <p>The trip is the same everywhere so the cities can be compared: 2 adults sharing 1 room for 4 nights at 200 euros or pounds a night before VAT, or 2,000 kroner in Norway. Where a city charges by hotel category we used four stars. Where a city stops charging after a number of nights, we applied that limit. The change column compares the tax on that trip in 2027 with 2026, so euro, pound and krone cities can sit in one list without an exchange rate. New taxes come first, in the order they start. VAT, booking fees and exemptions for children are not included.</p>
    <p>Rates change. Your booking is the final word. Spot a mistake or a change we missed? <a href="<?= e(url('contact')) ?>">Tell us</a> and we will check it.</p>
    <p class="muted">You may republish the table with a link to this page (<a href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a>). Writing about it? The CSV has every row and source.</p>
  </section>

  <?php $byg = ['topic' => 'tax', 'city' => '', 'name' => '', 'source' => 'tax_2027', 'return' => '/tourist-tax-2027#byg'];
        include __DIR__ . '/_byg_form.php'; ?>
</div>
