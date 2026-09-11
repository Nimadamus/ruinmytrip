<?php /** @var array $d @var ?array $me @var array $hub @var ?array $myGoing */
$city = (string) $d['name'];
$join = static fn(string $path): string => url('register?return=' . rawurlencode($path));
$here = '/d/' . $d['slug'] . '/travelers';
/* Which modules have nothing in them. A city nobody has posted about used to print six separate
   paragraphs, one under each heading, each of them saying a version of "nobody is here yet". Six
   statements of absence in a row is the strongest possible argument to close the tab, and it is
   the same fact repeated. The page says it once now, at the end, as an invitation with the actions
   attached, and skips the headings whose modules are empty. Nothing is pretended: a city with
   nobody in it still says so. */
$rmt_gaps = [];
$hereNow = $hereNow ?? [];
$cityPhotos = $cityPhotos ?? [];
$openLocals = $openLocals ?? [];
$cityPlans = $cityPlans ?? [];
$cityPopular = $cityPopular ?? [];
$winFrom = $winFrom ?? '';
$winTo = $winTo ?? '';
$winSource = $winSource ?? 'all';
$planCats = $planCats ?? [];
$planCat = $planCat ?? '';
$planOpen = $planOpen ?? false;
$planOpenCount = $planOpenCount ?? 0;
/* Every chip keeps the date window it was clicked in, because the window is the whole reason
   somebody is on this page. */
$planUrl = static function (array $over) use ($d, $winFrom, $winTo, $winSource, $planCat, $planOpen): string {
    $q = [];
    if ($winSource === 'url' || ($winSource === 'mine' && $winFrom !== '')) {
        $q['from'] = $winFrom; $q['to'] = $winTo;
    }
    $cat  = array_key_exists('cat', $over) ? $over['cat'] : $planCat;
    $open = array_key_exists('open', $over) ? $over['open'] : $planOpen;
    if ($cat !== '') $q['cat'] = $cat;
    if ($open) $q['open'] = '1';
    return url('d/' . $d['slug'] . '/travelers' . ($q ? '?' . http_build_query($q) : '')) . '#plans';
};
?>
<div class="wrap"><p class="crumbs"><a href="<?= e(url()) ?>">Home</a> /
  <a href="<?= e(url('d/'.$d['slug'])) ?>"><?= e($city) ?></a> / Travelers</p></div>

