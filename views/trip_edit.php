<?php /** @var array $t @var array $dests @var array $errors @var array $photos */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Edit trip</h1>
  <p class="muted">Changes go live as soon as you save.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('trip/'.(int)$t['id'].'/edit')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e($t['title'] ?? '') ?>" placeholder="Three quiet mornings in Kyoto" required>
    <label for="destination_id">Destination</label>
    <select id="destination_id" name="destination_id">
      <option value="">— Select a destination —</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (string)($t['destination_id'] ?? '') === (string)$d['id'] ? ' selected' : '' ?>><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>
    <label for="cover_url">Cover image URL <span class="hint">(optional — defaults to the destination photo)</span></label>
    <input type="url" id="cover_url" name="cover_url"
           value="<?= e(editable_url_value($t['cover_url'] ?? null)) ?>"
           placeholder="https://…">
    <label for="visited_on">When did you visit?</label>
    <input type="date" id="visited_on" name="visited_on" value="<?= e($t['visited_on'] ?? '') ?>">
    <label for="body">Your story</label>
    <textarea id="body" name="body" placeholder="What made it memorable? What would you tell a friend?" required><?= e($t['body'] ?? '') ?></textarea>

    <?php if (!empty($photos)): ?>
      <label>Current photos</label>
      <div class="grid g-3" style="gap:10px">
        <?php foreach ($photos as $ph): ?>
          <label style="display:block;cursor:pointer">
            <img class="card-media" src="<?= e($ph['url']) ?>" alt="" style="border-radius:8px">
            <span class="muted" style="display:flex;gap:6px;align-items:center;margin-top:4px;font-size:.9rem">
              <input type="checkbox" name="remove_photo[]" value="<?= (int)$ph['id'] ?>"> Remove
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <label for="photos">Add photos <span class="muted">(up to 6 total)</span></label>
    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    <p class="muted" style="margin:.3rem 0 0;font-size:.9rem">
      JPEG, PNG or WebP, up to 8MB each. Photos are resized and re-saved on upload, which removes
      camera metadata such as GPS location.
    </p>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
  <hr style="border:0;border-top:1px solid var(--line);margin:28px 0 18px">
  <form method="post" action="<?= e(url('trip/'.(int)$t['id'].'/delete')) ?>"
        onsubmit="return confirm('Delete this trip? It will be removed from your profile and the destination page.');">
    <?= csrf_field() ?>
    <button class="btn btn-ghost" style="color:#b42318">Delete this trip</button>
  </form>
</div></div>
