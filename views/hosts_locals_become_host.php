<?php
/**
 * /hosts-locals/become-a-host: what hosting will be, and the application.
 *
 * @var ?array $me @var ?array $host @var array $errors @var array $old @var array $dests
 */
$o = static fn(string $k, $def = '') => (string) ($old[$k] ?? $def);
$canApply = $me && !empty($me['email_verified_at']) && can_host_meetups($me);
$statusLine = [
    'pending'   => 'Your application is with our moderators. Nobody else can see it yet.',
    'approved'  => 'Approved. You appear as "interested in hosting" on your city page. Changing the description sends it back for review.',
    'paused'    => 'Paused. Your host profile is hidden until you resume it.',
    'rejected'  => 'Not approved. You can change it and apply again.',
    'withdrawn' => 'Withdrawn. You can apply again any time.',
];
?>
<section class="hl-hero">
  <div class="wrap hl-hero-in">
    <p class="hl-crumbs"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / Become a host</p>
    <p class="hl-kicker">Stay With a Local</p>
    <h1>Host travelers in your city, for free</h1>
    <p class="hl-lede">Offer a spare room or a couch to travelers who want to see your city through you. Applications are open now. Stay requests open once identity checks are live.</p>
  </div>
</section>

<div class="wrap hl-sec">
  <div class="hl-trust" style="margin-bottom:26px">
    <div><h3>Always free</h3><p>No payments, no commission and no paid listings. Hosting is hospitality, not a rental.</p></div>
    <div><h3>Reviewed first</h3><p>A moderator reads every application before it appears anywhere. You can pause or withdraw it any time.</p></div>
    <div><h3>Your address stays yours</h3><p>A host profile holds a city and a neighbourhood at most. There is no address field, so none can be shown.</p></div>
    <div><h3>You choose every guest</h3><p>When stays open, every request will be yours to accept or decline, and messages open only after you say yes.</p></div>
  </div>

  <?php if ($host): ?>
    <p class="hl-callout hl-callout-ok"><b>Status: <?= e(ucfirst((string) $host['status'])) ?>.</b> <?= e($statusLine[$host['status']] ?? '') ?>
      <a href="<?= e(url('hosts-locals/me#host')) ?>">Manage hosting</a>.</p>
  <?php endif; ?>

  <?php if (!$me): ?>
    <div class="hl-empty">
      <h3>Create your profile to apply</h3>
      <p>Hosting is for members 18 and over with a confirmed email address.</p>
      <a class="btn btn-primary" href="<?= e(url('register?return=%2Fhosts-locals%2Fbecome-a-host')) ?>">Join free</a>
      <a class="btn btn-ghost" href="<?= e(url('login?return=%2Fhosts-locals%2Fbecome-a-host')) ?>">Sign in</a>
    </div>
  <?php elseif (empty($me['email_verified_at'])): ?>
    <div class="hl-empty"><h3>Confirm your email first</h3><p>We sent you a link when you joined. Hosting needs a confirmed address.</p>
      <a class="btn btn-primary" href="<?= e(url('verify-email')) ?>">Confirm my email</a></div>
  <?php elseif (!can_host_meetups($me)): ?>
    <div class="hl-empty"><h3>Hosting is 18+</h3><p>Add your date of birth once. It stays private.</p>
      <a class="btn btn-primary" href="<?= e(url('account/age?return=%2Fhosts-locals%2Fbecome-a-host')) ?>">Add my date of birth</a></div>
  <?php else: ?>
  <form class="hl-form" method="post" action="<?= e(url('hosts-locals/become-a-host')) ?>">
    <?= csrf_field() ?>
    <h2 class="hl-h2" style="font-size:1.4rem"><?= $host ? 'Your host profile' : 'Apply to host' ?></h2>
    <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <fieldset><legend>Where</legend>
      <label for="h-dest">City</label>
      <select id="h-dest" name="destination_id" required>
        <option value="">Pick your city</option>
        <?php foreach ($dests as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $o('destination_id') === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name'] . ', ' . $d['country']) ?></option><?php endforeach; ?>
      </select>
      <label for="h-hood">Neighbourhood <span class="hint">(optional, never a street or a number)</span></label>
      <input id="h-hood" type="text" name="neighborhood" maxlength="80" value="<?= e($o('neighborhood')) ?>" placeholder="Alfama, Shibuya, Roma Norte">
    </fieldset>

    <fieldset><legend>The space</legend>
      <label for="h-space">What a guest would have</label>
      <select id="h-space" name="space_type" required>
        <option value="">Choose one</option>
        <?php foreach (RMT_HOST_SPACES as $k => $l): ?><option value="<?= $k ?>"<?= $o('space_type') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
      <div class="hl-row">
        <div><label for="h-guests">Guests at a time</label><input id="h-guests" type="number" name="max_guests" min="1" max="6" value="<?= e($o('max_guests', '1')) ?>" required></div>
        <div><label for="h-min">Shortest stay (nights)</label><input id="h-min" type="number" name="min_nights" min="1" max="14" value="<?= e($o('min_nights', '1')) ?>" required></div>
        <div><label for="h-max">Longest stay (nights)</label><input id="h-max" type="number" name="max_nights" min="1" max="30" value="<?= e($o('max_nights', '3')) ?>" required></div>
      </div>
      <label for="h-about">Describe the space and your home</label>
      <textarea id="h-about" name="about_space" maxlength="1500" required minlength="40"><?= e($o('about_space')) ?></textarea>
      <label for="h-when">When you could host <span class="hint">(optional)</span></label>
      <input id="h-when" type="text" name="available_note" maxlength="160" value="<?= e($o('available_note')) ?>" placeholder="Weekends, most of the summer, not in December">
    </fieldset>

    <fieldset><legend>House rules</legend>
      <div class="hl-row" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div><label for="h-pets">Pets</label><select id="h-pets" name="pets" required><option value="">Choose</option>
          <?php foreach (RMT_HOST_PETS as $k => $l): ?><option value="<?= $k ?>"<?= $o('pets') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label for="h-smoke">Smoking</label><select id="h-smoke" name="smoking" required><option value="">Choose</option>
          <?php foreach (RMT_HOST_SMOKING as $k => $l): ?><option value="<?= $k ?>"<?= $o('smoking') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <label for="h-rules">Anything else a guest should know <span class="hint">(optional)</span></label>
      <textarea id="h-rules" name="house_rules" maxlength="1000" placeholder="Quiet after 11, shoes off, a cat that will want your attention"><?= e($o('house_rules')) ?></textarea>
      <label for="h-access">Accessibility <span class="hint">(optional)</span></label>
      <input id="h-access" type="text" name="accessibility" maxlength="500" value="<?= e($o('accessibility')) ?>" placeholder="Third floor, no lift; step free shower">
    </fieldset>

    <label class="hl-checks" style="display:block"><span style="display:flex;gap:10px;align-items:center;font-weight:500;min-height:44px">
      <input type="checkbox" name="agree" value="1" required> I have read the <a href="<?= e(url('hosts-locals/safety')) ?>">safety guide</a> and <a href="<?= e(url('guidelines')) ?>">community guidelines</a>, and I understand hosting is free and that stay requests are not open yet.</span></label>
    <p style="margin:16px 0 0"><button class="btn btn-primary" type="submit"><?= $host ? 'Save and send for review' : 'Send my application' ?></button></p>
    <p class="hl-hint">Your languages, interests and photo come from <a href="<?= e(url('u/' . $me['username'] . '/edit')) ?>">your profile</a>.</p>
  </form>
  <?php endif; ?>
</div>
