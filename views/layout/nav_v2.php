<?php
/* The redesigned header (app/design_v2.php, surface "nav"). One row on a desktop: the brand, a
   search box, the four things a stranger can use today, and Join. On a phone the menu opens as a
   sheet grouped by what somebody came to do, each item saying what it does. Every link the current
   menu carries is still here, in the sheet or the "More" panel, so internal linking loses nothing.
   @var ?array $me */
$nv2Path = parse_url(rmt_current_url(), PHP_URL_PATH) ?: '/';
$nv2On = static fn(string $p): string => ($p === '/' ? $nv2Path === '/' : str_starts_with($nv2Path, $p)) ? ' aria-current="page"' : '';
?>
<header class="v2-header">
  <div class="wrap v2-header-in">
    <a class="v2-brand" href="<?= e(url()) ?>" aria-label="RuinMyTrip home">
      <span class="v2-brand-mark" aria-hidden="true">◈</span><span>Ruin<b>My</b>Trip</span>
    </a>
    <form class="v2-search" action="<?= e(url('search')) ?>" method="get" role="search"
          data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
      <span class="v2-search-ico" aria-hidden="true">⌕</span>
      <input type="search" name="q" placeholder="Search a city or place" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <nav class="v2-links" aria-label="Primary">
      <a href="<?= e(url('explore')) ?>"<?= $nv2On('/explore') ?>>Explore</a>
      <a href="<?= e(url('map')) ?>"<?= $nv2On('/map') ?>>Travel map</a>
      <a href="<?= e(url('reviews')) ?>"<?= $nv2On('/reviews') ?>>Reviews</a>
      <a href="<?= e(url('buddies')) ?>"<?= $nv2On('/buddies') ?>>Travel buddies</a>
      <details class="v2-more">
        <summary>More</summary>
        <div class="v2-more-panel">
          <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a>
          <a href="<?= e(url('talk')) ?>">Talk</a>
          <a href="<?= e(url('events')) ?>">Events</a>
          <a href="<?= e(url('going')) ?>">Who is going</a>
          <a href="<?= e(url('travelers')) ?>">All travelers</a>
          <a href="<?= e(url('meetups')) ?>">Meetups</a>
          <a href="<?= e(url('communities')) ?>">Communities</a>
          <a href="<?= e(url('ruined')) ?>">Ruined</a>
          <a href="<?= e(url('collections')) ?>">Collections</a>
          <a href="<?= e(url('leaderboard')) ?>">Top travelers</a>
          <a href="<?= e(url('tags')) ?>">Topics</a>
          <a href="<?= e(url('buddies')) ?>">Meet travelers</a>
        </div>
      </details>
    </nav>
    <div class="v2-acts">
      <?php if ($me): ?>
        <a class="v2-ico" href="<?= e(url('messages')) ?>" title="Messages" aria-label="Messages">&#9993;<?php
          $nv2Unread = rmt_unread_message_count((int) $me['id']);
          if ($nv2Unread): ?><span class="v2-badge"><?= $nv2Unread ?></span><?php endif; ?></a>
        <a class="v2-ico" href="<?= e(url('notifications')) ?>" title="Notifications" aria-label="Notifications">&#128276;<?php
          $nv2Unseen = rmt_unread_notification_count((int) $me['id']);
          if ($nv2Unseen): ?><span class="v2-badge v2-badge-alert"><?= $nv2Unseen ?></span><?php endif; ?></a>
        <a class="v2-me" href="<?= e(url('u/' . $me['username'])) ?>" title="Your profile">
          <span class="v2-initial" aria-hidden="true"><?= e(rmt_design_initial((string) $me['username'])) ?></span>
        </a>
        <a class="v2-btn v2-btn-primary v2-hide-sm" data-cta="nav_rate" href="<?= e(url('review/new?src=home')) ?>">Rate a place</a>
      <?php else: ?>
        <a class="v2-signin v2-hide-sm" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="v2-btn v2-btn-primary v2-hide-sm" data-cta="nav_join" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
      <button type="button" class="v2-menu-btn" aria-label="Menu" aria-expanded="false" aria-controls="v2-sheet"
              data-toggle-body="v2-sheet-open">
        <span aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>
