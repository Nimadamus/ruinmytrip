<?php /** @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Share a trip</h1>
  <p class="muted">Tell the story, tag the destination, add a cover photo.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('trip/new')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input type="text" id="title" name="title" value="<?= e(input('title')) ?>" placeholder="Three quiet mornings in Kyoto" required>
    <label for="destination_id">Destination</label>
    <select id="destination_id" name="destination_id">
      <option value="">— Select a destination —</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>
    <label for="cover_url">Cover image URL <span class="hint">(optional — defaults to the destination photo)</span></label>
    <input type="url" id="cover_url" name="cover_url" placeholder="https://…">
    <label for="visited_on">When did you visit?</label>
    <input type="date" id="visited_on" name="visited_on">
    <label for="body">Your story</label>
    <textarea id="body" name="body" placeholder="What made it memorable? What would you tell a friend?" required><?= e(input('body')) ?></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary" type="submit">Publish trip</button></div>
  </form>
</div></div>
