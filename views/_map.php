<?php
/**
 * A map of things somebody published.
 *
 * What is on it: places this site holds, and plans whose owner chose to make them visible. What is
 * never on it, and must never be added: where a person is now. A plan that says "dinner in Alfama
 * on Friday at eight" is a published intention; a dot that follows somebody around a city is
 * surveillance, and the difference is the whole reason this file says so out loud.
 *
 * Leaflet and OpenStreetMap tiles. No key, no account, and attribution printed rather than implied,
 * because the tiles and the place data are both used under a licence that requires it.
 *
 * @var list<array{lat:float,lng:float,label:string,href:?string,meta:?string,group:?string}> $mapPoints
 * @var string $mapId
 * @var ?string $mapTitle
 */
$mapPoints = array_values(array_filter($mapPoints ?? [], static fn(array $p): bool =>
    isset($p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng'])));
if (!$mapPoints) return;
$mapId = $mapId ?? ('map-' . substr(sha1(serialize($mapPoints)), 0, 8));
?>
<section class="map-block">
  <?php if (!empty($mapTitle)): ?>
    <div class="map-head"><h2><?= e((string) $mapTitle) ?></h2>
      <span class="hint"><?= count($mapPoints) ?> <?= count($mapPoints) === 1 ? 'place' : 'places' ?></span></div>
  <?php endif; ?>
  <div class="map-canvas" id="<?= e($mapId) ?>"
       data-points="<?= e(json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>"
       role="img" aria-label="Map of <?= count($mapPoints) ?> <?= count($mapPoints) === 1 ? 'place' : 'places' ?>"></div>
  <p class="hint map-credit">Map data and tiles from
    <a href="https://www.openstreetmap.org/copyright" rel="nofollow noopener" target="_blank">OpenStreetMap</a>
    contributors. Nothing here is anybody's current location.</p>
</section>

<?php /* Loaded once per page even if two maps are on it. The library is only fetched when a map is
         actually rendered, which keeps it off every other page on the site. */ ?>
<?php if (empty($GLOBALS['rmt_map_loaded'])): $GLOBALS['rmt_map_loaded'] = true; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
        integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
        crossorigin="anonymous" referrerpolicy="no-referrer">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
          integrity="sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g=="
          crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
  <script defer src="<?= e(rmt_asset('assets/js/map.js')) ?>"></script>
<?php endif; ?>
