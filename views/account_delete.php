<?php /** @var array $me @var ?string $blocked @var array $errors @var string $notice @var string $token @var bool $googleOnly */ ?>
<div class="wrap"><div class="form-card account-delete">
  <h1>Delete your account</h1>
  <?php if ($blocked): ?>
    <div class="callout" style="margin:12px 0 18px"><?= e($blocked) ?></div>
    <p><a class="btn btn-ghost" href="<?= e(url('u/' . $me['username'] . '/edit')) ?>">Back to settings</a></p>
  <?php else: ?>
    <p class="muted">This cannot be undone. Read what happens first.</p>

    <h2 style="font-size:1.05rem;margin:18px 0 6px">Deleted for good</h2>
    <ul class="account-delete-list">
      <li>Your profile: name, photo, cover, bio, home city, languages, links and interests.</li>
      <li>Your reviews, trips, plans, guides, blog posts, posts, polls, meetups and buddy posts.</li>
      <li>Every photo you uploaded, including the copies shown on place pages.</li>
      <li>Your comments. A comment somebody replied to is emptied and hidden, so their reply still makes sense.</li>
      <li>Follows, likes, saves, votes, RSVPs, badges, places you have been, travel dates, alerts and email settings.</li>
      <li>Your sign in: password, Google link, every signed in device and any open email links.</li>
    </ul>

    <h2 style="font-size:1.05rem;margin:18px 0 6px">Kept, without your name or profile</h2>
    <ul class="account-delete-list">
      <li>Private messages you sent stay in the other person's inbox, shown from "Deleted member". They can read them but cannot reply.</li>
      <li>A shared trip or group other members joined passes to the member who joined first. Your notes, cover, photos and plans are removed from it.</li>
      <li>Reports you filed, reports about you and moderation records stay, so we can keep the community safe and answer legal claims.</li>
    </ul>
    <p class="muted" style="font-size:.9rem">Your email address and username are released straight away. We send one email to
      <b><?= e((string) $me['email']) ?></b> to confirm the deletion and nothing after that.
      Want a copy of your data first? <a href="<?= e(url('contact?kind=privacy_request')) ?>">Send a privacy request</a> before you delete.</p>

    <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="callout" role="status" style="margin:12px 0"><?= e($notice) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('account/delete')) ?>" style="margin-top:18px"><?= csrf_field() ?>
      <label for="confirm_username">Type your username, <b><?= e((string) $me['username']) ?></b>, to confirm</label>
      <input type="text" id="confirm_username" name="confirm_username" required autocomplete="off"
             autocapitalize="none" spellcheck="false" value="">
      <?php if ($token !== ''): ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <p class="muted" style="font-size:.9rem;margin:.3rem 0 0">Your email link is confirmed. No password needed.</p>
      <?php else: ?>
        <label for="password">Your current password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      <?php endif; ?>
      <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-danger" id="account-delete-submit">Delete my account</button>
        <a class="btn btn-ghost" href="<?= e(url('u/' . $me['username'] . '/edit')) ?>">Keep my account</a>
      </div>
    </form>

    <?php if ($token === ''): ?>
      <div style="margin-top:22px;padding-top:16px;border-top:1px solid var(--line)">
        <p class="muted" style="font-size:.9rem;margin:0 0 8px"><?= $googleOnly
            ? 'You sign in with Google, so you may not have a password here.'
            : 'Forgot your password?' ?>
          We can email you a one time link instead. It works for 30 minutes.</p>
        <form method="post" action="<?= e(url('account/delete/email')) ?>"><?= csrf_field() ?>
          <button class="btn btn-ghost btn-sm">Email me a link</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
