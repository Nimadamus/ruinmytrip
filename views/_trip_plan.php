<?php
/**
 * The plan: what this traveler is actually doing, by day.
 *
 * @var array $t         the trip
 * @var array $planDays  from rmt_activities_by_day()
 * @var bool  $isOwner
 * @var bool  $canEdit  the owner, or somebody they invited to help plan it
 * @var ?array $me
 *
 * Two rules held this apart from every itinerary tool that nobody fills in twice. Adding a plan is
 * one line of typing and a day, with everything else behind a disclosure that most people never
 * open. And the same row is the post-trip answer: "did you go, was it any good", which is what
 * makes one traveler's Friday useful to the next traveler's Friday.
 */
$phaseNow = $phase ?? 'undated';
$catLabels = RMT_ACTIVITY_CATEGORIES;
$tripDays = [];
if (!empty($t['date_from']) && !empty($t['date_to'])) {
    for ($d = strtotime((string) $t['date_from']); $d <= strtotime((string) $t['date_to']); $d += 86400) {
        $tripDays[date('Y-m-d', $d)] = date('D j M', $d);
        if (count($tripDays) > 60) break;
    }
}
?>
<section class="plan" id="plan">
  <div class="plan-head">
    <h2><?= $phaseNow === 'past' ? 'What you did' : 'The plan' ?></h2>
    <?php if ($planDays): ?>
      <?php $rmt_planN = array_sum(array_map(static fn(array $d) => count($d['items']), $planDays)); ?>
      <span class="hint"><?= $rmt_planN ?> <?= $rmt_planN === 1 ? 'thing' : 'things' ?>
        <?= $phaseNow === 'past' ? 'they did' : 'planned' ?></span>
    <?php endif; ?>
  </div>

  <?php $canEdit = $canEdit ?? $isOwner; ?>
  <?php if (!$planDays && !$canEdit): ?>
    <p class="muted" style="margin:0 0 8px">Nothing planned here yet.
      <?php if (!empty($t['dest_slug'])): ?>
        <a href="<?= e(url('d/'.$t['dest_slug'])) ?>">See what other travelers are doing in <?= e((string) $t['dest_name']) ?></a>.
      <?php endif; ?></p>
  <?php endif; ?>

  <?php foreach ($planDays as $day): ?>
    <div class="plan-day">
      <h3><?= e($day['label']) ?></h3>
      <ul class="plan-list">
        <?php foreach ($day['items'] as $act): ?>
          <li class="plan-item<?= (int) $act['done'] === 1 ? ' done' : '' ?>">
            <span class="plan-when"><?= e((string) ($act['start_time'] ?? '')) ?></span>
            <div class="plan-what">
              <?php /* Every plan has a page now: who is coming, how to ask, where everybody is
                       meeting, and the thread for sorting it out. The title is the way in. */ ?>
              <b><a href="<?= e(url('activity/'.(int) $act['id'])) ?>"><?= e((string) $act['title']) ?></a></b>
              <?php if (!empty($act['cancelled_at'])): ?> <span class="chip">Cancelled</span><?php endif; ?>
              <?php /* The category line only when it says something. Printing "Something else"
                       under every quickly added plan is noise, and the quick path is the one
                       almost everybody uses. */ ?>
              <?php
                $rmt_meta = [];
                /* A category we no longer have is a gap, not a warning. Data outlives the list
                   of labels that describes it, and a page should not break because a row predates
                   a rename. */
                $rmt_cat = (string) ($act['category'] ?? 'other');
                if ($rmt_cat !== 'other' && isset($catLabels[$rmt_cat])) $rmt_meta[] = e($catLabels[$rmt_cat]);
                if (!empty($act['place_name'])) {
                    $rmt_meta[] = '<a href="' . e(url('p/'.$act['place_slug'])) . '">' . e((string) $act['place_name']) . '</a>';
                } elseif (!empty($act['location_text'])) {
                    $rmt_meta[] = e((string) $act['location_text']);
                }
                if (($act['visibility'] ?? 'trip') === 'private') $rmt_meta[] = '<span class="chip">Only you</span>';
                /* On a shared trip, who put this line on it. Said only when it is somebody other
                   than the traveler whose trip it is, because "@maya added it" on every row of
                   Maya's own itinerary is noise. */
                if (!empty($act['author_username']) && (int) $act['user_id'] !== (int) $t['user_id']) {
                    $rmt_meta[] = 'added by <a href="' . e(url('u/'.$act['author_username'])) . '">@'
                        . e((string) $act['author_username']) . '</a>';
                }
              ?>
              <?php if ($rmt_meta): ?>
                <span class="hint"><?= implode(' &middot; ', $rmt_meta) ?></span>
              <?php endif; ?>
              <?php if (!empty($act['notes'])): ?><p class="plan-note"><?= e((string) $act['notes']) ?></p><?php endif; ?>
              <?php if (!empty($act['link'])): ?>
                <p class="plan-note"><a href="<?= e((string) $act['link']) ?>" rel="noopener nofollow" target="_blank">Link</a></p>
              <?php endif; ?>

              <?php /* How it went, once it has. The plan becomes the recommendation. */ ?>
              <?php if ((int) $act['done'] === 1): ?>
                <p class="plan-verdict">
                  <?php if (!empty($act['rating'])): ?><span class="stars"><?= str_repeat('&#9733;', (int) $act['rating']) ?></span><?php endif; ?>
                  <?php if ($act['recommend'] !== null): ?>
                    <?= ((int) $act['recommend'] === 1) ? 'Would send somebody else' : 'Would not send somebody else' ?>
                  <?php elseif (empty($act['rating'])): ?>Done<?php endif; ?>
                </p>
              <?php endif; ?>

              <?php /* Who else is coming, and the way to be one of them. */ ?>
              <?php $going = (int) ($act['going_count'] ?? 0); $curious = (int) ($act['interested_count'] ?? 0); ?>
              <?php if ($going || $curious): ?>
                <p class="plan-note"><?php
                  $bits = [];
                  if ($going) $bits[] = $going . ' ' . ($going === 1 ? 'person is' : 'people are') . ' coming';
                  if ($curious) $bits[] = $curious . ' interested';
                  echo e(implode(' · ', $bits)); ?></p>
              <?php endif; ?>

              <?php if (!$canEdit && $me && ($act['join_mode'] ?? 'no') !== 'no'): ?>
                <?php $state = rmt_activity_join_state((int) $act['id'], $me); ?>
                <form class="plan-join" method="post" action="<?= e(url('activity/'.(int) $act['id'].'/join')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="state" value="<?= ($act['join_mode'] === 'open') ? 'going' : 'interested' ?>">
                  <button class="btn btn-ghost btn-sm">
                    <?php if ($state === 'going'): ?>You are coming
                    <?php elseif ($state === 'interested'): ?>You said interested
                    <?php elseif ($act['join_mode'] === 'open'): ?>I am going too
                    <?php else: ?>Ask to join<?php endif; ?>
                  </button>
                </form>
              <?php elseif (!$me && ($act['join_mode'] ?? 'no') !== 'no'): ?>
                <p class="plan-note"><a href="<?= e(url('register?return=' . rawurlencode('/trip/'.(int) $t['id']))) ?>">Join to say you are coming</a></p>
              <?php endif; ?>
            </div>

            <?php /* Answering for a plan, and deleting it, belong to whoever put it on the trip.
                     On a shared trip that is not always the person whose trip it is, and speaking
                     for somebody else about whether a dinner was any good is putting words in
                     their mouth. */ ?>
            <?php if ($me && (int) ($act['user_id'] ?? 0) === (int) $me['id']): ?>
              <div class="plan-own">
                <?php if ((int) $act['done'] === 0 && $phaseNow !== 'upcoming'): ?>
                  <details class="plan-didyou">
                    <summary>Did you go?</summary>
                    <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/done')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="done" value="1">
                      <label class="sr-only" for="r<?= (int) $act['id'] ?>">Rating</label>
                      <select id="r<?= (int) $act['id'] ?>" name="rating">
                        <option value="">Rate it</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('&#9733;', $i) ?></option><?php endfor; ?>
                      </select>
                      <select name="recommend">
                        <option value="">Would you send somebody?</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                      </select>
                      <button class="btn btn-primary btn-sm">Save</button>
                    </form>
                  </details>
                <?php endif; ?>
                <form method="post" action="<?= e(url('activity/'.(int) $act['id'].'/delete')) ?>"
                      onsubmit="return confirm('Remove this from the plan?');">
                  <?= csrf_field() ?>
                  <button class="act" title="Remove" aria-label="Remove this plan">&times;</button>
                </form>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>

  <?php if ($canEdit): ?>
    <?php /* One line and a day. Everything else is behind the disclosure, and most of it is never
             opened, which is exactly the intention: adding "dinner in Alfama" should cost one
             sentence of typing.

             On a trip that has already happened the same form is still useful, because people
             write down what they actually did afterwards, but it stops being the loud thing on
             the page: a finished trip asking "What is the plan?" reads as a product that has not
             noticed the trip is over. */ ?>
    <?php if ($phaseNow === 'past'): ?>
      <details class="plan-add-later">
        <summary>Add something you did</summary>
    <?php endif; ?>
    <form class="plan-add" method="post" action="<?= e(url('trip/'.(int) $t['id'].'/activity')) ?>">
      <?= csrf_field() ?>
      <div class="plan-add-row">
        <label class="sr-only" for="plan-title">What is the plan?</label>
        <input type="text" id="plan-title" name="title" maxlength="160" required
               placeholder="Dinner in Alfama">
        <?php if ($tripDays): ?>
          <label class="sr-only" for="plan-day">Day</label>
          <select id="plan-day" name="day">
            <option value="">Any day</option>
            <?php foreach ($tripDays as $val => $label): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm">Add</button>
      </div>
      <details class="plan-more">
        <summary>Time, place, notes, who can come</summary>
        <div class="plan-more-grid">
          <div>
            <label for="plan-time">Time</label>
            <input type="time" id="plan-time" name="start_time">
          </div>
          <div>
            <label for="plan-cat">What kind of thing</label>
            <select id="plan-cat" name="category">
              <?php foreach (RMT_ACTIVITY_CATEGORIES as $k => $label): ?>
                <option value="<?= e($k) ?>"<?= $k === 'other' ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="plan-where">Where</label>
            <input type="text" id="plan-where" name="location_text" maxlength="120"
                   placeholder="Alfama, or a place by name" autocomplete="off"
                   list="plan-places" data-place-suggest
                   data-dest="<?= (int) ($t['destination_id'] ?? 0) ?>">
            <?php /* The datalist is filled by the server as somebody types, from the places this
                     site holds for THIS city, matching aliases as well so "Tile Museum" finds the
                     Museu Nacional do Azulejo. Picking one attaches the plan to that place's page,
                     which is how a restaurant stops being a word.

                     It was a fixed list of sixty names, which is fine for a city with sixty places
                     and useless for one with four hundred: the place somebody meant was simply not
                     in it. With no JavaScript the field is still a plain text box, and typing an
                     exact name still matches on the server. */ ?>
            <datalist id="plan-places"></datalist>
          </div>
          <div>
            <label for="plan-join">Can anybody come?</label>
            <select id="plan-join" name="join_mode">
              <?php foreach (RMT_ACTIVITY_JOIN_MODES as $k => $label): ?>
                <option value="<?= e($k) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="plan-vis">Who can see it</label>
            <select id="plan-vis" name="visibility">
              <option value="trip">Same as the trip</option>
              <option value="private">Only me</option>
            </select>
          </div>
          <div>
            <label for="plan-link">Link</label>
            <input type="url" id="plan-link" name="link" placeholder="https://…">
          </div>
        </div>
        <label for="plan-notes">Notes</label>
        <textarea id="plan-notes" name="notes" rows="2" maxlength="2000"
                  placeholder="Booked for four, ask for the table by the window."></textarea>
      </details>
    </form>
    <?php if ($phaseNow === 'past'): ?></details><?php endif; ?>
  <?php endif; ?>
</section>
