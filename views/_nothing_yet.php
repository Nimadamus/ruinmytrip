<?php
/**
 * A page with nothing on it, made useful without inventing anything.
 *
 * @var string $emptyTitle   what is empty, said plainly
 * @var string $emptyWhy     why it is empty and what fills it
 * @var string $emptyCtaText the one action that fills it
 * @var string $emptyCtaUrl
 *
 * A young network is mostly empty by definition, and a page that only apologises teaches the
 * reader that the site is dead. So: say what is missing, say what fills it, offer the action, and
 * then offer real cities and real people.
 *
 * Everything below is a live row. A city is here because somebody is actually going to it and the
 * number says how many; a traveler is here because they exist and have done something. When there
 * is nothing real to offer, nothing is offered: the section simply does not appear. Nobody is ever
 * invented, no activity is ever implied, and no count is ever rounded up.
 */
$sug = function_exists('rmt_empty_state_suggestions')
    ? rmt_empty_state_suggestions(current_user())
    : ['cities' => [], 'people' => []];
?>
<div class="nothing-yet">
  <h2><?= e($emptyTitle) ?></h2>
  <p class="muted"><?= e($emptyWhy) ?></p>
  <?php if (!empty($emptyCtaText)): ?>
    <p style="margin:14px 0 0"><a class="btn btn-primary" href="<?= e($emptyCtaUrl) ?>"><?= e($emptyCtaText) ?></a></p>
  <?php endif; ?>
</div>

<?php if ($sug['cities']): ?>
  <section class="find-block">
    <h2 class="find-h">Cities with travelers in them</h2>
    <p class="hint">Live counts. A city is on this list because somebody posted dates for it.</p>
    <div class="tag-list">
      <?php foreach ($sug['cities'] as $c): ?>
        <a class="chip" href="<?= e(url('d/'.$c['slug'].'/travelers')) ?>">
          <?= e((string) $c['name']) ?>
          <span class="hint"><?php
            $bits = [];
            if ((int) $c['going'] > 0) $bits[] = (int) $c['going'] . ' going';
            if ((int) $c['meets'] > 0) $bits[] = (int) $c['meets'] . ' ' . ((int) $c['meets'] === 1 ? 'meetup' : 'meetups');
            echo e(implode(' · ', $bits)); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($sug['people']): ?>
  <section class="find-block">
    <h2 class="find-h">Travelers already here</h2>
    <?php foreach ($sug['people'] as $pp): ?>
      <?php $person = $pp; $because = (string) ($pp['reason'] ?? '');
            $backTo = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
            include __DIR__ . '/_person_card.php'; ?>
    <?php endforeach; ?>
    <p style="margin:10px 0 0"><a href="<?= e(url('travelers')) ?>">Find travelers</a></p>
  </section>
<?php endif; ?>
