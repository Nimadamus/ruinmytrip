<?php
/**
 * Shown for any well-formed unsubscribe link, whether or not the address was subscribed —
 * a page that said "that address was not on our list" would be an enumeration oracle for
 * other people's travel plans.
 */
?>
<div class="form-card">
  <h1>You are unsubscribed</h1>
  <p>That address will not receive travel warning alerts from RuinMyTrip again. Nothing else about
     your account, if you have one, has changed.</p>
  <p class="muted">Changed your mind? You can <a href="<?= e(url('alerts')) ?>">resubscribe at any time</a>,
     or set per-trip alerts from your <a href="<?= e(url('dashboard')) ?>">dashboard</a>.</p>
  <a class="btn btn-primary" href="<?= e(url()) ?>">Back to RuinMyTrip</a>
</div>
