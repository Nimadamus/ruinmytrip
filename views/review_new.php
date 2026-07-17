<?php /** @var array $dests @var array $errors */ ?>
<div class="wrap"><div class="form-card form-wide">
  <h1>Write a review</h1>
  <p class="muted">Be specific and fair. Real experiences help other travelers.</p>
  <?php if ($errors): ?><div class="errors"><ul><?php foreach($errors as $e):?><li><?= e($e) ?></li><?php endforeach;?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('review/new')) ?>"><?= csrf_field() ?>
    <label for="destination_id">Destination</label>
    <select id="destination_id" name="destination_id">
      <option value="">— Select —</option>
      <?php foreach ($dests as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name'].', '.$d['country']) ?></option><?php endforeach; ?>
    </select>
    <label for="subject_type">What are you reviewing?</label>
    <select id="subject_type" name="subject_type">
      <?php foreach (['destination','hotel','restaurant','nightlife','attraction','tour','business'] as $s): ?>
        <option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <label for="subject_name">Name</label>
    <input type="text" id="subject_name" name="subject_name" placeholder="e.g. Skyline Gondola, Queenstown" required>
    <label for="rating">Rating</label>
    <select id="rating" name="rating"><?php for($i=5;$i>=1;$i--):?><option value="<?= $i ?>"><?= str_repeat('★',$i) ?> (<?= $i ?>)</option><?php endfor;?></select>
    <label for="title">Headline</label>
    <input type="text" id="title" name="title" placeholder="Touristy but the view earns it">
    <label for="body">Your review</label>
    <textarea id="body" name="body" required placeholder="What was it actually like?"></textarea>
    <div style="margin-top:18px"><button class="btn btn-primary">Post review</button></div>
  </form>
</div></div>
