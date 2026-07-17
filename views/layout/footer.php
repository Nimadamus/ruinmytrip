</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">◈</span> Ruin<span>My</span>Trip</a>
      <p class="muted">Real trips. Honest reviews. Safe, optional meetups. A travel community built on trust.</p>
    </div>
    <div>
      <h4>Explore</h4>
      <a href="<?= e(url('explore')) ?>">Destinations</a>
      <a href="<?= e(url('guides')) ?>">Guides & itineraries</a>
      <a href="<?= e(url('reviews')) ?>">Reviews</a>
      <a href="<?= e(url('meetups')) ?>">Meetups</a>
      <a href="<?= e(url('going')) ?>">Who's going</a>
    </div>
    <div>
      <h4>Community</h4>
      <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>
      <a href="<?= e(url('safety')) ?>">Meetup Safety</a>
      <a href="<?= e(url('register')) ?>">Create an account</a>
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
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
