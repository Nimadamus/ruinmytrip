<?php /** @var array $bf @var array $res @var array $cards @var array $sailings @var ?array $me @var array $dests @var array $countries @var ?string $label */
$bcBack = (string) ($_SERVER['REQUEST_URI'] ?? '/buddies');
$action = url(ltrim((string) parse_url($bcBack, PHP_URL_PATH), '/'));
$cruiseOpen = $bf['type'] === 'cruise' || $bf['line'] !== '' || $bf['ship'] !== '' || $bf['port'] !== '';
$postQuery = array_filter(['type' => $bf['type'], 'dest' => $bf['dest']['slug'] ?? '', 'from' => $bf['from'], 'to' => $bf['to'],
                           'ship' => $bf['ship'], 'line' => $bf['line'], 'port' => $bf['port']]);
$postPath = '/buddies/new' . ($postQuery ? '?' . http_build_query($postQuery) : '');
$postHref = $me ? url(ltrim($postPath, '/')) : url('login?return=' . rawurlencode($postPath));
$n = count($cards);
$place = $bf['dest']['name'] ?? ($bf['country'] !== '' ? $bf['country'] : ($bf['where'] !== '' ? $bf['where'] : ''));
$shown = array_filter($sailings, static fn($s) => count($s['cards']) > 1);
?>
<?php $meetOn = 'going'; include __DIR__ . '/_meet_nav.php'; ?>
<section class="buddy-hero"><div class="wrap">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <?php if ($label && str_starts_with(trim((string) parse_url($bcBack, PHP_URL_PATH), '/'), 'buddies/')): ?><a href="<?= e(url('buddies')) ?>">Travel buddies</a> / <?= e($label) ?><?php else: ?>Travel buddies<?php endif; ?></p>
  <p class="eyebrow">Travel buddies</p>
  <h1><?= $bf['type'] === 'cruise' ? 'Find people on your cruise.' : 'Going somewhere? Find people heading the same way.' ?></h1>
  <p class="buddy-lede">Meet travelers going where you are going, connect with locals and people already there, or find others on the same cruise. Nobody can message you until you say yes.</p>
  <div class="buddy-cta">
    <a class="btn btn-accent" href="<?= e($postHref) ?>">Post your trip</a>
    <?php if ($me): ?><a class="btn btn-ghost-light" href="<?= e(url('buddies/mine')) ?>">Your buddies and requests</a><?php endif; ?>
  </div>

  <form class="buddy-search" method="get" action="<?= e($action) ?>" role="search">
    <div class="bs-row">
      <label class="bs-where"><span>Where</span>
        <input type="text" name="where" list="bs-places" value="<?= e($bf['where']) ?>" placeholder="Tokyo, Thailand, a ship name" autocomplete="off"></label>
      <label><span>From</span><input type="date" name="from" value="<?= e($bf['from']) ?>"></label>
      <label><span>To</span><input type="date" name="to" value="<?= e($bf['to']) ?>"></label>
      <button class="btn btn-accent bs-go" type="submit">Find travelers</button>
    </div>
    <datalist id="bs-places">
      <?php foreach ($dests as $d): ?><option value="<?= e($d['name']) ?>"><?= e((string) $d['country']) ?></option><?php endforeach; ?>
      <?php foreach ($countries as $c): ?><option value="<?= e($c) ?>"></option><?php endforeach; ?>
    </datalist>
    <?php $pathType = str_starts_with(trim((string) parse_url($bcBack, PHP_URL_PATH), '/'), 'buddies/');
          // A type that came from the address (/buddies/cruise) is the page, not a filter somebody set.
          $moreActive = ($bf['type'] !== '' && !$pathType) || $bf['line'] !== '' || $bf['ship'] !== '' || $bf['port'] !== '' || $bf['party'] !== '' || $bf['interest'] !== '' || $bf['show'] !== 'all' || $bf['flexible'] || $bf['myage']; ?>
    <details class="bs-filters" id="bs-filters"<?= $moreActive ? ' open' : '' ?>>
    <summary>More filters<?= $moreActive ? ' (on)' : '' ?></summary>
    <div class="bs-row bs-more">
      <label><span>Trip type</span><select name="type"><option value="">Any trip</option>
        <?php foreach (RMT_BUDDY_TYPES as $k => $v): ?><option value="<?= e($k) ?>"<?= $bf['type'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label><span>Travelling</span><select name="party"><option value="">Solo, group, anyone</option>
        <?php foreach (RMT_BUDDY_PARTIES as $k => $v): ?><option value="<?= e($k) ?>"<?= $bf['party'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label><span>Into</span><select name="interest"><option value="">Any interest</option>
        <?php foreach (RMT_INTERESTS as $k => $v): ?><option value="<?= e($k) ?>"<?= $bf['interest'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label><span>Show</span><select name="show">
        <?php foreach (['all' => 'Everyone', 'going' => 'Travelers going', 'here' => 'There right now', 'locals' => 'Locals open to meeting'] as $k => $v): ?>
          <option value="<?= e($k) ?>"<?= $bf['show'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label class="bs-check"><input type="checkbox" name="flexible" value="1"<?= $bf['flexible'] ? ' checked' : '' ?>> <span>Flexible dates, a week either side</span></label>
      <?php if ($me): ?>
        <label class="bs-check" title="Uses only the age range a poster asked for, checked against your own age. Nobody else's age is ever searched or shown."><input type="checkbox" name="myage" value="1"<?= $bf['myage'] ? ' checked' : '' ?>> <span>Only trips open to my age</span></label>
      <?php endif; ?>
    </div>
    <details class="bs-cruise"<?= $cruiseOpen ? ' open' : '' ?>><summary>Cruise details: line, ship, port</summary>
      <div class="bs-row">
        <label><span>Cruise line</span><input type="text" name="line" value="<?= e($bf['line']) ?>" placeholder="Royal Caribbean"></label>
        <label><span>Ship</span><input type="text" name="ship" value="<?= e($bf['ship']) ?>" placeholder="Icon of the Seas"></label>
        <label><span>Departure port</span><input type="text" name="port" value="<?= e($bf['port']) ?>" placeholder="Miami"></label>
      </div>
    </details>
    </details>
  </form>
  <script>
  (function () { var d = document.getElementById('bs-filters'); if (d && window.matchMedia('(min-width: 760px)').matches) d.open = true; })();
  </script>
</div></section>

<div class="wrap buddy-body">
  <nav class="buddy-types" aria-label="Kind of trip">
    <a class="chip<?= $bf['type'] === '' ? ' active' : '' ?>" href="<?= e(url('buddies')) ?>">All trips</a>
    <?php foreach (RMT_BUDDY_TYPES as $k => $v): ?>
      <a class="chip<?= $bf['type'] === $k ? ' active' : '' ?>" href="<?= e(url('buddies/' . str_replace('_', '-', $k))) ?>"><?= e($v) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!empty($mine)): ?>
    <p class="buddy-mine"><span class="hint">Who else is going on your trips:</span>
      <?php foreach ($mine as $m): ?><a class="chip" href="<?= e($m['href']) ?>"><?= e($m['label']) ?></a><?php endforeach; ?></p>
  <?php endif; ?>
  <div class="buddy-layout">
    <main class="buddy-results">
      <div class="buddy-count">
        <h2><?php if ($n === 0): ?>No travelers <?= $place !== '' ? 'for ' . e($place) . ' ' : '' ?>yet<?php else: ?><?= $n ?> <?= $n === 1 ? 'traveler' : 'travelers' ?><?= $place !== '' ? ' for ' . e($place) : '' ?><?php endif; ?></h2>
        <?php if ($n): ?><p class="hint"><?= implode(' · ', array_filter([
            $res['counts']['post'] ? $res['counts']['post'] . ' looking for company' : '',
            $res['counts']['trip'] ? $res['counts']['trip'] . ' with trips posted' : '',
            $res['counts']['here'] ? $res['counts']['here'] . ' there right now' : '',
            $res['counts']['local'] ? $res['counts']['local'] . ($res['counts']['local'] === 1 ? ' local' : ' locals') : '',
          ])) ?></p><?php endif; ?>
      </div>
      <?php if ($res['widened']): ?>
        <div class="callout">Nobody overlaps <?= e(date('M j', strtotime($bf['from']))) ?> to <?= e(date('M j', strtotime($bf['to']))) ?> yet, so these are the people going<?= $place !== '' ? ' to ' . e($place) : '' ?> at other times. <a href="<?= e($postHref) ?>">Post your dates</a> and the next traveler who matches will be told.</div>
      <?php endif; ?>

      <?php if ($shown): ?>
        <section class="buddy-sailings">
          <h3>On the same sailing</h3>
          <?php foreach ($shown as $s): ?>
            <p class="sailing"><b><?= e($s['ship'] !== '' ? $s['ship'] : $s['cruise_line']) ?></b>
              <?= e(date('M j, Y', strtotime($s['from']))) ?><?= $s['nights'] ? ', ' . (int) $s['nights'] . ' nights' : '' ?><?= $s['port'] !== '' ? ', from ' . e($s['port']) : '' ?>
              &middot; <?= count($s['cards']) ?> travelers
              <a href="<?= e(url('buddies/cruise') . rmt_buddy_query($bf, ['ship' => $s['ship'], 'from' => $s['from'], 'to' => $s['from'], 'type' => ''])) ?>">See them</a></p>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <?php if ($n === 0 && $examples): ?>
        <div class="buddy-first">
          <p><b><?= $place !== '' ? 'Be the first real traveler people find for ' . e($place) . '.' : 'Be the first real traveler people find.' ?></b>
            Post where you are going and when, and you will be told the moment somebody lines up with you.</p>
          <a class="btn btn-accent btn-sm" href="<?= e($postHref) ?>">Post your trip</a>
        </div>
      <?php elseif ($n === 0): ?>
        <div class="empty-cta">
          <h3><?= $place !== '' ? 'Be the first traveler people find for ' . e($place) . '.' : 'Be the first traveler people find.' ?></h3>
          <p class="muted">Post where you are going and when. When somebody posts a trip that lines up with yours, you get told, and they can find you here.</p>
          <p><a class="btn btn-accent" href="<?= e($postHref) ?>">Post your trip</a>
            <?php if (rmt_buddy_filters_active($bf)): ?><a class="btn btn-ghost" href="<?= e(url('buddies')) ?>">Clear the search</a><?php endif; ?></p>
        </div>
      <?php else: ?>
        <div class="buddy-grid">
          <?php foreach ($cards as $bc): ?><?php include __DIR__ . '/_buddy_card.php'; ?><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($examples): ?>
        <section class="buddy-examples" aria-labelledby="bx-h">
          <div class="buddy-examples-head">
            <h2 id="bx-h">Example listings</h2>
            <p>These are samples, not real travelers. They show how Travel Buddies works while the community grows: people post a city or a cruise and dates, others ask to join, and messages open once the poster says yes.</p>
          </div>
          <div class="buddy-grid">
            <?php foreach ($examples as $bc): ?><?php include __DIR__ . '/_buddy_card.php'; ?><?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>

    <aside class="buddy-side">
      <?php if ($me): ?>
        <div class="card"><div class="card-body">
          <h3>Already there?</h3>
          <p class="hint">Show up for travelers searching a city while you are in it. Only the city and your dates show.</p>
          <form method="post" action="<?= e(url('buddies/here')) ?>"><?= csrf_field() ?>
            <label for="here-dest">I am in</label>
            <select id="here-dest" name="destination_id" required><option value="">Pick a city</option>
              <?php foreach ($dests as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) ($bf['dest_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
            <label for="here-until">Until</label>
            <input id="here-until" type="date" name="until" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d', strtotime('+3 days'))) ?>">
            <button class="btn btn-primary btn-sm" style="margin-top:10px">I'm here now</button>
          </form>
        </div></div>
        <div class="card"><div class="card-body">
          <h3>Live somewhere travelers go?</h3>
          <p class="hint">List yourself as a local who is happy to meet travelers. Only your city shows, and you choose who to accept.</p>
          <form method="post" action="<?= e(url('buddies/local')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="return" value="<?= e($bcBack) ?>">
            <label for="local-dest">I live in</label>
            <select id="local-dest" name="destination_id" required><option value="">Pick a city</option>
              <?php foreach ($dests as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-ghost btn-sm" style="margin-top:10px">I'm open to meeting travelers</button>
          </form>
        </div></div>
      <?php else: ?>
        <div class="card"><div class="card-body">
          <h3>Join to connect</h3>
          <p class="hint">Free and 18+. Post your trip, say you are already there, or list yourself as a local.</p>
          <a class="btn btn-accent btn-sm" href="<?= e(url('register?return=' . rawurlencode('/buddies'))) ?>">Create an account</a>
        </div></div>
      <?php endif; ?>
      <?php /* Hub for the country and cruise line landing pages (app/buddy_landing.php). */ ?>
      <nav class="card bl-hub" aria-label="Travel buddies by place"><div class="card-body">
        <h3>Travel buddies by country</h3>
        <p class="bl-links"><?php foreach (rmt_buddy_landing_all('country') as $lp): ?><a class="chip" href="<?= e(url(rmt_buddy_landing_path($lp['slug']))) ?>"><?= e($lp['name']) ?></a><?php endforeach; ?></p>
        <h3>Cruise buddies by line</h3>
        <p class="bl-links"><?php foreach (rmt_buddy_landing_all('cruise') as $lp): ?><a class="chip" href="<?= e(url(rmt_buddy_landing_path($lp['slug']))) ?>"><?= e($lp['name']) ?></a><?php endforeach; ?></p>
      </div></nav>
      <div class="callout buddy-safety"><b>How privacy works.</b> Cards show a city or ship and dates, never a hotel, cabin, address or live location. Requests carry no contact details, and messages open only after the other person accepts. Block or <a href="<?= e(url('report')) ?>">report</a> anyone. Meet in public first. <a href="<?= e(url('safety')) ?>">Safety guide</a></div>
    </aside>
  </div>
</div>
