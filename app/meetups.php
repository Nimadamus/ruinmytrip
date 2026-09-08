<?php
declare(strict_types=1);

/**
 * Hosting a meetup.
 *
 * The read side (index, show, RSVP) has always existed in app/controllers.php. This file is the
 * write side and the rules that go with it, kept together because they are the part with teeth:
 * a meetup is the one thing on this site that puts two strangers in the same physical place, so
 * what it will and will not accept is not incidental detail.
 */

/** A meetup can be seen by anyone; only these two states are real. Anything else is not a meetup. */
const RMT_MEETUP_STATUSES = ['published', 'cancelled'];

/** No cap, or a real one. 500 is not a meetup, it is an event, and the safety model is different. */
const RMT_MEETUP_CAPACITY_MAX = 200;

/**
 * Validate a submitted meetup.
 *
 * The rules that matter and why:
 *
 *  - a destination is REQUIRED. The whole location model is "tied to a destination, never a
 *    precise or live position". A meetup with no destination has nothing anchoring it, and the
 *    index groups by destination chip.
 *  - a start date is REQUIRED and must be in the future when the meetup is created. A meetup
 *    someone can RSVP to after it happened is not a bug the reader can see; they just turn up.
 *  - the end, when given, must be after the start.
 *  - safety_ack must be checked. The column has always been there, unused. A host agreeing to the
 *    safety terms in the open is the point of it, and it is stored so it can be shown later.
 *  - capacity is 0 (no limit) or 2..200. One is not a meetup.
 *
 * $existingStart lets an edit keep a start date that has since passed: forcing a host to move a
 * meetup into the future in order to fix a typo in its title would be absurd, and the past date
 * is the truth about that meetup.
 *
 * @return array{ok:bool, errors:string[], data:array}
 */
function rmt_meetup_validate(array $in, ?string $existingStart = null): array {
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    $desc  = trim((string) ($in['description'] ?? ''));
    $dest  = (int) ($in['destination_id'] ?? 0);
    $start = trim((string) ($in['date_start'] ?? ''));
    $end   = trim((string) ($in['date_end'] ?? ''));
    $cap   = (int) ($in['capacity'] ?? 0);
    $ack   = !empty($in['safety_ack']);

    if (mb_strlen($title) < 5)   $errors[] = 'Give your meetup a title (5+ characters).';
    if (mb_strlen($title) > 140) $errors[] = 'That title is too long (140 characters max).';
    if (mb_strlen($desc) < 30)   $errors[] = 'Say what the plan actually is (30+ characters), including where to meet.';
    if (mb_strlen($desc) > 4000) $errors[] = 'That description is too long (4000 characters max).';

    if ($dest <= 0)             $errors[] = 'Pick the destination this meetup is in.';
    elseif (!dest_by_id($dest)) $errors[] = 'That destination does not exist.';

    $startTs = $start !== '' ? strtotime($start) : false;
    if ($start === '')      $errors[] = 'Pick a date and time.';
    elseif (!$startTs)      $errors[] = 'That start date is not a real date and time.';
    elseif ($startTs < time() && $start !== (string) $existingStart) {
        $errors[] = 'Pick a date in the future.';
    }

    $endTs = $end !== '' ? strtotime($end) : null;
    if ($end !== '' && !$endTs)                       $errors[] = 'That end time is not a real date and time.';
    elseif ($endTs && $startTs && $endTs <= $startTs) $errors[] = 'The end time has to be after the start.';

    if ($cap !== 0 && ($cap < 2 || $cap > RMT_MEETUP_CAPACITY_MAX)) {
        $errors[] = 'Capacity is either 0 for no limit, or between 2 and ' . RMT_MEETUP_CAPACITY_MAX . '.';
    }
    if (!$ack) $errors[] = 'You have to agree to the meetup safety terms to host one.';

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'title' => $title, 'description' => $desc, 'destination_id' => $dest ?: null,
        // Stored in the same 'Y-m-d H:i:s' shape as every other timestamp in the schema, so
        // strtotime() on the read side gets the same thing back whatever the browser sent.
        'date_start' => $startTs ? date('Y-m-d H:i:s', $startTs) : '',
        'date_end'   => $endTs ? date('Y-m-d H:i:s', $endTs) : null,
        'capacity'   => $cap, 'safety_ack' => 1,
    ]];
}

/** Only the host edits or cancels a meetup. Moderators act through the report queue, not here. */
function rmt_meetup_can_edit(array $m, ?array $user): bool {
    return $user !== null && (int) $m['host_id'] === (int) $user['id'];
}

