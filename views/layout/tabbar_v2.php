<?php
/* The redesigned phone bar (surface "nav"): Home, Explore, +, My map, You. Meet and Talk are in the
   menu sheet until they have people in them; the two tabs a stranger opened most were empty rooms.
   The + is the thing we want people to do: rate the place they are looking at, or any place. */
$tb2Me = current_user();
$tb2Path = parse_url(rmt_current_url(), PHP_URL_PATH) ?: '/';
$tb2Rate = url('review/new?src=home');
if (preg_match('#^/p/([a-z0-9\-]+)#', $tb2Path, $tb2m) && ($tb2Place = rmt_place_by_slug($tb2m[1]))) {
    $tb2Rate = url('review/new?place=' . (int) $tb2Place['id'] . '&src=place');
} elseif (preg_match('#^/d/([a-z0-9\-]+)#', $tb2Path, $tb2m) && ($tb2Dest = dest_by_slug($tb2m[1]))) {
    $tb2Rate = url('review/new?destination=' . (int) $tb2Dest['id'] . '&src=destination');
}
$tb2On = static fn(bool $on): string => $on ? ' class="on" aria-current="page"' : '';
?>
<nav class="v2-tabbar" aria-label="Primary mobile">
  <a href="<?= e(url()) ?>"<?= $tb2On($tb2Path === '/' || $tb2Path === '/feed') ?>>
    <span class="v2-tb-ico" aria-hidden="true">⌂</span><span>Home</span></a>
  <a href="<?= e(url('explore')) ?>"<?= $tb2On(str_starts_with($tb2Path, '/explore') || str_starts_with($tb2Path, '/d/') || str_starts_with($tb2Path, '/p/')) ?>>
    <span class="v2-tb-ico" aria-hidden="true">⚲</span><span>Explore</span></a>
  <a class="v2-tb-plus" href="<?= e($tb2Rate) ?>" data-cta="nav_rate" aria-label="Rate a place">
    <span aria-hidden="true">+</span></a>
  <a href="<?= e(url('map')) ?>"<?= $tb2On(str_starts_with($tb2Path, '/map')) ?>>
    <span class="v2-tb-ico" aria-hidden="true">◉</span><span>My map</span></a>
  <?php if ($tb2Me): ?>
    <a href="<?= e(url('u/' . $tb2Me['username'])) ?>"<?= $tb2On(str_starts_with($tb2Path, '/u/' . $tb2Me['username'])) ?>>
      <span class="v2-tb-ico" aria-hidden="true">☺</span><span>You</span></a>
  <?php else: ?>
    <a href="<?= e(url('register')) ?>" data-cta="nav_join"<?= $tb2On(str_starts_with($tb2Path, '/register')) ?>>
      <span class="v2-tb-ico" aria-hidden="true">☺</span><span>Join</span></a>
  <?php endif; ?>
</nav>
