<?php
/**
 * A grid of photographs.
 *
 * @var list<array{url:string,href?:string,caption?:string,kind?:string,id?:int}> $gridPhotos
 * @var bool $gridLead  when true the first photo is drawn at double size, which is what makes a
 *                      wall look composed rather than tiled
 *
 * Every cell links to the photo's own page rather than to the parent, because the thing somebody
 * clicked is the thing they want to see. Captions appear on hover and are always in the alt text,
 * so they are not decoration a screen reader misses.
 */
$gridPhotos = $gridPhotos ?? [];
$gridLead = $gridLead ?? false;
if ($gridPhotos):
?>
<div class="photo-grid<?= $gridLead ? ' photo-grid-lead' : '' ?>">
  <?php foreach ($gridPhotos as $gp):
    $gpHref = (string) ($gp['href'] ?? (isset($gp['kind'], $gp['id'])
      ? url(ltrim(rmt_photo_path((string) $gp['kind'], (int) $gp['id']), '/'))
      : ''));
    $gpCap = trim((string) ($gp['caption'] ?? ''));
  ?>
    <?php if ($gpHref !== ''): ?><a class="photo-cell" href="<?= e($gpHref) ?>"><?php
      else: ?><figure class="photo-cell" style="margin:0"><?php endif; ?>
      <img loading="lazy" src="<?= e(abs_url((string) $gp['url'])) ?>"
           alt="<?= e($gpCap !== '' ? $gpCap : 'Traveler photo') ?>">
      <?php if ($gpCap !== ''): ?><figcaption><?= e(rmt_photo_trim($gpCap, 90)) ?></figcaption><?php endif; ?>
    <?php if ($gpHref !== ''): ?></a><?php else: ?></figure><?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>
