<?php
/**
 * One link from a place or review page to its city's sourced "Scams and traps" section
 * (views/_city_scams.php, anchor #scams). Renders nothing for a city with no sourced entry, so
 * no page links to a section that is not there. Adds a link only: no robots, canonical or
 * sitemap effect.
 * @var string $destSlug
 * @var string $destName
 * @var string $scamsLinkClass button classes for the surrounding group
 * @var string $scamsLinkSrc   data-cta value, so /admin/funnel can count clicks per surface
 */
$scamsCity = ($destSlug ?? '') !== '' && function_exists('rmt_city_scams') ? rmt_city_scams((string) $destSlug) : null;
if (!$scamsCity || empty($scamsCity['items'])) return;
$scamsN = count($scamsCity['items']);
?>
<a class="<?= e($scamsLinkClass ?? 'btn btn-ghost btn-sm') ?>" data-cta="<?= e($scamsLinkSrc ?? 'scams_link') ?>"
   href="<?= e(url('d/' . $destSlug) . '#scams') ?>"><?= $scamsN ?> scam<?= $scamsN === 1 ? '' : 's' ?> to know in <?= e((string) $destName) ?></a>
