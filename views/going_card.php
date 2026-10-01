<?php
/** @var array $c @var ?array $me @var bool $mine @var string $line @var string $find @var array $share @var string $tooUrl
 *
 * An "I'm going" card (app/going_cards.php). For whoever it was sent to: say me too, or see who else
 * is going. For the traveler who made it: the share buttons, and how many people opened it.
 */
$heroSet = function_exists('rmt_media_srcset') ? rmt_media_srcset($c['hero_url'] ?? null) : '';
$city = (string) $c['dest_name'];
?>
<div class="wrap" style="max-width:860px">
  <article class="going-card">
    <?php if (!empty($c['hero_url'])): ?>
      <img class="going-bg" src="<?= e(abs_url((string) $c['hero_url'])) ?>"<?php if ($heroSet !== ''): ?> srcset="<?= e($heroSet) ?>" sizes="(max-width: 900px) 100vw, 860px"<?php endif; ?> fetchpriority="high" alt="<?= e($city . ', ' . $c['country']) ?>">
    <?php endif; ?>
    <div class="going-body">
      <p class="going-brand">RuinMyTrip</p>
      <?php if ($c['who'] !== ''): ?>
        <p class="going-who"><img class="avatar" src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt="" width="36" height="36"> <?= e($c['who']) ?></p>
      <?php endif; ?>
      <h1><?= e($line) ?></h1>
      <p class="going-q">Who else is going?</p>
      <div class="going-ctas">
        <?php if ($mine): ?>
          <a class="btn btn-accent" data-cta="going_find" href="<?= e($find) ?>">See who overlaps</a>
        <?php elseif ($me): ?>
          <a class="btn btn-accent" data-cta="going_too" href="<?= e($tooUrl) ?>">I'm going too</a>
          <a class="btn btn-light" data-cta="going_find" href="<?= e($find) ?>">Find travelers</a>
        <?php else: ?>
          <a class="btn btn-accent" data-cta="going_too" href="#match-alert">I'm going too</a>
          <a class="btn btn-light" data-cta="going_find" href="<?= e($find) ?>">Find travelers</a>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <?php if ($mine): ?>
    <section class="card" style="margin:18px 0"><div class="card-body">
      <h2 style="margin:0 0 6px">Send it where your people are</h2>
      <p class="hint" style="margin:0 0 12px">The group chat for the trip, the friends who said "we should go one year", the forum thread. Anyone who says me too is matched with you.</p>
      <?php if ((int) $c['visits'] > 0): ?><p><b><?= (int) $c['visits'] ?></b> <?= (int) $c['visits'] === 1 ? 'person has' : 'people have' ?> opened your card.</p><?php endif; ?>
    </div></section>
  <?php endif; ?>

  <div class="share-row" data-share-url="<?= e($share['copy']) ?>" data-share-text="<?= e($line . ' Who else is going?') ?>">
    <button type="button" class="btn btn-accent share-native" data-cta="share_native" hidden>Share</button>
    <a class="btn btn-ghost" data-cta="share_whatsapp" href="<?= e($share['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <a class="btn btn-ghost" data-cta="share_facebook" href="<?= e($share['facebook']) ?>" target="_blank" rel="noopener">Facebook</a>
    <a class="btn btn-ghost" data-cta="share_x" href="<?= e($share['x']) ?>" target="_blank" rel="noopener">X</a>
    <a class="btn btn-ghost" data-cta="share_telegram" href="<?= e($share['telegram']) ?>" target="_blank" rel="noopener">Telegram</a>
    <a class="btn btn-ghost" data-cta="share_email" href="<?= e($share['email']) ?>">Email</a>
    <button type="button" class="btn btn-ghost share-copy" data-cta="share_copy">Copy link</button>
  </div>

  <?php if (!$mine && !$me): ?>
    <?php $ma = ['dest' => ['id' => (int) $c['destination_id'], 'name' => $city], 'occ' => (string) ($c['occasion'] ?? ''),
                 'from' => $c['date_from'], 'to' => $c['date_to'], 'source' => 'share', 'return' => '/im-going/' . $c['code'],
                 'heading' => 'Going too? Add your email and dates and you are matched.'];
          include __DIR__ . '/_match_alert.php'; ?>
  <?php endif; ?>
  <p class="hint" style="margin:14px 0 28px">RuinMyTrip matches travelers going to the same place on the same days. City and dates only; nobody can message you until you say yes.
    <a href="<?= e($find) ?>">More about <?= e($c['occ'] ? $c['occ']['short'] . ' in ' . $city : $city) ?></a></p>
</div>
<script src="<?= e(rmt_asset('assets/js/share.js')) ?>" defer></script>
