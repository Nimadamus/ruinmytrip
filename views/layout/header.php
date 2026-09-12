<?php /** @var array $__meta */ $me = current_user(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__meta['title']) ?></title>
<meta name="description" content="<?= e($__meta['description']) ?>">
<?php if (($__meta['canonical'] ?? '') !== ''): ?><link rel="canonical" href="<?= e($__meta['canonical']) ?>"><?php endif; ?>
<?php /* One robots tag, and one place that decides what it says. It was hardcoded to
         "index, follow" on every page including the ones that should never be indexed; a page
         type that has not earned a place in the index now says so from its controller. */ ?>
<meta name="robots" content="<?= e((string) ($__meta['robots'] ?? 'index, follow')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($__meta['title']) ?>">
<meta property="og:description" content="<?= e($__meta['description']) ?>">
<?php if (($__meta['canonical'] ?? '') !== ''): ?><meta property="og:url" content="<?= e($__meta['canonical']) ?>"><?php endif; ?>
<meta property="og:image" content="<?= e($__meta['og_image']) ?>">
<meta property="og:site_name" content="RuinMyTrip">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0f1b2d">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<?php if (!empty($me) && function_exists('rmt_push_enabled') && rmt_push_enabled()): ?>
<meta name="vapid-key" content="<?= e(rmt_push_public_key()) ?>">
<script src="<?= e(url('assets/js/push.js')) ?>" defer></script>
<?php endif; ?>
<link rel="apple-touch-icon" href="<?= e(url('assets/img/icon-192.png')) ?>">
<?php /* The autocomplete click beacon posts a CSRF token like every other write on the site. */ ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="alternate" type="application/rss+xml" title="RuinMyTrip" href="<?= e(url('feed.xml')) ?>">
<?php /* The two faces the stylesheet asks for, fetched in parallel with it rather than after it.
         Without this the browser only learns the fonts exist once the CSS has parsed, which is one
         round trip too late and shows a frame of fallback type on every first visit. */ ?>
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= e(rmt_asset('assets/fonts/inter-latin.woff2')) ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin href="<?= e(rmt_asset('assets/fonts/fraunces-latin.woff2')) ?>">
<link rel="stylesheet" href="<?= e(rmt_asset('assets/css/app.css')) ?>">
<?= $__meta['jsonld'] ?? '' ?>
<?php if (!empty($__meta['breadcrumbs'])) echo breadcrumb_jsonld($__meta['breadcrumbs']); ?>
</head>
<body data-event-url="<?= e(url('event')) ?>"<?php if ($me): ?> data-suggest-users="<?= e(url('suggest/users')) ?>"<?php endif; ?>>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-mark">◈</span> Ruin<span>My</span>Trip
    </a>
    <form class="nav-search" action="<?= e(url('search')) ?>" method="get" role="search"
          data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
      <input type="search" name="q" placeholder="Search destinations, trips, guides…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <nav class="site-nav" aria-label="Primary">
      <form class="nav-search-mobile" action="<?= e(url('search')) ?>" method="get" role="search"
              data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>">
        <input type="search" name="q" placeholder="Search destinations, trips, guides…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
      </form>
      <?php /* People first, and only a handful of them. The nav used to carry thirteen links and
               two buttons, which is what a product looks like when every feature is argued for one
               at a time: nothing was wrong with any single link, and together they read as a
               directory rather than a place. The four that lead now are the four a member opens
               daily. Nothing is removed, because a page nobody can reach from the nav is a page
               that quietly dies: the rest sit in a disclosure that needs no JavaScript, so they
               are one click away, in the markup for a crawler, and keyboard reachable. */ ?>
      <?php if ($me): ?>
        <a href="<?= e(url('feed')) ?>">Feed</a>
      <?php endif; ?>
      <a href="<?= e(url('travelers')) ?>">Travelers</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a>
      <a href="<?= e(url('talk')) ?>">Talk</a>
      <a href="<?= e(url('explore')) ?>">Explore</a>
      <details class="nav-more">
        <summary aria-label="More of the site">More</summary>
        <div class="nav-more-panel">
          <a href="<?= e(url('going')) ?>">Who is going</a>
          <a href="<?= e(url('communities')) ?>">Communities</a>
          <a href="<?= e(url('ruined')) ?>">Ruined</a>
          <a href="<?= e(url('reviews')) ?>">Reviews</a>
          <a href="<?= e(url('guides')) ?>">Guides</a>
          <a href="<?= e(url('collections')) ?>">Collections</a>
          <a href="<?= e(url('leaderboard')) ?>">Top travelers</a>
          <a href="<?= e(url('tags')) ?>">Topics</a>
          <a href="<?= e(url('blog')) ?>">Blog</a>
          <?php if ($me): ?>
            <a href="<?= e(url('matches')) ?>">Matches</a>
            <a href="<?= e(url('saved')) ?>">Saved</a>
            <a href="<?= e(url('invite')) ?>">Invite a traveler</a>
            <a href="<?= e(url('settings')) ?>">Settings</a>
            <?php if (in_array($me['role'], ['admin', 'mod'], true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
          <?php endif; ?>
        </div>
      </details>
      <?php if ($me): ?>
        <?php /* Messages and notifications are glyphs with a count, the way every social product
                 does it, because they are checked rather than read. */ ?>
        <a class="nav-icon" href="<?= e(url('messages')) ?>" title="Messages" aria-label="Messages">&#9993;<?php
          $unread = rmt_unread_message_count((int) $me['id']);
          if ($unread): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?></a>
        <a class="nav-icon" href="<?= e(url('notifications')) ?>" title="Notifications" aria-label="Notifications">&#128276;<?php
          $unseen = rmt_unread_notification_count((int) $me['id']);
          if ($unseen): ?><span class="nav-badge nav-badge-alert"><?= $unseen ?></span><?php endif; ?></a>
        <a class="nav-me" href="<?= e(url('u/'.$me['username'])) ?>" title="Your profile">
          <img class="avatar" style="width:30px;height:30px"
               src="<?= e(avatar_url(rmt_profile_avatar((int) $me['id']))) ?>" alt="">
          <span class="nav-me-name">@<?= e($me['username']) ?></span>
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('trip/new')) ?>">Post a trip</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($f = flash()): ?><div class="flash wrap"><?= e($f) ?></div><?php endif; ?>
<main id="main">
