<?php
/**
 * /sailing/{ship}/{date}: one ship, one departure day (app/sailings.php).
 *
 * @var ?array $me @var string $ship @var string $slug @var string $date @var string $label @var string $line
 * @var array $members @var array $mine @var array $others @var array $cards @var int $watchers @var bool $past
 */
$n = count($members);
$here = url(ltrim(rmt_sailing_path($ship, $date), '/'));
$postUrl = url('buddies/new?' . http_build_query(['type' => 'cruise', 'ship' => $ship, 'line' => $line, 'from' => $date, 'cta' => 'sailing_page']));
$joinUrl = url('register?return=' . rawurlencode('/buddies/new?' . http_build_query(['type' => 'cruise', 'ship' => $ship, 'line' => $line, 'from' => $date, 'cta' => 'sailing_page'])));
$bcBack = $here;
?>
<div class="wrap" style="max-width:860px">
  <p class="hl-crumbs" style="margin:18px 0 6px"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('buddies/cruise')) ?>">Cruise buddies</a> / <?= e($ship) ?></p>
  <h1 style="margin:0 0 6px"><?= e($ship) ?>, leaving <?= e($label) ?></h1>
  <p class="muted" style="margin:0 0 18px">
    <?php if ($n > 0): ?>
      <b><?= (int) $n ?></b> <?= $n === 1 ? 'traveler has' : 'travelers have' ?> posted this sailing<?= $watchers > 0 ? ', and ' . (int) $watchers . ' more ' . ($watchers === 1 ? 'is' : 'are') . ' watching for company' : '' ?>.
    <?php elseif ($watchers > 0): ?>
      <?= (int) $watchers ?> <?= $watchers === 1 ? 'traveler is' : 'travelers are' ?> watching this sailing for company. Post yours and they hear about it.
    <?php else: ?>
      You are early. Post this sailing and you are the one the next traveler on board finds.
    <?php endif; ?>
  </p>

  <?php if ($past): ?>
    <section class="card" style="margin:0 0 22px"><div class="card-body">
      <p style="margin:0">This sailing has left. <a href="<?= e(url('buddies/cruise')) ?>">Find people on an upcoming cruise</a>.</p>
    </div></section>
  <?php elseif ($me && $mine): ?>
    <section class="card" style="margin:0 0 22px"><div class="card-body">
      <?php if (count(array_unique(array_column($members, 'user_id'))) >= RMT_SAILING_CHAT_MIN): ?>
        <h2 style="margin:0 0 8px">Your sailing chat is open</h2>
        <p class="hint" style="margin:0 0 12px">Everyone who posted <?= e($ship) ?> leaving <?= e($label) ?> can talk here. Only they can read it.</p>
        <a class="btn btn-accent" href="<?= e(url(ltrim(rmt_sailing_path($ship, $date), '/') . '/chat')) ?>">Open the chat</a>
        <p class="hint" style="margin:12px 0 0"><a href="<?= e(url('buddy/' . (int) $mine[0]['id'])) ?>">Your post</a></p>
      <?php else: ?>
      <p style="margin:0">You are on this sailing. When somebody else posts it, you get a notification and an email, and a chat for everyone on the ship opens.
        <a href="<?= e(url('buddy/' . (int) $mine[0]['id'])) ?>">Your post</a></p>
      <?php endif; ?>
    </div></section>
  <?php elseif ($me): ?>
    <section class="card" style="margin:0 0 22px"><div class="card-body">
      <h2 style="margin:0 0 8px">On this sailing?</h2>
      <p class="hint" style="margin:0 0 12px">Post it in a minute. Anybody else on <?= e($ship) ?> that day can ask to connect, and nobody can message you until you say yes.</p>
      <a class="btn btn-accent" href="<?= e($postUrl) ?>">I'm on this sailing</a>
    </div></section>
  <?php else: ?>
    <section class="card match-alert" id="sailing-alert" style="margin:0 0 22px"><div class="card-body">
      <h2 style="margin:0 0 8px">Get told when someone joins this sailing</h2>
      <p class="hint" style="margin:0 0 12px">No account needed. One email to confirm, then we write only when a real traveler posts <?= e($ship) ?> leaving <?= e($label) ?>.</p>
      <form method="post" action="<?= e(url('sailing-alerts')) ?>" class="ma-form">
        <?= csrf_field() ?>
        <input type="hidden" name="ship" value="<?= e($ship) ?>">
        <input type="hidden" name="sail_date" value="<?= e($date) ?>">
        <input type="hidden" name="return" value="<?= e(rmt_sailing_path($ship, $date)) ?>">
        <div class="ma-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="ma-row">
          <label class="ma-email">Email<input type="email" name="email" required autocomplete="email" maxlength="254" placeholder="you@example.com"></label>
        </div>
        <button class="btn btn-accent" type="submit">Alert me</button>
      </form>
      <p class="hint" style="margin:12px 0 0">Rather be findable yourself? <a href="<?= e($joinUrl) ?>">Join free and post this sailing</a>.</p>
    </div></section>
  <?php endif; ?>

  <?php if ($cards): ?>
    <section style="margin:0 0 26px" aria-labelledby="sail-people">
      <h2 class="hl-h2" id="sail-people">Travelers on this sailing</h2>
      <div class="buddy-grid"><?php foreach ($cards as $bc): ?><?php include __DIR__ . '/_buddy_card.php'; ?><?php endforeach; ?></div>
    </section>
  <?php endif; ?>

  <section class="card" style="margin:0 0 26px"><div class="card-body">
    <h2 style="margin:0 0 8px">Check another sailing</h2>
    <form method="get" action="<?= e(url('sailing/find')) ?>" class="buddy-search">
      <div class="bs-row">
        <label><span>Ship</span><input type="text" name="ship" value="<?= e($ship) ?>" required minlength="3" maxlength="80"></label>
        <label><span>Leaves</span><input type="date" name="date" value="<?= e($date) ?>" required></label>
        <button class="btn btn-primary bs-go" type="submit">Find people on it</button>
      </div>
    </form>
  </div></section>
</div>
