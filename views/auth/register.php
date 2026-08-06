<?php
/**
 * Signup.
 *
 * The reason to register is stated as a concrete benefit, not as membership of a community —
 * "save destinations and receive important warnings before your trip". The benefit list sits
 * beside the form rather than after it, because the decision is made before the first field.
 *
 * @var array $errors
 */
?>
<div class="wrap signup-grid">
  <div class="form-card" style="margin:0;max-width:none">
    <h1 style="margin-bottom:.2rem">Save destinations and receive important warnings before your trip.</h1>
    <p class="muted" style="margin-top:0">Free, and takes a minute.</p>

    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register')) ?>"><?= csrf_field() ?>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?= e(input('username')) ?>" required
             pattern="[A-Za-z0-9_]{3,24}" autocomplete="username">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email">
      <span class="hint">Used to confirm your account and send the trip alerts you ask for. Nothing else.</span>
      <label for="password">Password <span class="hint">(8+ characters)</span></label>
      <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      <label for="birthdate">Date of birth <span class="hint">(you must be 16+ to join)</span></label>
      <input type="date" id="birthdate" name="birthdate" value="<?= e(input('birthdate')) ?>" required>
      <p class="hint" style="margin-top:14px">By joining you agree to our <a href="<?= e(url('terms')) ?>">Terms</a>,
        <a href="<?= e(url('privacy')) ?>">Privacy Policy</a>, and
        <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>
      <div style="margin-top:12px"><button class="btn btn-primary btn-block">Create my account</button></div>
    </form>
    <p class="muted" style="margin-top:16px">Already have an account? <a href="<?= e(url('login')) ?>">Sign in</a></p>
  </div>

  <aside class="empty-cta" style="text-align:left;margin:0">
    <h2 style="font-size:1.1rem;margin-top:0">What an account gets you</h2>
    <ul class="tips-list" style="margin-top:10px">
      <li><b>A trip watchlist</b> — save a destination with your travel dates.</li>
      <li><b>Alerts that matter</b> — email only when a warning serious enough to change your plans is published for where you are going. Weekly at most; you set the bar.</li>
      <li><b>A preparation checklist</b> built from the actual warnings for that destination, not a generic packing list.</li>
      <li><b>Submit and track your own warnings</b>, and see exactly where each one is in moderation.</li>
      <li><b>Vote reports helpful</b> so the most useful ones rise for the next traveler.</li>
      <li><b>Follow destinations</b> you care about without committing to dates.</li>
    </ul>
    <p class="hint" style="margin-top:14px">Just want the emails?
      <a href="<?= e(url('alerts')) ?>">Subscribe without an account</a>.</p>
  </aside>
</div>
