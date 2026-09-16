<?php
/**
 * The city's community, directly under the hero.
 *
 * Why it is here and not further down: this page is the one a stranger lands on from a search, and
 * what used to greet them was our own rating, our own writing and a wall of places, with the
 * conversation four screens below all of it. A traveler reading about Lisbon does not need another
 * paragraph about Lisbon. They need to see that people are here, that a question gets an answer,
 * and that asking one takes ten seconds.
 *
 * Every number on the strip is counted from real rows and drawn only when it is above zero. There
 * is no filler, no "be the first of 0", no rounded-up follower count. An empty city says it is
 * empty and asks for the first question, because that is a truthful invitation and a fabricated
 * crowd is the one thing that would make this site worthless.
 *
 * Expects: $d, $me, $talk, $talkCount, $saved, $wantCount, $beenCount, $going, $hereNow,
 *          $photoCount, $photos, $meetups, $avg.
 */
$cityUrl   = url('d/' . $d['slug']);
$cityName  = (string) $d['name'];
$goingN    = count($going ?? []);
$hereN     = count($hereNow ?? []);
$meetN     = count($meetups ?? []);
$reviewN   = (int) ($avg['c'] ?? 0);
$followN   = (int) ($wantCount ?? 0);
$beenN     = (int) ($beenCount ?? 0);
$photoN    = (int) ($photoCount ?? 0);
$talkN     = (int) ($talkCount ?? 0);
/* Counted, not guessed: a fact is on the strip because a query returned it. */
$stats = array_values(array_filter([
    $followN  ? ['n' => $followN,  'label' => $followN === 1 ? 'follower' : 'followers',        'href' => null] : null,
    $goingN   ? ['n' => $goingN,   'label' => $goingN === 1 ? 'going' : 'going',                'href' => url('d/'.$d['slug'].'/travelers')] : null,
    $hereN    ? ['n' => $hereN,    'label' => 'here now',                                       'href' => url('d/'.$d['slug'].'/travelers')] : null,
    $beenN    ? ['n' => $beenN,    'label' => 'been here',                                      'href' => null] : null,
    $talkN    ? ['n' => $talkN,    'label' => $talkN === 1 ? 'question' : 'questions',           'href' => '#city-talk'] : null,
    $reviewN  ? ['n' => $reviewN,  'label' => $reviewN === 1 ? 'review' : 'reviews',            'href' => '#reviews'] : null,
    $photoN   ? ['n' => $photoN,   'label' => $photoN === 1 ? 'photo' : 'photos',               'href' => url('d/'.$d['slug'].'/photos')] : null,
    $meetN    ? ['n' => $meetN,    'label' => $meetN === 1 ? 'meetup' : 'meetups',              'href' => url('meetups')] : null,
]));
?>
<section class="city-community" id="city-community" aria-labelledby="city-community-h">
  <?php /* What this is, for somebody who has never been here.
           Somebody arriving from a Reddit comment or a shared link has no idea what site they are
           on, and the block below assumes they do: it opens with "The Bangkok community" as though
           they already knew there was one. One line, signed out only, so a member never reads an
           explanation of a site they use. */ ?>
  <?php if (!$me): ?>
    <p class="cc-what">RuinMyTrip is where you find the people traveling where you are going.
      Post your dates, see whose overlap, and ask the travelers who have been.</p>
  <?php endif; ?>

  <div class="cc-head">
    <div class="cc-head-text">
      <h2 id="city-community-h">The <?= e($cityName) ?> community</h2>
      <p class="hint" style="margin:.15rem 0 0">Travelers who have been, are going, or are asking. Follow it and its questions, reviews and meetups come to you.</p>
    </div>
    <div class="cc-actions">
      <?php if ($me): ?>
        <form method="post" action="<?= e(url('destination/save')) ?>">
          <?= csrf_field() ?><input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
          <input type="hidden" name="return" value="<?= e($cityUrl) ?>">
          <input type="hidden" name="want" value="<?= $saved ? 'off' : 'on' ?>">
          <button class="btn <?= $saved ? 'btn-ghost' : 'btn-primary' ?> cc-follow">
            <?= $saved ? '★ Following ' . e($cityName) : '☆ Follow ' . e($cityName) ?></button>
        </form>
      <?php else: ?>
        <a class="btn btn-primary cc-follow" href="<?= e(url('register?return=' . rawurlencode('/d/' . $d['slug']))) ?>">Follow <?= e($cityName) ?></a>
      <?php endif; ?>
      <a class="btn btn-accent cc-ask" href="#city-ask">Ask the community</a>
      <?php /* The two things a stranger arriving from a link is actually here for, put where they
               can see them. Both existed already and both were thousands of pixels down the page,
               under the editorial: on a phone the link to the travelers hub sat around seven screens
               below the fold, and the only "post a trip" control was inside the collapsed menu, so a
               signed in visitor on a phone could not see one at all. Neither is a new feature and
               neither claims anybody is there; the pages they open say honestly when they are empty. */ ?>
      <a class="btn btn-ghost cc-travelers" href="<?= e(url('d/' . $d['slug'] . '/travelers')) ?>">See who is going</a>
      <a class="btn btn-ghost cc-post-dates" href="<?= e(url('trip/new?destination_id=' . (int) $d['id'])) ?>">Post your dates</a>
    </div>
  </div>

  <?php /* Somebody who arrived from a campaign about a real window already knows the city and
           roughly the fortnight. Making them pick both again from an empty form is asking them to
           redo work we have already done, so the obvious next action is offered with the dates in
           it. It creates nothing: the form is still theirs to change and to submit.
           Only for the city the campaign is about, and only while the window is still ahead. */ ?>
  <?php $ccWindow = function_exists('rmt_acq_window') ? rmt_acq_window() : null;
        if (!$ccWindow || $ccWindow['slug'] !== (string) $d['slug']) {
            /* Somebody who searched their way here four days before the thing starts wants the same
               offer the campaign visitor gets, so it is shown once the window is close. Not before:
               a December date on a page read all year is clutter. Cities in the title experiment are
               left alone, because changing a page mid experiment is how a clean result stops being
               readable. */
            $ccWindow = function_exists('rmt_acq_window_near') ? rmt_acq_window_near((string) $d['slug']) : null;
        } ?>
  <?php if ($ccWindow && $ccWindow['slug'] === (string) $d['slug']): ?>
    <p class="cc-window">
      <b><?= e((string) $ccWindow['label']) ?> runs
        <?= e(date('j F', strtotime((string) $ccWindow['from']))) ?> to
        <?= e(date('j F', strtotime((string) $ccWindow['to']))) ?>.</b>
      Put your dates up and you will see which other travelers are here at the same time.
      <a class="btn btn-primary btn-sm" href="<?= e(rmt_acq_trip_link($ccWindow)) ?>">Post your <?= e($cityName) ?> dates</a>
    </p>
  <?php endif; ?>

  <?php /* Sharing, on the page that is worth sharing.
           A young site is not found by a search engine first, it is found by one person sending a
           link to another, and this page had no way to do that at all: the control existed and was
           on trips, reviews, guides, photos and profiles, and not on the city page that every
           search result points at. The words that travel are about people rather than about the
           city, because "Bangkok travel guide" is a link nobody sends and "see who else is going to
           Bangkok" is one somebody does. */ ?>
  <div class="cc-share">
    <span class="hint">Know somebody going to <?= e($cityName) ?>?</span>
    <?php $shareUrl = abs_url('/d/' . $d['slug']);
          $shareText = 'Going to ' . $cityName . '? See who else is traveling there.';
          include __DIR__ . '/_share.php'; ?>
  </div>

  <?php if ($stats): ?>
    <ul class="cc-stats">
      <?php foreach ($stats as $st): ?>
        <li><?php if ($st['href']): ?><a href="<?= e($st['href']) ?>"><b><?= (int) $st['n'] ?></b> <?= e($st['label']) ?></a><?php
              else: ?><b><?= (int) $st['n'] ?></b> <?= e($st['label']) ?><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php /* The composer, not a button that opens a composer. A box you can already type in is the
           difference between a page with a conversation on it and a page with a link to one. */ ?>
  <div class="cc-ask-box" id="city-ask">
    <?php if ($me): ?>
      <form method="post" action="<?= e(url('post/new')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
        <input type="hidden" name="destination_id" value="<?= (int) $d['id'] ?>">
        <input type="hidden" name="return" value="<?= e($cityUrl . '#city-talk') ?>">
        <label class="sr-only" for="cc-body">Ask the <?= e($cityName) ?> community</label>
        <textarea id="cc-body" name="body" rows="3" required maxlength="<?= RMT_POST_MAX ?>"
                  data-track="ask_question_click" data-track-source="destination"
                  data-destination-id="<?= (int) $d['id'] ?>"
                  placeholder="Ask <?= e($cityName) ?> travelers something: where to stay, whether November is worth it, who is around in October."></textarea>
        <div class="cc-ask-row">
          <label class="btn btn-ghost btn-sm" style="cursor:pointer">
            Photo <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none">
          </label>
          <button class="btn btn-accent">Post to <?= e($cityName) ?></button>
        </div>
      </form>
    <?php else: ?>
      <p style="margin:0 0 10px"><b>Ask <?= e($cityName) ?> travelers anything.</b>
        Best neighborhood to stay in? Worth it in November? Anybody going in October?</p>
      <p class="hint" style="margin:0 0 12px">Answers come from travelers who have been there or are going. Joining takes a minute.</p>
      <div class="cc-ask-row">
        <a class="btn btn-accent" href="<?= e(url('register?return=' . rawurlencode('/d/' . $d['slug'] . '#city-ask'))) ?>">Join free and ask</a>
        <a class="btn btn-ghost" href="<?= e(url('login?return=' . rawurlencode('/d/' . $d['slug'] . '#city-ask'))) ?>">Sign in</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="cc-talk" id="city-talk">
    <div class="section-rule">
      <h3>Travelers talking about <?= e($cityName) ?></h3>
      <?php if ($talkN > count($talk ?? [])): ?>
        <a class="hint" href="<?= e(url('talk?d=' . $d['slug'])) ?>">all <?= (int) $talkN ?></a>
      <?php endif; ?>
    </div>
    <?php if (empty($talk)): ?>
      <?php /* The honest empty state. No invented posts, no phantom members, no "join 1,200
               travelers". What it offers instead is the one thing that is true: whoever asks
               first is the reason the next person finds an answer here. */ ?>
      <div class="cc-empty">
        <p style="margin:0 0 6px"><b>No questions about <?= e($cityName) ?> yet.</b></p>
        <p class="hint" style="margin:0">Ask the first one. It is what the next traveler searching for <?= e($cityName) ?> will find.</p>
      </div>
    <?php endif; ?>
    <?php foreach (($talk ?? []) as $tp): ?>
      <article class="cc-post">
        <a class="cc-post-av" href="<?= e(url('u/' . $tp['username'])) ?>">
          <img class="avatar" src="<?= e(avatar_url($tp['avatar_url'] ?? null)) ?>" alt="@<?= e((string) $tp['username']) ?>"></a>
        <div class="cc-post-main">
          <p class="cc-post-by">
            <b><a href="<?= e(url('u/' . $tp['username'])) ?>">@<?= e((string) $tp['username']) ?></a></b>
            <span class="hint"> · <?= e(ago((string) $tp['created_at'])) ?></span>
            <?php if (!empty($tp['place_slug'])): ?>
              <span class="hint"> · <a href="<?= e(url('p/' . $tp['place_slug'])) ?>"><?= e((string) $tp['place_name']) ?></a></span>
            <?php endif; ?>
            <span class="hint"> · <a href="<?= e($cityUrl) ?>"><?= e($cityName) ?></a></span>
          </p>
          <p class="cc-post-body"><?= rmt_linkify_tags(rmt_linkify_mentions(nl2br(e(mb_strimwidth((string) $tp['body'], 0, 320, '…'))))) ?></p>
          <?php if (!empty($tp['image_url'])): ?>
            <a href="<?= e(url('post/' . (int) $tp['id'])) ?>"><img class="cc-post-img" loading="lazy"
                 src="<?= e(abs_url((string) $tp['image_url'])) ?>" alt=""></a>
          <?php endif; ?>
          <p class="hint" style="margin:0"><a href="<?= e(url('post/' . (int) $tp['id'])) ?>">
            <?php $ccn = (int) ($tp['reply_count'] ?? 0); ?>
            <?= $ccn ? $ccn . ' ' . ($ccn === 1 ? 'reply' : 'replies') : 'Reply' ?></a></p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php /* What the city looks like from a traveler's phone, not from a stock library. Six at most,
           because this is a taste of the wall rather than the wall, and drawn only when real
           photographs exist. Every one opens its own page and carries its owner with it. */ ?>
  <?php $ccPhotos = array_slice($photos ?? [], 0, 6); ?>
  <?php if ($ccPhotos): ?>
    <div class="cc-photos">
      <div class="section-rule">
        <h3>Recent photos</h3>
        <span class="count"><?= (int) $photoN ?></span>
      </div>
      <?php $gridPhotos = $ccPhotos; $gridLead = false;   // a lead tile here is half a screen of photograph in front of the conversation
            include __DIR__ . '/_photo_grid.php'; ?>
      <?php if ($photoN > count($ccPhotos)): ?>
        <p class="hint" style="margin:10px 0 0"><a href="<?= e(url('d/' . $d['slug'] . '/photos')) ?>">See all <?= (int) $photoN ?> photos</a></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
