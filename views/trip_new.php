<?php /** @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1 style="margin-bottom:.2rem">Post a trip</h1>
  <p class="muted">Where you are going and when. That is enough: everything under it is optional,
    and a trip with only a city and dates is the one that puts you in front of the people who will
    be there.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('trip/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_submit" value="<?= e(rmt_submit_token('trip_new')) ?>">

    <?php /* City and dates first. The form used to open with a title and demand a twenty character
             story, which meant the sentence this whole product is built around, "I am going to
             Lisbon on the 3rd", could not be posted on the page called Share a trip: somebody with
             dates and no story had to invent a paragraph or give up, and most people give up. */ ?>
    <label for="destination_id">Where are you going?</label>
    <select id="destination_id" name="destination_id">
      <option value="">Pick a city</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"<?= (string)input('destination_id') === (string)$d['id'] ? ' selected' : '' ?>><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
      <div style="flex:1;min-width:150px">
        <label for="date_from">Arriving</label>
        <input type="date" id="date_from" name="date_from" value="<?= e(input('date_from')) ?>">
      </div>
      <div style="flex:1;min-width:150px">
        <label for="date_to">Leaving</label>
        <input type="date" id="date_to" name="date_to" value="<?= e(input('date_to')) ?>">
      </div>
    </div>
    <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
      City and a date range, never anything finer. RuinMyTrip does not show precise or live location.
    </p>

    <label for="visibility">Who can see this trip</label>
    <select id="visibility" name="visibility">
      <option value="public"<?= input('visibility') === 'public' || input('visibility') === '' ? ' selected' : '' ?>>Everyone</option>
      <option value="followers"<?= input('visibility') === 'followers' ? ' selected' : '' ?>>People who follow me</option>
      <option value="private"<?= input('visibility') === 'private' ? ' selected' : '' ?>>Only me</option>
    </select>

    <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)">
      <p class="eyebrow" style="margin:0 0 10px">Everything below is optional</p>

      <label for="title">Title <span class="hint">(we will name it after the city and the dates if you leave this)</span></label>
      <input type="text" id="title" name="title" value="<?= e(input('title')) ?>" placeholder="Three quiet mornings in Kyoto">

      <label for="body">Anything you want to say</label>
      <textarea id="body" name="body" rows="4" placeholder="Where you are staying, what you are hoping to do, what you want a warning about."><?= e(input('body')) ?></textarea>

      <label for="photos">Photos <span class="muted">(up to 6)</span></label>
      <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
      <p class="muted" style="margin:.3rem 0 0;font-size:.9rem">
        JPEG, PNG or WebP, up to 8MB each. Photos are resized and re-saved on upload, which removes
        camera metadata such as GPS location.
      </p>

      <label for="cover_url">Cover image URL <span class="hint">(defaults to the city photo)</span></label>
      <input type="url" id="cover_url" name="cover_url" value="<?= e(input('cover_url')) ?>" placeholder="https://…">
    </div>

    <div style="margin-top:20px"><button class="btn btn-primary" type="submit">Post this trip</button></div>
  </form>
</div></div>
