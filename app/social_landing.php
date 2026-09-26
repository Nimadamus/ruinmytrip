<?php
declare(strict_types=1);

/**
 * Somebody tapped one of our social posts. The page they land on says which post, in one line, and
 * offers the thing the post asked for: answer the question here, or post the trip.
 *
 * The post is looked up in docs/social/content_bank.json by its utm_content id (Facebook posts, one
 * link each). Instagram and TikTok carry one bio link per profile, so a bio visitor is shown the
 * post of the day from the bank's calendar instead. Shown on the landing request only, and nothing
 * about the person is stored.
 */

const RMT_SOCIAL_CAMPAIGNS = ['social_v1', 'profile'];

/** @return array<string,mixed> the content bank, or [] */
function rmt_social_bank(): array {
    static $bank = null;
    if ($bank !== null) return $bank;
    $f = BASE_PATH . '/docs/social/content_bank.json';
    $bank = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    return $bank;
}

/** The post a bio visitor most likely just saw: today's in the calendar, else the last one. */
function rmt_social_post_of_day(?string $today = null): ?array {
    $b = rmt_social_bank();
    $cal = $b['calendar'] ?? null;
    if (!$cal || empty($cal['days'])) return null;
    $today = $today ?? date('Y-m-d');
    $i = (int) floor((strtotime($today) - strtotime((string) $cal['start'])) / 86400);
    if ($i < 0) return null;
    $id = $cal['days'][min($i, count($cal['days']) - 1)];
    foreach ($b['posts'] ?? [] as $p) if ($p['id'] === $id) return $p;
    return null;
}

/** The social post behind this visit, or null. */
function rmt_social_landing(): ?array {
    $camp = (string) input('utm_campaign');
    $src = (string) input('utm_source');
    $post = null;
    if (in_array($camp, RMT_SOCIAL_CAMPAIGNS, true) && in_array($src, ['facebook', 'instagram', 'tiktok'], true)) {
        $id = (string) input('utm_content');
        foreach (rmt_social_bank()['posts'] ?? [] as $p) if ($p['id'] === $id) $post = $p;
        if (!$post && $camp === 'profile') $post = rmt_social_post_of_day();
    }
    return $post;
}

/** Where "answer it" goes for a post: the composer on the page it links to. */
function rmt_social_answer_url(array $post): string {
    $path = (string) ($post['link_path'] ?? '/talk');
    if ($path === '/ruined') return url('ruined') . '#ruined-text';
    if (preg_match('#^/d/([a-z0-9\-]+)$#', $path, $m)) return url('d/' . $m[1]) . '#city-ask';
    if (str_starts_with($path, '/buddies') || str_starts_with($path, '/travel-buddies')) {
        return url('plan?buddy=1&cta=social_answer');
    }
    return url('talk') . '#say';
}

/** The question, as one line, from the post's Facebook text. */
function rmt_social_question(array $post): string {
    $t = (string) ($post['facebook'] ?? '');
    if (preg_match('/[^?]*\?/', $t, $m)) $t = $m[0];
    return trim(excerpt($t, 160));
}

/**
 * GET /cron/social?key=CRON_KEY
 *
 * The site feeding the social channels: what is happening here that is worth a post. Aggregates
 * only for members (a city with several travelers going, a country with open buddy requests), never
 * a name, never one person's dates. Member questions come back marked for review, because posting
 * somebody's words on our account is a choice a person makes. Research warnings are marked for
 * review too, because they make claims about places and every published fact has to be checked.
 * The team's own questions and the city prompts need no review.
 */
function cron_social(array $a): void {
    $key = (string) (getenv('CRON_KEY') ?: '');
    $given = (string) input('key');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) not_found();
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $today = date('Y-m-d');
    $ed = defined('RMT_EDITORIAL_ROLE') ? RMT_EDITORIAL_ROLE : 'editorial';
    $safe = static function (callable $f): array { try { return $f(); } catch (Throwable $e) { return []; } };

    $going = $safe(static fn() => q_all("SELECT d.slug, d.name, COUNT(DISTINCT t.user_id) n, MIN(t.date_from) first_from
          FROM trips t JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
          JOIN destinations d ON d.id = t.destination_id
         WHERE t.status = 'published' AND COALESCE(t.visibility, 'public') = 'public'
           AND t.date_from IS NOT NULL AND t.date_to >= ?
      GROUP BY d.slug, d.name HAVING COUNT(DISTINCT t.user_id) >= 2 ORDER BY n DESC LIMIT 10", [$ed, $today]));
    $buddies = $safe(static fn() => q_all("SELECT d.slug, d.name, d.country, COUNT(*) n FROM buddy_posts b
          JOIN users u ON u.id = b.user_id AND u.status = 'active'
          JOIN destinations d ON d.id = b.destination_id
         WHERE b.status = 'open' AND b.date_to >= ?
      GROUP BY d.slug, d.name, d.country HAVING COUNT(*) >= 2 ORDER BY n DESC LIMIT 10", [$today]));
    $questions = $safe(static fn() => q_all("SELECT p.id, p.body, p.created_at, d.slug dest_slug, d.name dest_name,
               SUBSTR(u.username, 1, 5) = 'team_' AS ours,
               (SELECT COUNT(*) FROM comments c WHERE c.target_type = 'post' AND c.target_id = p.id AND c.status = 'published') replies
          FROM posts p JOIN users u ON u.id = p.user_id AND u.status = 'active'
     LEFT JOIN destinations d ON d.id = p.destination_id
         WHERE p.status = 'published' AND p.collection_id IS NULL AND p.created_at >= ?
      ORDER BY p.created_at DESC LIMIT 20", [date('Y-m-d H:i:s', time() - 30 * 86400)]));
    $warnings = $safe(static fn() => q_all("SELECT r.id, r.what_ruined, d.slug dest_slug, d.name dest_name, pl.name place_name
          FROM reviews r JOIN users u ON u.id = r.user_id AND u.status = 'active'
     LEFT JOIN destinations d ON d.id = r.destination_id
     LEFT JOIN places pl ON pl.id = r.place_id
         WHERE r.status = 'published' AND r.what_ruined IS NOT NULL AND TRIM(r.what_ruined) <> ''
      ORDER BY r.created_at DESC, r.id DESC LIMIT 30", []));

    echo json_encode([
        'generated_at' => date('c'),
        'cities_going' => $going,
        'buddy_countries' => $buddies,
        'questions' => array_map(static fn($q) => [
            'id' => (int) $q['id'], 'body' => excerpt((string) $q['body'], 240), 'dest_slug' => $q['dest_slug'],
            'dest_name' => $q['dest_name'], 'replies' => (int) $q['replies'], 'review' => !(bool) $q['ours'],
        ], $questions),
        'warnings' => array_map(static fn($w) => [
            'id' => (int) $w['id'], 'text' => excerpt((string) $w['what_ruined'], 240), 'dest_slug' => $w['dest_slug'],
            'dest_name' => $w['dest_name'], 'place_name' => $w['place_name'], 'review' => true,
        ], $warnings),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}
