<?php
/** A strip that turns a search visitor into a member.
 *
 *  @var string $destSlug @var string $destName @var ?array $me
 *
 *  Every page that ranks on this site answers a question about a building, and then offers the
 *  reader nothing to do but leave. The one thing they cannot get from the ten other pages on that
 *  results screen is the people: who is going to this city, and when.
 *
 *  This used to be a line of text with two links. It is now an adapter onto _dest_social_cta.php,
 *  which carries the campaign window and a trip form with the city already in it, because having
 *  two components doing this job meant two of them on the same page and neither being the one that
 *  was maintained. One component, five call sites: blog, guide, place, review and trip.
 */
$dsSlug = (string) ($destSlug ?? '');
$dsName = (string) ($destName ?? '');
$dsId   = isset($destId) ? (int) $destId
        : (int) (q_one('SELECT id FROM destinations WHERE slug = ?', [$dsSlug])['id'] ?? 0);
include __DIR__ . '/_dest_social_cta.php';
