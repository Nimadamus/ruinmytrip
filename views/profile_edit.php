<?php /** @var array $me @var array $errors @var array $p */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit your profile</h1>
  <p class="muted">This is what other travelers see at
    <a href="<?= e(url('u/'.$me['username'])) ?>">ruinmytrip.com/u/<?= e($me['username']) ?></a>.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $er):?><li><?= e($er) ?></li><?php endforeach;?></ul></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="<?= e(url('u/'.$me['username'].'/edit')) ?>"><?= csrf_field() ?>
    <label>Username</label>
    <input type="text" value="<?= e($me['username']) ?>" disabled>
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      Your username is permanent: it is your public profile address and other travelers' links to you depend on it.
    </p>

    <label for="display_name">Display name</label>
    <input type="text" id="display_name" name="display_name" maxlength="60"
           value="<?= e($p['display_name'] ?? '') ?>" placeholder="How your name appears on your profile">

    <label for="avatar">Profile photo</label>
    <?php if (!empty($p['avatar_url'])): ?>
      <img class="avatar" style="width:72px;height:72px;margin-bottom:8px" src="<?= e(avatar_url($p['avatar_url']??null)) ?>" alt="">
    <?php endif; ?>
    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      JPEG, PNG or WebP, up to 8MB. Your photo is resized and re-saved on upload, which removes
      camera metadata such as GPS location.
    </p>

    <label for="avatar_url">…or paste a photo URL</label>
    <input type="url" id="avatar_url" name="avatar_url" maxlength="500"
           value="<?= e(editable_url_value($p['avatar_url'] ?? null)) ?>" placeholder="https://…">

    <?php /* A cover. profiles.cover_url has been in the schema since the beginning and nothing
             could set it, so every profile fell back to a gradient and then to the member's own
             most recent trip photograph. This is the way to put a real one there. */ ?>
    <label for="cover">Cover photo <span class="hint">(optional, wide works best)</span></label>
    <?php if (!empty($p['cover_url'])): ?>
      <img src="<?= e(abs_url((string) $p['cover_url'])) ?>" alt=""
           style="width:100%;max-width:380px;aspect-ratio:3/1;object-fit:cover;border-radius:10px;display:block;margin-bottom:8px">
      <label class="opt-in" style="padding:8px 12px">
        <input type="checkbox" name="remove_cover" value="1">
        <span><b>Remove this cover</b>
          <span class="hint">Your profile goes back to using your most recent trip photo.</span></span>
      </label>
    <?php endif; ?>
    <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      An uploaded file takes priority over this field.
    </p>

    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" rows="4" maxlength="600"
              placeholder="Where you have been, what you look for in a trip."><?= e($p['bio'] ?? '') ?></textarea>

    <?php /* Optional, and a short list rather than a text box, because this is something other
             travelers filter by: "solo travelers in Lisbon" is a question the site can only answer
             if the answers are comparable. */ ?>
    <?php /* Opt in, off by default, and described exactly as what it is. Living somewhere is
             never taken as consent to be listed as available to strangers, so this is a box
             somebody has to find and tick, and the sentence under it is the whole promise. */ ?>
    <label class="opt-in">
      <input type="checkbox" name="open_to_meeting" value="1"<?= !empty($p['open_to_meeting']) ? ' checked' : '' ?>>
      <span>
        <b>I am open to meeting travelers in my city</b>
        <span class="hint">Puts you on your city's page under "locals open to meeting travelers".
          It means you are happy to answer a question or meet in a public place. It shares nothing
          else: no address, no live location, and you can untick it at any time.</span>
      </span>
    </label>

    <label for="travel_style">How you usually travel <span class="hint">(optional)</span></label>
    <select id="travel_style" name="travel_style">
      <option value="">Rather not say</option>
      <?php foreach (RMT_TRAVEL_STYLES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= ($p['travel_style'] ?? '') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      Shown on your profile and used to answer "who else is travelling solo here". Leave it unset
      and nothing about it is displayed.
    </p>

    <label for="home_city">Home location</label>
    <input type="text" id="home_city" name="home_city" maxlength="80"
           value="<?= e($p['home_city'] ?? '') ?>" placeholder="e.g. Lisbon, PT">
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      City-level only. Never a precise address. If it is a city we have a page for, you will be
      listed as a local there, where travelers heading over can find you.
    </p>

    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primary">Save profile</button>
      <a class="btn btn-ghost" href="<?= e(url('u/'.$me['username'])) ?>">View my profile</a>
    </div>
  </form>
</div></div>
