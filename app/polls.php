<?php
/**
 * Polls on posts (migration 067).
 *
 * A poll is the cheapest thing a member can add to a post and the thing most likely to get a
 * stranger to touch the page: a vote costs one click and no words. Everything here is a live
 * count; there is no stored tally to drift.
 */
declare(strict_types=1);

const RMT_POLL_MIN_OPTIONS = 2;
const RMT_POLL_MAX_OPTIONS = 4;
const RMT_POLL_LABEL_MAX   = 60;
const RMT_POLL_DAYS        = [1, 3, 7];

/**
 * Validate the poll part of a post form. An absent poll (every option blank) is fine and returns
 * ok with no options: the post is just a post.
 *
 * @return array{ok:bool, errors:string[], options:string[], days:int}
 */
function rmt_poll_validate(array $in): array {
    $raw = $in['poll'] ?? [];
    if (!is_array($raw)) $raw = [];
    $options = [];
    foreach ($raw as $o) {
        $o = trim(preg_replace('/\s+/', ' ', (string) $o) ?? '');
        if ($o === '') continue;
        $options[] = $o;
    }
    $days = (int) ($in['poll_days'] ?? 3);
    if (!in_array($days, RMT_POLL_DAYS, true)) $days = 3;
    if ($options === []) return ['ok' => true, 'errors' => [], 'options' => [], 'days' => $days];

    $errors = [];
    if (count($options) < RMT_POLL_MIN_OPTIONS) $errors[] = 'A poll needs at least two choices.';
    if (count($options) > RMT_POLL_MAX_OPTIONS) $errors[] = 'A poll can have at most ' . RMT_POLL_MAX_OPTIONS . ' choices.';
    foreach ($options as $o) {
        if (mb_strlen($o) > RMT_POLL_LABEL_MAX) { $errors[] = 'Keep each choice under ' . RMT_POLL_LABEL_MAX . ' characters.'; break; }
    }
    $lower = array_map(fn($o) => mb_strtolower($o), $options);
    if (count(array_unique($lower)) !== count($lower)) $errors[] = 'Two of the choices are the same.';
    return ['ok' => $errors === [], 'errors' => $errors, 'options' => $options, 'days' => $days];
}

function rmt_poll_create(int $postId, array $options, int $days): void {
    $now = date('Y-m-d H:i:s');
    $closes = date('Y-m-d H:i:s', time() + $days * 86400);
    q_run('INSERT INTO post_polls (post_id, closes_at, created_at) VALUES (?,?,?)', [$postId, $closes, $now]);
    foreach (array_values($options) as $i => $label) {
        q_run('INSERT INTO poll_options (post_id, position, label) VALUES (?,?,?)', [$postId, $i, $label]);
    }
}

function rmt_poll_is_closed(?string $closesAt): bool {
    return $closesAt !== null && $closesAt !== '' && $closesAt <= date('Y-m-d H:i:s');
}

/**
 * The poll on one post with live counts, or null when the post has none.
 *
 * @return ?array{post_id:int, closes_at:?string, closed:bool, total:int, my_option_id:?int,
 *                options:list<array{id:int,label:string,votes:int,pct:int}>}
 */
function rmt_poll_for_post(int $postId, ?int $userId = null): ?array {
    $polls = rmt_polls_for_posts([$postId], $userId);
    return $polls[$postId] ?? null;
}

/**
 * Polls for a page of posts in three queries, keyed by post id. A stream of forty posts must not
 * cost forty round trips to find out that two of them have a poll.
 *
 * @param int[] $postIds
 * @return array<int, array>
 */
function rmt_polls_for_posts(array $postIds, ?int $userId = null): array {
    $postIds = array_values(array_unique(array_map('intval', $postIds)));
    if ($postIds === []) return [];
    $ph = implode(',', array_fill(0, count($postIds), '?'));
    $out = [];
    foreach (q_all("SELECT post_id, closes_at FROM post_polls WHERE post_id IN ($ph)", $postIds) as $r) {
        $out[(int) $r['post_id']] = [
            'post_id' => (int) $r['post_id'], 'closes_at' => $r['closes_at'],
            'closed' => rmt_poll_is_closed($r['closes_at']), 'total' => 0, 'my_option_id' => null, 'options' => [],
        ];
    }
    if ($out === []) return [];
    $ids = array_keys($out);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = q_all("SELECT o.id, o.post_id, o.label, o.position,
                          (SELECT COUNT(*) FROM poll_votes v WHERE v.option_id = o.id) votes
                     FROM poll_options o WHERE o.post_id IN ($ph) ORDER BY o.post_id, o.position", $ids);
    foreach ($rows as $r) {
        $pid = (int) $r['post_id'];
        $out[$pid]['options'][] = ['id' => (int) $r['id'], 'label' => (string) $r['label'], 'votes' => (int) $r['votes'], 'pct' => 0];
        $out[$pid]['total'] += (int) $r['votes'];
    }
    foreach ($out as &$poll) {
        foreach ($poll['options'] as &$o) {
            $o['pct'] = $poll['total'] > 0 ? (int) round($o['votes'] * 100 / $poll['total']) : 0;
        }
        unset($o);
    }
    unset($poll);
    if ($userId) {
        foreach (q_all("SELECT post_id, option_id FROM poll_votes WHERE user_id=? AND post_id IN ($ph)", array_merge([$userId], $ids)) as $r) {
            $out[(int) $r['post_id']]['my_option_id'] = (int) $r['option_id'];
        }
    }
    return $out;
}

/**
 * Cast or change a vote. One row per member per poll; voting again moves it. Closed polls refuse.
 *
 * @return array{ok:bool, error?:string}
 */
function rmt_poll_vote(int $postId, int $optionId, int $userId): array {
    $poll = q_one('SELECT closes_at FROM post_polls WHERE post_id=?', [$postId]);
    if (!$poll) return ['ok' => false, 'error' => 'There is no poll on that post.'];
    if (rmt_poll_is_closed($poll['closes_at'])) return ['ok' => false, 'error' => 'That poll has closed.'];
    $opt = q_one('SELECT id FROM poll_options WHERE id=? AND post_id=?', [$optionId, $postId]);
    if (!$opt) return ['ok' => false, 'error' => 'Pick one of the choices.'];
    $now = date('Y-m-d H:i:s');
    $existing = q_one('SELECT option_id FROM poll_votes WHERE post_id=? AND user_id=?', [$postId, $userId]);
    if ($existing) {
        if ((int) $existing['option_id'] !== $optionId) {
            q_run('UPDATE poll_votes SET option_id=?, created_at=? WHERE post_id=? AND user_id=?', [$optionId, $now, $postId, $userId]);
        }
    } else {
        q_run('INSERT INTO poll_votes (post_id, option_id, user_id, created_at) VALUES (?,?,?,?)', [$postId, $optionId, $userId, $now]);
    }
    return ['ok' => true];
}

/** "Closes in 2 days" / "Final". */
function rmt_poll_closes_label(?array $poll): string {
    if (!$poll) return '';
    if ($poll['closed']) return 'Final';
    $secs = strtotime((string) $poll['closes_at']) - time();
    if ($secs < 3600) return 'Closes in under an hour';
    if ($secs < 86400) return 'Closes in ' . max(1, (int) round($secs / 3600)) . 'h';
    $d = (int) round($secs / 86400);
    return 'Closes in ' . $d . ($d === 1 ? ' day' : ' days');
}
