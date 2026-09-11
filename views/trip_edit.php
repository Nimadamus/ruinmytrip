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
      <option value="">Select a destination</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (string)($t['destination_id'] ?? '') === (string)$d['id'] ? ' selected' : '' ?>><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>
    <label for="cover_url">Cover image URL <span class="hint">(optional, defaults to the destination photo)</span></label>
    <input type="url" id="cover_url" name="cover_url"
           value="<?= e(editable_url_value($t['cover_url'] ?? null)) ?>"
           placeholder="https://…">
    <?php /* Same two questions the create form asks. Leaving them off the edit form would mean an
             edit silently stripped the dates and the visibility off a trip that had them. */ ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:150px">
        <label for="date_from">Arriving</label>
        <input type="date" id="date_from" name="date_from" value="<?= e($t['date_from'] ?? '') ?>">
      </div>
      <div style="flex:1;min-width:150px">
        <label for="date_to">Leaving</label>
        <input type="date" id="date_to" name="date_to" value="<?= e($t['date_to'] ?? '') ?>">
      </div>
    </div>
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">City and dates only, never anything finer.</p>
    <label for="visibility">Who can see this trip</label>
    <select id="visibility" name="visibility">
      <?php $vis = (string) ($t['visibility'] ?? 'public'); ?>
      <option value="public"<?= $vis === 'public' ? ' selected' : '' ?>>Everyone</option>
      <option value="followers"<?= $vis === 'followers' ? ' selected' : '' ?>>People who follow me</option>
      <option value="private"<?= $vis === 'private' ? ' selected' : '' ?>>Only me</option>
    </select>
    <label for="body">Your story</label>
    <textarea id="body" name="body" placeholder="What made it memorable? What would you tell a friend?" required><?= e($t['body'] ?? '') ?></textarea>

    <?php if (!empty($photos)): ?>
      <label>Photos on this trip</label>
      <div class="edit-photos">
        <?php foreach ($photos as $ph): ?>
          <div class="edit-photo">
            <a href="<?= e(url('photo/trip/'.(int) $ph['id'])) ?>" title="Open this photo">
              <img src="<?= e($ph['url']) ?>" alt=""></a>
            <input type="text" name="caption[<?= (int) $ph['id'] ?>]" maxlength="300"
                   value="<?= e((string) ($ph['caption'] ?? '')) ?>" placeholder="Say what this is">
            <label class="edit-photo-rm">
              <input type="checkbox" name="remove_photo[]" value="<?= (int)$ph['id'] ?>"> Remove
            </label>
          </div>
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
