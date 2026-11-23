<?php
/**
 * What happened to travelers (app/traveler_stories.php). Shown only when an editor has published at
 * least one story for this city; an empty section is never drawn.
 *
 * @var array $tsTopics topics to show, e.g. ['warnings:amsterdam-netherlands', 'tax:amsterdam']
 * @var string $tsCity city name
 */
$tsRows = function_exists('rmt_stories_for') ? rmt_stories_for($tsTopics ?? []) : [];
if (!$tsRows) return;
?>
<section class="card" id="traveler-stories" style="margin:18px 0;border-left:4px solid #b45309"><div class="card-body">
  <h2 class="h-card" style="margin:0 0 4px">What happened to travelers in <?= e((string) ($tsCity ?? '')) ?></h2>
  <p class="hint" style="margin:0 0 12px">Sent in by travelers and read by our editor before publishing. One person's account, not an official finding.</p>
  <?php foreach ($tsRows as $ts): ?>
    <blockquote style="margin:0 0 12px;padding:0 0 0 12px;border-left:2px solid #e5e7eb">
      <p style="margin:0 0 4px;white-space:pre-wrap"><?= e((string) $ts['body']) ?></p>
      <p class="hint" style="margin:0">A traveler<?= !empty($ts['when_text']) ? ', ' . e((string) $ts['when_text']) : '' ?></p>
    </blockquote>
  <?php endforeach; ?>
</div></section>
