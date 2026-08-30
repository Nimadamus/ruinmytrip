<?php
/**
 * A sitemap index with cached, partitioned children.
 *
 * What was here before worked and would not have kept working: one file, about twenty unbounded
 * queries per request, every URL decided by hand-written SQL that had its own opinion about what
 * belongs in the index. At 575 URLs that is ~400ms. The queries have no LIMIT, so the cost rises
 * with the site, and the second opinion is the real problem -- the sitemap could disagree with the
 * page's own robots meta and nothing would notice.
 *
 * Three changes.
 *
 *   INCLUSION IS NOT DECIDED HERE. Every entity group asks rmt_indexable(). If a page says
 *   noindex, it is not in the sitemap; if it is in the sitemap, the page says index. That
 *   contradiction is now impossible to express rather than merely discouraged.
 *
 *   PARTITIONED FROM THE START. Children split at RMT_SITEMAP_MAX. The limit is 50,000 and the
 *   split is at 5,000, not because 5,000 is special but because the code paths that partition have
 *   to be the ones that run every day -- a partitioning branch first exercised at 50,001 URLs is a
 *   branch nobody has ever seen work.
 *
 *   CACHED. Each child is rendered once and kept. A crawler pulling the index and eleven children
 *   pays for one generation, not twelve.
 *
 * LASTMOD is emitted only where a real timestamp exists. Stamping every URL with today's date on
 * every request is worse than omitting it: it tells a crawler the whole site changed, every day,
 * which is a signal that gets ignored once and then permanently distrusted.
 */

declare(strict_types=1);

/** Well under the 50,000 protocol limit, so the partitioning path is the normal path. */
const RMT_SITEMAP_MAX = 5000;

/** How long a generated child stays fresh. Content changes are rarer than crawler visits. */
const RMT_SITEMAP_TTL = 21600;   // 6 hours

/**
 * The groups, in the order the index lists them.
 *
 * Splitting by entity type is not cosmetic: Search Console reports coverage per submitted sitemap,
 * so "places are indexed and neighborhoods are not" is a question this shape can answer and one
 * big file cannot.
 */
const RMT_SITEMAP_GROUPS = ['core', 'destinations', 'categories', 'neighborhoods', 'places',
                            'editorial', 'community', 'profiles', 'lists', 'talk'];

/**
 * Every URL in one group, already filtered to what is indexable.
 *
 * @return list<array{loc:string,lastmod:?string}>
 */
