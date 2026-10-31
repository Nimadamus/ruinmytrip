<?php
/** /stay/new/{username}: ask a host. @var array $host @var ?string $why @var array $errors @var array $old */
$o = static fn(string $k, $def = '') => (string) ($old[$k] ?? $def);
$name = (string) ($host['display_name'] ?: '@' . $host['username']);
?>
<div class="wrap hl-sec" style="max-width:760px">
  <p class="crumbs"><a href="<?= e(url('hosts-locals')) ?>">Hosts &amp; Locals</a> / <a href="<?= e(url('u/' . $host['username'])) ?>"><?= e($name) ?></a> / Ask to stay</p>
  <div class="hl-person-top" style="margin-bottom:12px">
    <img class="avatar" src="<?= e(avatar_url($host['avatar_url'] ?? null)) ?>" alt="">
    <div><h1 class="hl-h2" style="font-size:1.6rem;margin:0">Ask to stay with <?= e($name) ?></h1>
      <span class="hint"><?= e((string) $host['dest_name']) ?><?= ($host['neighborhood'] ?? '') !== '' ? ', ' . e((string) $host['neighborhood']) . ' area' : '' ?>
        &middot; <?= e(RMT_HOST_SPACES[$host['space_type']] ?? '') ?> &middot; up to <?= (int) $host['max_guests'] ?> &middot; <?= (int) $host['min_nights'] ?> to <?= (int) $host['max_nights'] ?> nights</span></div>
  </div>
  <?php if (($host['house_rules'] ?? '') !== ''): ?><p class="hl-callout hl-callout-ok"><b>House rules:</b> <?= e((string) $host['house_rules']) ?></p><?php endif; ?>
  <p class="hl-callout">Staying is free: never send money to a host, for any reason. We do not check identities. Read their profile and references, keep messages on RuinMyTrip, and tell someone where you are staying. <a href="<?= e(url('hosts-locals/safety')) ?>">Safety guide</a>.</p>

  <?php if ($why): ?>
    <div class="hl-empty"><h3>You cannot send a request right now</h3><p><?= e($why) ?></p>
      <a class="btn btn-ghost" href="<?= e(url('stays')) ?>">Your stays</a></div>
  <?php else: ?>
  <form class="hl-form" method="post" action="<?= e(url('stay/new/' . $host['username'])) ?>">
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="hl-row">
      <div><label for="s-from">Arriving</label><input id="s-from" type="date" name="date_from" value="<?= e($o('date_from')) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" required></div>
      <div><label for="s-to">Leaving</label><input id="s-to" type="date" name="date_to" value="<?= e($o('date_to')) ?>" min="<?= e(date('Y-m-d', strtotime('+2 days'))) ?>" required></div>
      <div><label for="s-guests">Guests</label><input id="s-guests" type="number" name="guests" min="1" max="<?= (int) $host['max_guests'] ?>" value="<?= e($o('guests', '1')) ?>" required></div>
    </div>
    <label for="s-intro">Introduce yourself</label>
    <textarea id="s-intro" name="intro" minlength="<?= RMT_STAY_INTRO_MIN ?>" maxlength="<?= RMT_STAY_INTRO_MAX ?>" required
              placeholder="Who you are, why you are coming, and what you hope to do while you are there."><?= e($o('intro')) ?></textarea>
    <label class="hl-checks" style="display:block;margin-top:12px"><span style="display:flex;gap:10px;align-items:center;font-weight:500;min-height:44px">
      <input type="checkbox" name="agree" value="1" required> I have read the <a href="<?= e(url('hosts-locals/safety')) ?>">safety guide</a> and will respect the house rules.</span></label>
    <p style="margin:16px 0 0"><button class="btn btn-primary" type="submit">Send request</button></p>
    <p class="hl-hint">The host sees your profile and this introduction. Messages open only if they say yes.</p>
  </form>
  <?php endif; ?>
</div>
