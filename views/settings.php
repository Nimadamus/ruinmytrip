<?php /** @var array $me */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Settings</h1>
  <form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?>
    <label for="display_name">Display name</label>
    <input type="text" id="display_name" name="display_name" value="<?= e($me['display_name']??'') ?>">
    <label for="home_city">Home city</label>
    <input type="text" id="home_city" name="home_city" value="<?= e($me['home_city']??'') ?>">
    <label for="avatar_url">Avatar image URL</label>
    <input type="url" id="avatar_url" name="avatar_url" value="<?= e($me['avatar_url']??'') ?>">
    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" style="min-height:100px"><?= e($me['bio']??'') ?></textarea>

    <h2 style="margin-top:24px;font-size:1.2rem">Privacy</h2>
    <div class="callout">Your travel plans (“Who's going”) are opt-in and shown at <b>destination + date-range level only</b>. RuinMyTrip never shares precise or real-time location. Per-plan visibility (public / followers / private) is set when you add a plan.</div>

    <div style="margin-top:16px"><button class="btn btn-primary">Save changes</button>
      <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">View profile</a></div>
  </form>
</div></div>