<div class="wrap" style="max-width:900px">
  <h1 style="margin-bottom:.2rem">Travelers in <?= e($city) ?></h1>
  <p class="muted" style="max-width:62ch">Who is going and when, what meetups are on, and the people
    who have already been. Everything here is posted by members, not by us.</p>

  <?php /* The one thing a stranger who landed from search is asked to do. It is the product, not a
           mailing list: three real actions, each of which is why they searched. */ ?>
  <div class="card" style="margin:18px 0"><div class="card-body">
    <?php if ($me): ?>
      <p class="eyebrow" style="margin:0 0 10px">Your move</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= e(url('going')) ?>"><?= $myGoing ? 'Update your dates' : 'Post your dates' ?></a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('meetup/new?destination='.(int)$d['id'])) ?>">Host a meetup</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('talk')) ?>">Ask the group</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('matches')) ?>">Find matching dates</a>
      </div>
    <?php else: ?>
      <p style="margin:0 0 10px;font-size:1.05rem"><b>Meet the people going to <?= e($city) ?>.</b>
        Post your dates and see whose overlap, join a meetup, and ask travelers who have been.
        Free, takes a minute.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <a class="btn btn-accent" href="<?= e($join($here)) ?>">Join RuinMyTrip</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('login?return=' . rawurlencode($here))) ?>">Sign in</a>
        <span class="hint">16+. Meetups are 18+ and always in public.</span>
      </div>
    <?php endif; ?>
  </div></div>

  <?php if (!$hub['active']): ?>
    <?php /* An empty city, said plainly. Pretending otherwise is the thing that makes somebody
             close the tab: they can tell, and then nothing else on the page is believable. */ ?>
    <div class="callout">
      <b>Nobody has posted about <?= e($city) ?> yet.</b> Whoever goes first is the person every
      traveler who searches this next month will find. Post your dates, or ask the question you
      came here with.
    </div>
  <?php endif; ?>

  <?php /* What people are actually doing. The question the site could not answer until now, and
           the one that turns two overlapping date ranges into a reason to say hello: "dinner in
           Alfama on Friday" is something another traveler can answer. Real plans by real people,
           and the counts are counts of people. */ ?>
  <?php if ($cityPlans || $planCat !== '' || $planOpen): ?>
    <h2 id="plans">What travelers are doing<?php if ($winFrom !== ''): ?>
      <span class="hint" style="font-family:var(--sans);font-size:.8rem;text-transform:none;letter-spacing:0">
        <?= e(rmt_card_date_range($winFrom, $winTo)) ?><?= $winSource === 'mine' ? ', while you are here' : '' ?>
      </span><?php endif; ?></h2>
    <?php if ($winSource === 'mine'): ?>
      <p class="hint" style="margin:0 0 10px">Narrowed to your own dates.
        <a href="<?= e(url('d/'.$d['slug'].'/travelers?from=&to=')) ?>">Show everything upcoming</a>.</p>
    <?php endif; ?>

    <?php /* Two filters, both one tap, both real. The "open to join" chip only exists when a plan
             is actually open, and a category chip only when somebody planned something in it. */ ?>
    <?php if ($planOpenCount > 0 || count($planCats) > 1): ?>
      <div class="plan-filters">
        <a class="chip<?= ($planCat === '' && !$planOpen) ? ' is-on' : '' ?>" href="<?= e($planUrl(['cat'=>'','open'=>false])) ?>">All</a>
        <?php if ($planOpenCount > 0): ?>
          <a class="chip<?= $planOpen ? ' is-on' : '' ?>" href="<?= e($planUrl(['open'=>!$planOpen])) ?>">Open to join <span class="hint"><?= (int) $planOpenCount ?></span></a>
        <?php endif; ?>
        <?php foreach ($planCats as $ck => $cn): ?>
          <a class="chip<?= $planCat === $ck ? ' is-on' : '' ?>" href="<?= e($planUrl(['cat'=>$planCat === $ck ? '' : $ck])) ?>"><?= e(RMT_ACTIVITY_CATEGORIES[$ck]) ?> <span class="hint"><?= (int) $cn ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <ul class="city-plans">
      <?php foreach (array_slice($cityPlans, 0, 12) as $pl): ?>
        <li>
          <a class="city-plan-who" href="<?= e(url('u/'.$pl['username'])) ?>">
            <img class="avatar" style="width:26px;height:26px" src="<?= e(avatar_url($pl['avatar_url'] ?? null)) ?>" alt=""></a>
          <span>
            <a href="<?= e(url('activity/'.(int) $pl['id'])) ?>"><b><?= e((string) $pl['title']) ?></b></a>
            <?php if (empty($pl['cancelled_at']) && in_array((string) $pl['join_mode'], ['ask','open'], true)): ?>
              <span class="chip chip-join"><?= (string) $pl['join_mode'] === 'open' ? 'Join' : 'Ask to join' ?></span>
            <?php endif; ?>
            <span class="hint">
              <a href="<?= e(url('trip/'.(int) $pl['trip_id'].'/'.(string) $pl['trip_slug'])) ?>">@<?= e((string) $pl['username']) ?></a>
              <?php if (!empty($pl['day'])): ?> &middot; <?= e(date('D j M', strtotime((string) $pl['day']))) ?><?php endif; ?>
              <?php if (!empty($pl['start_time'])): ?> &middot; <?= e((string) $pl['start_time']) ?><?php endif; ?>
              <?php if (!empty($pl['place_name'])): ?> &middot; <?= e((string) $pl['place_name']) ?><?php
                elseif (!empty($pl['location_text'])): ?> &middot; <?= e((string) $pl['location_text']) ?><?php endif; ?>
              <?php if ((int) ($pl['going_count'] ?? 0) > 0): ?>
                &middot; <?= (int) $pl['going_count'] ?> <?= (int) $pl['going_count'] === 1 ? 'other person coming' : 'others coming' ?>
              <?php endif; ?>
            </span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$cityPlans): ?>
      <p class="hint">Nothing here matches that yet. <a href="<?= e($planUrl(['cat'=>'','open'=>false])) ?>">Show everything</a>.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* What more than one person planned. Counted in people, never rounded: if one person
           saved it, it says one, and if nothing has two it does not appear at all. */ ?>
  <?php $rmt_pop = array_values(array_filter($cityPopular, static fn(array $r) => (int) $r['n'] > 1)); ?>
  <?php if ($rmt_pop): ?>
    <h2 style="margin-top:28px">More than one traveler is doing this</h2>
    <div class="tag-list">
      <?php foreach ($rmt_pop as $pp): ?>
        <span class="chip"><?= e((string) $pp['label']) ?> <span class="hint"><?= (int) $pp['n'] ?> travelers</span></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* Who is in the city today, according to dates they published themselves. Above "who is
           going", because somebody who is there now is somebody you can have a coffee with this
           afternoon. Never a location: a range that covers today is a fact its owner wrote down. */ ?>
  <?php if ($hereNow): ?>
    <h2>Here right now</h2>
    <div class="tag-list">
      <?php foreach ($hereNow as $hn): ?>
        <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
           href="<?= e(url('u/'.$hn['username'])) ?>">
          <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($hn['avatar_url'] ?? null)) ?>" alt="">
          @<?= e((string) $hn['username']) ?> <span class="hint">until <?= e(date('j M', strtotime((string) $hn['date_to']))) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:.6rem 0 0">Their own published dates cover today.
      <a href="<?= e(url('travelers?city='.(int) $d['id'])) ?>">Find travelers in <?= e($city) ?></a>.</p>
  <?php endif; ?>

  <?php if ($hub['going']): ?><h2>Who is going</h2><?php endif; ?>
  <?php if ($hub['going']): ?>
    <div class="tag-list">
      <?php foreach ($hub['going'] as $g): ?>
        <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
           href="<?= e(url('u/'.$g['username'])) ?>">
          <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($g['avatar_url']??null)) ?>" alt="">
          @<?= e($g['username']) ?> · <?= e(date('M j', strtotime((string)$g['date_from']))) ?> to <?= e(date('M j', strtotime((string)$g['date_to']))) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:.6rem 0 0">
      <?php if (!empty($hub['solo'])): ?>
        <?= (int) $hub['solo'] ?> of them <?= (int) $hub['solo'] === 1 ? 'says they travel' : 'say they travel' ?> solo.
      <?php endif; ?>
      Destination and date range only. RuinMyTrip never shows anybody's precise or live location.</p>
