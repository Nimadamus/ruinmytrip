<?php /** @var string $msg */ ?>
<?php /* The status code stays 403 on the wire, where it belongs. It does not belong on the page.
         A traveller who was removed from a trip, or who pressed a button on a page that went stale
         while it was open, is not helped by the number or by the phrase "not authorized": both
         read as the site being broken rather than as something having changed. The explanation the
         caller passed in leads instead, because it is the only part that says what happened. */ ?>
<div class="wrap" style="text-align:center;padding:80px 20px">
  <h1 style="font-size:1.6rem">That is not available to you</h1>
  <p class="muted" style="max-width:44ch;margin:10px auto 0"><?= e($msg) ?></p>
  <p class="hint" style="margin-top:14px">If this used to work, something changed: a trip may have
    been made private, or somebody may have left it.</p>
  <p style="margin-top:22px"><a class="btn btn-primary" href="<?= e(url()) ?>">Back home</a>
    <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
</div>
