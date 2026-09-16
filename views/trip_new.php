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

    <?php /* Somebody who arrived from a campaign link already has the city and both dates in the
             form, and the only thing left to do is agree. On a phone the submit button was 1,774
             pixels away, past a visibility selector, a travel style, a title and a description, all
             of which are optional. So when the form arrives filled, the same button is offered here
             and everything below becomes what it always was: optional, and available.
             Nothing is removed and nothing is decided for them. */ ?>
    <?php $tnPrefilled = input('destination_id') !== '' && input('date_from') !== '' && input('date_to') !== ''; ?>
    <?php if ($tnPrefilled): ?>
      <div style="margin:0 0 18px">
        <button class="btn btn-primary" type="submit">Post this trip</button>
        <p class="hint" style="margin:6px 0 0">Public by default, and you can change any of that
          below or afterwards. Nobody sees your dates until you post them.</p>
      </div>
      <details style="margin:0 0 6px"><summary class="btn btn-ghost btn-sm">Add a title, a
        description, or change who can see it</summary>
      <div style="padding-top:12px">
    <?php endif; ?>

    <label for="visibility">Who can see this trip</label>
    <select id="visibility" name="visibility">
      <option value="public"<?= input('visibility') === 'public' || input('visibility') === '' ? ' selected' : '' ?>>Everyone</option>
      <option value="followers"<?= input('visibility') === 'followers' ? ' selected' : '' ?>>People who follow me</option>
      <option value="private"<?= input('visibility') === 'private' ? ' selected' : '' ?>>Only me</option>
    </select>

    <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)">
      <p class="eyebrow" style="margin:0 0 10px">Everything below is optional</p>

      <?php /* Two questions that change who this trip is shown to, so they sit at the top of the
               optional block rather than under the photographs. Neither is required and neither is
               answered on somebody's behalf: "no answer" is stored as no answer. */ ?>
      <label for="travel_style">How are you travelling this time?</label>
      <select id="travel_style" name="travel_style">
        <option value="">Rather not say</option>
        <?php foreach (RMT_TRAVEL_STYLES as $tsKey => $tsLabel): ?>
          <option value="<?= e($tsKey) ?>"<?= input('travel_style') === $tsKey ? ' selected' : '' ?>><?= e($tsLabel) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="open_to_meeting">Open to meeting other travelers on this trip?</label>
      <select id="open_to_meeting" name="open_to_meeting">
        <option value=""<?= input('open_to_meeting') === '' ? ' selected' : '' ?>>No answer</option>
        <option value="1"<?= input('open_to_meeting') === '1' ? ' selected' : '' ?>>Yes, introduce me to people whose dates overlap</option>
        <option value="0"<?= input('open_to_meeting') === '0' ? ' selected' : '' ?>>No, I am just posting where I will be</option>
      </select>
      <p class="muted" style="margin:.3rem 0 1rem;font-size:.9rem">
        Say no and you are never offered as a match to anybody. Your trip stays as visible as you
        set it above; you are simply not on the list of people to meet.
      </p>

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

    <?php if ($tnPrefilled): ?>
      </div></details>
    <?php endif; ?>
    <div style="margin-top:20px"><button class="btn btn-primary" type="submit">Post this trip</button></div>
  </form>
</div></div>
