<?php /** @var array $__meta */ $me = current_user(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__meta['title']) ?></title>
<meta name="description" content="<?= e($__meta['description']) ?>">
<link rel="canonical" href="<?= e($__meta['canonical']) ?>">
<?php /* One robots tag, and one place that decides what it says. It was hardcoded to
         "index, follow" on every page including the ones that should never be indexed; a page
         type that has not earned a place in the index now says so from its controller. */ ?>
<meta name="robots" content="<?= e((string) ($__meta['robots'] ?? 'index, follow')) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($__meta['title']) ?>">
<meta property="og:description" content="<?= e($__meta['description']) ?>">
<meta property="og:url" content="<?= e($__meta['canonical']) ?>">
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
      <a href="<?= e(url('explore')) ?>">Explore</a>
      <a href="<?= e(url('guides')) ?>">Guides</a>
      <a href="<?= e(url('blog')) ?>">Blog</a>
      <a href="<?= e(url('travelers')) ?>">Travelers</a>
      <a href="<?= e(url('ruined')) ?>">Ruined</a>
      <a href="<?= e(url('talk')) ?>">Talk</a>
      <a href="<?= e(url('communities')) ?>">Communities</a>
      <a href="<?= e(url('going')) ?>">Going</a>
      <?php if ($me): ?>
        <a href="<?= e(url('matches')) ?>">Matches</a>
        <a href="<?= e(url('feed')) ?>">Feed</a>
        <a href="<?= e(url('saved')) ?>">Saved</a>
        <a href="<?= e(url('invite')) ?>">Invite</a>
        <a href="<?= e(url('messages')) ?>" title="Messages">✉️<?php $unread = rmt_unread_message_count((int)$me['id']); if ($unread): ?> <span class="chip" style="background:#0f766e;color:#fff"><?= $unread ?></span><?php endif; ?></a>
        <a href="<?= e(url('notifications')) ?>" title="Notifications">🔔<?php $unseen = rmt_unread_notification_count((int)$me['id']); if ($unseen): ?> <span class="chip" style="background:#b42318;color:#fff"><?= $unseen ?></span><?php endif; ?></a>
        <?php if (in_array($me['role'],['admin','mod'],true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">@<?= e($me['username']) ?></a>
        <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Write a Review</a>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Write a Review</a>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($f = flash()): ?><div class="flash wrap"><?= e($f) ?></div><?php endif; ?>
<main id="main">