/** How many people are going. Always counted, never stored -- a stale "going" number strands people. */
function rmt_meetup_going_count(int $meetupId): int {
    return (int) (q_one("SELECT COUNT(*) c FROM meetup_rsvps WHERE meetup_id = ? AND status = 'going'",
                        [$meetupId])['c'] ?? 0);
}

/**
 * Is this meetup full?
 *
 * Capacity has been a column since the schema was written and nothing ever read it, so a meetup
 * that said "capacity 8" accepted forty. That is not a cosmetic bug: eight people planned around
 * a number the site published and did not keep.
 */
function rmt_meetup_is_full(array $m, ?int $goingCount = null): bool {
    $cap = (int) ($m['capacity'] ?? 0);
    if ($cap <= 0) return false;
    return ($goingCount ?? rmt_meetup_going_count((int) $m['id'])) >= $cap;
}

/** A meetup whose start time has passed. It stays readable; it just stops taking RSVPs. */
function rmt_meetup_is_past(array $m): bool {
    $ts = strtotime((string) ($m['date_start'] ?? ''));
    return $ts !== false && $ts < time();
}

/**
 * Meetups this user is hosting that have not happened yet.
 *
 * Public, because the host is already named on the meetup page and on the index -- this only puts
 * it where somebody deciding whether to go and meet a stranger will actually look for it.
 * Cancelled ones are left out: a called-off meetup is not something you are hosting.
 */
function rmt_meetups_hosted_upcoming(int $userId, int $limit = 10): array {
    return q_all("SELECT m.*, d.name dest_name, d.slug dest_slug,
                         (SELECT COUNT(*) FROM meetup_rsvps r WHERE r.meetup_id = m.id AND r.status = 'going') going
                    FROM meetups m LEFT JOIN destinations d ON d.id = m.destination_id
                   WHERE m.host_id = ? AND m.status = 'published' AND m.date_start >= ?
                   ORDER BY m.date_start LIMIT " . max(1, $limit),
                 [$userId, date('Y-m-d H:i:s')]);
}

/**
 * Meetups this user has RSVPed to and that have not happened yet.
 *
 * Shown to the owner of the profile and to nobody else. Each individual going-list is already
 * public on its own meetup page, but a per-person list of everywhere they will physically be over
 * the next month is a different thing entirely, and this site does not build that for strangers.
 */
function rmt_meetups_attending_upcoming(int $userId, int $limit = 10): array {
    return q_all("SELECT m.*, d.name dest_name, d.slug dest_slug
                    FROM meetup_rsvps r
                    JOIN meetups m ON m.id = r.meetup_id AND m.status = 'published'
                    LEFT JOIN destinations d ON d.id = m.destination_id
                   WHERE r.user_id = ? AND r.status = 'going' AND m.date_start >= ?
                   ORDER BY m.date_start LIMIT " . max(1, $limit),
                 [$userId, date('Y-m-d H:i:s')]);
}

/** The things that happen to a meetup which the other people involved need told about. */
const RMT_MEETUP_NOTIFY_TYPES = ['meetup_rsvp', 'meetup_changed', 'meetup_cancelled', 'meetup_nearby', 'meetup_comment'];

/** Everyone currently going, except one person (normally the host, who is doing the thing). */
function rmt_meetup_going_user_ids(int $meetupId, int $exceptUserId = 0): array {
    $rows = q_all("SELECT user_id FROM meetup_rsvps WHERE meetup_id = ? AND status = 'going'", [$meetupId]);
    $ids = array_map(static fn(array $r) => (int) $r['user_id'], $rows);
    return array_values(array_filter($ids, static fn(int $id) => $id !== $exceptUserId));
}

/**
 * Tell people something happened to a meetup.
 *
 * This is the half that was missing from meetups entirely: a host had no idea anyone had signed
 * up, and an attendee found out the time had moved, or that it was off, by turning up. A meetup is
 * the one thing here that costs somebody their afternoon, so the notification is not a nicety.
 *
 * Silently ignores an unknown type rather than writing a row the notifications page cannot render
 * into anything but "meetup_whatever from @someone".
 */
