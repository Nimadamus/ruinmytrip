<?php /** @var array $posts @var ?array $me @var array $dests @var array $myCommunities @var ?array $dest @var ?array $community */ ?>
<div class="wrap"><p class="crumbs">
  <a href="<?= e(url()) ?>">Home</a> / <?php if ($dest || $community): ?><a href="<?= e(url('talk')) ?>">Talk</a> /
    <?= e($dest ? (string) $dest['name'] : (string) $community['title']) ?><?php else: ?>Talk<?php endif; ?>
</p></div>
<div class="wrap">
  <h1><?php if ($dest): ?>Talking about <?= e((string) $dest['name']) ?>
      <?php elseif ($community): ?><?= e((string) $community['title']) ?>
      <?php else: ?>Travel talk<?php endif; ?></h1>
  <p class="muted" style="max-width:62ch">Questions, warnings and what a place is actually like. Short
    is fine. If it turns into a story worth keeping, write it up as a
    <a href="<?= e(url('trip/new')) ?>">trip</a> or a <a href="<?= e(url('review/new')) ?>">review</a>.</p>

  <?php /* The composer sits above the stream, not behind a button. A conversation surface whose
           first job is to make you click "new post" gets one post a week. */ ?>
  <?php if ($me): ?>
    <div class="card" style="margin:18px 0"><div class="card-body">
      <form method="post" action="<?= e(url('post/new')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="_submit" value="<?= e(rmt_submit_token('post_new')) ?>">
        <input type="hidden" name="return" value="<?= e(url('talk')) ?>">
        <label for="body" class="sr-only">What do you want to say?</label>
        <textarea id="body" name="body" rows="4" required maxlength="<?= RMT_POST_MAX ?>"
                  placeholder="Ask something, warn somebody, or say what a place was really like."></textarea>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:10px">
          <select name="destination_id" aria-label="About a destination (optional)">
            <option value="">Anywhere in particular? (optional)</option>
            <?php foreach ($dests as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= $dest && (int) $dest['id'] === (int) $d['id'] ? 'selected' : '' ?>>
                <?= e((string) $d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($myCommunities): ?>
            <select name="collection_id" aria-label="Post into a community (optional)">
              <option value="">Everyone (optional community)</option>
              <?php foreach ($myCommunities as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $community && (int) $community['id'] === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= e((string) $c['title']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <label class="btn btn-ghost btn-sm" style="cursor:pointer">
            Photo <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none">
          </label>
          <button class="btn btn-accent">Post</button>
        </div>
      </form>
    </div></div>
  <?php else: ?>
    <p style="margin:16px 0"><a class="btn btn-accent" href="<?= e(url('register')) ?>">Join free to reply</a>
      <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Sign in</a></p>
  <?php endif; ?>

  <?php if (!$posts): ?>
    <div class="empty-cta" style="margin:14px 0 50px">
      <h3>Nothing here yet.</h3>
      <p class="muted" style="margin:0">Be the first to say something. A question counts.</p>
    </div>
  <?php endif; ?>

  <div style="padding-bottom:50px">
    <?php foreach ($posts as $p): ?>
      <div class="card" style="margin-bottom:12px"><div class="card-body">
        <div style="display:flex;gap:12px;align-items:center">
          <img class="avatar" style="width:36px;height:36px" src="<?= e(avatar_url($p['avatar_url'] ?? null)) ?>" alt="">
          <div style="flex:1;min-width:0">
            <b><a href="<?= e(url('u/'.$p['username'])) ?>">@<?= e((string) $p['username']) ?></a></b>
            <span class="hint"> · <?= e(ago((string) $p['created_at'])) ?></span>
            <?php if (!empty($p['dest_slug'])): ?>
              <span class="hint"> · <a href="<?= e(url('talk?d='.$p['dest_slug'])) ?>"><?= e((string) $p['dest_name']) ?></a></span>
            <?php endif; ?>
            <?php if (!empty($p['community_slug'])): ?>
              <span class="chip"><a href="<?= e(url('c/'.$p['community_slug'])) ?>"><?= e((string) $p['community_title']) ?></a></span>
            <?php endif; ?>
          </div>
        </div>
        <p style="margin:.6rem 0 .4rem;white-space:pre-wrap"><?= nl2br(e(mb_strimwidth((string) $p['body'], 0, 500, '…'))) ?></p>
        <?php if (!empty($p['image_url'])): ?>
          <a href="<?= e(url('post/'.(int) $p['id'])) ?>"><img loading="lazy" src="<?= e(abs_url((string) $p['image_url'])) ?>"
               alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:10px;margin:.2rem 0 .5rem"></a>
        <?php endif; ?>
        <p class="hint" style="margin:0"><a href="<?= e(url('post/'.(int) $p['id'])) ?>">
          <?php $n = (int) ($p['reply_count'] ?? 0); ?>
          <?= $n ? $n . ' ' . ($n === 1 ? 'reply' : 'replies') : 'Reply' ?></a></p>
      </div></div>
    <?php endforeach; ?>
  </div>
</div>
