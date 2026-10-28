<?php /** @var array $acts @var bool $onDark @var ?array $me */
$ghost = !empty($onDark) ? 'btn-ghost-light' : 'btn-ghost';
?>
<div class="buddy-cta buddy-acts">
  <a class="btn btn-accent" href="<?= e($acts['trip']) ?>">Post your <?= e($acts['city']) ?> trip</a>
  <a class="btn <?= e($ghost) ?>" href="<?= e($acts['review']) ?>">Review <?= e($acts['city']) ?></a>
  <a class="btn <?= e($ghost) ?>" href="<?= e($acts['buddies']) ?>">Find <?= e($acts['country']) ?> buddies</a>
  <?php if (empty($me)): ?>
    <a class="btn <?= e($ghost) ?>" href="<?= e($acts['join']) ?>">Create a free account</a>
  <?php endif; ?>
  <a class="btn <?= e($ghost) ?>" href="<?= e($acts['profile']) ?>">Build your profile</a>
</div>
