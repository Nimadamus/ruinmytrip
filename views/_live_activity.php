<?php
/** @var array $laItems  from rmt_live_activity()  @var string|null $laTitle  @var string|null $laCity slug, for the CTAs
 *
 * The stream of what is happening, as cards. Every card says what kind of thing it is, and every
 * card that is ours (research, team questions) says so in the byline: nothing here is dressed up
 * as a member.
 */
$laItems = $laItems ?? [];
if (!$laItems) return;
$laTitle = $laTitle ?? 'Happening on RuinMyTrip';
$laQ = !empty($laCity) ? ['d' => (string) $laCity] : [];
$laAsk = !empty($laCity) ? url('d/' . $laCity) . '#city-ask' : url('talk') . '#say';
?>
<section class="la block-tight">
  <div class="la-head">
    <h2 style="margin:0"><?= e($laTitle) ?></h2>
    <p class="la-acts">
      <a class="btn btn-primary btn-sm" data-cta="feed_plan" href="<?= e(url('plan?' . http_build_query($laQ + ['cta' => 'feed_plan']))) ?>">Post your trip</a>
      <a class="btn btn-ghost btn-sm" data-cta="cta_buddy" href="<?= e(url('plan?' . http_build_query($laQ + ['buddy' => '1', 'cta' => 'cta_buddy']))) ?>">Find a travel buddy</a>
      <a class="btn btn-ghost btn-sm" data-cta="cta_ask" href="<?= e($laAsk) ?>">Ask travelers</a>
    </p>
  </div>
  <ul class="la-grid">
    <?php foreach ($laItems as $it): ?>
      <li class="la-card la-<?= e((string) $it['kind']) ?><?= !empty($it['editorial']) ? ' la-ours' : '' ?>">
        <a href="<?= e((string) $it['href']) ?>">
          <span class="la-label"><?= e((string) $it['label']) ?></span>
          <span class="la-title"><?= e((string) $it['title']) ?></span>
          <?php if ((string) $it['meta'] !== ''): ?><span class="hint"><?= e((string) $it['meta']) ?></span><?php endif; ?>
          <span class="la-who"><?= e((string) $it['who']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php unset($laItems, $laTitle, $laQ, $laAsk, $laCity); ?>