<div class="v2-sheet" id="v2-sheet" role="dialog" aria-label="Menu">
  <div class="v2-sheet-in">
    <div class="v2-sheet-top">
      <span class="v2-brand"><span class="v2-brand-mark" aria-hidden="true">◈</span><span>Ruin<b>My</b>Trip</span></span>
      <button type="button" class="v2-sheet-x" aria-label="Close menu"
              data-close-body="v2-sheet-open">✕</button>
    </div>
    <form class="v2-search v2-search-sheet" action="<?= e(url('search')) ?>" method="get" role="search"
          data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
      <span class="v2-search-ico" aria-hidden="true">⌕</span>
      <input type="search" name="q" placeholder="A place, a city, or a country" aria-label="Search">
    </form>
    <p class="v2-sheet-h">Share</p>
    <a class="v2-sheet-item" data-cta="nav_rate" href="<?= e(url('review/new?src=home')) ?>"><span class="v2-si-ico" aria-hidden="true">★</span>
      <span><b>Rate a place</b><small>Stars and a few lines, no account to start</small></span></a>
    <a class="v2-sheet-item" href="<?= e(url('map')) ?>"><span class="v2-si-ico" aria-hidden="true">◉</span>
      <span><b>My travel map</b><small>Color the countries you have been to</small></span></a>
    <a class="v2-sheet-item" href="<?= e($me ? url('trip/new') : url('plan?cta=nav_plan')) ?>" data-cta="nav_plan"><span class="v2-si-ico" aria-hidden="true">✈</span>
      <span><b>Post a trip</b><small>Where and when, so travelers on your dates find you</small></span></a>
    <p class="v2-sheet-h">Plan</p>
    <a class="v2-sheet-item" href="<?= e(url('explore')) ?>"><span class="v2-si-ico" aria-hidden="true">⚲</span>
      <span><b>Explore cities</b><small>Places with hours, addresses and honest reviews</small></span></a>
    <a class="v2-sheet-item" href="<?= e(url('buddies')) ?>"><span class="v2-si-ico" aria-hidden="true">☺</span>
      <span><b>Travel buddies</b><small>See who overlaps your dates</small></span></a>
    <a class="v2-sheet-item" href="<?= e(url('hosts-locals')) ?>"><span class="v2-si-ico" aria-hidden="true">&#8962;</span>
      <span><b>Hosts &amp; Locals</b><small>Meet people who live where you are going</small></span></a>
    <a class="v2-sheet-item" href="<?= e(url('talk')) ?>"><span class="v2-si-ico" aria-hidden="true">?</span>
      <span><b>Ask a question</b><small>Travelers who have been answer</small></span></a>
    <p class="v2-sheet-h">More</p>
    <div class="v2-sheet-links">
      <a href="<?= e(url('reviews')) ?>">Reviews</a><a href="<?= e(url('events')) ?>">Events</a>
      <a href="<?= e(url('going')) ?>">Who is going</a><a href="<?= e(url('travelers')) ?>">All travelers</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a><a href="<?= e(url('communities')) ?>">Communities</a>
      <a href="<?= e(url('ruined')) ?>">Ruined</a><a href="<?= e(url('collections')) ?>">Collections</a>
      <a href="<?= e(url('leaderboard')) ?>">Top travelers</a><a href="<?= e(url('tags')) ?>">Topics</a>
      <?php if ($me): ?>
        <a href="<?= e(url('feed')) ?>">Feed</a><a href="<?= e(url('matches')) ?>">Matches</a>
        <a href="<?= e(url('buddies/mine')) ?>">Your travel buddies</a><a href="<?= e(url('saved')) ?>">Saved</a>
        <a href="<?= e(url('invite')) ?>">Invite a traveler</a><a href="<?= e(url('settings')) ?>">Settings</a>
        <form class="signout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="link-btn">Sign out</button></form>
        <?php if (in_array($me['role'], ['admin', 'mod'], true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if (!$me): ?>
      <div class="v2-sheet-cta">
        <a class="v2-btn v2-btn-primary v2-btn-block" data-cta="nav_join" href="<?= e(url('register')) ?>">Join free</a>
        <a class="v2-btn v2-btn-ghost v2-btn-block" href="<?= e(url('login')) ?>">Sign in</a>
      </div>
    <?php endif; ?>
  </div>
</div>