<?php else: $rmt_gaps['going'] = true; endif; ?>

  <?php if (!empty($hub['locals'])): ?><h2 style="margin-top:28px">Locals</h2><?php endif; ?>
  <?php if (!empty($hub['locals'])): ?>
    <div class="tag-list">
      <?php foreach ($hub['locals'] as $l): ?>
        <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
           href="<?= e(url('u/'.$l['username'])) ?>">
          <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($l['avatar_url'])) ?>" alt="">
          @<?= e($l['username']) ?>
          <?php if ($l['reviews']): ?><span class="hint"><?= $l['reviews'] ?> <?= $l['reviews'] === 1 ? 'review' : 'reviews' ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:.6rem 0 0">People who live in <?= e($city) ?>. Ask them what a
      visitor gets wrong.</p>
<?php else: $rmt_gaps['locals'] = true; endif; ?>

  <?php if ($hub['meetups']): ?><h2 style="margin-top:28px">Meetups</h2><?php endif; ?>
  <?php if ($hub['meetups']): ?>
    <ul class="list-plain">
      <?php foreach ($hub['meetups'] as $m): ?>
        <li class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
          <a href="<?= e(url('meetup/'.(int)$m['id'])) ?>"><b><?= e($m['title']) ?></b></a>
          <p class="hint" style="margin:.25rem 0 0">
            <?= e(date('D, M j · g:ia', strtotime((string)$m['date_start']))) ?> ·
            <?= (int)$m['going_count'] === 1 ? '1 person going' : (int)$m['going_count'] . ' people going' ?>
          </p>
        </div></li>
      <?php endforeach; ?>
    </ul>
