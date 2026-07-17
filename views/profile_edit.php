<?php /** @var array $me @var array $errors @var array $p */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit your profile</h1>
  <p class="muted">This is what other travelers see at
    <a href="<?= e(url('u/'.$me['username'])) ?>">ruinmytrip.com/u/<?= e($me['username']) ?></a>.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $er):?><li><?= e($er) ?></li><?php endforeach;?></ul></div><?php endif; ?>

  <form method="post" action="<?= e(url('u/'.$me['username'].'/edit')) ?>"><?= csrf_field() ?>
    <label>Username</label>
    <input type="text" value="<?= e($me['username']) ?>" disabled>
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      Your username is permanent — it is your public profile address and other travelers' links to you depend on it.
    </p>

    <label for="display_name">Display name</label>
    <input type="text" id="display_name" name="display_name" maxlength="60"
           value="<?= e($p['display_name'] ?? '') ?>" placeholder="How your name appears on your profile">

    <label for="avatar_url">Profile photo URL</label>
    <input type="url" id="avatar_url" name="avatar_url" maxlength="500"
           value="<?= e($p['avatar_url'] ?? '') ?>" placeholder="https://…">
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      Paste an https:// link to a photo. Direct photo upload is coming — it needs image hosting
      that is not wired up yet.
    </p>

    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" rows="4" maxlength="600"
              placeholder="Where you have been, what you look for in a trip."><?= e($p['bio'] ?? '') ?></textarea>

    <label for="home_city">Home location</label>
    <input type="text" id="home_city" name="home_city" maxlength="80"
           value="<?= e($p['home_city'] ?? '') ?>" placeholder="e.g. Lisbon, PT">
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      City-level only. Never a precise address.
    </p>

    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary">Save profile</button>
      <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">View my profile</a>
    </div>
  </form>
</div></div>
