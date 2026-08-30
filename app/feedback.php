<?php
/**
 * "Something here is wrong" -- corrections to a place, and messages about the site.
 *
 * One rule governs the whole file: NOTHING SUBMITTED HERE CHANGES ANYTHING. A submission is a
 * message that a person reads and acts on by hand. A form that could mark a restaurant permanently
 * closed on the strength of one anonymous click is a denial-of-service tool with a friendly label,
 * and the reasoning is the same one that stops a report from hiding a review: the person reporting
 * is telling us something, not deciding something.
 *
 * The second rule is that reporting a mistake must be easier than living with it. No account
 * required, no email required, one field that has to be filled in. A correction we never hear about
 * because the form asked for a login is worse for the reader than one we hear about anonymously and
 * have to check.
 */

declare(strict_types=1);

/**
 * What can be wrong. Closed, in both senses: a closed list because these get counted and filtered,
 * and because a free-text "reason" field becomes a different sentence every time.
 */
const RMT_FEEDBACK_KINDS = [
    'closed_permanently' => 'It has closed down',
    'closed_temporarily' => 'It is closed for now',
    'moved'              => 'It has moved',
    'wrong_hours'        => 'The opening hours are wrong',
    'wrong_address'      => 'The address is wrong',
    'wrong_contact'      => 'The phone number or website is wrong',
    'wrong_category'     => 'It is filed under the wrong kind of place',
    'duplicate'          => 'This place is on the site twice',
    'other_place'        => 'Something else about this place',
    'site_problem'       => 'Something on the site is broken',
    'privacy_request'    => 'A privacy request',
    'general'            => 'General feedback',
];

/** The ones that belong to a place, and are offered on a place page. */
const RMT_FEEDBACK_PLACE_KINDS = [
    'closed_permanently', 'closed_temporarily', 'moved', 'wrong_hours', 'wrong_address',
    'wrong_contact', 'wrong_category', 'duplicate', 'other_place',
];

const RMT_FEEDBACK_STATUSES = ['pending', 'resolved', 'rejected', 'duplicate'];

/** Short enough to read, long enough to explain. */
const RMT_FEEDBACK_MIN = 4;
const RMT_FEEDBACK_MAX = 2000;

/**
 * Record one submission.
 *
 * @return array{ok:bool,error:?string,id:?int}
 */
function rmt_feedback_submit(string $kind, ?int $placeId, string $message,
                             ?int $userId = null, string $email = ''): array {
    $fail = static fn(string $e): array => ['ok' => false, 'error' => $e, 'id' => null];

    if (!isset(RMT_FEEDBACK_KINDS[$kind])) return $fail('Pick what is wrong.');

    $isPlaceKind = in_array($kind, RMT_FEEDBACK_PLACE_KINDS, true);
    if ($isPlaceKind && !$placeId) return $fail('That correction needs a place.');
    if (!$isPlaceKind) $placeId = null;      // a site problem is not about a place, whatever was posted
    if ($placeId && !q_one("SELECT 1 FROM places WHERE id = ? AND status = 'active'", [$placeId])) {
        return $fail('We could not find that place.');
    }

    $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
    if (mb_strlen($message) < RMT_FEEDBACK_MIN) return $fail('Tell us what is wrong, in a sentence or two.');
    if (mb_strlen($message) > RMT_FEEDBACK_MAX) return $fail('That is too long. A couple of sentences is plenty.');

    // Optional, and only so we can reply. An invalid one is dropped rather than refused: somebody
    // mistyping their address should not cost us the correction.
    $email = trim($email);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    if (mb_strlen($email) > 190) $email = '';

    $id = (int) q_run(
        "INSERT INTO feedback (kind, place_id, message, contact_email, reported_by, status, created_at)
         VALUES (?,?,?,?,?,'pending',?)",
        [$kind, $placeId, $message, $email !== '' ? $email : null, $userId, date('Y-m-d H:i:s')]);

    return ['ok' => true, 'error' => null, 'id' => $id];
}

/**
 * The queue, newest first.
 *
 * @return list<array>
 */
function rmt_feedback_queue(string $status = 'pending', int $limit = 100): array {
    if (!in_array($status, RMT_FEEDBACK_STATUSES, true)) $status = 'pending';
    return q_all(
        "SELECT f.*, p.slug place_slug, p.name place_name, p.type place_type,
                d.name dest_name, u.username reporter
           FROM feedback f
           LEFT JOIN places p ON p.id = f.place_id
           LEFT JOIN destinations d ON d.id = p.destination_id
           LEFT JOIN users u ON u.id = f.reported_by
          WHERE f.status = ?
          ORDER BY f.created_at DESC, f.id DESC
          LIMIT " . max(1, $limit), [$status]);
}

/** How many are waiting, so the admin index can say so without loading them. */
function rmt_feedback_pending_count(): int {
    return (int) (q_one("SELECT COUNT(*) c FROM feedback WHERE status = 'pending'")['c'] ?? 0);
}

/**
 * Close one submission.
 *
 * Records who and when, and never touches the place. Whatever the correction says, changing the
 * data is a separate deliberate act in the place editor -- which is the whole point: the queue
 * tells a human what to look at, and the human decides what is true.
 */
function rmt_feedback_resolve(int $id, int $actorId, string $status, string $note = ''): bool {
    if (!in_array($status, ['resolved', 'rejected', 'duplicate'], true)) return false;
    $row = q_one("SELECT id FROM feedback WHERE id = ?", [$id]);
    if (!$row) return false;
    q_run("UPDATE feedback SET status = ?, resolved_by = ?, resolved_at = ?, resolution_note = ?
            WHERE id = ?",
          [$status, $actorId, date('Y-m-d H:i:s'), mb_substr(trim($note), 0, 500), $id]);
    return true;
}

/** Human wording for a kind, for the queue and the confirmation. */
function rmt_feedback_kind_label(string $kind): string {
    return RMT_FEEDBACK_KINDS[$kind] ?? $kind;
}

/**
 * Has somebody already told us this about this place?
 *
 * Shown to nobody publicly -- a place page must not announce "3 people say this is closed", which
 * would be a rating of its own and exactly the mob signal we keep out of moderation. It exists so
 * the admin queue can group and so a duplicate submission does not read as new information.
 */
function rmt_feedback_open_for_place(int $placeId): int {
    return (int) (q_one("SELECT COUNT(*) c FROM feedback WHERE place_id = ? AND status = 'pending'",
                        [$placeId])['c'] ?? 0);
}
