<?php
/**
 * The one-question front door: "What ruined your trip?"
 *
 * Nobody arrives wanting to "write a review". Everybody arrives with the thing that went wrong
 * still annoying them. Ask for that sentence, and only that sentence; the review form opens
 * with it already in the box, and signup happens on the way there with the words carried along.
 *
 * @var array  $dests      destinations for the optional city select
 * @var string $askVariant 'hero' (dark ground) or 'card'
 */
$askVariant = $askVariant ?? 'card';
$dark = $askVariant === 'hero';
?>
<form class="ruined-ask" method="get" action="<?= e(url('review/new')) ?>" style="max-width:640px">
  <input type="hidden" name="src" value="ruined">
  <label for="ruined-text" style="display:block;font-weight:700;font-size:1.25rem;margin:0 0 8px;<?= $dark ? 'color:#fff' : '' ?>">What ruined your trip?</label>
  <textarea id="ruined-text" name="ruined" rows="2" maxlength="280" required
            placeholder="The queue nobody mentioned. The hotel that lied about the shower. The taxi that charged triple."
            style="width:100%;box-sizing:border-box"></textarea>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
    <select name="destination" aria-label="Where was it? (optional)" style="max-width:260px">
      <option value="">Where was it? (optional)</option>
      <?php foreach ($dests as $d): ?>
        <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-accent" type="submit">Warn the next traveler</button>
  </div>
  <p class="hint" style="margin:8px 0 0;<?= $dark ? 'color:rgba(255,255,255,.8)' : '' ?>">Free account, one minute. Your sentence becomes the start of a review that helps somebody dodge it.</p>
</form>
