<?php
/** @var array $o @var array $people @var array $talk @var ?array $me @var bool $saved @var string $dates @var array $links
 *  @var ?array $myTrip @var array $others
 *
 * An occasion page (app/occasions.php): the window, what is worth knowing (ours, labelled and
 * sourced), who is going (members only, read live), and the seven things a traveler can do here.
 * The empty state is an invitation, never a count of nobody.
 */
$d = $o['d'];
$city = (string) $d['name'];
$heroSet = function_exists('rmt_media_srcset') ? rmt_media_srcset($d['hero_url'] ?? null) : '';
$join = static fn(string $path): string => url('register?return=' . rawurlencode($path));
$here = '/e/' . $o['slug'];
?>
<div class="wrap">
  <div class="dest-hero">
    <?php if (!empty($d['hero_url'])): ?>
      <img src="<?= e(abs_url((string) $d['hero_url'])) ?>"<?php if ($heroSet !== ''): ?> srcset="<?= e($heroSet) ?>" sizes="(max-width: 1140px) 100vw, 1100px"<?php endif; ?> fetchpriority="high" alt="<?= e($city . ', ' . $d['country']) ?>">
    <?php endif; ?>
    <div class="overlay"><div>
      <span class="chip"><?= e(RMT_OCCASION_KINDS[$o['kind']] ?? 'Event') ?></span>
      <h1><?= e((string) $o['name']) ?></h1>
      <p style="color:#e8eef5;margin:.2rem 0 0;max-width:60ch"><?= e($city) ?> · <?= e($dates) ?></p>
    </div></div>
  </div>

  <?php if (!$me): ?>
    <?php $ma = ['dest' => $d, 'occ' => $o['slug'], 'from' => $o['from'], 'to' => $o['to'], 'source' => 'occasion', 'return' => $here,
                 'heading' => 'Going for ' . $o['short'] . '? Get told when another traveler overlaps your dates.'];
          include __DIR__ . '/_match_alert.php'; ?>
  <?php elseif ($myTrip): ?>
    <section class="card occ-share"><div class="card-body">
      <h2 style="margin:0 0 6px">You are going. Bring the others.</h2>
      <p class="hint" style="margin:0 0 10px">Make an "I'm going" card for your <?= e($city) ?> dates and send it to the group chats where the other half of your match already is.</p>
      <form method="post" action="<?= e(url('im-going')) ?>" class="act-form">
        <?= csrf_field() ?><input type="hidden" name="trip_id" value="<?= (int) $myTrip['id'] ?>">
        <label class="hint" style="display:block;margin:0 0 8px"><input type="checkbox" name="show_name" value="1"> Show my name and photo on it</label>
        <button class="btn btn-accent" data-cta="card_make">Make my card</button>
      </form>
    </div></section>
  <?php endif; ?>

  <section class="occ-acts block-tight">
    <ul class="act-grid">
      <li><a class="act-tile act-main" data-cta="occ_trip" href="<?= e($links['trip']) ?>">
        <b>Add my trip</b><span>Your <?= e($city) ?> dates, prefilled for <?= e((string) $o['short']) ?>. We alert you when anyone overlaps.</span></a></li>
      <li><a class="act-tile" data-cta="occ_find" href="<?= e($links['find']) ?>">
        <b>Find travelers</b><span>People in <?= e($city) ?> on the same days, locals open to meeting.</span></a></li>
      <li><a class="act-tile" data-cta="occ_ask" href="<?= e($links['ask']) ?>">
        <b>Ask a question</b><span>Tickets, transport, where to stay. Ask the <?= e($city) ?> community.</span></a></li>
      <li>
        <?php if ($me): ?>
          <form method="post" action="<?= e(url('destination/save')) ?>" class="act-form">
            <?= csrf_field() ?><input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="return" value="<?= e($here) ?>"><input type="hidden" name="want" value="<?= $saved ? 'off' : 'on' ?>">
            <button class="act-tile" data-cta="occ_follow"><b><?= $saved ? 'Following ' . e($city) : 'Follow ' . e($city) ?></b>
              <span>Get match alerts and new questions, trips and meetups.</span></button>
          </form>
        <?php else: ?>
          <a class="act-tile" data-cta="occ_follow" href="<?= e($join($here)) ?>"><b>Follow <?= e($city) ?></b>
            <span>Get match alerts and new questions, trips and meetups.</span></a>
        <?php endif; ?>
      </li>
      <li><a class="act-tile" data-cta="occ_review" href="<?= e($me ? $links['review'] : $join('/review/new?destination=' . (int) $d['id'])) ?>">
        <b>Write a review</b><span>Been before? Help the next traveler.</span></a></li>
      <li><a class="act-tile" data-cta="occ_ruined" href="<?= e($links['ruined']) ?>">
        <b>Share what ruined my trip</b><span>The fee, the scam, what you wish you had known.</span></a></li>
    </ul>
  </section>

  <div class="grid g-2" style="align-items:start;gap:28px">
    <section>
      <h2 style="margin:0 0 8px">Who is going</h2>
      <?php if ($people): ?>
        <div class="tag-list">
          <?php foreach ($people as $pp): ?>
            <a class="chip" style="display:inline-flex;align-items:center;gap:6px;padding:.35rem .7rem" href="<?= e(url('u/' . $pp['username'])) ?>">
              <img class="avatar" style="width:22px;height:22px" src="<?= e(avatar_url($pp['avatar_url'] ?? null)) ?>" alt="">
              @<?= e((string) $pp['username']) ?>
              <span class="hint"><?= e(date('M j', strtotime((string) $pp['date_from']))) ?> to <?= e(date('M j', strtotime((string) $pp['date_to']))) ?></span></a>
          <?php endforeach; ?>
        </div>
        <p class="hint">Destination and date range only. Nobody can message you until you say yes.</p>
        <p class="hint">Matched here: anyone in <?= e($city) ?> between <?= e(date('j F', strtotime($o['from']))) ?> and <?= e(date('j F', strtotime($o['to']))) ?>.</p>
      <?php else: ?>
        <div class="callout">
          <b>Be the first traveler heading to <?= e($city) ?> for <?= e((string) $o['short']) ?>.</b>
          Add your dates and you are who everybody planning this finds. The moment somebody posts dates that
          overlap yours, we tell you. Matched here: anyone in <?= e($city) ?> between <?= e(date('j F', strtotime($o['from']))) ?>
          and <?= e(date('j F', strtotime($o['to']))) ?>.
          <p style="margin:10px 0 0"><a class="btn btn-accent" data-cta="occ_trip" href="<?= e($links['trip']) ?>">Add my trip</a></p>
        </div>
      <?php endif; ?>

      <?php foreach ((array) ($o['guide'] ?? []) as [$gh, $gparas]): ?>
        <h2 style="margin:26px 0 8px"><?= e((string) $gh) ?></h2>
        <?php foreach ($gparas as $gp): ?><p><?= e((string) $gp) ?></p><?php endforeach; ?>
      <?php endforeach; ?>

      <?php if (!empty($o['warnings'])): ?>
        <section class="callout occ-warn" style="margin:26px 0 0">
          <h2 style="margin:0 0 8px;font-size:1.1rem">What travelers wish they knew</h2>
          <ul style="margin:0;padding-left:18px">
            <?php foreach ($o['warnings'] as $w): ?><li style="margin:0 0 6px"><?= e((string) $w) ?></li><?php endforeach; ?>
          </ul>
          <p style="margin:10px 0 0"><a data-cta="occ_ruined" href="<?= e($links['ruined']) ?>">Been before? Tell people what ruined it for you.</a></p>
        </section>
      <?php endif; ?>

      <?php if (!empty($o['faq'])): ?>
        <h2 style="margin:26px 0 8px">Questions people ask</h2>
        <?php foreach ($o['faq'] as [$fq, $fa]): ?>
          <h3 style="margin:14px 0 4px;font-size:1rem"><?= e((string) $fq) ?></h3><p style="margin:0"><?= e((string) $fa) ?></p>
        <?php endforeach; ?>
      <?php endif; ?>

      <h2 style="margin:26px 0 8px">Questions about <?= e($city) ?></h2>
      <?php if ($talk): ?>
        <ul class="plain-list">
          <?php foreach ($talk as $t): ?>
            <li style="margin:0 0 10px"><a href="<?= e(url('post/' . (int) $t['id'])) ?>"><?= e(excerpt((string) $t['body'], 140)) ?></a>
              <span class="hint"> · @<?= e((string) $t['username']) ?><?= ($t['author_role'] ?? '') === RMT_EDITORIAL_ROLE || str_starts_with((string) $t['username'], 'team_') ? ' (RuinMyTrip team)' : '' ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p><a class="btn btn-ghost" data-cta="occ_ask" href="<?= e($links['ask']) ?>">Start the conversation</a>
         <a class="btn btn-ghost" href="<?= e($links['city']) ?>">The <?= e($city) ?> community</a></p>
    </section>

    <section class="card"><div class="card-body">
      <p class="eyebrow" style="margin:0 0 4px">What is worth knowing</p>
      <p style="margin:0 0 10px"><?= e((string) $o['lede']) ?></p>
      <ul style="margin:0 0 10px;padding-left:18px">
        <?php foreach ($o['facts'] as $f): ?><li style="margin:0 0 6px"><?= e((string) $f) ?></li><?php endforeach; ?>
      </ul>
      <p class="hint" style="margin:0">Written by RuinMyTrip, checked <?= e(date('j F Y', strtotime((string) $o['checked']))) ?>.
        Not a traveler review.
        <?php if ($o['sources']): ?>Sources:
          <?php foreach ($o['sources'] as $i => [$label, $href]): ?><?= $i ? ', ' : '' ?><a href="<?= e($href) ?>" rel="nofollow noopener" target="_blank"><?= e($label) ?></a><?php endforeach; ?>.
        <?php endif; ?></p>
    </div></section>
  </div>

  <section class="block-tight" style="margin-top:28px">
    <h2 style="margin:0 0 8px">More in <?= e($city) ?></h2>
    <p><a href="<?= e($links['city']) ?>">The <?= e($city) ?> community</a> ·
       <a href="<?= e($links['people']) ?>">Travelers going to <?= e($city) ?></a> ·
       <a href="<?= e(url('buddies?' . http_build_query(['where' => $city]))) ?>">Travel buddies for <?= e($city) ?></a> ·
       <a href="<?= e(url('events')) ?>">All events</a></p>
    <?php if ($others): ?>
      <h2 style="margin:18px 0 8px">Other trips people plan around</h2>
      <ul class="plain-list">
        <?php foreach (array_slice($others, 0, 4) as $x): ?>
          <li style="margin:0 0 6px"><a href="<?= e(url('e/' . $x['slug'])) ?>"><?= e((string) $x['name']) ?></a>
            <span class="hint"> · <?= e((string) $x['d']['name']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
