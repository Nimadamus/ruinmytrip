<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function cfg(string $k, $default = null) { return $GLOBALS['config'][$k] ?? $default; }

function url(string $path = ''): string {
    return rtrim((string)cfg('app_url'), '/') . '/' . ltrim($path, '/');
}

/** Public asset URL with mtime cache-bust. */
function rmt_asset(string $rel): string {
    $rel = ltrim($rel, '/');
    $file = BASE_PATH . '/public/' . $rel;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url($rel) . '?v=' . $v;
}

function redirect(string $path): never { header('Location: ' . $path); exit; }

/**
 * A 301, for a URL that has moved for good.
 *
 * Separate from redirect() because the difference matters to a crawler and is irreversible in
 * practice: a 302 keeps the old URL indexed and splits the signals between two addresses, while a
 * 301 passes them to the new one. Used for retired place slugs, where the entity is the same row
 * and only its presentation changed.
 */
function redirect_permanent(string $path): never { header('Location: ' . $path, true, 301); exit; }

/**
 * Canonical path for one saved item. Kept out of SQL so a URL is never assembled by the
 * database (`||` concatenates on Postgres and SQLite but is logical OR on MySQL), and kept here
 * rather than in the controller so it can be unit tested on its own.
 *
 * A trip or review whose slug went missing still gets a working link: both routes take the slug as
 * an optional trailing segment, so dropping it is a shorter URL, not a broken one.
 */
function rmt_saved_path(string $kind, int $id, string $slug): string {
    return match ($kind) {
        'guide'      => '/g/' . $slug,
        'blog_post'  => '/blog/' . $slug,
        'collection' => '/c/' . $slug,
        'trip'       => '/trip/' . $id . ($slug !== '' ? '/' . $slug : ''),
        'review'     => '/review/' . $id . ($slug !== '' ? '/' . $slug : ''),
        default      => '/',
    };
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'item';
}

function rmt_country_slug(string $country): string {
    return slugify($country);
}

/** Canonical country name for a slug, or null. */
function rmt_country_from_slug(string $slug): ?string {
    foreach (q_all('SELECT DISTINCT country FROM destinations WHERE country IS NOT NULL AND country <> \'\'') as $r) {
        if (rmt_country_slug((string) $r['country']) === $slug) return (string) $r['country'];
    }
    return null;
}

function old(string $k, $default = '') { return $_SESSION['_old'][$k] ?? $default; }
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['_flash'] = $msg; return null; }
    $m = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $m;
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function input(string $k, $default = ''): string { return trim((string)($_POST[$k] ?? $_GET[$k] ?? $default)); }

/** Human "3 days ago" style. */
function ago(string $ts): string {
    $t = strtotime($ts); if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d/60) . 'm ago';
    if ($d < 86400) return floor($d/3600) . 'h ago';
    if ($d < 604800) return floor($d/86400) . 'd ago';
    return date('M j, Y', $t);
}

/**
 * Absolute URL for a stored asset path.
 *
 * Images are stored root-relative ("/media/abc.jpg") so the same row works on localhost, a
 * preview, and production. og:image is the one place that must be absolute, because scrapers
 * resolve it out of context.
 */
function abs_url(?string $u): string {
    $u = (string) $u;
    if ($u === '') return url('assets/img/og-default.svg');
    if (preg_match('#^https?://#i', $u)) return $u;
    return url(ltrim($u, '/'));
}

/**
 * Avatar <img> src, falling back to a real placeholder icon instead of an empty string.
 *
 * An empty src attribute renders as a broken-image icon in every browser -- with zero real
 * users yet, that was the single most common "looks unfinished" thing on the site. The old
 * fallback (og-default.svg, a 1200x630 wordmark banner) isn't a fix either: cropped to a 34px
 * circle it shows an unreadable sliver of text, not a placeholder that reads as intentional.
 */
function avatar_url(?string $u): string {
    $u = (string) $u;
    if ($u === '') return url('assets/img/avatar-default.svg');
    if (preg_match('#^https?://#i', $u)) return $u;
    return url(ltrim($u, '/'));
}

/**
 * Value to pre-fill into a `type="url"` edit-form field. Some stored URLs (a destination's
 * fallback photo, copied onto a trip at creation) are relative paths -- fine in an <img src>, but
 * a relative value in a type="url" input fails the browser's native constraint validation and
 * silently blocks the whole form from submitting. Only ever show a value the user could have
 * typed here themselves; anything else is treated as unset.
 */
function editable_url_value(?string $u): string {
    return preg_match('#^https://#i', (string) $u) ? (string) $u : '';
}

/** Render a view within the layout. */
function view(string $name, array $data = [], array $meta = []): void {
    extract($data, EXTR_SKIP);
    $__meta = array_merge([
        'title' => cfg('app_name'),
        'description' => 'RuinMyTrip — a trustworthy travel community for real trips, honest reviews, and safe meetups.',
        'canonical' => rmt_current_url(),
        'og_image' => url('media/4667ce3c70aadb7989e73b6fb6eb8c5e.jpg'),
        'jsonld' => null,
        'breadcrumbs' => [],
    ], $meta);
    $__view = BASE_PATH . '/views/' . $name . '.php';
    require BASE_PATH . '/views/layout/header.php';
    require $__view;
    require BASE_PATH . '/views/layout/footer.php';
}

function age_from(string $birthdate): int {
    $b = strtotime($birthdate); if (!$b) return 0;
    return (int)floor((time() - $b) / 31557600);
}

/**
 * Is there a real verification system behind the "Verified" badge?
 *
 * No. Nothing in the codebase ever sets trips.verified / reviews.verified to 1 — only the
 * demo seed did, and that data is gone. Showing the badge would assert a trust signal the
 * product cannot currently earn, so every render site is gated on this.
 *
 * Flip to true ONLY when geo-checkin / receipt / EXIF verification actually exists and writes
 * the column. The badge markup is left in place so that work is a one-line switch.
 */
function verification_system_exists(): bool {
    // Test-only escape hatch so the editorial-exclusion guard in show_verified() below can be
    // exercised as if verification were live, instead of trivially passing because this is off.
    // Never defined outside tests/editorial_test.php; production behavior is unchanged.
    return defined('RMT_TEST_FORCE_VERIFICATION_EXISTS') ? RMT_TEST_FORCE_VERIFICATION_EXISTS : false;
}

/**
 * Show the "verified" badge only when the row is flagged AND a real system stands behind it.
 *
 * Editorial content is excluded first and unconditionally: nobody from the team necessarily
 * visited (see app/editorial.php), so no future write to a row's `verified` column can ever
 * make this true for editorial content, independent of whether verification_system_exists().
 */
function show_verified(?array $row): bool {
    if (rmt_is_editorial($row)) return false;
    return verification_system_exists() && !empty($row['verified']);
}
