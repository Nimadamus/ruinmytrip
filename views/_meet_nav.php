<?php /* Buddies, Travelers and Meetups were three nav items for one idea: meeting other travelers.
         The nav now carries one, Meet travelers, and this strip ties the three pages together as
         views of the same section. $meetOn is 'going', 'people' or 'meetups'. */
$meetOn = $meetOn ?? 'going'; ?>
<nav class="meet-nav" aria-label="Meet travelers"><div class="wrap">
  <a href="<?= e(url('buddies')) ?>" class="<?= $meetOn === 'going' ? 'on' : '' ?>"<?= $meetOn === 'going' ? ' aria-current="page"' : '' ?>>Going your way</a>
  <a href="<?= e(url('travelers')) ?>" class="<?= $meetOn === 'people' ? 'on' : '' ?>"<?= $meetOn === 'people' ? ' aria-current="page"' : '' ?>>All travelers</a>
  <a href="<?= e(url('meetups')) ?>" class="<?= $meetOn === 'meetups' ? 'on' : '' ?>"<?= $meetOn === 'meetups' ? ' aria-current="page"' : '' ?>>Meetups</a>
</div></nav>
