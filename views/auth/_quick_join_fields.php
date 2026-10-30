<?php
/**
 * The short join form (Q3, 2026-10-01): Google in one tap, or an email, a password and the 16+
 * tick. The username is drawn from the email and can be changed later; nothing else is asked
 * until the account exists.
 *
 * @var string $qjReturn  where to land afterwards ('' for the default)
 */
$qjReturn = (string) ($qjReturn ?? '');
?>
<?php include __DIR__ . '/_google_button.php'; ?>
<label for="email">Email</label>
<input type="email" id="email" name="email" value="<?= e(input('email') !== '' ? (string) input('email') : (string) ($_SESSION['alert_email'] ?? '')) ?>" required autocomplete="email">
<label for="password">Password <span class="hint">(8+ characters)</span></label>
<input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
<label class="qj-age"><input type="checkbox" name="age_ok" value="1" required<?= input('age_ok') === '1' ? ' checked' : '' ?>>
  I am 16 or older</label>
<p class="hint" style="margin-top:10px">Travel buddies and meetups are for members 18 and over; we ask your date of birth only when you use them.
  Your username is made from your email; change it any time in settings.
  By joining you agree to our <a href="<?= e(url('terms')) ?>">Terms</a>, <a href="<?= e(url('privacy')) ?>">Privacy Policy</a>,
  and <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>
