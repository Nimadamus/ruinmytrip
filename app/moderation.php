<?php
declare(strict_types=1);

/**
 * Moderation: the one path by which content changes state, and the record of why.
 *
 * The rules this enforces are the ones a review site gets wrong under pressure:
 *
 *   1. A REPORT IS NOT A VERDICT. Reporting creates a queue item and changes nothing. There is no
 *      threshold at which reports hide something automatically, and there is no code path here
 *      that reads a report count — mob voting is not moderation, and on a site whose whole value
 *      is candid reviews it is the fastest way to lose the candid ones.
 *   2. CRITICISM IS NOT A VIOLATION. Nothing here looks at a rating. A one-star review saying the
 *      service was terrible and they would not return is an ordinary traveler opinion, and a
 *      moderation system that can act on it because it is negative is a system that quietly works
 *      for businesses rather than travelers. What gets moderated is behaviour — spam, abuse,
 *      fraud, personal information — and behaviour is a property of the content, never of the score.
 *   3. NOTHING VANISHES WITHOUT A RECORD. Every action writes a moderation_log row through this
 *      function, including what the status was before, so "restored" means something and a
 *      decision can be looked at again later.
 *
 * Content is never physically deleted here. Hidden and removed are statuses; the row stays so the
 * history stays, and every public aggregate on the site already filters on status = 'published'.
 */

/** What a moderator can do. `remove` is for content that should not come back; `hide` is reversible. */
const RMT_MOD_ACTIONS = ['dismiss', 'hide', 'remove', 'restore'];

/** The statuses those actions move content into. Dismiss moves nothing. */
const RMT_MOD_STATUS = ['hide' => 'hidden', 'remove' => 'removed', 'restore' => 'published'];

/**
 * Apply one moderation decision and record it.
 *
 * @param  string $targetType a key of RMT_REPORT_TARGETS
 * @return array{ok:bool,error?:string,from?:?string,to?:?string}
 */
function rmt_moderate(int $actorId, string $targetType, int $targetId, string $action,
                      ?int $reportId = null, string $note = ''): array {
    if (!in_array($action, RMT_MOD_ACTIONS, true)) return ['ok' => false, 'error' => 'Unknown action.'];
    $table = RMT_REPORT_TARGETS[$targetType] ?? null;
    if (!$table) return ['ok' => false, 'error' => 'Not something that can be moderated.'];

    // An account is not content: suspending one is a different decision with different
    // consequences, and it is not made by pressing hide on a report.
    $movesStatus = $targetType !== 'user' && isset(RMT_MOD_STATUS[$action]);

    $from = null;
    $to = null;
    if ($movesStatus) {
        $row = q_one("SELECT status FROM {$table} WHERE id = ?", [$targetId]);
        if (!$row) return ['ok' => false, 'error' => 'That content no longer exists.'];
        $from = (string) $row['status'];
        $to = RMT_MOD_STATUS[$action];
        if ($from !== $to) {
            q_run("UPDATE {$table} SET status = ? WHERE id = ?", [$to, $targetId]);
        }
    }

    q_run('INSERT INTO moderation_log
           (actor_id, target_type, target_id, report_id, action, from_status, to_status, note, created_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$actorId ?: null, $targetType, $targetId, $reportId ?: null, $action,
           $from, $to, mb_substr(trim($note), 0, 500) ?: null, date('Y-m-d H:i:s')]);

    return ['ok' => true, 'from' => $from, 'to' => $to];
}

/**
 * The moderation queue: open reports with enough context to decide without opening five tabs.
 *
 * Reports about the same thing are grouped, because five reports about one review is one decision
 * and a queue that lists it five times is a queue that gets skimmed. The count is shown so a
 * moderator can see the volume — and it is shown as information, never used as a rule.
 */
function rmt_moderation_queue(int $limit = 100): array {
    $rows = q_all("SELECT r.target_type, r.target_id,
                          COUNT(*) reports,
                          MIN(r.id) first_report_id,
                          MAX(r.created_at) last_reported,
                          MIN(r.reason) reason
                     FROM reports r
                    WHERE r.status = 'open'
                    GROUP BY r.target_type, r.target_id
                    ORDER BY MAX(r.created_at) DESC
                    LIMIT " . max(1, $limit));

    foreach ($rows as &$row) {
        $row['reports'] = (int) $row['reports'];
        $row['reasons'] = array_column(
            q_all("SELECT DISTINCT reason FROM reports
                    WHERE status='open' AND target_type=? AND target_id=?",
                  [$row['target_type'], (int) $row['target_id']]), 'reason');
        $row['context'] = rmt_moderation_context((string) $row['target_type'], (int) $row['target_id']);
        $row['history'] = q_all("SELECT action, to_status, created_at FROM moderation_log
                                  WHERE target_type = ? AND target_id = ?
                                  ORDER BY id DESC LIMIT 3",
                                [$row['target_type'], (int) $row['target_id']]);
    }
    unset($row);
    return $rows;
}

/**
 * Enough of the reported thing to judge it: what it says, who wrote it, where it lives.
 *
 * A moderator deciding without the text in front of them is deciding on the report's word, which
 * is exactly the failure mode that removes legitimate criticism.
 */
function rmt_moderation_context(string $targetType, int $targetId): array {
    $out = ['title' => null, 'excerpt' => null, 'author' => null, 'status' => null,
            'url' => null, 'where' => null, 'rating' => null];

    if ($targetType === 'review') {
        $r = q_one("SELECT r.id, r.slug, r.title, r.body, r.rating, r.status, r.subject_name,
                           u.username, p.slug place_slug, p.name place_name,
                           d.name dest_name
                      FROM reviews r
                      JOIN users u ON u.id = r.user_id
                      LEFT JOIN places p ON p.id = r.place_id
                      LEFT JOIN destinations d ON d.id = r.destination_id
                     WHERE r.id = ?", [$targetId]);
        if (!$r) return $out;
        return [
            'title'   => (string) ($r['title'] ?: $r['subject_name']),
            'excerpt' => mb_strimwidth(strip_tags((string) $r['body']), 0, 400, '…'),
            'author'  => (string) $r['username'],
            'status'  => (string) $r['status'],
            'url'     => url('review/' . (int) $r['id'] . '/' . ($r['slug'] ?: '')),
            'where'   => trim(($r['place_name'] ?? $r['subject_name'] ?? '') . ($r['dest_name'] ? ', ' . $r['dest_name'] : ''), ', '),
            // Shown for context and for nothing else. No rule in this file reads it.
            'rating'  => isset($r['rating']) ? (int) $r['rating'] : null,
        ];
    }

    $table = RMT_REPORT_TARGETS[$targetType] ?? null;
    if (!$table) return $out;
    $row = q_one("SELECT * FROM {$table} WHERE id = ?", [$targetId]);
    if (!$row) return $out;
    $out['title']  = (string) ($row['title'] ?? $row['username'] ?? ('#' . $targetId));
    $out['excerpt'] = isset($row['body']) ? mb_strimwidth(strip_tags((string) $row['body']), 0, 300, '…') : null;
    $out['status'] = isset($row['status']) ? (string) $row['status'] : null;
    return $out;
}

/** What a moderator has done lately, for the audit view. */
function rmt_moderation_history(int $limit = 100): array {
    return q_all("SELECT m.*, u.username actor FROM moderation_log m
                   LEFT JOIN users u ON u.id = m.actor_id
                   ORDER BY m.id DESC LIMIT " . max(1, $limit));
}
