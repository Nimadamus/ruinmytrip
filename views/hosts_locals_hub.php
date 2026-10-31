<?php
/**
 * /hosts-locals: the front door. Three experiences, a city search, real cities and real people.
 *
 * @var ?array $me @var array $cities @var array $locals @var array $hosts @var string $notFound
 */
$heroImg = '';
foreach ($cities as $c) if (!empty($c['hero_url'])) { $heroImg = (string) $c['hero_url']; break; }
$myLocal = [];
if ($me && $locals) foreach (q_all('SELECT to_user_id, state FROM local_connects WHERE from_user_id = ?', [(int) $me['id']]) as $r) $myLocal[(int) $r['to_user_id']] = (string) $r['state'];
$back = '/hosts-locals';
?>
<section class="hl-hero">
  <?php if ($heroImg !== ''): ?><img class="hl-hero-img" src="<?= e($heroImg) ?>" alt="" fetchpriority="high" data-hide-broken><?php endif; ?>
  <div class="wrap hl-hero-in">
    <p class="hl-crumbs"><a href="<?= e(url()) ?>">Home</a> / Hosts &amp; Locals</p>
    <p class="hl-kicker">Hosts &amp; Locals</p>
    <h1>Experience the world through the people who live there</h1>
    <p class="hl-lede">Going somewhere new? Meet someone who knows it best. Find local friends, travelers on your dates and, soon, hosts who offer a free place to stay.</p>
    <form class="hl-search" method="get" action="<?= e(url('hosts-locals')) ?>" role="search">
      <div class="hl-field"><label for="hl-where">Where are you going
        <input id="hl-where" type="text" name="where" list="hl-cities" placeholder="Tokyo, Lisbon, Mexico City" autocomplete="off" required></label></div>
      <div class="hl-field"><label for="hl-from">From<input id="hl-from" type="date" name="from"></label></div>
      <div class="hl-field"><label for="hl-to">To<input id="hl-to" type="date" name="to"></label></div>
      <button class="btn btn-accent" type="submit">Find people</button>
      <datalist id="hl-cities"><?php foreach (rmt_hl_destinations_for_select() as $d): ?><option value="<?= e((string) $d['name']) ?>"><?php endforeach; ?></datalist>
    </form>
    <?php if ($notFound !== ''): ?>
      <p class="hl-lede" style="margin:12px 0 0">We do not have a city page for "<?= e($notFound) ?>" yet. <a style="color:#fff;text-decoration:underline" href="<?= e(url('hosts-locals/destinations')) ?>">See every city</a>.</p>
    <?php endif; ?>
  </div>
</section>

<section class="hl-sec">
  <div class="wrap">
    <h2 class="hl-h2">Three ways to meet the people behind a place</h2>
    <p class="hl-sub">One free account does all three. Pick what you want from a trip, or offer it in your own city.</p>
    <div class="hl-three">
      <div class="hl-exp">
        <span class="hl-exp-ico" aria-hidden="true">&#9749;</span>
        <h3>Meet a Local</h3>
        <p>People who live in a city and are happy to meet a traveler: a coffee, a walk through their neighbourhood, the restaurants they actually go to, how the metro works.</p>
        <p class="hint">Meeting a local is a meetup, not a place to stay.</p>
        <div class="hl-exp-acts">
          <a class="btn btn-primary btn-sm" href="<?= e(url('hosts-locals/destinations')) ?>">Find locals</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url($me ? 'hosts-locals/me#local' : 'register?return=%2Fhosts-locals%2Fme')) ?>">Be a local</a>
        </div>
      </div>
      <div class="hl-exp">
        <span class="hl-exp-ico" aria-hidden="true">&#9992;</span>
        <h3>Find a Travel Buddy</h3>
        <p>Travelers going to the same place on the same dates. Search by city and dates, filter by interests and travel style, and plan things to do together.</p>
        <p class="hint">Your trip shows the city and dates, never where you are staying.</p>
        <div class="hl-exp-acts">
          <a class="btn btn-primary btn-sm" href="<?= e(url('buddies')) ?>">Search buddies</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('buddies/new')) ?>">Post your trip</a>
        </div>
      </div>
      <div class="hl-exp">
        <span class="hl-exp-ico" aria-hidden="true">&#8962;</span>
        <h3>Stay With a Local <span class="hl-soon">Coming later</span></h3>
        <p>Members who offer a free room or couch to travelers. Hosting will always be free. Stay requests are not open yet.</p>
        <p class="hint">You can apply to host now. Every application is reviewed before anybody sees it.</p>
        <div class="hl-exp-acts">
          <a class="btn btn-primary btn-sm" href="<?= e(url('hosts-locals/become-a-host')) ?>">Become a host</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('hosts-locals/how-it-works')) ?>">How it works</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="hl-sec hl-sec-tint">
  <div class="wrap">
    <h2 class="hl-h2">Featured cities</h2>
    <p class="hl-sub">Counted live: the locals and travelers you see are real members, and a quiet city says so.</p>
    <div class="hl-cities">
      <?php foreach ($cities as $c): $people = (int) $c['locals'] + (int) $c['hosts'] + (int) $c['going']; ?>
        <a class="hl-city" href="<?= e(url('hosts-locals/destinations/' . $c['slug'])) ?>">
          <?php if (!empty($c['hero_url'])): ?><img src="<?= e((string) $c['hero_url']) ?>" alt="" loading="lazy" data-hide-broken><?php endif; ?>
          <span class="hl-city-txt"><b><?= e((string) $c['name']) ?></b>
            <small><?php $bits = [];
              if ($c['locals']) $bits[] = $c['locals'] . ' local' . ($c['locals'] === 1 ? '' : 's');
              if ($c['going'])  $bits[] = $c['going'] . ' going';
              if ($c['hosts'])  $bits[] = $c['hosts'] . ' hosting soon';
              echo e($bits ? implode(' · ', $bits) : 'Be the first local here'); ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p style="margin:16px 0 0"><a class="v2-link" href="<?= e(url('hosts-locals/destinations')) ?>">Every city &rarr;</a></p>
  </div>
