<?php /** @var ?array $me @var array $recentDestinations @var int $myReviews */ ?>
<div class="wrap" style="max-width:760px">
  <h1 style="margin:.2rem 0 .4rem">Review a place you went to</h1>
  <p class="muted" style="margin:0 0 6px">
    Not every review starts from a page. Search for the hotel, restaurant or thing you did, and
    write what it was actually like.
  </p>
  <p class="hint" style="margin:0 0 22px">
    <?php if ($myReviews > 0): ?>
      You have written <?= (int) $myReviews ?> <?= $myReviews === 1 ? 'review' : 'reviews' ?> so far.
    <?php else: ?>
      What it cost, what surprised you, what you wish somebody had told you first.
    <?php endif; ?>
  </p>

  <?php /* The same suggestion endpoint the header uses, so what a traveler types here behaves
           exactly the way it does everywhere else on the site. */ ?>
  <form class="contribute-search" role="search" method="get" action="<?= e(url('search')) ?>"
        data-suggest-url="<?= e(url('suggest')) ?>" data-suggest-click="<?= e(url('suggest/click')) ?>"
        data-suggest-target="review" data-review-url="<?= e(url('review/new')) ?>"
        style="margin:0 0 26px">
    <label for="contribute-q" style="display:block;margin:0 0 6px;font-weight:600">Where did you go?</label>
    <input type="search" id="contribute-q" name="q" autocomplete="off"
           placeholder="Hotel Sacher, Rijksmuseum, a restaurant in Prague&hellip;"
           aria-label="Search for a place you visited" style="width:100%">
    <p class="hint" style="margin:8px 0 0">
      Pick a place from the suggestions to go straight to writing. Pressing Enter searches
      everything instead.
    </p>
  </form>

  <?php if ($recentDestinations): ?>
    <?php /* Somewhere to start for a person who does not have a name in mind. Real destinations
             with places in them, not a curated "popular" list we cannot substantiate. */ ?>
    <h2 style="font-size:1.05rem;margin:0 0 10px">Or browse somewhere you have been</h2>
    <div class="chip-row" style="margin:0 0 30px">
      <?php foreach ($recentDestinations as $rd): ?>
        <a class="chip" href="<?= e(url('d/'.$rd['slug'].'/places')) ?>">
          <?= e((string) $rd['name']) ?>
          <span class="chip-count"><?= (int) $rd['places'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="card" style="margin:0 0 30px"><div class="card-body">
    <h2 style="margin:0 0 8px;font-size:1.02rem">Can't find the place?</h2>
    <p class="muted" style="margin:0 0 10px;font-size:.95rem">
      Tell us the name and the city and we will check it and add it. Places are added by hand
      rather than instantly, so nobody can fill the site with entries that do not exist.
    </p>
    <?php if ($me): ?>
      <form method="post" action="<?= e(url('contribute/suggest-place')) ?>">
        <?= csrf_field() ?>
        <div class="grid g-2" style="gap:12px">
          <div>
            <label for="sp-name">Place name</label>
            <input type="text" id="sp-name" name="name" maxlength="200" required>
          </div>
          <div>
            <label for="sp-city">City or destination</label>
            <input type="text" id="sp-city" name="city" maxlength="120" required>
          </div>
        </div>
        <div class="grid g-2" style="gap:12px">
          <div>
            <label for="sp-type">What kind of place</label>
            <select id="sp-type" name="type">
              <?php foreach (RMT_PLACE_TYPES as $t): ?>
                <option value="<?= e($t) ?>"><?= e(rmt_place_type_label($t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="sp-url">Website <span class="muted">(optional)</span></label>
            <input type="text" id="sp-url" name="website_url" maxlength="500">
          </div>
        </div>
        <p style="margin:14px 0 0"><button class="btn btn-primary">Suggest this place</button></p>
      </form>
    <?php else: ?>
      <p style="margin:0">
        <a class="btn btn-ghost" href="<?= e(url('login?return=' . rawurlencode('/contribute'))) ?>">Sign in to suggest a place</a>
      </p>
    <?php endif; ?>
  </div></section>
</div>