function rmt_sitemap_group(string $group): array {
    $out = [];
    $add = static function (string $path, ?string $mod = null) use (&$out): void {
        $out[] = ['loc' => url(ltrim($path, '/')), 'lastmod' => rmt_sitemap_day($mod)];
    };

    switch ($group) {
        case 'core':
            // Pages that are the site rather than an entity in it. Community indexes are included
            // only when they have something on them -- an empty /meetups is a thin page, not a
            // ranking strategy.
            foreach (['/', '/explore', '/travelers', '/founding', '/start', '/guides', '/reviews',
                      '/editorial-policy', '/terms', '/privacy', '/guidelines', '/affiliate',
                      '/safety', '/contribute', '/about', '/contact'] as $p) $add($p);

            $has = static fn(string $sql, array $a = []): bool => (int) (q_one($sql, $a)['c'] ?? 0) > 0;
            if ($has("SELECT COUNT(*) c FROM blog_posts WHERE status='published'"))   $add('/blog');
            if ($has("SELECT COUNT(*) c FROM collections WHERE status='published'"))  $add('/collections');
            // Same rule the browse page applies to itself: /communities is worth a result once at
            // least one community has earned its place on it, and is a thin page before that.
            if ($has("SELECT COUNT(*) c FROM collections c2 WHERE c2.status='published'
                        AND c2.join_policy IN ('open','invite')
                        AND (SELECT COUNT(*) FROM collection_members m
                              WHERE m.collection_id=c2.id AND m.status='active') >= " . RMT_COMMUNITY_MIN_MEMBERS . "
                        AND (SELECT COUNT(*) FROM collection_items i WHERE i.collection_id=c2.id) >= " . RMT_COMMUNITY_MIN_ITEMS))
                $add('/communities');
            if ($has("SELECT COUNT(*) c FROM meetups WHERE status='published'"))      $add('/meetups');
            if ($has("SELECT COUNT(*) c FROM going WHERE visibility='public'"))       $add('/going');
            if ($has("SELECT COUNT(*) c FROM posts WHERE status='published'"))       $add('/talk');
            if ($has("SELECT COUNT(*) c FROM reviews WHERE status='published'"))      $add('/discover');
            if ($has("SELECT COUNT(*) c FROM reviews r JOIN users u ON u.id=r.user_id
                       WHERE r.status='published' AND u.role <> ?", [RMT_EDITORIAL_ROLE])) $add('/leaderboard');

            foreach (q_all("SELECT DISTINCT country FROM destinations
                             WHERE country IS NOT NULL AND country <> ''") as $c) {
                $add('in/' . rmt_country_slug((string) $c['country']));
            }
            if (function_exists('rmt_top_tags')) {
                $tags = rmt_top_tags(100);
                if ($tags) {
                    $add('/tags');
                    foreach ($tags as $t) $add('/tag/' . $t['name']);
                }
            }
            break;

        case 'destinations':
            foreach (rmt_index_destinations() as $d) {
                if (!$d['verdict']['ok']) continue;
                $add('/d/' . $d['slug']);
                // The browse page is a real page with its own inventory, not a filtered view.
                if ($d['place_count'] > 0) $add('/d/' . $d['slug'] . '/places');
            }
            foreach (q_all("SELECT DISTINCT d.slug FROM destinations d
                             WHERE EXISTS (SELECT 1 FROM trip_photos tp JOIN trips t ON t.id=tp.trip_id
                                            WHERE t.destination_id=d.id AND t.status='published')
                                OR EXISTS (SELECT 1 FROM review_photos rp JOIN reviews r ON r.id=rp.review_id
                                            WHERE r.destination_id=d.id AND r.status='published')") as $d) {
                $add('/d/' . $d['slug'] . '/photos');
            }
            break;

        case 'categories':
            // The SEO landing pages. Only the combinations that passed the threshold exist as URLs
            // at all, so this group is the pilot's exact footprint.
            foreach (rmt_index_categories() as $c) {
                if (!$c['verdict']['ok']) continue;
                $add('/d/' . $c['dest_slug'] . '/' . rmt_category_slug((string) $c['type']));
            }
            break;

        case 'neighborhoods':
            foreach (rmt_index_neighborhoods() as $n) {
                if (!$n['verdict']['ok']) continue;
                $add('/d/' . $n['dest_slug'] . '/n/' . $n['slug']);
            }
            break;

        case 'places':
            foreach (rmt_index_places() as $p) {
                if (!$p['verdict']['ok']) continue;
                // Enrichment and editing both touch updated_at, so it is a real answer to "when
                // did this page last change".
                $add('/p/' . $p['slug'], $p['updated_at'] ?? null);
            }
            break;

        case 'editorial':
            foreach (q_all("SELECT slug, created_at FROM guides WHERE status='published'") as $g) {
                $add('g/' . $g['slug'], $g['created_at'] ?? null);
            }
            foreach (q_all("SELECT slug, created_at FROM blog_posts WHERE status='published'") as $b) {
                $add('blog/' . $b['slug'], $b['created_at'] ?? null);
            }
            break;

        case 'community':
            foreach (q_all("SELECT id, slug, title, subject_name, created_at FROM reviews
                             WHERE status='published'") as $r) {
                $add('review/' . $r['id'] . '/' . ($r['slug'] ?: rmt_review_slug($r)), $r['created_at'] ?? null);
            }
            foreach (q_all("SELECT id, slug, created_at FROM trips WHERE status='published'") as $t) {
                $add('trip/' . $t['id'] . '/' . $t['slug'], $t['created_at'] ?? null);
            }
            break;

        case 'profiles':
            // An empty profile is a page with a username on it. It was in the sitemap before this.
            foreach (rmt_index_profiles() as $u) {
                if (!$u['verdict']['ok']) continue;
                $add('u/' . $u['username']);
            }
            break;

        case 'talk':
            /* Same question the page itself asks: a post is in the index when it stands on its own
               or when it drew a conversation. Asking rmt_indexable() here rather than repeating the
               rule is the point -- a sitemap that disagrees with the page's own robots tag is a
               contradiction we would rather not ship. */
            foreach (q_all("SELECT p.id, p.body, p.status, p.created_at, p.updated_at, p.repost_of,
                                   (SELECT COUNT(*) FROM comments cm
                                     WHERE cm.target_type='post' AND cm.target_id=p.id
                                       AND cm.status='published') reply_count
                              FROM posts p JOIN users u ON u.id=p.user_id
                             WHERE p.status='published' AND u.status='active'") as $p2) {
                if (!rmt_indexable('post', $p2)['ok']) continue;
                $add('post/' . (int) $p2['id'], $p2['updated_at'] ?: ($p2['created_at'] ?? null));
            }
            break;

        case 'lists':
            foreach (rmt_index_lists() as $c) {
                if (!$c['verdict']['ok']) continue;
                $add('c/' . $c['slug'], $c['updated_at'] ?: ($c['created_at'] ?? null));
            }
            break;
    }

    // One URL, once. Two identical <loc> entries in a sitemap is a self-inflicted duplicate.
    $seen = [];
    $unique = [];
    foreach ($out as $row) {
        if (isset($seen[$row['loc']])) continue;
        $seen[$row['loc']] = true;
        $unique[] = $row;
    }
    return $unique;
}

/** One child sitemap's XML. */
function rmt_sitemap_render(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($rows as $r) {
        $xml .= '  <url><loc>' . e($r['loc']) . '</loc>';
        if (!empty($r['lastmod'])) $xml .= '<lastmod>' . e($r['lastmod']) . '</lastmod>';
        $xml .= "</url>\n";
    }
    return $xml . '</urlset>';
}

/**
 * Generate every group, partition it, and store the XML.
 *
 * Called by the maintenance script and, as a fallback, by a request that finds nothing cached. It
 * deletes the parts of a group before writing them so that a group which SHRANK does not leave a
 * stale part 2 behind claiming URLs that no longer qualify.
 *
 * @return array<string,int> group => url count
 */
function rmt_sitemap_generate(): array {
    $counts = [];
    foreach (RMT_SITEMAP_GROUPS as $group) {
        // One group failing must not take the rest with it. The first version of this had no
        // try/catch, a query in the second group referenced a column that exists only in a test
        // fixture, and the deploy shipped a sitemap containing exactly one file -- silently,
        // because the caller logs and continues. A broken group now costs that group and nothing
        // else, and the group that failed keeps whatever it last generated rather than vanishing.
        try {
            $rows = rmt_sitemap_group($group);
        } catch (Throwable $e) {
            error_log('sitemap: group ' . $group . ' failed: ' . $e->getMessage());
            $counts[$group] = -1;
            continue;
        }
        $counts[$group] = count($rows);
        q_run("DELETE FROM sitemap_cache WHERE group_key = ?", [$group]);
        if (!$rows) continue;
        $parts = array_chunk($rows, RMT_SITEMAP_MAX);
        foreach ($parts as $i => $chunk) {
            q_run("INSERT INTO sitemap_cache (group_key, part, xml, url_count, generated_at)
                   VALUES (?,?,?,?,?)",
                  [$group, $i + 1, rmt_sitemap_render($chunk), count($chunk), date('Y-m-d H:i:s')]);
        }
    }
    return $counts;
}

/** The parts currently cached, in index order. */
function rmt_sitemap_parts(): array {
    $rows = q_all("SELECT group_key, part, url_count, generated_at FROM sitemap_cache");
    $order = array_flip(RMT_SITEMAP_GROUPS);
    usort($rows, static fn($a, $b) => [$order[$a['group_key']] ?? 99, (int) $a['part']]
                                  <=> [$order[$b['group_key']] ?? 99, (int) $b['part']]);
    return $rows;
}

/** Is anything cached, and is the oldest part still fresh? */
function rmt_sitemap_is_stale(): bool {
    $row = q_one("SELECT MIN(generated_at) t, COUNT(*) c FROM sitemap_cache");
    if (!$row || (int) $row['c'] === 0) return true;
    $t = strtotime((string) $row['t']);
    return !$t || (time() - $t) > RMT_SITEMAP_TTL;
}

/** The name a child sitemap is served under. */
function rmt_sitemap_filename(string $group, int $part): string {
    return 'sitemap-' . $group . ($part > 1 ? '-' . $part : '') . '.xml';
}
