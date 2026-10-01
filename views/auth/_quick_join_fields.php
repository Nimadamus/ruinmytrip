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
<?php if (function_exists('rmt_google_enabled') && rmt_google_enabled()): ?>
  <a class="btn btn-google btn-block" href="<?= e(url('auth/google' . ($qjReturn !== '' ? '?return=' . rawurlencode($qjReturn) : ''))) ?>">
    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.2 5.3-4.6 6.9l7.4 5.7c4.3-4 6.9-9.9 6.9-17.1z"/><path fill="#FBBC05" d="M10.6 28.6A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.8-4.6l-7.9-6.1A24 24 0 0 0 0 24c0 3.9.9 7.5 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.4-5.7c-2.1 1.4-4.8 2.3-8.5 2.3-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
    Continue with Google</a>
  <p class="qj-or"><span>or with email</span></p>
<?php endif; ?>
<label for="email">Email</label>
<input type="email" id="email" name="email" value="<?= e(input('email') !== '' ? (string) input('email') : (string) ($_SESSION['alert_email'] ?? '')) ?>" required autocomplete="email">
<label for="password">Password <span class="hint">(8+ characters)</span></label>
<input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
<label class="qj-age"><input type="checkbox" name="age_ok" value="1" required<?= input('age_ok') === '1' ? ' checked' : '' ?>>
  I am 16 or older</label>
<p class="hint" style="margin-top:10px">Your username is made from your email; change it any time in settings.
  By joining you agree to our <a href="<?= e(url('terms')) ?>">Terms</a>, <a href="<?= e(url('privacy')) ?>">Privacy Policy</a>,
  and <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>
