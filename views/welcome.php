<?php /** @var array $dests @var array $saved @var array $me @var array $communities @var array $suggested @var array $popular */
/* Ordered by what the product is, not by what is easy to ask. The first thing on the page is the
   trip, because a member with dates posted is a member the whole site works for: they turn up in a
   city's people page, they appear in somebody's matches, and their own feed has something in it on
   day one. Everything after it is optional and says so.

   What this replaced: eighty four checkboxes, alphabetical, above the fold. That is a form, and a
   form is what somebody closes. */
$rmt_popular_ids = [];
foreach ($popular as $pp) $rmt_popular_ids[(int) $pp['id']] = true;
?>
<section class="block"><div class="wrap" style="max-width:720px">
  <p class="eyebrow">You're in</p>
  <h1 style="margin-bottom:.2rem">Two minutes, and people can find you</h1>
  <p class="muted" style="max-width:58ch">Destination and dates only, never a precise location. Every
    field here is optional, and you can change any of it later.</p>

  <form method="post" action="<?= e(url('welcome')) ?>" class="onboard">
    <?= csrf_field() ?>

    <section class="onboard-step">
      <h2><span class="onboard-n">1</span> Where are you going next?</h2>
      <p class="hint">This is the one that does the work. Post it and the travelers whose dates
        overlap yours can find you, in that city, on those days.</p>
      <label for="destination_id">City</label>
      <select id="destination_id" name="destination_id">
        <option value="">Not sure yet</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= (int) $dd['id'] ?>"><?= e($dd['name']) ?>, <?= e($dd['country']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="grid g-2" style="gap:12px;margin-top:10px">
        <div><label for="date_from">From</label><input type="date" id="date_from" name="date_from"></div>
        <div><label for="date_to">Until</label><input type="date" id="date_to" name="date_to"></div>
      </div>
      <input type="hidden" name="visibility" value="public">
    </section>

    <section class="onboard-step">
      <h2><span class="onboard-n">2</span> Where do you live?</h2>
      <p class="hint">City only. If we have a page for it you are listed as a local, which is the
        person a traveler heading there most wants to find.</p>
      <div class="grid g-2" style="gap:12px">
        <div>
          <label for="home_city">Your city</label>
          <input type="text" id="home_city" name="home_city" maxlength="80"
                 value="<?= e($me['home_city'] ?? '') ?>" placeholder="e.g. Lisbon, PT">
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
      <h2><span class="onboard-n">3</span> Cities you want to see</h2>
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

    <?php /* People before rooms: following somebody is the one action that makes the next screen
             worth opening, because it is the only one that puts other people's activity in it. */ ?>
    <?php if ($suggested): ?>
      <section class="onboard-step">
        <h2><span class="onboard-n">4</span> Follow a few travelers</h2>
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
      <section class="onboard-step">
        <h2><span class="onboard-n"><?= $suggested ? '5' : '4' ?></span> Join a community</h2>
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

    <section class="onboard-step">
      <h2><span class="onboard-n"><?= 4 + (int) (bool) $suggested + (int) (bool) $communities ?></span> Say something</h2>
      <p class="hint">A question counts. So does a warning about the last place that ruined your trip.</p>
      <textarea name="hello" rows="3" maxlength="<?= RMT_POST_MAX ?>"
                placeholder="Where are you going next, or what should the rest of us avoid?"></textarea>
    </section>

    <p style="margin:26px 0 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <button class="btn btn-primary" type="submit">Save and get started</button>
      <a class="btn btn-ghost" href="<?= e(url('feed')) ?>">Skip for now</a>
    </p>
  </form>
</div></section>
