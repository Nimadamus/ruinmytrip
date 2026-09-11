<?php
/**
 * What is publicly true about an account, where somebody is deciding whether to meet them.
 *
 * Facts with a link to check them, never a score. Drawn only when there are at least two of them:
 * a box containing the single line "joined this week" reads as an accusation rather than as
 * information, and a new account is not a suspect.
 *
 * @var int    $trustUserId
 * @var string $trustUsername
 * @var ?array $me
 */
$trustSignals = rmt_trust_signals((int) $trustUserId, $me ?? null);
if (!rmt_trust_worth_showing($trustSignals)) return;
?>
<div class="trust">
  <span class="trust-who">About @<?= e((string) $trustUsername) ?></span>
  <ul class="trust-list">
    <?php foreach ($trustSignals as $sig): ?>
      <li>
        <?php if (!empty($sig['href'])): ?>
          <a href="<?= e((string) $sig['href']) ?>"><?= e((string) $sig['label']) ?></a>
        <?php else: ?>
          <?= e((string) $sig['label']) ?>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="hint" style="margin:6px 0 0">Public facts anybody can check. They are not a guarantee
    about a person. <a href="<?= e(url('safety')) ?>">Safety guidance</a>.</p>
</div>
