<?php
declare(strict_types=1);

/**
 * Review domain logic: validation, slugs, ownership, permalinks.
 *
 * Server-side validation lives here and is the ONLY thing standing between user input and the
 * database. Client-side `required` attributes are a convenience, never a control — every rule
 * below is re-checked on the server regardless of what the browser sent. On pgsql the CHECK
 * constraints from migration 003 are a second, independent backstop.
 */

const RMT_REVIEW_CATEGORIES = ['destination', 'hotel', 'restaurant', 'attraction', 'experience'];
const RMT_REVIEW_STATUSES   = ['draft', 'published', 'hidden', 'removed'];

const RMT_REVIEW_BODY_MIN   = 40;
const RMT_REVIEW_BODY_MAX   = 12000;
const RMT_REVIEW_TITLE_MAX  = 140;
const RMT_REVIEW_FIELD_MAX  = 2000;

/**
 * Validate and normalise a submitted review.
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_review_validate(array $in, bool $isDraft): array {
    $errors = [];

    $destId   = (int) ($in['destination_id'] ?? 0);
    $category = (string) ($in['subject_type'] ?? '');
    $subject  = trim((string) ($in['subject_name'] ?? ''));
    $title    = trim((string) ($in['title'] ?? ''));
    $body     = trim((string) ($in['body'] ?? ''));
    $great    = trim((string) ($in['what_great'] ?? ''));
    $ruined   = trim((string) ($in['what_ruined'] ?? ''));
    $visited  = trim((string) ($in['visited_on'] ?? ''));

    $rating = (int) ($in['rating'] ?? 0);
    $safety = ($in['safety_rating'] ?? '') === '' ? null : (int) $in['safety_rating'];
    $value  = ($in['value_rating']  ?? '') === '' ? null : (int) $in['value_rating'];

    if (!in_array($category, RMT_REVIEW_CATEGORIES, true)) $errors[] = 'Choose what you are reviewing.';

    if ($destId > 0 && !dest_by_id($destId)) $errors[] = 'That destination does not exist.';

    // A draft may be incomplete — that is the point of a draft. Publishing is held to the full bar.
    if (!$isDraft) {
        if ($destId <= 0)      $errors[] = 'Choose a destination.';
        if ($subject === '')   $errors[] = 'Name what you are reviewing.';
        if ($title === '')     $errors[] = 'Add a headline.';
        if ($rating < 1 || $rating > 5) $errors[] = 'Give an overall rating from 1 to 5.';
        if (mb_strlen($body) < RMT_REVIEW_BODY_MIN) {
            $errors[] = 'Write at least ' . RMT_REVIEW_BODY_MIN . ' characters so the review is useful.';
        }
    } else {
        if ($rating !== 0 && ($rating < 1 || $rating > 5)) $errors[] = 'Rating must be from 1 to 5.';
    }

    if (mb_strlen($title)  > RMT_REVIEW_TITLE_MAX) $errors[] = 'Headline is too long.';
    if (mb_strlen($body)   > RMT_REVIEW_BODY_MAX)  $errors[] = 'Review is too long.';
    if (mb_strlen($great)  > RMT_REVIEW_FIELD_MAX) $errors[] = '"What was great?" is too long.';
    if (mb_strlen($ruined) > RMT_REVIEW_FIELD_MAX) $errors[] = '"What nearly ruined the trip?" is too long.';
    if (mb_strlen($subject) > 200) $errors[] = 'That name is too long.';

    foreach (['safety_rating' => $safety, 'value_rating' => $value] as $k => $v) {
        if ($v !== null && ($v < 1 || $v > 5)) $errors[] = 'Ratings must be from 1 to 5.';
    }

    // A trip date in the future would mean reviewing a trip that has not happened.
    if ($visited !== '') {
        $ts = strtotime($visited);
        if ($ts === false)        $errors[] = 'That trip date is not a valid date.';
        elseif ($ts > time())     $errors[] = 'Your trip date is in the future.';
        elseif ($ts < strtotime('-30 years')) $errors[] = 'That trip date is too far in the past.';
    }

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'destination_id' => $destId ?: null,
        'subject_type'   => $category,
        'subject_name'   => $subject,
        'title'          => $title,
        'body'           => $body,
        'what_great'     => $great ?: null,
        'what_ruined'    => $ruined ?: null,
        'visited_on'     => $visited ?: null,
        'rating'         => max(1, min(5, $rating ?: 1)),  // NOT NULL in schema; drafts default to 1
        'safety_rating'  => $safety,
        'value_rating'   => $value,
    ]];
}

/** URL slug for a review permalink. Never empty — falls back to the id. */
function rmt_review_slug(array $r): string {
    $base = trim((string) ($r['title'] ?: $r['subject_name'] ?: ''));
    $slug = slugify($base);
    if ($slug === '') $slug = 'review';
    return mb_substr($slug, 0, 70);
}

/** Canonical path for a review. */
function rmt_review_path(array $r): string {
    return '/review/' . (int) $r['id'] . '/' . ($r['slug'] ?: rmt_review_slug($r));
}

/** Fetch one review with its author and destination. */
function rmt_review_get(int $id): ?array {
    return q_one('SELECT r.*, d.name dest_name, d.slug dest_slug, u.username, u.status user_status
                  FROM reviews r
                  LEFT JOIN destinations d ON d.id = r.destination_id
                  JOIN users u ON u.id = r.user_id
                  WHERE r.id = ?', [$id]);
}

/**
 * Can $user see $review?
 * Published = everyone. Draft = author only. Hidden/removed = author and moderators, so a user
 * can still see (and fix) their own moderated content rather than it silently vanishing.
 */
function rmt_review_can_view(array $r, ?array $user): bool {
    if ($r['status'] === 'published') return true;
    if (!$user) return false;
    if ((int) $r['user_id'] === (int) $user['id']) return true;
    return in_array($user['role'], ['admin', 'mod'], true);
}

/** Only the author may edit or delete. Moderators hide via the report queue, they do not edit. */
function rmt_review_can_edit(array $r, ?array $user): bool {
    return $user !== null && (int) $r['user_id'] === (int) $user['id'];
}
