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
$rmt_share_t = rawurlencode($shareText);
/* Each destination gets its own tagged copy of the same address, so a click arriving from a member's
   WhatsApp message is not filed under the same word as one from a post we published. The campaign
   defaults to the member share label; a page that is part of a campaign can pass $shareCampaign and
   keep the credit where it belongs. Nothing private is added: this is a public page address. */
$rmt_share_c = $shareCampaign ?? null;
$rmt_share_link = static function (string $channel) use ($shareUrl, $rmt_share_c): string {
    return function_exists('rmt_share_url') ? rmt_share_url($shareUrl, $channel, $rmt_share_c) : $shareUrl;
};
$rmt_share_copy = $rmt_share_link('other');
$rmt_share_enc  = static fn (string $channel): string => rawurlencode($rmt_share_link($channel));
?>
<?php /* One control that opens, rather than five buttons in a row. Five is a wall on a phone, and
         four of the five are for the one person in ten who wants that particular app. The native
         sheet, where the browser has it, is promoted out of the disclosure by share.js. */ ?>
<details class="share-row" data-share-url="<?= e($rmt_share_copy) ?>" data-share-text="<?= e($shareText) ?>">
  <summary class="btn btn-ghost btn-sm"><?= e($shareLabel ?? 'Share') ?></summary>
  <div class="share-opts">
  <button type="button" class="btn btn-ghost btn-sm js-share-native" hidden>Share…</button>
  <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($rmt_share_copy) ?>">Copy link</button>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://wa.me/?text=<?= $rmt_share_t ?>%20<?= $rmt_share_enc('whatsapp') ?>">WhatsApp</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://www.facebook.com/sharer/sharer.php?u=<?= $rmt_share_enc('facebook') ?>">Facebook</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://x.com/intent/tweet?text=<?= $rmt_share_t ?>&url=<?= $rmt_share_enc('x') ?>">X</a>
  <a class="btn btn-ghost btn-sm" rel="noopener nofollow" target="_blank"
     href="https://reddit.com/submit?title=<?= $rmt_share_t ?>&url=<?= $rmt_share_enc('reddit') ?>">Reddit</a>
  </div>
</details>
<?php /* Per-include state, and it must go: the next share control on the same page would otherwise
         inherit this one's campaign and its label. */ ?>
<?php unset($shareCampaign, $shareLabel); ?>
