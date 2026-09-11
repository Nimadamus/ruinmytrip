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
/* Namespaced on purpose. This partial is included in the middle of pages that already hold their
   own $t (the trip) and $u, and a bare $t here overwrote the trip with a URL-encoded string: every
   trip page then died at the next line that read $t['id'], losing its comments and everything under
   them. Two-letter locals in an included template are shared state. */
$rmt_share_u = rawurlencode($shareUrl);
$rmt_share_t = rawurlencode($shareText);
?>
<?php /* One control that opens, rather than five buttons in a row. Five is a wall on a phone, and
         four of the five are for the one person in ten who wants that particular app. The native
         sheet, where the browser has it, is promoted out of the disclosure by share.js. */ ?>
<details class="share-row" data-share-url="<?= e($shareUrl) ?>" data-share-text="<?= e($shareText) ?>">
  <summary class="btn btn-ghost btn-sm">Share</summary>
  <div class="share-opts">
  <button type="button" class="btn btn-ghost btn-sm js-share-native" hidden>Share…</button>
  <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($shareUrl) ?>">Copy link</button>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://wa.me/?text=<?= $rmt_share_t ?>%20<?= $rmt_share_u ?>">WhatsApp</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://x.com/intent/tweet?text=<?= $rmt_share_t ?>&url=<?= $rmt_share_u ?>">X</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://reddit.com/submit?title=<?= $rmt_share_t ?>&url=<?= $rmt_share_u ?>">Reddit</a>
  </div>
</details>
