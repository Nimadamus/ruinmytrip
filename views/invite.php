<?php /** @var ?array $me @var string $link @var int $joined @var int $contributed */ ?>
<div class="wrap" style="max-width:760px;padding-top:32px">
  <p class="eyebrow">Invite</p>
  <h1 style="margin:.2rem 0 .5rem">Invite a traveler you'd actually take advice from</h1>
  <p class="muted" style="font-size:1.05rem">RuinMyTrip is worth exactly as much as the honesty of the people writing on it. We would rather have fifty travelers who write the truth about a place than fifty thousand accounts padding a number. So there is no reward for inviting people, and there never will be. Invite someone because you want to read what they think.</p>

  <?php if ($me): ?>
    <div class="card" style="margin-top:22px"><div class="card-body">
      <h2 style="margin-top:0;font-size:1.15rem">Your invite link</h2>
      <div class="invite-row">
        <input id="invite-link" type="text" readonly value="<?= e($link) ?>" aria-label="Your invite link">
        <button class="btn btn-primary" type="button" onclick="rmtCopyInvite(this)">Copy</button>
      </div>
      <p class="hint">Anyone who joins from this link is recorded as invited by @<?= e($me['username']) ?>. That is all it does, there is no reward and no tracking beyond that one field.</p>

      <h3 style="margin:20px 0 6px;font-size:1rem">Something you can send</h3>
      <textarea id="invite-msg" rows="4" readonly style="width:100%;font:inherit;padding:10px;border:1px solid var(--line);border-radius:10px">I've been using RuinMyTrip, it's a travel site built on honest first-hand reviews, including the parts that went wrong. You've got trips worth writing up. Join here: <?= e($link) ?></textarea>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
        <button class="btn btn-ghost btn-sm" type="button" onclick="rmtCopyMsg(this)">Copy message</button>
        <a class="btn btn-ghost btn-sm" href="mailto:?subject=<?= e(rawurlencode('A travel site that keeps it honest')) ?>&amp;body=<?= e(rawurlencode("I've been using RuinMyTrip, it's a travel site built on honest first-hand reviews, including the parts that went wrong. You've got trips worth writing up.\n\nJoin here: " . $link)) ?>">Send by email</a>
      </div>
    </div></div>

    <div class="grid g-2" style="margin-top:18px">
      <div class="card"><div class="card-body">
        <p class="eyebrow" style="margin:0">Joined from your link</p>
        <p style="font-size:2rem;font-weight:800;margin:.2rem 0 0"><?= (int)$joined ?></p>
      </div></div>
      <div class="card"><div class="card-body">
        <p class="eyebrow" style="margin:0">…who published a review</p>
        <p style="font-size:2rem;font-weight:800;margin:.2rem 0 0"><?= (int)$contributed ?></p>
        <p class="hint" style="margin:0">The only number worth counting.</p>
      </div></div>
    </div>
  <?php else: ?>
    <div class="callout" style="margin-top:22px">
      <b>Sign in to get your invite link.</b> Every link carries the username of the member who sent it, so you need an account first.
      <p style="margin:10px 0 0"><a class="btn btn-primary btn-sm" href="<?= e(url('register')) ?>">Join free</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('login')) ?>">Sign in</a></p>
    </div>
  <?php endif; ?>

  <h2 style="margin-top:34px">What we ask of people you bring</h2>
  <ul class="list-plain" style="line-height:1.9">
    <li><b>Write from a real trip.</b> A review here means you went. No second-hand summaries, no AI-written filler.</li>
    <li><b>Say what went wrong.</b> "What nearly ruined the trip" is a required part of the form for a reason.</li>
    <li><b>No undisclosed freebies.</b> If a stay or meal was comped, say so in the review.</li>
    <li><b>Be specific.</b> A price, a street, a month, a name. Specifics are what make a review useful a year later.</li>
  </ul>
  <p class="muted">Read the full <a href="<?= e(url('guidelines')) ?>">Community Guidelines</a>.</p>

  <div class="empty-cta" style="margin-top:26px">
    <h2 style="margin:0 0 6px">Already have a trip worth writing up?</h2>
    <p class="muted" style="margin:0 0 14px">Start with the last place you went. It takes about five minutes.</p>
    <a class="btn btn-accent" href="<?= e(url('review/new')) ?>">Share your experience</a>
  </div>
</div>

<script>
function rmtCopyValue(el, text, done) {
  var ok = function () { var t = el.textContent; el.textContent = done; setTimeout(function(){ el.textContent = t; }, 1600); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(ok); return; }
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); ok(); } finally { document.body.removeChild(ta); }
}
function rmtCopyInvite(btn) { rmtCopyValue(btn, document.getElementById('invite-link').value, 'Copied'); }
function rmtCopyMsg(btn) { rmtCopyValue(btn, document.getElementById('invite-msg').value, 'Copied'); }
</script>
