<?php
/**
 * @var array<string,array> $cities
 * @var string $slug    the chosen city, or ''
 * @var ?array $city
 * @var string $tierKey
 * @var int $nights
 * @var int $people
 * @var int $rooms
 * @var string $priceIn
 * @var ?array $result
 */
$needs = $city ? rmt_tt_needs($city) : null;
$tiers = $city ? array_filter($city['tiers'], static fn(array $t) => ($t['label'] ?? '') !== '') : [];
?>
<div class="wrap tt">
  <header class="tt-head">
    <p class="tt-kicker">Free travel tool</p>
    <h1>Tourist tax calculator</h1>
    <p class="muted">Pick a city and see what its 2026 tourist tax adds to your stay. No account needed.</p>
  </header>

  <form class="tt-form card" method="get" action="<?= e(url('tourist-tax-calculator')) ?>" data-tt-form>
    <label class="tt-wide"><span>City</span>
      <select name="city" data-tt-city>
        <option value="">Choose a city</option>
        <?php foreach ($cities as $k => $c): ?>
          <option value="<?= e($k) ?>"<?= $k === $slug ? ' selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($city): ?>
      <?php if ($tiers): ?>
        <label class="tt-wide"><span>Where you are staying</span>
          <select name="tier">
            <?php foreach ($tiers as $k => $t): ?>
              <option value="<?= e((string) $k) ?>"<?= (string) $k === $tierKey ? ' selected' : '' ?>><?= e($t['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <label><span>Nights</span><input type="number" name="nights" min="1" max="60" value="<?= e((string) $nights) ?>" inputmode="numeric"></label>
      <?php if ($needs['people']): ?>
        <label><span>People who pay</span><input type="number" name="people" min="1" max="20" value="<?= e((string) $people) ?>" inputmode="numeric"></label>
      <?php endif; ?>
      <?php if ($needs['rooms']): ?>
        <label><span>Rooms</span><input type="number" name="rooms" min="1" max="10" value="<?= e((string) $rooms) ?>" inputmode="numeric"></label>
      <?php endif; ?>
      <?php if ($needs['price']): ?>
        <label class="tt-wide"><span><?= $needs['per_person_price'] ? 'Price per person per night' : 'Room price per night' ?> (<?= e($city['cur']) ?>)</span>
          <input type="number" name="price" min="0" step="any" value="<?= e($priceIn) ?>" inputmode="decimal" required></label>
      <?php endif; ?>
    <?php endif; ?>
    <button class="btn btn-accent tt-wide" type="submit"><?= $city ? 'Work it out' : 'Next' ?></button>
  </form>

  <?php if ($result): ?>
    <section class="tt-result card" aria-live="polite">
      <p class="tt-kicker"><?= e($city['name']) ?> tourist tax for this stay</p>
      <p class="tt-total"><?= e(rmt_tt_money($result['total'], $city['cur'])) ?></p>
      <p class="muted"><?= e(rmt_tt_money($result['per_night'], $city['cur'])) ?> a night for <?= e((string) $result['paid_nights']) ?> <?= $result['paid_nights'] === 1 ? 'night' : 'nights' ?><?php if ($result['paid_nights'] < $result['nights']): ?>. <?= e($city['name']) ?> stops charging after <?= e((string) $result['paid_nights']) ?>, so the rest of your <?= e((string) $result['nights']) ?> nights are free of it<?php endif; ?>.</p>
      <?php if ($city['tip'] !== ''): ?><p><?= e($city['tip']) ?></p><?php endif; ?>
      <p class="tt-links">
        <a href="<?= e(url('blog/' . $city['note'])) ?>">How <?= e($city['name']) ?> charges it, with sources</a>
        <a href="<?= e(url('d/' . $city['dest'])) ?>">See who is going to <?= e($city['name']) ?></a>
      </p>
    </section>
  <?php endif; ?>

  <section class="tt-table">
    <h2>2026 tourist tax by city</h2>
    <div class="tt-scroll"><table>
      <thead><tr><th>City</th><th>2026 rate</th></tr></thead>
      <tbody>
        <?php foreach ($cities as $k => $c): ?>
          <tr><td><a href="<?= e(url('tourist-tax-calculator?city=' . $k)) ?>"><?= e($c['name']) ?></a></td><td><?= e($c['rate']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted">Rates come from our <a href="<?= e(url('blog/tourist-taxes-2026')) ?>">2026 tourist tax notes</a>, which cite the city or tax office. This is an estimate: your booking is the final word.</p>
  </section>

  <section class="tt-faq">
    <h2>Questions</h2>
    <?php foreach (rmt_tt_faq() as [$q, $a]): ?>
      <h3><?= e($q) ?></h3>
      <p><?= e($a) ?></p>
    <?php endforeach; ?>
  </section>
</div>
<script src="<?= e(rmt_asset('assets/js/tourist-tax.js')) ?>" defer></script>
