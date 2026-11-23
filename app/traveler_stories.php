<?php
declare(strict_types=1);

/**
 * Traveler stories on city pages (2026-11-23, migration 134).
 *
 * A story arrives through the "Did something go wrong here? Tell us" form (POST /story) or the
 * after-trip email, and lands in the feedback queue as kind trip_story. Nothing goes on a page by
 * itself: an editor reads it, checks what can be checked, may shorten it, and presses Publish. Only
 * then does it show, on the warnings page and the tax note for that city, labelled for what it is:
 * one traveler's account, checked by an editor, not a RuinMyTrip finding.
 *
 * No name is shown. Stories come from people who did not make an account, and the form never asked
 * to publish a name, so "A traveler" is the only honest byline.
 */

const RMT_STORY_SHOW = 5;
const RMT_STORY_BODY_MAX = 1200;

/** A story topic is tax:{tax key} or warnings:{destination slug}. */
function rmt_story_topic_ok(string $t): bool {
    return (bool) preg_match('/^(tax|warnings):[a-z0-9-]{2,80}$/', $t);
}

/** Split "When: May 2026 | what happened" as story_submit stores it. */
function rmt_story_split(string $message): array {
    if (preg_match('/^When: (.{1,60}?) \| (.*)$/s', $message, $m)) return ['when' => trim($m[1]), 'body' => trim($m[2])];
    return ['when' => '', 'body' => trim($message)];
}

/**
 * Publish one queued story with the editor's text. Returns an error string, '' on success.
 * The feedback row is closed as resolved, noting that it was published.
 */
function rmt_story_publish(int $feedbackId, int $actorId, string $body, string $when): string {
    $f = q_one("SELECT * FROM feedback WHERE id = ? AND kind = 'trip_story'", [$feedbackId]);
    if (!$f) return 'That story is not in the queue.';
    $topic = (string) ($f['topic'] ?? '');
    if (!rmt_story_topic_ok($topic)) return 'This story is not tied to a city page, so there is nowhere to show it.';
    $body = trim((string) preg_replace("/[ \t]+/", ' ', strip_tags($body)));
    if (mb_strlen($body) < 20) return 'Too short to publish.';
    if (mb_strlen($body) > RMT_STORY_BODY_MAX) return 'Shorten it to ' . RMT_STORY_BODY_MAX . ' characters.';
    if (q_one('SELECT 1 x FROM traveler_stories WHERE feedback_id = ?', [$feedbackId])) return 'Already published.';
    $now = date('Y-m-d H:i:s');
    q_run("INSERT INTO traveler_stories (feedback_id, topic, body, when_text, published_by, published_at, status) VALUES (?,?,?,?,?,?,'live')",
          [$feedbackId, $topic, $body, mb_substr(trim(strip_tags($when)), 0, 60) ?: null, $actorId, $now]);
    if (function_exists('rmt_feedback_resolve')) rmt_feedback_resolve($feedbackId, $actorId, 'resolved', 'Published on the city page');
    return '';
}

/** Take a story down. It stays in the table, marked down, so the decision is on record. */
function rmt_story_take_down(int $storyId): bool {
    if (!q_one("SELECT 1 x FROM traveler_stories WHERE id = ? AND status = 'live'", [$storyId])) return false;
    q_run("UPDATE traveler_stories SET status = 'down' WHERE id = ?", [$storyId]);
    return true;
}

/** Live stories for any of these topics, newest first. */
function rmt_stories_for(array $topics, int $limit = RMT_STORY_SHOW): array {
    $topics = array_values(array_filter(array_unique($topics), 'rmt_story_topic_ok'));
    if (!$topics) return [];
    $ph = implode(',', array_fill(0, count($topics), '?'));
    return q_all("SELECT * FROM traveler_stories WHERE status = 'live' AND topic IN ($ph) ORDER BY published_at DESC, id DESC LIMIT " . max(1, $limit), $topics);
}

/** The live story published from a feedback row, if any: for the queue. */
function rmt_story_for_feedback(int $feedbackId): ?array {
    $s = q_one('SELECT * FROM traveler_stories WHERE feedback_id = ?', [$feedbackId]);
    return $s ?: null;
}

/** Both topics a city's pages share: its tax key and its warnings slug. */
function rmt_story_topics_for_dest(string $destSlug): array {
    $out = ['warnings:' . $destSlug];
    $k = function_exists('rmt_tt_key_for_dest') ? rmt_tt_key_for_dest($destSlug) : null;
    if ($k !== null) $out[] = 'tax:' . $k;
    return $out;
}

/* ---------- admin ---------- */

/** POST /admin/stories/publish */
function admin_story_publish(array $a): void {
    require_role('admin', 'mod');
    csrf_check();
    $me = current_user();
    $err = rmt_story_publish((int) input('id'), (int) $me['id'], (string) input('body'), (string) input('when'));
    flash($err === '' ? 'Published on the city page.' : $err);
    redirect('/admin/feedback');
}

/** POST /admin/stories/down */
function admin_story_down(array $a): void {
    require_role('admin', 'mod');
    csrf_check();
    flash(rmt_story_take_down((int) input('story')) ? 'Taken down.' : 'Could not take that down.');
    redirect('/admin/feedback?status=resolved');
}