<?php else: $rmt_gaps['meetups'] = true; endif; ?>

  <h2 style="margin-top:28px">What people are asking</h2>
  <?php if ($me): ?>
    <?php /* The box is here rather than behind a link because the gap between wanting to ask
             something and finding the form is where the question is lost. It posts to the same
             endpoint /talk uses, tagged to this city, and comes straight back here. */ ?>
    <form id="say" method="post" action="<?= e(url('post/new')) ?>" style="margin:0 0 16px"><?= csrf_field() ?>
      <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
      <input type="hidden" name="destination_id" value="<?= (int)$d['id'] ?>">
      <input type="hidden" name="return" value="<?= e($here) ?>">
      <textarea name="body" rows="2" maxlength="1000"
                placeholder="Ask <?= e($city) ?> travelers something, or say what you found"
                style="width:100%"></textarea>
      <div style="display:flex;gap:8px;align-items:center;margin-top:6px">
        <button class="btn btn-primary btn-sm">Post to <?= e($city) ?></button>
        <span class="hint">Goes to your followers and to everyone watching this city.</span>
      </div>
    </form>
  <?php else: ?>
    <p style="margin:0 0 14px"><a class="btn btn-accent btn-sm" href="<?= e($join($here)) ?>">Join to ask <?= e($city) ?> travelers</a></p>
  <?php endif; ?>
  <?php if ($hub['talk']): ?>
    <ul class="list-plain">
      <?php foreach ($hub['talk'] as $p): ?>
        <li class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
          <span class="hint">@<?= e($p['username'] ?? '') ?> · <?= e(ago($p['created_at'])) ?></span>
          <p style="margin:.25rem 0 0"><a href="<?= e(url('post/'.(int)$p['id'])) ?>"><?= e(rmt_post_title($p, 140)) ?></a></p>
        </div></li>
      <?php endforeach; ?>
    </ul>
<?php else: $rmt_gaps['talk'] = true; endif; ?>

  <?php if (!empty($hub['reviews'])): ?><h2 style="margin-top:28px">Reviews from travelers</h2><?php endif; ?>
  <?php if (!empty($hub['reviews'])): ?>
    <ul class="list-plain">
      <?php foreach ($hub['reviews'] as $rv): ?>
        <li class="card" style="margin-bottom:10px"><div class="card-body" style="padding:12px 16px">
          <span class="hint">@<?= e($rv['username']) ?> · <?= e(ago($rv['created_at'])) ?><?php
            if (!empty($rv['rating'])): ?> · <?= str_repeat('★', max(0, min(5, (int) $rv['rating']))) ?><?php endif; ?></span>
          <p style="margin:.25rem 0 0">
            <a href="<?= e(url(ltrim(rmt_review_path($rv), '/'))) ?>"><b><?= e($rv['title'] ?: $rv['subject_name']) ?></b></a>
          </p>
          <?php if (!empty($rv['what_ruined'])): ?>
            <p class="muted" style="margin:.25rem 0 0"><?= e(mb_strimwidth((string) $rv['what_ruined'], 0, 140, '…')) ?></p>
          <?php endif; ?>
        </div></li>
      <?php endforeach; ?>
    </ul>
    <p style="margin:.4rem 0 0"><a href="<?= e(url('d/'.$d['slug'])) ?>">Everything written about <?= e($city) ?> &rarr;</a></p>
<?php else: $rmt_gaps['reviews'] = true; endif; ?>

  <?php if ($hub['people']): ?><h2 style="margin-top:28px">Travelers who have been</h2><?php endif; ?>
  <?php if ($hub['people']): ?>
    <div class="tag-list">
      <?php foreach ($hub['people'] as $p): ?>
        <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem"
           href="<?= e(url('u/'.$p['username'])) ?>">
          <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($p['avatar_url'])) ?>" alt="">
          @<?= e($p['username']) ?>
          <span class="hint"><?= $p['reviews'] ?> <?= $p['reviews'] === 1 ? 'review' : 'reviews' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin:.6rem 0 0">Message any of them. They wrote about <?= e($city) ?> themselves.</p>
