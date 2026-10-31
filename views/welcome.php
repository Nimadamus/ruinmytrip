<?php /** @var array $dests @var array $saved @var array $me @var array $communities @var array $suggested @var array $popular */
/* The profile is first because a name, one sentence, and a home city is what turns a registration
   into a page another traveler can open. The trip stays on the same screen. Cities, follows, and
   a first note stay in the form, folded, so the save button is reachable on a phone. */
$rmt_popular_ids = [];
foreach ($popular as $pp) $rmt_popular_ids[(int) $pp['id']] = true;
?>
<section class="block"><div class="wrap onboard-wrap">
  <p class="eyebrow">You're in</p>
  <h1>Finish your traveler profile</h1>
  <p class="muted onboard-lead">Three things make your page worth opening: your name, one sentence,
    and the city you live in. Everything else can wait.</p>
  <p class="onboard-meter" id="profile-meter" aria-live="polite">0 of 3 done</p>

  <form method="post" action="<?= e(url('welcome')) ?>" class="onboard">
    <?= csrf_field() ?>

    <section class="onboard-step onboard-now">
      <h2><span class="onboard-n">1</span> How should travelers find you?</h2>
      <ul class="onboard-need">
        <li id="need-name">Your name</li>
        <li id="need-bio">One sentence, about twenty characters</li>
        <li id="need-city">The city you live in</li>
      </ul>
      <label for="display_name">What should people call you?</label>
      <input type="text" id="display_name" name="display_name" maxlength="60" autocomplete="name"
             value="<?= e((string) ($me['display_name'] ?? '')) ?>" placeholder="Your name">
      <label for="bio">One sentence about how you travel</label>
      <textarea id="bio" name="bio" maxlength="600" rows="3"
                placeholder="I usually travel solo and like walking a new city."><?= e((string) ($me['bio'] ?? '')) ?></textarea>
      <div class="grid g-2 onboard-pair">
        <div>
          <label for="home_city">Your city</label>
          <input type="text" id="home_city" name="home_city" maxlength="80" autocomplete="address-level2"
                 value="<?= e($me['home_city'] ?? '') ?>" placeholder="Lisbon">
        </div>
        <div>
          <label for="travel_style">How do you usually travel?</label>
          <select id="travel_style" name="travel_style">
            <option value="">Rather not say</option>
            <?php foreach (RMT_TRAVEL_STYLES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= ($me['travel_style'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="onboard-step">
      <h2><span class="onboard-n">2</span> Going somewhere?</h2>
      <p class="hint">City and two dates, never a street address. You can add a travel buddy note later.
        <?php if ((int) ($upcomingTrips ?? 0) === 0): ?>Nobody has posted dates yet, so yours
        would be the first listing. You are told when somebody lines up with you.<?php else: ?>You are
        told when somebody's dates line up with yours.<?php endif; ?></p>
      <label for="destination_id">City</label>
      <select id="destination_id" name="destination_id">
        <option value="">Not sure yet</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= (int) $dd['id'] ?>"><?= e($dd['name']) ?>, <?= e($dd['country']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="grid g-2 onboard-pair">
        <div><label for="date_from">From</label><input type="date" id="date_from" name="date_from"></div>
        <div><label for="date_to">Until</label><input type="date" id="date_to" name="date_to"></div>
      </div>
      <input type="hidden" name="visibility" value="public">
      <p class="hint onboard-else">Not traveling soon?
        <a href="<?= e(url('map')) ?>">Color in where you have been</a>
        or <a href="<?= e(url('review/new')) ?>">write what went wrong</a> on the last trip.</p>
    </section>

    <details class="onboard-extra">
      <summary>Add cities, people, or a first note</summary>

      <section class="onboard-step onboard-inner">
        <h2>Cities you want to see</h2>
        <p class="hint">Anything happening in one of these reaches your feed: somebody going, a meetup,
          a question asked.</p>
        <div class="pick-grid">
          <?php foreach ($popular as $pp): $on = !empty($saved[(int) $pp['id']]); ?>
            <label class="pick<?= $on ? ' on' : '' ?>">
              <input type="checkbox" name="want[]" value="<?= (int) $pp['id'] ?>"<?= $on ? ' checked' : '' ?>>
              <span class="pick-name"><?= e($pp['name']) ?></span>
              <span class="pick-sub"><?= e((string) $pp['country']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <details class="onboard-more">
          <summary>Every other city we have a page for</summary>
          <div class="pick-chips">
            <?php foreach ($dests as $dd): if (isset($rmt_popular_ids[(int) $dd['id']])) continue; $on = !empty($saved[(int) $dd['id']]); ?>
              <label class="chip pick-chip<?= $on ? ' on' : '' ?>">
                <input type="checkbox" name="want[]" value="<?= (int) $dd['id'] ?>"<?= $on ? ' checked' : '' ?>>
                <?= e($dd['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
      </section>

      <?php if ($suggested): ?>
        <section class="onboard-step onboard-inner">
          <h2>Follow a few travelers</h2>
          <div class="pick-people">
            <?php foreach ($suggested as $sg): ?>
              <label class="pick-person">
                <input type="checkbox" name="follow[]" value="<?= (int) $sg['id'] ?>">
                <img class="avatar" src="<?= e(avatar_url($sg['avatar_url'] ?? null)) ?>" alt="">
                <span>
                  <b>@<?= e((string) $sg['username']) ?></b>
                  <span class="hint"><?php if (!empty($sg['home_city'])): ?><?= e((string) $sg['home_city']) ?> · <?php endif; ?><?= e((string) $sg['reason']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($communities): ?>
        <section class="onboard-step onboard-inner">
          <h2>Join a community</h2>
          <p class="hint">Groups other travelers started. Leave any of them whenever you like.</p>
          <div class="pick-people">
            <?php foreach ($communities as $cc): ?>
              <label class="pick-person">
                <input type="checkbox" name="join[]" value="<?= (int) $cc['id'] ?>">
                <span>
                  <b><?= e((string) $cc['title']) ?></b>
                  <span class="hint"><?= (int) $cc['member_count'] ?> members<?php
                    if (!empty($cc['summary'])): ?> · <?= e((string) $cc['summary']) ?><?php endif; ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <section class="onboard-step onboard-inner">
        <h2>Say something</h2>
        <p class="hint">A question counts. So does a warning about the last place that ruined your trip.</p>
        <textarea name="hello" aria-label="Say hello" rows="3" maxlength="<?= RMT_POST_MAX ?>"
                  placeholder="Where are you going next, or what should the rest of us avoid?"></textarea>
      </section>

      <?php if (defined('RMT_INTERESTS')): ?>
        <fieldset class="onboard-interests">
          <legend class="hint">What you like doing when you get there</legend>
          <?php foreach (RMT_INTERESTS as $ik => $il): ?>
            <label class="chip-check"><input type="checkbox" name="interests[]" value="<?= e($ik) ?>"> <?= e($il) ?></label>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>
    </details>

    <div class="onboard-actions">
      <button class="btn btn-primary" type="submit">Save my profile</button>
      <a class="btn btn-ghost" href="<?= e(url('feed')) ?>">Skip for now</a>
    </div>
    <p class="hint">A trip you filled in is saved with it.</p>
  </form>
</div></section>
<script>
(function () {
  var name = document.getElementById('display_name');
  var bio = document.getElementById('bio');
  var city = document.getElementById('home_city');
  var meter = document.getElementById('profile-meter');
  if (!name || !bio || !city || !meter) return;
  function mark(id, on) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('done', on);
  }
  function paint() {
    var nameOn = name.value.trim() !== '';
    var bioOn = bio.value.trim().length >= 20;
    var cityOn = city.value.trim() !== '';
    mark('need-name', nameOn);
    mark('need-bio', bioOn);
    mark('need-city', cityOn);
    var n = (nameOn ? 1 : 0) + (bioOn ? 1 : 0) + (cityOn ? 1 : 0);
    meter.textContent = n === 3 ? 'Your profile is ready to save.' : (n + ' of 3 done');
    meter.classList.toggle('ready', n === 3);
  }
  ['input', 'change'].forEach(function (ev) {
    name.addEventListener(ev, paint);
    bio.addEventListener(ev, paint);
    city.addEventListener(ev, paint);
  });
  paint();
})();
</script>
