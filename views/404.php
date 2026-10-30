<div class="wrap" style="text-align:center;padding:80px 20px;min-height:50vh">
  <?php /* The status code is 404 on the wire and nowhere on the page. A number above a sentence
           tells a traveller nothing they can act on, and it is the clearest sign a product was
           written for whoever built it. */ ?>
  <?php if (!empty($goneCity)): ?>
  <h1>This place is no longer listed</h1>
  <p class="muted">We removed this listing because it was not a real venue we could tell you
    anything useful about. Everything else we know about <?= e($goneCity['name']) ?> is still here.</p>
  <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url('d/' . $goneCity['slug'])) ?>"><?= e($goneCity['name']) ?></a> <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
  <?php else: ?>
  <h1>This trip went off the map</h1>
  <p class="muted">That page does not exist, or it moved. If you followed a link from somewhere
    else, the thing it pointed at may have been deleted or made private.</p>
  <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url()) ?>">Back home</a> <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
  <?php endif; ?>
</div>
