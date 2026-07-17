<?php /** @var array $__meta */ $me = current_user(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__meta['title']) ?></title>
<meta name="description" content="<?= e($__meta['description']) ?>">
<link rel="canonical" href="<?= e($__meta['canonical']) ?>">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($__meta['title']) ?>">
<meta property="og:description" content="<?= e($__meta['description']) ?>">
<meta property="og:url" content="<?= e($__meta['canonical']) ?>">
<meta property="og:image" content="<?= e($__meta['og_image']) ?>">
<meta property="og:site_name" content="RuinMyTrip">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#0f1b2d">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
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
    <form class="nav-search" action="<?= e(url('search')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="Search destinations, trips, guides…" aria-label="Search" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <nav class="site-nav" aria-label="Primary">
      <a href="<?= e(url('explore')) ?>">Explore</a>
      <a href="<?= e(url('guides')) ?>">Guides</a>
      <a href="<?= e(url('reviews')) ?>">Reviews</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a>
      <a href="<?= e(url('going')) ?>">Who's going</a>
      <?php if ($me): ?>
        <a href="<?= e(url('feed')) ?>">Feed</a>
        <a href="<?= e(url('notifications')) ?>" title="Notifications">🔔</a>
        <?php if (in_array($me['role'],['admin','mod'],true)): ?><a href="<?= e(url('admin')) ?>">Admin</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">@<?= e($me['username']) ?></a>
        <a class="btn btn-primary" href="<?= e(url('trip/new')) ?>">Share a trip</a>
      <?php else: ?>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">Join free</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($f = flash()): ?><div class="flash wrap"><?= e($f) ?></div><?php endif; ?>
<main id="main">
