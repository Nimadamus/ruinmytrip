<div class="wrap" style="text-align:center;padding:80px 20px;min-height:50vh">
  <?php /* The status code is 404 on the wire and nowhere on the page. A number above a sentence
           tells a traveller nothing they can act on, and it is the clearest sign a product was
           written for whoever built it. */ ?>
  <h1>This trip went off the map</h1>
  <p class="muted">That page does not exist, or it moved. If you followed a link from somewhere
    else, the thing it pointed at may have been deleted or made private.</p>
  <p style="margin-top:20px"><a class="btn btn-primary" href="<?= e(url()) ?>">Back home</a> <a class="btn btn-ghost" href="<?= e(url('explore')) ?>">Explore destinations</a></p>
</div>
