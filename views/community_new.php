<?php /** @var array $errors @var ?array $me */ ?>
<div class="wrap"><p class="crumbs">
  <a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('communities')) ?>">Communities</a> / Start one
</p></div>
<div class="wrap prose" style="max-width:680px">
  <h1>Start a community</h1>
  <p class="muted">A group other travelers can join. The ones that work are about how somebody
    travels rather than where: solo women in Southeast Asia, travelling with a toddler, places that
    ruined a honeymoon. Cities already have their own pages.</p>

  <?php if ($errors): ?><div class="errors"><ul>
    <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
  </ul></div><?php endif; ?>

  <form method="post" action="<?= e(url('communities/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('community_new')) ?>">

    <label for="title">Name</label>
    <input type="text" id="title" name="title" maxlength="120" required
           value="<?= e((string) input('title')) ?>" placeholder="Solo travel in Southeast Asia">

    <label for="summary">Who is it for</label>
    <textarea id="summary" name="summary" rows="3" maxlength="500"
              placeholder="One or two lines. Somebody deciding whether to join reads this and nothing else."><?= e((string) input('summary')) ?></textarea>

    <?php /* Closed is not offered here. A closed collection is a personal list and already has its
             own page; offering it as a "community" would create rooms nobody can ever enter. */ ?>
    <label for="join_policy">Who can join</label>
    <select id="join_policy" name="join_policy">
      <option value="open">Anybody signed in</option>
      <option value="invite">Only people with the invite link</option>
    </select>

    <label style="display:block;margin-top:14px">
      <input type="checkbox" name="members_can_add" value="1" checked>
      Members can add places and cities
    </label>
    <p class="hint" style="margin:.3rem 0 0">Letting somebody in and handing them the pen are
      different decisions. You can change this later from the community's edit page.</p>

    <p style="margin:22px 0 40px">
      <button class="btn btn-accent">Create it</button>
      <a class="btn btn-ghost" href="<?= e(url('communities')) ?>">Cancel</a>
    </p>
  </form>
</div>
