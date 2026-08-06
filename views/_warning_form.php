<?php
/**
 * The warning submission form, shared by /warning/new and /warning/{id}/edit.
 *
 * Two things drive the layout. First, the fields that make a warning *useful to someone else* —
 * when it happened, how bad it was, and how to avoid it — are required or prominent, while the
 * optional ones sit below. Second, the naming of a business is deliberately framed: the label and
 * hint tell the writer what standard applies before they type, rather than a moderator having to
 * explain it afterwards.
 *
 * @var array  $dests
 * @var ?array $w        existing values (edit, or a re-rendered failed submit)
 * @var string $formKey  idempotency form name
 * @var string $action
 * @var bool   $isEdit
 */
$v = static function (string $k, $default = '') use ($w) {
    return $w[$k] ?? $default;
};
$months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form-card form-wide">
  <?= csrf_field() ?>
  <input type="hidden" name="_submit" value="<?= e(rmt_submit_token($formKey)) ?>">

  <?php if (!empty($errors)): ?>
    <div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <h1 style="margin-bottom:.2rem"><?= $isEdit ? 'Edit your warning' : 'Share a travel warning' ?></h1>
  <p class="muted" style="margin-top:0">
    <?= $isEdit
      ? 'Editing sends this back to the moderation queue, because the words a moderator approved would otherwise change after approval.'
      : 'One specific problem, in your own words. A moderator reads every submission before it appears, usually within a day or two.' ?>
  </p>

  <fieldset style="border:0;padding:0;margin:26px 0 0">
    <legend class="eyebrow" style="padding:0">The basics</legend>

    <p>
      <label for="wf-dest"><b>Destination</b> <span class="muted">(required)</span></label>
      <select id="wf-dest" name="destination_id" required style="width:100%">
        <option value="">Choose a destination…</option>
        <?php foreach ($dests as $dd): ?>
          <option value="<?= (int) $dd['id'] ?>" <?= (int) $v('destination_id') === (int) $dd['id'] ? 'selected' : '' ?>>
            <?= e($dd['name'] . ', ' . $dd['country']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Not listed? <a href="<?= e(url('report')) ?>">Tell us</a> and we will add it.</span>
    </p>

    <p>
      <label for="wf-title"><b>Warning title</b> <span class="muted">(required)</span></label>
      <input id="wf-title" name="title" maxlength="<?= RMT_WARNING_TITLE_MAX ?>" required style="width:100%"
             placeholder="Airport taxi quoted 6x the metered fare" value="<?= e((string) $v('title')) ?>">
      <span class="hint">Say what the problem was, not how you felt about it. Another traveler should recognise it from the title alone.</span>
    </p>

    <p>
      <label for="wf-cat"><b>Category</b> <span class="muted">(required)</span></label>
      <select id="wf-cat" name="category" required style="width:100%">
        <?php foreach (RMT_WARNING_CATEGORIES as $k => $c): ?>
          <option value="<?= e($k) ?>" <?= (string) $v('category') === $k ? 'selected' : '' ?>>
            <?= e($c['label']) ?> — <?= e($c['blurb']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </p>

    <p>
      <label for="wf-body"><b>What happened</b> <span class="muted">(required, at least <?= RMT_WARNING_BODY_MIN ?> characters)</span></label>
      <textarea id="wf-body" name="body" rows="8" required style="width:100%"
                maxlength="<?= RMT_WARNING_BODY_MAX ?>"
                placeholder="What happened, where, and what it cost you in money or time. Specifics are what make this useful: the exact spot, the amount, the wording they used."><?= e((string) $v('body')) ?></textarea>
    </p>

    <p>
      <label><b>How badly did it affect the trip?</b> <span class="muted">(required)</span></label>
      <?php foreach (RMT_WARNING_SEVERITIES as $n => $s): ?>
        <label style="display:block;padding:.35rem 0;font-weight:400">
          <input type="radio" name="severity" value="<?= (int) $n ?>" <?= (int) $v('severity', 2) === $n ? 'checked' : '' ?>>
          <b><?= e($s['label']) ?></b> — <span class="muted"><?= e($s['desc']) ?></span>
        </label>
      <?php endforeach; ?>
    </p>

    <p>
      <label for="wf-when"><b>When did you experience this?</b> <span class="muted">(required)</span></label>
      <input id="wf-when" type="month" name="date_experienced" required
             max="<?= e(date('Y-m')) ?>" value="<?= e(substr((string) $v('date_experienced'), 0, 7)) ?>">
      <span class="hint">The month is enough. Travel problems date fast, and a report without a date cannot be judged.</span>
    </p>
  </fieldset>

  <fieldset style="border:0;padding:0;margin:30px 0 0">
    <legend class="eyebrow" style="padding:0">Details that make it actionable</legend>

    <p>
      <label for="wf-advice"><b>How can someone avoid this?</b></label>
      <textarea id="wf-advice" name="advice" rows="3" style="width:100%" maxlength="<?= RMT_WARNING_FIELD_MAX ?>"
                placeholder="Book the transfer in advance, use the official rank on level 2, insist on the meter."><?= e((string) $v('advice')) ?></textarea>
      <span class="hint">This is the part most readers act on.</span>
    </p>

    <p>
      <label for="wf-where"><b>Neighbourhood or specific location</b></label>
      <input id="wf-where" name="location_detail" maxlength="200" style="width:100%"
             placeholder="Sultanahmet tram stop, arrivals hall, Barrio Gótico" value="<?= e((string) $v('location_detail')) ?>">
    </p>

    <p>
      <label for="wf-cost"><b>Roughly what did it cost you?</b> <span class="muted">(US dollars, optional)</span></label>
      <input id="wf-cost" name="cost_impact_usd" inputmode="numeric" style="max-width:220px"
             placeholder="120" value="<?= e((string) $v('cost_impact_usd')) ?>">
      <span class="hint">Your own estimate. Leave blank if it cost time rather than money.</span>
    </p>

    <p>
      <label for="wf-travtype"><b>What kind of trip was it?</b></label>
      <select id="wf-travtype" name="traveler_type" style="width:100%">
        <option value="">Prefer not to say</option>
        <?php foreach (RMT_TRAVELER_TYPES as $k => $lab): ?>
          <option value="<?= e($k) ?>" <?= (string) $v('traveler_type') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="hint">A problem that ruins a family trip may be irrelevant to a backpacker, and readers can filter on this.</span>
    </p>
  </fieldset>

  <fieldset style="border:0;padding:0;margin:30px 0 0">
    <legend class="eyebrow" style="padding:0">Naming a business (optional)</legend>
    <div class="callout warn" style="margin-top:8px">
      Naming a hotel, operator or airline is allowed, and often the most useful part of a warning. It is also
      an allegation about a real business: write only what you personally experienced, keep it factual, and
      leave out anything you cannot describe first-hand. The business can post a response on this page, and
      your report stays labelled <b>Unverified</b> until a moderator can corroborate it.
    </div>
    <p>
      <label for="wf-ptype">Type of business</label>
      <select id="wf-ptype" name="provider_type" style="width:100%">
        <option value="">Not about a specific business</option>
        <?php foreach (RMT_PROVIDER_TYPES as $k => $lab): ?>
          <option value="<?= e($k) ?>" <?= (string) $v('provider_type') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <p>
      <label for="wf-pname">Name</label>
      <input id="wf-pname" name="provider_name" maxlength="200" style="width:100%"
             placeholder="Hotel, airline, tour company or transport operator" value="<?= e((string) $v('provider_name')) ?>">
    </p>
  </fieldset>

  <fieldset style="border:0;padding:0;margin:30px 0 0">
    <legend class="eyebrow" style="padding:0">Photos (optional)</legend>
    <p>
      <label for="wf-photos">Up to 4 photos</label>
      <input id="wf-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
      <span class="hint">A photo of the sign, the receipt or the scaffolding is worth a paragraph. Images are
        re-encoded on upload, which strips location data from the file.</span>
    </p>
  </fieldset>

  <p style="margin-top:26px">
    <label style="font-weight:400">
      <input type="checkbox" name="attested" value="1" <?= $v('attested') ? 'checked' : '' ?>>
      I confirm this is my own genuine experience, described accurately and in good faith.
    </label>
  </p>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
    <button class="btn btn-accent" name="action" value="submit" type="submit">
      <?= $isEdit ? 'Save and resubmit' : 'Submit for review' ?>
    </button>
    <button class="btn btn-ghost" name="action" value="draft" type="submit">Save as draft</button>
    <a class="btn btn-ghost" href="<?= e(url('warnings')) ?>">Cancel</a>
  </div>
  <p class="hint" style="margin-top:12px">
    By submitting you agree to the <a href="<?= e(url('guidelines')) ?>">community guidelines</a>.
    We remove reports that are second-hand, retaliatory, or that name a person rather than a business.
  </p>
</form>
