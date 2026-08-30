<?php
/**
 * Share this page.
 *
 * @var string $shareUrl  absolute URL
 * @var string $shareText what the sharer is saying, usually the title
 *
 * A young site is not found by search engines first, it is found by one person sending a link to
 * another. Every page worth reading needs a two-second way to do that, and "copy the address bar"
 * is not it on a phone. Native share where the browser has it, three destinations where it does
 * not, and a copy button that always works.
 */
$shareUrl ??= url();
$shareText ??= 'RuinMyTrip';
$u = rawurlencode($shareUrl);
$t = rawurlencode($shareText);
?>
<div class="share-row" data-share-url="<?= e($shareUrl) ?>" data-share-text="<?= e($shareText) ?>"
     style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:18px 0">
  <span class="hint">Share</span>
  <button type="button" class="btn btn-ghost btn-sm js-share-native" hidden>Share…</button>
  <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($shareUrl) ?>">Copy link</button>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://wa.me/?text=<?= $t ?>%20<?= $u ?>">WhatsApp</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://x.com/intent/tweet?text=<?= $t ?>&url=<?= $u ?>">X</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://reddit.com/submit?title=<?= $t ?>&url=<?= $u ?>">Reddit</a>
</div>
