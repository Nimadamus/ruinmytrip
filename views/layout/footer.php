</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">◈</span> Ruin<span>My</span>Trip</a>
      <p class="muted">Know what could ruin your trip before you book it. Researched destination risk reports
        and honest traveler warnings — scams, hidden costs, closures, crowds and the mistakes nobody mentions.</p>
    </div>
    <div>
      <h4>Check a destination</h4>
      <a href="<?= e(url('explore')) ?>">All destinations</a>
      <a href="<?= e(url('warnings')) ?>">All warnings</a>
      <a href="<?= e(url('warning-guides')) ?>">Warning guides</a>
      <a href="<?= e(url('alerts')) ?>">Travel alerts</a>
      <a href="<?= e(url('warning/new')) ?>">Share a warning</a>
    </div>
    <div>
      <h4>What ruins trips</h4>
      <?php foreach (array_slice(RMT_WARNING_CATEGORIES, 0, 6, true) as $k => $c): ?>
        <a href="<?= e(url('warnings/' . $k)) ?>"><?= e($c['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Community</h4>
      <a href="<?= e(url('reviews')) ?>">Reviews</a>
      <a href="<?= e(url('guides')) ?>">Traveler guides</a>
      <a href="<?= e(url('blog')) ?>">Blog</a>
      <a href="<?= e(url('collections')) ?>">Collections</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a>
      <a href="<?= e(url('going')) ?>">Who's going</a>
      <a href="<?= e(url('leaderboard')) ?>">Top reviewers</a>
    </div>
    <div>
      <h4>Trust &amp; legal</h4>
      <a href="<?= e(url('editorial-policy')) ?>">Editorial Policy</a>
      <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>
      <a href="<?= e(url('safety')) ?>">Meetup Safety</a>
      <a href="<?= e(url('terms')) ?>">Terms</a>
      <a href="<?= e(url('privacy')) ?>">Privacy</a>
      <a href="<?= e(url('affiliate')) ?>">Affiliate Disclosure</a>
    </div>
  </div>
  <div class="wrap footer-base muted">
    © <?= date('Y') ?> RuinMyTrip · Traveler warnings are first-hand accounts, labelled with their
    verification status and the date they happened · <a href="<?= e(url('editorial-policy')) ?>">How we work</a>
  </div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
