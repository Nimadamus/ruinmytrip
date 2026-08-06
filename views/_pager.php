<?php
/**
 * Pagination that preserves the current filters.
 *
 * Pagination matters here for more than ergonomics: an unpaginated warning list would grow
 * without bound on a busy destination and become the slowest page on the site.
 *
 * @var int    $page
 * @var int    $perPage
 * @var int    $total
 * @var string $base   path to link to (filters are carried from $_GET)
 */
$pages = (int) ceil($total / max(1, $perPage));
if ($pages > 1):
  $q = $_GET; unset($q['page']);
  $link = static function (int $n) use ($q, $base): string {
      $q['page'] = $n;
      return url(ltrim($base, '/')) . '?' . http_build_query($q);
  };
  $from = max(1, $page - 2);
  $to   = min($pages, $page + 2);
?>
<nav class="filter-chips" aria-label="Pagination" style="margin:22px 0">
  <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>" rel="prev">← Previous</a><?php endif; ?>
  <?php if ($from > 1): ?><a href="<?= e($link(1)) ?>">1</a><?php if ($from > 2): ?><span class="muted" style="padding:.25rem">…</span><?php endif; ?><?php endif; ?>
  <?php for ($i = $from; $i <= $to; $i++): ?>
    <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e($link($i)) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($to < $pages): ?><?php if ($to < $pages - 1): ?><span class="muted" style="padding:.25rem">…</span><?php endif; ?><a href="<?= e($link($pages)) ?>"><?= $pages ?></a><?php endif; ?>
  <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>" rel="next">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>
