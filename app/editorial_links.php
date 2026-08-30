<?php
/**
 * Which of our own entities does a piece of editorial actually talk about?
 *
 * We have 80 guides. Every one links to its destination and to nothing else -- not the museum it
 * spends a paragraph on, not the neighborhood it tells you to stay in. A reader who has just been
 * told the Louvre now charges non-EU visitors more has no way to get to the Louvre page, and the
 * page has no way back. Eighty articles sat one link deep in a graph they should be threading.
 *
 * THE MATCH IS DELIBERATELY CONSERVATIVE, because the failure mode of automatic linking is worse
 * than the gap it fills. A guide that links every occurrence of "museum" and "restaurant" is
 * keyword spam that happens to be internal, and it teaches a reader to stop trusting the links.
 * So:
 *
 *   - Only entities in the SAME destination as the guide. A Paris guide mentioning "Old Town" does
 *     not link to Prague's.
 *   - Only names long enough to be a name. "Rules" is a real London restaurant and also an
 *     ordinary English word, and matching it would put a link on the sentence "the rules changed".
 *     Short names are skipped rather than guessed at.
 *   - Whole words only, accent- and case-insensitive, so "cafe savoy" finds "Café Savoy" and
 *     "Procopes" does not match "Procope".
 *   - A capped list. If a guide really names twelve venues, the eight it names first are enough to
 *     be useful; more is a directory.
 *
 * Nothing is stored. This is computed when a page renders, from the text as written, so an edit to
 * a guide changes its links immediately and there is no second copy of the relationship to go
 * stale.
 */

declare(strict_types=1);

/** Below this, a name is a word. "Rules", "Ondine" and "Ratana" are all real venues and all traps. */
const RMT_LINK_MIN_NAME = 8;

/** Enough to be useful, few enough to still be a list of what the piece is about. */
const RMT_LINK_MAX = 8;

/**
 * Entities this text genuinely names, within one destination.
 *
 * @return array{places:list<array>,areas:list<array>}
 */
function rmt_editorial_entities(int $destId, string $text): array {
    $out = ['places' => [], 'areas' => []];
    if ($destId <= 0) return $out;

    $hay = rmt_link_norm($text);
    if ($hay === '') return $out;

    foreach (q_all(
        "SELECT id, slug, name, type FROM places
          WHERE destination_id = ? AND status = 'active'
          ORDER BY LENGTH(name) DESC", [$destId]) as $p) {
        if (count($out['places']) >= RMT_LINK_MAX) break;
        if (rmt_link_mentions($hay, (string) $p['name'])) $out['places'][] = $p;
    }

    $kinds = "'" . implode("','", RMT_NB_BROWSABLE) . "'";
    foreach (q_all(
        "SELECT n.id, n.slug, n.canonical_name name, n.local_name, d.slug dest_slug
           FROM neighborhoods n JOIN destinations d ON d.id = n.destination_id
          WHERE n.destination_id = ? AND n.kind IN ($kinds)
          ORDER BY LENGTH(n.canonical_name) DESC", [$destId]) as $n) {
        if (count($out['areas']) >= RMT_LINK_MAX) break;
        if (rmt_link_mentions($hay, (string) $n['name'])
            || (!empty($n['local_name']) && rmt_link_mentions($hay, (string) $n['local_name']))) {
            $out['areas'][] = $n;
        }
    }
    return $out;
}

/** Lowercased, accent-folded, punctuation-flattened, space-padded so word boundaries are cheap. */
function rmt_link_norm(string $s): string {
    $s = rmt_search_norm(strip_tags($s));
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
    return ' ' . trim(preg_replace('/\s+/', ' ', $s) ?? '') . ' ';
}

/**
 * Does this text name that entity?
 *
 * Tries the name as written and, for a place whose slug carries its city ("Ginza Kyubey, Tokyo"),
 * the name with a trailing city dropped -- an article writes "Ginza Kyubey", not the disambiguated
 * form we store. Both must still clear the length floor on their own.
 */
function rmt_link_mentions(string $normalisedHaystack, string $name): bool {
    foreach ([$name, preg_replace('/\s*[,(].*$/u', '', $name) ?? $name] as $candidate) {
        $needle = trim(rmt_link_norm((string) $candidate));
        if (strlen($needle) < RMT_LINK_MIN_NAME) continue;
        if (str_contains($normalisedHaystack, ' ' . $needle . ' ')) return true;
    }
    return false;
}

/**
 * The published guides of a destination whose text names this place.
 *
 * The other direction, and the reason it is worth having: somebody on the Louvre page wondering
 * what the ticket change means should be able to reach the guide that explains it. Bounded to the
 * destination's own guides, so this is a handful of rows rather than a scan.
 *
 * @return list<array{slug:string,title:string}>
 */
function rmt_guides_mentioning_place(array $place, int $limit = 3): array {
    $destId = (int) ($place['destination_id'] ?? 0);
    $name = (string) ($place['name'] ?? '');
    if ($destId <= 0 || $name === '') return [];

    $out = [];
    foreach (q_all("SELECT slug, title, body, summary FROM guides
                     WHERE destination_id = ? AND status = 'published'
                     ORDER BY id DESC LIMIT 20", [$destId]) as $g) {
        if (count($out) >= $limit) break;
        $hay = rmt_link_norm((string) ($g['body'] ?? '') . ' ' . (string) ($g['summary'] ?? ''));
        if (rmt_link_mentions($hay, $name)) $out[] = ['slug' => $g['slug'], 'title' => $g['title']];
    }
    return $out;
}
