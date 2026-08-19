<?php /** @var array $dests @var array $errors @var array $m */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Host a meetup</h1>
  <p class="muted">Public, optional, and safety-first. A way to meet other travelers in a destination — never dating, never precise location.</p>
  <?php $isEdit = false; $action = url('meetup/new'); require __DIR__ . '/_meetup_form.php'; ?>
</div></div>