function rmt_meetup_notify(array $userIds, string $type, int $actorId, int $meetupId): int {
    if (!in_array($type, RMT_MEETUP_NOTIFY_TYPES, true)) return 0;
    $sent = 0;
    $now = date('Y-m-d H:i:s');
    foreach (array_unique(array_map('intval', $userIds)) as $uid) {
        if ($uid <= 0 || $uid === $actorId) continue;   // nobody is told about their own action
        q_run('INSERT INTO notifications (user_id,type,actor_id,target_type,target_id,created_at)
               VALUES (?,?,?,?,?,?)', [$uid, $type, $actorId, 'meetup', $meetupId, $now]);
        $sent++;
    }
    return $sent;
}

/**
 * Did an edit move the meetup in time?
 *
 * Only the when matters here. Fixing a typo in the title should not fire a notification at
 * everybody who RSVPed; moving the start by three hours absolutely should.
 */
function rmt_meetup_time_changed(array $before, array $after): bool {
    return (string) ($before['date_start'] ?? '') !== (string) ($after['date_start'] ?? '')
        || (string) ($before['date_end'] ?? '')   !== (string) ($after['date_end'] ?? '');
}

/**
 * Who hears about a new comment on a meetup, besides the host.
 *
 * A meetup page had a discussion nobody was told about, which is the same as having none: the
 * question "is this beginner friendly, and where exactly are we meeting" sat there until whoever
 * happened to reload the page saw it. Everybody who said they are going is planning around this
 * meetup, so they are the people a new line on it concerns.
 *
 * The host is left out here because the ordinary comment notification already tells them, and
 * being told twice about one sentence reads as a bug. The author is left out by
 * rmt_meetup_going_user_ids(), which is also what covers the host commenting on their own meetup.
 *
 * @return int[]
 */
function rmt_meetup_discussion_recipients(int $meetupId, int $actorId, int $hostId): array {
    $ids = rmt_meetup_going_user_ids($meetupId, $actorId);
    if ($hostId !== $actorId) {
        $ids = array_values(array_filter($ids, static fn(int $id) => $id !== $hostId));
    }
    return $ids;
}

/**
 * A meetup as schema.org Event.
 *
 * Meetups are the one thing here that Google has a rich result for, and they were emitting no
 * structured data at all: a real, dated, public event with a host and an attendee count was being
 * read as an anonymous page of text. Everything below is a fact already printed on the page.
 *
 * The location is the destination and nothing finer, which is the same promise the page makes to
 * the people attending. schema.org is happy with a Place carrying only addressLocality and
 * addressCountry, and inventing a street to satisfy a validator would break the one rule this
 * feature has.
 *
 * A cancelled meetup keeps its markup and says EventCancelled, because that is exactly the state
 * somebody holding the link needs to be told about, in the result as well as on the page.
 */
function rmt_meetup_jsonld(array $m, int $goingCount = 0): array {
    $start = strtotime((string) ($m['date_start'] ?? ''));
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => (string) $m['title'],
        'url' => url('meetup/' . (int) $m['id']),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => ($m['status'] ?? '') === 'cancelled'
            ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'isAccessibleForFree' => true,
    ];
    if ($start) $ld['startDate'] = date('c', $start);
    $end = $m['date_end'] ? strtotime((string) $m['date_end']) : false;
    if ($end) $ld['endDate'] = date('c', $end);
    if (!empty($m['description'])) {
        $ld['description'] = mb_strimwidth(strip_tags((string) $m['description']), 0, 300, '…');
    }
    if (!empty($m['dest_name'])) {
        $addr = ['@type' => 'PostalAddress', 'addressLocality' => (string) $m['dest_name']];
        if (!empty($m['dest_country'])) $addr['addressCountry'] = (string) $m['dest_country'];
        $ld['location'] = ['@type' => 'Place', 'name' => (string) $m['dest_name'], 'address' => $addr];
    }
    if (!empty($m['host']['username'])) {
        $ld['organizer'] = ['@type' => 'Person', 'name' => '@' . $m['host']['username'],
                            'url' => url('u/' . $m['host']['username'])];
    }
    // Capacity and attendance are published on the page, so they are published here too. Neither is
    // ever rounded up: a going count is a COUNT(*), not a marketing number.
    if ((int) ($m['capacity'] ?? 0) > 0) {
        $ld['maximumAttendeeCapacity'] = (int) $m['capacity'];
        // Zero remaining is a fact worth publishing: a full meetup should read as full rather than
        // as a meetup that declined to say. Uncapped meetups claim neither number.
        $ld['remainingAttendeeCapacity'] = max(0, (int) $m['capacity'] - $goingCount);
    }
    return $ld;
}
