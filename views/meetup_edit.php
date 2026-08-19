<?php /** @var array $m @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit meetup</h1>
  <p class="muted">Anyone who has already RSVPed keeps their place. Changing the time does not un-RSVP them, so say so in the description if it moved.</p>
  <?php $isEdit = true; $action = url('meetup/'.(int)$m['id'].'/edit'); require __DIR__ . '/_meetup_form.php'; ?>
</div></div>
