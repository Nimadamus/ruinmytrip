<?php
declare(strict_types=1);

/**
 * The place page redesign switch (2026-10-11).
 *
 *   RMT_PLACE_V2=1           every place page uses views/place_show_v2.php
 *   RMT_PLACE_V2 unset or 0  the current page, untouched (the default)
 *   ?design=v2 or ?design=v1 a preview of either design on any place. A query string already
 *                            keeps a request out of the page cache, and a preview answers noindex
 *                            so the variant can never compete with the real URL.
 *
 * Both designs share the controller, so title, description, canonical, JSON-LD and the indexing
 * verdict are identical. Turning the flag off is a complete rollback.
 */
function rmt_place_v2_enabled(): bool {
    $ask = (string) ($_GET['design'] ?? '');
    if ($ask === 'v2') return true;
    if ($ask === 'v1') return false;
    return getenv('RMT_PLACE_V2') === '1';
}

/** True when this request is a design preview rather than the page itself. */
function rmt_place_design_preview(): bool {
    return in_array((string) ($_GET['design'] ?? ''), ['v1', 'v2'], true);
}
