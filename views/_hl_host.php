<?php
/**
 * One approved host. The neighbourhood is the only location a host profile holds. While overnight
 * stays are switched off (rmt_stays_enabled), the request button says so instead of pretending.
 *
 * @var array  $hc  a row from rmt_hl_hosts()
 * @var ?array $me
 */
$hcName = (string) ($hc['display_name'] ?: '@' . $hc['username']);
$hcLangs = rmt_language_labels(rmt_languages_for($hc['languages'] ?? null));
?>
<article class="hl-person">
  <div class="hl-person-top">
    <a href="<?= e(url('u/' . $hc['username'])) ?>"><img class="avatar" src="<?= e(avatar_url($hc['avatar_url'] ?? null)) ?>" alt="" loading="lazy"></a>
    <div>
      <a href="<?= e(url('u/' . $hc['username'])) ?>"><b><?= e($hcName) ?></b></a>
      <span class="hint">Interested in hosting in <?= e((string) $hc['dest_name']) ?><?php
        if (($hc['neighborhood'] ?? '') !== ''): ?>, <?= e((string) $hc['neighborhood']) ?> area<?php endif; ?></span>
    </div>
  </div>
  <div class="hl-badges">
    <span class="hl-badge hl-badge-role">Host</span>
    <?php if (!empty($hc['email_verified_at'])): ?><span class="hl-badge hl-badge-ok">Email confirmed</span><?php endif; ?>
    <?php if (!empty($hc['id_verified_at'])): ?><span class="hl-badge hl-badge-ok">Identity checked</span>
    <?php else: ?><span class="hl-badge hl-badge-no">Identity not checked yet</span><?php endif; ?>
    <?php foreach (array_slice($hcLangs, 0, 2) as $l): ?><span class="hl-badge"><?= e($l) ?></span><?php endforeach; ?>
  </div>
  <dl class="hl-facts">
    <dt>Space</dt><dd><?= e(RMT_HOST_SPACES[$hc['space_type']] ?? 'Space') ?>, up to <?= (int) $hc['max_guests'] ?> <?= (int) $hc['max_guests'] === 1 ? 'guest' : 'guests' ?></dd>
    <dt>Stays</dt><dd><?= (int) $hc['min_nights'] ?> to <?= (int) $hc['max_nights'] ?> nights</dd>
    <dt>House</dt><dd><?= e(RMT_HOST_PETS[$hc['pets']] ?? '') ?>, <?= e(mb_strtolower(RMT_HOST_SMOKING[$hc['smoking']] ?? '')) ?></dd>
    <?php if (($hc['available_note'] ?? '') !== ''): ?><dt>When</dt><dd><?= e((string) $hc['available_note']) ?></dd><?php endif; ?>
  </dl>
  <p class="muted"><?= e(mb_strimwidth((string) $hc['about_space'], 0, 180, '…')) ?></p>
  <div class="hl-person-acts">
    <?php if (rmt_stays_enabled()): ?>
      <a class="btn btn-primary btn-sm" href="<?= e(url('u/' . $hc['username'])) ?>">Request to stay</a>
    <?php else: ?>
      <button class="btn btn-ghost btn-sm" type="button" disabled aria-describedby="hl-stays-note">Stay requests not open yet</button>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('u/' . $hc['username'])) ?>">Profile</a>
  </div>
</article>
