<?php /** @var array $acts @var bool $onDark @var ?array $me */
/* One clear next step (post the trip), one alternative (review the city), and the rest as quiet
   links, so the block reads as a choice of one rather than five equal buttons. */
$ghost = !empty($onDark) ? 'btn-ghost-light' : 'btn-ghost';
?>
<div class="buddy-cta buddy-acts">
  <a class="btn btn-accent" href="<?= e($acts['trip']) ?>">Post your <?= e($acts['city']) ?> trip</a>
  <a class="btn <?= e($ghost) ?>" href="<?= e($acts['review']) ?>">Review <?= e($acts['city']) ?></a>
</div>
<p class="buddy-acts-more<?= !empty($onDark) ? ' on-dark' : '' ?>">
  <a href="<?= e($acts['buddies']) ?>">Find <?= e($acts['country']) ?> buddies</a>
  <?php if (empty($me)): ?><a href="<?= e($acts['join']) ?>">Create a free account</a><?php endif; ?>
  <a href="<?= e($acts['profile']) ?>">Build your profile</a>
</p>
