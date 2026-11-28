<?php
/* The redesigned header (app/design_v2.php, surface "nav"). One row on a desktop: the brand, a
   search box, the four things a stranger can use today, and Join. On a phone the menu opens as a
   sheet grouped by what somebody came to do, each item saying what it does. Every link the current
   menu carries is still here, in the sheet or the "More" panel, so internal linking loses nothing.
   @var ?array $me */
$nv2Path = parse_url(rmt_current_url(), PHP_URL_PATH) ?: '/';
/* The home page wears the darker header of its photograph hero (2026-11-25 mockup): airplane mark,
   tagline, an icon on each main link and a round search button that jumps to the hero search. */
$nv2Home = $nv2Path === '/';
$nv2I = static fn(string $d): string => $nv2Home ? '<svg class="v2-li" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>' : '';
/* Phase 1 (app/phase1.php): three actions, Explore Traps, Report a Trap and Log In / Sign Up; the
   social pages keep their URLs, only their links wait until rmt_social_on() turns them back on. */
$nv2P1 = function_exists('rmt_phase1_on') && rmt_phase1_on();
$nv2S = static fn(string $f): bool => !$nv2P1 || rmt_social_on($f);
$nv2Trim = $nv2P1 && rmt_p1_trim_links(); /* trimmed primary nav only once approved; default keeps every link */
$nv2On = static fn(string $p): string => ($p === '/' ? $nv2Path === '/' : str_starts_with($nv2Path, $p)) ? ' aria-current="page"' : '';
?>
<header class="v2-header<?= $nv2Home ? ' v2-header-home' : '' ?>">
  <div class="wrap v2-header-in">
    <a class="v2-brand" href="<?= e(url()) ?>" aria-label="RuinMyTrip home">
      <?php if ($nv2Home): ?>
      <svg class="v2-brand-plane" viewBox="0 0 64 40" width="58" height="36" aria-hidden="true" focusable="false"><path d="M3 35c12 3 30-2 45-14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M61 3c-2-1.6-5-1.4-7.4.3L44.8 9.6 27 6.4l-4 2.8 13.7 6.6-7.4 5.4-6.2-1.3-2.8 2.1 7.3 3.1 3.5 6.9 2.8-2.1-.6-6.3 7.4-5.4 4.6 14.6 4-2.8-.3-18 8.6-6.6c2.3-1.8 3-4.7 2.1-6z" fill="currentColor"/></svg>
      <span class="v2-brand-txt"><span class="v2-brand-name">Ruin<b>My</b>Trip</span><small>Honest travel reviews &amp; warnings</small></span>
      <?php else: ?>
      <span class="v2-brand-mark" aria-hidden="true">◈</span><span>Ruin<b>My</b>Trip</span>
      <?php endif; ?>
    </a>
    <form class="v2-search" action="<?= e(url('search')) ?>" method="get" role="search"
          data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
      <span class="v2-search-ico" aria-hidden="true">⌕</span>
      <input type="search" name="q" placeholder="Search a city or place" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <nav class="v2-links" aria-label="Primary">
      <?php if ($nv2Trim): ?>
      <a href="<?= e(url('explore')) ?>"<?= $nv2On('/explore') ?> data-cta="nav_explore_traps"><?= $nv2I('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/>') ?>Explore Traps</a>
      <?php if ($nv2S('talk')): ?><a href="<?= e(url('talk')) ?>">Travel Talk</a><?php endif; ?>
      <?php if ($nv2S('going')): ?><a href="<?= e(url('going')) ?>">Who is going</a><?php endif; ?>
      <?php if ($nv2S('meetups')): ?><a href="<?= e(url('meetups')) ?>">Meetups</a><?php endif; ?>
      <?php if ($nv2S('collections')): ?><a href="<?= e(url('collections')) ?>">Collections</a><?php endif; ?>
      <?php else: ?>
      <a href="<?= e(url('explore')) ?>"<?= $nv2On('/explore') ?>><?= $nv2I('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/>') ?><?= $nv2P1 ? 'Explore Traps' : 'Explore' ?></a>
      <?php /* Warnings are what the site is for (2026-11-07); the map stays one tap away in More and in the phone tab bar. */ ?>
      <a href="<?= e(url('ruined')) ?>"<?= $nv2On('/ruined') ?>><?= $nv2I('<path d="M12 3.5 2.5 20h19L12 3.5z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/>') ?>Warnings</a>
      <a href="<?= e(url('reviews')) ?>"<?= $nv2On('/reviews') ?>><?= $nv2I('<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z"/>') ?>Reviews</a>
      <a href="<?= e(url('buddies')) ?>"<?= $nv2On('/buddies') ?>><?= $nv2I('<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5.4 6-5.4s5.4 2 6 5.4"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14.6c2.3.3 4 2 4.5 5"/>') ?><?= $nv2Home ? 'Travel Buddies' : 'Travel buddies' ?></a>
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
          <a href="<?= e(url('map')) ?>">Travel map</a>
          <a href="<?= e(url('collections')) ?>">Collections</a>
          <a href="<?= e(url('leaderboard')) ?>">Top travelers</a>
          <a href="<?= e(url('tags')) ?>">Topics</a>
          <a href="<?= e(url('buddies')) ?>">Meet travelers</a>
        </div>
      </details>
      <?php endif; ?>
    </nav>
    <div class="v2-acts">
      <?php if ($nv2Home): ?>
      <a class="v2-ico-search" href="<?= e(url('search')) ?>" data-focus="v2-home-q" aria-label="Search" title="Search"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/></svg></a>
      <?php endif; ?>
      <?php if ($nv2P1): ?>
        <a class="v2-btn v2-btn-report" data-cta="nav_report" href="<?= e(url('report-a-trap')) ?>"><span>Report<span class="p1-sm-hide"> a Trap</span></span></a>
      <?php endif; ?>
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
        <?php if (!$nv2P1): ?><a class="v2-btn v2-btn-primary v2-hide-sm" data-cta="nav_rate" href="<?= e(url('review/new?src=home')) ?>">Rate a place</a><?php endif; ?>
      <?php elseif ($nv2P1): ?>
        <span class="v2-auth v2-hide-sm"><a class="v2-signin" href="<?= e(url('login')) ?>">Log In</a><span aria-hidden="true"> / </span><a class="v2-signin" data-cta="nav_join" href="<?= e(url('register')) ?>">Sign Up</a></span>
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
    <?php if ($nv2P1): ?>
    <a class="v2-sheet-item" data-cta="nav_report" href="<?= e(url('report-a-trap')) ?>"><span class="v2-si-ico" aria-hidden="true">!</span>
      <span><b>Report a Trap</b><small>A surprise fee, scam or tourist trap, in three quick steps</small></span></a>
    <a class="v2-sheet-item" data-cta="nav_explore_traps" href="<?= e(url('explore')) ?>"><span class="v2-si-ico" aria-hidden="true">⚲</span>
      <span><b>Explore Traps</b><small>Hidden fees, scams and gotchas by city</small></span></a>
    <?php if ($me): ?><a class="v2-sheet-item" href="<?= e(url('saved') . '#checklist') ?>"><span class="v2-si-ico" aria-hidden="true">✓</span>
      <span><b>Your trip checklist</b><small>The warnings you saved</small></span></a><?php endif; ?>
    <?php endif; ?>
    <p class="v2-sheet-h">Share</p>
    <a class="v2-sheet-item" data-cta="nav_warn" href="<?= e(url('ruined')) ?>"><span class="v2-si-ico" aria-hidden="true">!</span>
      <span><b>Travel warnings</b><small>What went wrong for others, and yours for the next traveler</small></span></a>
    <a class="v2-sheet-item" data-cta="nav_rate" href="<?= e(url('review/new?src=home')) ?>"><span class="v2-si-ico" aria-hidden="true">★</span>
      <span><b>Rate a place</b><small>Stars and a few lines, no account to start</small></span></a>
    <a class="v2-sheet-item" href="<?= e(url('map')) ?>"><span class="v2-si-ico" aria-hidden="true">◉</span>
      <span><b>My travel map</b><small>Color the countries you have been to</small></span></a>
    <a class="v2-sheet-item" href="<?= e($me ? url('trip/new') : url('plan?cta=nav_plan')) ?>" data-cta="nav_plan"><span class="v2-si-ico" aria-hidden="true">✈</span>
      <span><b>Post a trip</b><small>Where and when, so travelers on your dates find you</small></span></a>
    <p class="v2-sheet-h">Plan</p>
    <a class="v2-sheet-item" href="<?= e(url('explore')) ?>"><span class="v2-si-ico" aria-hidden="true">⚲</span>
      <span><b>Explore cities</b><small>Places with hours, addresses and honest reviews</small></span></a>
    <?php if ($nv2S('going')): ?><a class="v2-sheet-item" href="<?= e(url('buddies')) ?>"><span class="v2-si-ico" aria-hidden="true">☺</span>
      <span><b>Travel buddies</b><small>See who overlaps your dates</small></span></a><?php endif; ?>
    <a class="v2-sheet-item" href="<?= e(url('hosts-locals')) ?>"><span class="v2-si-ico" aria-hidden="true">&#8962;</span>
      <span><b>Hosts &amp; Locals</b><small>Meet people who live where you are going</small></span></a>
    <?php if ($nv2S('talk')): ?><a class="v2-sheet-item" href="<?= e(url('talk')) ?>"><span class="v2-si-ico" aria-hidden="true">?</span>
      <span><b>Ask a question</b><small>Travelers who have been answer</small></span></a><?php endif; ?>
    <p class="v2-sheet-h">More</p>
    <div class="v2-sheet-links">
      <a href="<?= e(url('reviews')) ?>">Reviews</a><a href="<?= e(url('events')) ?>">Events</a>
      <?php if ($nv2S('going')): ?><a href="<?= e(url('going')) ?>">Who is going</a><?php endif; ?><a href="<?= e(url('travelers')) ?>">All travelers</a>
      <?php if ($nv2S('meetups')): ?><a href="<?= e(url('meetups')) ?>">Meetups</a><?php endif; ?><a href="<?= e(url('communities')) ?>">Communities</a>
      <?php if ($nv2S('collections')): ?><a href="<?= e(url('collections')) ?>">Collections</a><?php endif; ?>
      <a href="<?= e(url('leaderboard')) ?>">Top travelers</a><a href="<?= e(url('tags')) ?>">Topics</a>
      <?php if ($me): ?>
        <a href="<?= e(url('feed')) ?>">Feed</a><a href="<?= e(url('matches')) ?>">Matches</a>
        <a href="<?= e(url('buddies/mine')) ?>">Your travel buddies</a><a href="<?= e(url('saved')) ?>">Saved</a>
        <?php if (function_exists('rmt_trust_open_for') && rmt_trust_open_for($me)): ?><a href="<?= e(url('trust')) ?>">Your trust</a><?php endif; ?>
        <a href="<?= e(url('invite')) ?>">Invite a traveler</a><a href="<?= e(url('settings')) ?>">Settings</a>
        <form class="signout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="link-btn">Sign out</button></form>
        <?php if (in_array($me['role'], ['admin', 'mod'], true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if (!$me): ?>
      <div class="v2-sheet-cta">
        <a class="v2-btn v2-btn-primary v2-btn-block" data-cta="nav_join" href="<?= e(url('register')) ?>"><?= $nv2P1 ? 'Sign Up' : 'Join free' ?></a>
        <a class="v2-btn v2-btn-ghost v2-btn-block" href="<?= e(url('login')) ?>"><?= $nv2P1 ? 'Log In' : 'Sign in' ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
