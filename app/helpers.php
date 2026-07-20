<?php
declare(strict_types=1);

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function cfg(string $k, $default = null) { return $GLOBALS['config'][$k] ?? $default; }

function url(string $path = ''): string {
    return rtrim((string)cfg('app_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never { header('Location: ' . $path); exit; }

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-') ?: 'item';
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

/** Render a view within the layout. */
function view(string $name, array $data = [], array $meta = []): void {
    extract($data, EXTR_SKIP);
    $__meta = array_merge([
        'title' => cfg('app_name'),
        'description' => 'RuinMyTrip — a trustworthy travel community for real trips, honest reviews, and safe meetups.',
        'canonical' => rmt_current_url(),
        'og_image' => url('assets/img/og-default.svg'),
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
function verification_system_exists(): bool { return false; }

/** Show the "verified" badge only when the row is flagged AND a real system stands behind it. */
function show_verified(?array $row): bool {
    return verification_system_exists() && !empty($row['verified']);
}
