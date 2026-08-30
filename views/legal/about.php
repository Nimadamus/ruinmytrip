<?php /** @var array $counts */ ?>
<div class="wrap prose" style="max-width:760px;padding:30px 20px 60px">
  <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / About</p>
  <p class="eyebrow">About</p>
  <h1>A travel site that tells you what actually goes wrong</h1>

  <p style="font-size:1.1rem;color:var(--muted)">
    RuinMyTrip is a place to look up somewhere you are thinking of going, find out what it costs and
    when it is open, and read what travelers who went actually thought. It is also where you write
    that down for the next person.
  </p>

  <h2>Where we are right now</h2>
  <?php /* Live counts, not a marketing number typed into a template a year ago. The traveler-review
           figure is the one that matters and it is the one we are least proud of, so it is stated
           first and plainly rather than buried. A site that opens by claiming a community it does
           not have has already told you what its reviews are worth. */ ?>
  <p>
    We currently cover <b><?= (int) $counts['destinations'] ?></b>
    <?= (int) $counts['destinations'] === 1 ? 'destination' : 'destinations' ?> and
    <b><?= (int) $counts['places'] ?></b> individual
    <?= (int) $counts['places'] === 1 ? 'place' : 'places' ?> &mdash; hotels, restaurants and things
    to do &mdash; with addresses, opening hours and prices where we can source them.
  </p>
  <p>
    <?php if ((int) $counts['community_reviews'] === 0): ?>
      There are <b>no traveler reviews on this site yet</b>. Not one. Everything you can read today
      is editorial research written by us and labelled as ours on every screen it appears on. We
      could have filled the gap with invented accounts and generated reviews, the way a certain kind
      of new review site does. We did not, and we are not going to, and this page will keep saying
      so until the number above changes because a real person wrote something.
    <?php else: ?>
      <b><?= (int) $counts['community_reviews'] ?></b>
      <?= (int) $counts['community_reviews'] === 1 ? 'review has' : 'reviews have' ?> been written by
      <b><?= (int) $counts['reviewers'] ?></b>
      <?= (int) $counts['reviewers'] === 1 ? 'traveler' : 'travelers' ?>. Every one of them is a real
      person who chose to write it. None was bought, generated, imported or invented.
    <?php endif; ?>
  </p>

  <h2>What we promise</h2>
  <ul style="line-height:1.9">
    <li><b>Bad reviews are as welcome as good ones.</b> The disappointing parts are the useful
      parts. A one-star review that explains itself is worth more to the next traveler than a
      five-star one that does not, and it is treated that way here.</li>
    <li><b>Criticism is not removed for being critical.</b> Reporting a review does not hide it, no
      number of reports hides it, and nothing in the system reads a rating when deciding whether a
      review breaks the rules. See the <a href="<?= e(url('guidelines')) ?>">community guidelines</a>.</li>
    <li><b>Nobody can buy a rating.</b> There is no way for a business to pay for a better score, a
      higher position, or the removal of a review, because we have not built one and do not intend
      to.</li>
    <li><b>Traveler reviews are never fabricated.</b> No generated accounts, no AI-written reviews,
      no ratings copied from another review site, no counts padded to look busier.</li>
    <li><b>Editorial is labelled, separated and never counted as a traveler review.</b> Our own
      research is excluded from every community rating by query, not by good intentions. See
      <a href="<?= e(url('editorial-policy')) ?>">editorial standards</a>.</li>
    <li><b>Place information comes from real sources, and says which.</b> Addresses, hours and
      contact details are taken from OpenStreetMap and official venue pages, and each place page
      names its source and the date it was last checked.</li>
    <li><b>Corrections are welcome and easy.</b> If something here is wrong, tell us on the place
      page itself. A person reads it; nothing you send changes the site automatically.</li>
  </ul>

  <h2>What we are not</h2>
  <p>
    We are not a booking site and we take no commission on a stay or a table. We are not a
    guidebook publisher with inspectors in the field. We are not big. This is a small operation
    building the thing carefully rather than a large one filling it quickly, and the difference
    shows most in what is missing rather than in what is wrong.
  </p>

  <h2>Who writes what</h2>
  <p>
    Editorial content is published under one account,
    <a href="<?= e(url('u/' . RMT_EDITORIAL_USERNAME)) ?>">RuinMyTrip Editorial</a>, and carries a
    label wherever it appears. Traveler reviews are published under the traveler's own profile and
    carry their name. The two are never mixed, never averaged together, and never dressed up as each
    other.
  </p>

  <h2>Telling us something is wrong</h2>
  <p>
    Wrong address, wrong hours, a place that has closed or moved: use the correction link on the
    place page. A review that breaks the guidelines: use Report on the review. Anything else:
    <a href="<?= e(url('contact')) ?>">get in touch</a>.
  </p>

  <p style="margin-top:26px">
    <a class="btn btn-accent" data-review-cta="other" href="<?= e(url('contribute')) ?>">Review a place you went to</a>
    <a class="btn btn-ghost" href="<?= e(url('guidelines')) ?>">Community guidelines</a>
    <a class="btn btn-ghost" href="<?= e(url('editorial-policy')) ?>">Editorial standards</a>
  </p>
</div>