</section>

<section class="hl-sec">
  <div class="wrap">
    <h2 class="hl-h2">Locals who said yes to meeting travelers</h2>
    <?php if ($locals): ?>
      <p class="hl-sub">Ask to meet and they decide. Nobody can message anybody until both sides agree.</p>
      <div class="hl-people"><?php foreach ($locals as $lc) include __DIR__ . '/_hl_local.php'; ?></div>
    <?php else: ?>
      <div class="hl-empty">
        <h3>The first locals are joining now</h3>
        <p>Live somewhere travelers go? Say what you would be happy to do, from a coffee to a walk around your neighbourhood. Only your city shows, and you decide who you meet.</p>
        <a class="btn btn-primary" href="<?= e(url($me ? 'hosts-locals/me#local' : 'register?return=%2Fhosts-locals%2Fme')) ?>">Become a local</a>
      </div>
    <?php endif; ?>
    <?php if ($hosts): ?>
      <h3 style="margin:26px 0 10px">Interested in hosting</h3>
      <p class="hint" id="hl-stays-note">Stay requests are not open yet.</p>
      <div class="hl-people"><?php foreach ($hosts as $hc) include __DIR__ . '/_hl_host.php'; ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="hl-sec hl-sec-tint">
  <div class="wrap">
    <h2 class="hl-h2">How it works</h2>
    <ol class="hl-steps">
      <li><b>Make one profile</b>Your photo, a few lines about you, your languages and what you like doing. Traveler, local and host are all the same account.</li>
      <li><b>Ask, and get asked</b>A request carries no message. The other person says yes or no, and a no is never announced.</li>
      <li><b>Talk, then meet</b>Once both of you say yes, private messages open. Meet somewhere public the first time, and leave a reference afterwards.</li>
    </ol>
    <p style="margin:14px 0 0"><a class="v2-link" href="<?= e(url('hosts-locals/how-it-works')) ?>">The full guide &rarr;</a></p>
  </div>
</section>

<section class="hl-sec">
  <div class="wrap">
    <h2 class="hl-h2">Trust and safety</h2>
    <p class="hl-sub">We are connecting strangers, so we say plainly what we check and what we do not.</p>
    <div class="hl-trust">
      <div><h3>Adults only</h3><p>Meeting travelers, posting trips and hosting are for members 18 and over.</p></div>
      <div><h3>We do not check identities</h3><p>"Email confirmed" means exactly that and nothing more. Read profiles and references, and meet in public first.</p></div>
      <div><h3>You decide who reaches you</h3><p>No messages until both sides agree. Block anybody in one tap, report anything, and we review every report.</p></div>
      <div><h3>No addresses, ever</h3><p>Profiles show a city, and a host shows a neighbourhood at most. Never a street, a number or a live location.</p></div>
    </div>
    <p class="hl-callout" style="margin-top:16px">A check is not a guarantee. RuinMyTrip cannot promise that any host, guest or meetup is safe. Trust your judgement, meet in public first, and tell someone where you are going. <a href="<?= e(url('hosts-locals/safety')) ?>">Read the safety guide</a>.</p>
  </div>
</section>

<?php if (!$me): ?>
<section class="hl-sec hl-sec-tint">
  <div class="wrap" style="text-align:center">
    <h2 class="hl-h2">Join free</h2>
    <p class="hl-sub" style="margin:0 auto 16px">One account for reviews, travel buddies, locals and hosting.</p>
    <a class="btn btn-accent" href="<?= e(url('register?return=%2Fhosts-locals%2Fme')) ?>">Create your profile</a>
  </div>
</section>
<?php endif; ?>
