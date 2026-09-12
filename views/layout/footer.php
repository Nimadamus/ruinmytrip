</main>
<?php /* An app surface ends where its content ends. Everything the big footer links to is one tap
         away in the bar at the bottom of the screen, so repeating it under a chat is noise the
         reader has to scroll past to reach nothing. */ ?>
<?php if (!empty($meta['app_shell'])): ?>
  <footer class="site-footer site-footer-slim">
    <div class="wrap"><p class="muted" style="margin:0">
      <a href="<?= e(url('safety')) ?>">Safety</a> &middot;
      <a href="<?= e(url('about')) ?>">About</a> &middot;
      <a href="<?= e(url('privacy')) ?>">Privacy</a> &middot;
      <a href="<?= e(url('terms')) ?>">Terms</a>
    </p></div>
  </footer>
<?php else: ?>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">◈</span> Ruin<span>My</span>Trip</a>
      <p class="muted">Real trips. Honest reviews. Safe, optional meetups. A travel community built on trust.</p>
    </div>
    <div>
      <h4>Explore</h4>
      <a href="<?= e(url('explore')) ?>">Destinations</a>
      <a href="<?= e(url('travelers')) ?>">Travelers</a>
      <a href="<?= e(url('guides')) ?>">Guides & itineraries</a>
      <a href="<?= e(url('reviews')) ?>">Reviews</a>
      <a href="<?= e(url('contribute')) ?>">Write a review</a>
      <a href="<?= e(url('blog')) ?>">Blog</a>
      <a href="<?= e(url('discover')) ?>">Discover</a>
      <a href="<?= e(url('talk')) ?>">Travel talk</a>
      <a href="<?= e(url('communities')) ?>">Communities</a>
      <a href="<?= e(url('collections')) ?>">Collections</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a>
      <a href="<?= e(url('going')) ?>">Who's going</a>
<?php /* Signed out this is a redirect to /login, which is a link worth nothing to a reader
             and a wasted crawl to everybody else. */ ?>
      <?php if (current_user()): ?><a href="<?= e(url('matches')) ?>">Your matches</a><?php endif; ?>
      <a href="<?= e(url('leaderboard')) ?>">Top Reviewers</a>
      <a href="<?= e(url('tags')) ?>">Topics</a>
    </div>
    <div>
      <h4>Community</h4>
      <a href="<?= e(url('safety')) ?>">Meetup Safety</a>
      <a href="<?= e(url('register')) ?>">Create an account</a>
      <a href="<?= e(url('founding')) ?>">Founding Traveler</a>
      <a href="<?= e(url('start')) ?>">How to start</a>
    </div>
    <div>
      <?php /* "Legal" is where terms and privacy belong. How the site works, what it promises and
               how to reach it are not legal documents, and filing them under that heading is how
               nobody reads them. */ ?>
      <h4>Trust</h4>
      <a href="<?= e(url('about')) ?>">About RuinMyTrip</a>
      <a href="<?= e(url('editorial-policy')) ?>">Editorial standards</a>
      <a href="<?= e(url('guidelines')) ?>">Community guidelines</a>
      <a href="<?= e(url('contact')) ?>">Contact and corrections</a>
    </div>
    <div>
      <h4>Legal</h4>
      <a href="<?= e(url('terms')) ?>">Terms</a>
      <a href="<?= e(url('privacy')) ?>">Privacy</a>
      <a href="<?= e(url('affiliate')) ?>">Affiliate Disclosure</a>
    </div>
  </div>
  <div class="wrap footer-base muted">
    © <?= date('Y') ?> RuinMyTrip · Travel boldly, travel safe · <a href="<?= e(url('safety')) ?>">Safety first</a>
  </div>
</footer>
<?php endif; ?>
<?php /* The app bar. Most of this site's readers arrive on a phone, where the whole navigation was
         behind a hamburger: five taps to reach the thing they came for. These are the five places
         worth a permanent thumb-reach target, and the middle one is the only thing we actually want
         people to do. Server rendered like everything else, so it costs nothing in speed or SEO. */ ?>
<?php $rmt_me = current_user(); $rmt_path = parse_url(rmt_current_url(), PHP_URL_PATH) ?: '/'; ?>
<nav class="tabbar" aria-label="Primary mobile">
  <a href="<?= e(url()) ?>" class="<?= $rmt_path === '/' ? 'on' : '' ?>" aria-label="Home">
    <span aria-hidden="true">&#8962;</span><span class="tabbar-l">Home</span></a>
  <a href="<?= e(url('travelers')) ?>" class="<?= str_starts_with($rmt_path, '/travelers') || str_starts_with($rmt_path, '/explore') ? 'on' : '' ?>" aria-label="Travelers">
    <span aria-hidden="true">&#9906;</span><span class="tabbar-l">Travelers</span></a>
  <?php
    /* One tap to the thing the site is for. Where that leads depends on where the reader is
       standing: on a city page it is that city's composer, because somebody looking at Lisbon who
       taps + means "say something about Lisbon", and sending them to a generic form is how the
       thought gets lost on the way. */
    $rmt_post_to = '/going';
    $rmt_post_label = 'Post your dates';
    if (preg_match('#^/d/([a-z0-9\-]+)#', $rmt_path, $rmt_m)) {
        $rmt_post_to = '/d/' . $rmt_m[1] . '/travelers#say';
        $rmt_post_label = 'Post about this city';
    } elseif (str_starts_with($rmt_path, '/talk') || str_starts_with($rmt_path, '/post/')) {
        $rmt_post_to = '/talk#say';
        $rmt_post_label = 'Say something';
    }
  ?>
  <a class="tabbar-post" aria-label="<?= e($rmt_post_label) ?>"
     href="<?= e($rmt_me ? url(ltrim($rmt_post_to, '/')) : url('register?return=' . rawurlencode($rmt_post_to))) ?>">
    <span aria-hidden="true">+</span></a>
  <a href="<?= e(url('talk')) ?>" class="<?= str_starts_with($rmt_path, '/talk') || str_starts_with($rmt_path, '/post/') ? 'on' : '' ?>" aria-label="Talk">
    <span aria-hidden="true">&#9993;</span><span class="tabbar-l">Talk</span></a>
  <?php if ($rmt_me): ?>
    <a href="<?= e(url('notifications')) ?>" class="<?= str_starts_with($rmt_path, '/notifications') ? 'on' : '' ?>" aria-label="Notifications">
      <span aria-hidden="true">&#9788;</span><span class="tabbar-l">Alerts</span>
      <?php $rmt_n = rmt_unread_notification_count((int) $rmt_me['id']); if ($rmt_n): ?>
        <span class="tabbar-dot" aria-hidden="true"></span>
      <?php endif; ?></a>
  <?php else: ?>
    <a href="<?= e(url('register')) ?>" aria-label="Join"><span aria-hidden="true">&#9734;</span><span class="tabbar-l">Join</span></a>
  <?php endif; ?>
</nav>

<script src="<?= e(rmt_asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(rmt_asset('assets/js/suggest.js')) ?>" defer></script>
<script src="<?= e(rmt_asset('assets/js/contribute-track.js')) ?>" defer></script>
<script src="<?= e(rmt_asset('assets/js/review-draft.js')) ?>" defer></script>
</body>
</html>