<?php else: $rmt_gaps['people'] = true; endif; ?>

  <?php /* What the city looks like, from the people who were there. Eight, linking into the
           wall. A city page with no photograph on it is a directory entry. */ ?>
  <?php if ($cityPhotos): ?>
    <h2 style="margin-top:28px">Photos from travelers</h2>
    <?php $gridPhotos = $cityPhotos; $gridLead = count($cityPhotos) > 3;
          include __DIR__ . '/_photo_grid.php'; ?>
    <p style="margin:10px 0 0"><a href="<?= e(url('d/'.$d['slug'].'/photos')) ?>">All photos of <?= e($city) ?></a></p>
  <?php endif; ?>

  <?php /* Locals who ticked the box. Opt in, always, and the sentence says exactly what it means
           so nobody has to guess what they agreed to. */ ?>
  <?php if ($openLocals): ?>
    <h2 style="margin-top:28px">Locals open to meeting travelers</h2>
    <p class="hint" style="margin:0 0 10px">People who live in <?= e($city) ?> and said they are
      happy to answer a question or meet in a public place.</p>
    <?php foreach ($openLocals as $lo): ?>
      <?php $person = $lo;
            $because = 'Lives in ' . $city
                     . ((int) ($lo['reviews'] ?? 0) > 0 ? ' · ' . (int) $lo['reviews'] . ' reviews' : '');
            $backTo = $here;
            include __DIR__ . '/_person_card.php'; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($rmt_gaps): ?>
    <?php /* One invitation, naming only what is actually missing, with the action beside each
             thing rather than six paragraphs of absence. Whoever does any of these first is the
             person every traveler searching this city next month will find, which is true and is
             the only argument worth making on an empty page. */ ?>
    <section class="first-in">
      <h2 style="margin:0 0 6px">Be the first in <?= e($city) ?></h2>
      <p class="muted" style="margin:0 0 16px;max-width:60ch">Nobody has done these yet. Whoever goes
        first is the traveler everybody searching <?= e($city) ?> next month finds.</p>
      <div class="first-in-grid">
        <?php if (!empty($rmt_gaps['going'])): ?>
          <a class="first-in-act" href="<?= e($me ? url('trip/new?destination='.(int)$d['id']) : $join($here)) ?>">
            <b>Post your dates</b><span class="hint">See whose trip overlaps yours</span></a>
        <?php endif; ?>
        <?php if (!empty($rmt_gaps['meetups'])): ?>
          <a class="first-in-act" href="<?= e($me ? url('meetup/new?destination='.(int)$d['id']) : $join($here)) ?>">
            <b>Host a meetup</b><span class="hint">Public place, any day. Coffee counts</span></a>
        <?php endif; ?>
        <?php if (!empty($rmt_gaps['talk'])): ?>
          <a class="first-in-act" href="<?= e($me ? '#say' : $join($here)) ?>">
            <b>Ask a question</b><span class="hint">Somebody who has been will answer</span></a>
        <?php endif; ?>
        <?php if (!empty($rmt_gaps['reviews']) || !empty($rmt_gaps['people'])): ?>
          <a class="first-in-act" href="<?= e($me ? url('review/new?destination='.(int)$d['id'].'&src=travelers') : $join($here)) ?>">
            <b>Review somewhere</b><span class="hint">What nearly ruined it counts double</span></a>
        <?php endif; ?>
        <?php if (!empty($rmt_gaps['locals'])): ?>
          <a class="first-in-act" href="<?= e($me ? url('u/'.$me['username'].'/edit') : $join($here)) ?>">
            <b>Live in <?= e($city) ?>?</b><span class="hint">Put it on your profile and be findable</span></a>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <p style="margin:26px 0 60px">
    <a href="<?= e(url('d/'.$d['slug'])) ?>">Everything about <?= e($city) ?> &rarr;</a> ·
    <a href="<?= e(url('travelers')) ?>">All travelers</a> ·
    <a href="<?= e(url('meetups')) ?>">All meetups</a>
  </p>
</div>
