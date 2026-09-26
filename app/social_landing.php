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
