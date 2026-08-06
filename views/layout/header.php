<?php /** @var array $__meta */ $me = current_user(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__meta['title']) ?></title>
<meta name="description" content="<?= e($__meta['description']) ?>">
<link rel="canonical" href="<?= e($__meta['canonical']) ?>">
<meta name="robots" content="index, follow">
<meta property="og:type" content="<?= e($__meta['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($__meta['title']) ?>">
<meta property="og:description" content="<?= e($__meta['description']) ?>">
<meta property="og:url" content="<?= e($__meta['canonical']) ?>">
<meta property="og:image" content="<?= e($__meta['og_image']) ?>">
<meta property="og:site_name" content="RuinMyTrip">
<?php /* Twitter tags are emitted explicitly rather than left to fall back to the og:* ones —
         a summary_large_image card with no twitter:title renders as a bare URL in some clients. */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($__meta['title']) ?>">
<meta name="twitter:description" content="<?= e($__meta['description']) ?>">
<meta name="twitter:image" content="<?= e($__meta['og_image']) ?>">
<meta name="theme-color" content="#0f1b2d">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="alternate" type="application/rss+xml" title="RuinMyTrip" href="<?= e(url('feed.xml')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<?= $__meta['jsonld'] ?? '' ?>
<?php if (!empty($__meta['breadcrumbs'])) echo breadcrumb_jsonld($__meta['breadcrumbs']); ?>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url()) ?>">
      <span class="brand-mark">◈</span> Ruin<span>My</span>Trip
    </a>
    <form class="nav-search ac-wrap" action="<?= e(url('search')) ?>" method="get" role="search">
      <input type="search" name="q" autocomplete="off" data-suggest
             placeholder="Check a destination…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
      <div class="ac-list" role="listbox" aria-label="Suggestions"></div>
    </form>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <?php /* Primary nav is the risk product. The community features this site already has are still
             fully available, but they moved under "More" so a first-time visitor sees the one thing
             the site is for rather than ten equal-weight destinations. */ ?>
    <nav class="site-nav" aria-label="Primary">
      <form class="nav-search-mobile ac-wrap" action="<?= e(url('search')) ?>" method="get" role="search">
        <input type="search" name="q" autocomplete="off" data-suggest
               placeholder="Check a destination…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
        <div class="ac-list" role="listbox" aria-label="Suggestions"></div>
      </form>
      <a href="<?= e(url('explore')) ?>">Destinations</a>
      <a href="<?= e(url('warnings')) ?>">Warnings</a>
      <a href="<?= e(url('warning-guides')) ?>">Guides</a>
      <a href="<?= e(url('alerts')) ?>">Alerts</a>
      <details class="nav-more" style="display:inline-block">
        <summary style="cursor:pointer;font-weight:600;color:var(--ink-2);list-style:none">More ▾</summary>
        <div class="nav-more-menu">
          <a href="<?= e(url('reviews')) ?>">Reviews</a>
          <a href="<?= e(url('guides')) ?>">Traveler guides</a>
          <a href="<?= e(url('blog')) ?>">Blog</a>
          <a href="<?= e(url('collections')) ?>">Collections</a>
          <a href="<?= e(url('tags')) ?>">Topics</a>
          <a href="<?= e(url('discover')) ?>">Discover</a>
          <a href="<?= e(url('meetups')) ?>">Meetups</a>
          <a href="<?= e(url('going')) ?>">Who's going</a>
          <a href="<?= e(url('leaderboard')) ?>">Top reviewers</a>
        </div>
      </details>
      <?php if ($me): ?>
        <a href="<?= e(url('dashboard')) ?>">Dashboard</a>
        <a href="<?= e(url('messages')) ?>" title="Messages">✉️<?php $unread = rmt_unread_message_count((int)$me['id']); if ($unread): ?> <span class="chip" style="background:#0f766e;color:#fff"><?= $unread ?></span><?php endif; ?></a>
        <a href="<?= e(url('notifications')) ?>" title="Notifications">🔔</a>
        <?php if (in_array($me['role'],['admin','mod'],true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">@<?= e($me['username']) ?></a>
        <a class="btn btn-accent" href="<?= e(url('warning/new')) ?>">Share a Warning</a>
      <?php else: ?>
        <a class="btn btn-accent" href="<?= e(url('warning/new')) ?>">Share a Warning</a>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($f = flash()): ?><div class="flash wrap"><?= e($f) ?></div><?php endif; ?>
<main id="main">
