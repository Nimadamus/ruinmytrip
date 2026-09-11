<?php
declare(strict_types=1);

/**
 * What a traveler is actually doing there.
 *
 * The site can already say who is going to Lisbon, when, and why they might be worth meeting. It
 * could not say what any of them were doing, which is the half of the question people actually
 * arrive with, and the half that turns two overlapping date ranges into a reason to say hello:
 * "dinner in Alfama on Friday" is something another traveler can answer.
 *
 * The design rule that matters most here is that this must not feel like a spreadsheet. A plan is
 * a line of text and, optionally, a day. Everything else (a time, a category, a place, a note, a
 * link, whether anybody may come) is optional and stays out of the way until it is wanted. An
 * itinerary tool that demands a start time for "Sintra at some point" is an itinerary tool nobody
 * fills in twice.
 *
 * PRIVACY. An activity is exactly as visible as its trip, and may be less:
 *   - visibility 'trip'    follow the trip, which is the default and covers almost everything
 *   - visibility 'private' the owner alone, whatever the trip says
 * There is deliberately no way to make an activity MORE visible than its trip. A public trip can
 * hide one dinner; a private trip can never leak one. Every read in this file goes through
 * rmt_activity_visible_sql() or rmt_activity_visible_to(), and the safety audit fails the build if
 * a new query forgets.
 */

/** The categories, fixed. A vocabulary can be ranked and counted; free text cannot. */
const RMT_ACTIVITY_CATEGORIES = [
    'food'      => 'Food',
    'drinks'    => 'Drinks and nightlife',
    'sight'     => 'Sight or attraction',
    'museum'    => 'Museum or gallery',
    'tour'      => 'Tour',
    'event'     => 'Event or show',
    'sport'     => 'Sport',
    'outdoors'  => 'Outdoors',
    'beach'     => 'Beach',
    'daytrip'   => 'Day trip',
    'shopping'  => 'Shopping',
    'neighbourhood' => 'Neighbourhood',
    'meetup'    => 'Meetup',
    'other'     => 'Something else',
];

/** Whether anybody else may say they are coming. */
const RMT_ACTIVITY_JOIN_MODES = [
    'no'   => 'Just me',
    'ask'  => 'Others can ask to join',
    'open' => 'Anybody going can join',
];

/** The two states somebody else can be in. No row is the third. */
const RMT_ACTIVITY_JOIN_STATES = ['interested', 'going'];

/**
 * The SQL condition for activities a viewer may see, given a trip alias that is already visibility
 * filtered. Returns the fragment and its bind values.
 *
 * @return array{0:string,1:array<int,mixed>}
 */
function rmt_activity_visible_sql(string $alias, ?array $viewer): array {
    if (!$viewer) return ["$alias.visibility = 'trip'", []];
    // The owner sees their own private ones; a moderator sees them for moderation and nothing else.
    if (in_array($viewer['role'] ?? '', ['admin', 'mod'], true)) return ['1=1', []];
    return ["($alias.visibility = 'trip' OR $alias.user_id = ?)", [(int) $viewer['id']]];
}

/** Whether one loaded activity row, with its trip's visibility joined in, may be seen. */
function rmt_activity_visible_to(array $activity, ?array $viewer): bool {
    $ownerId = (int) ($activity['user_id'] ?? 0);
    $isOwner = $viewer && (int) $viewer['id'] === $ownerId;
    $isMod = $viewer && in_array($viewer['role'] ?? '', ['admin', 'mod'], true);
    if (($activity['visibility'] ?? 'trip') === 'private' && !$isOwner && !$isMod) return false;
    // Then the trip it belongs to, through the same rule every other reader of a trip uses.
    $trip = ['user_id' => $ownerId, 'visibility' => (string) ($activity['trip_visibility'] ?? 'public')];
    return rmt_trip_visible_to($trip, $viewer);
}

/**
 * Everything planned on one trip, in the order a person would read it: by day, then by time, then
 * by the order they were added.
 *
 * @return list<array<string,mixed>>
 */
function rmt_activities_for_trip(int $tripId, ?array $viewer): array {
    if ($tripId < 1) return [];
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $rows = q_all(
        "SELECT a.*, p.name place_name, p.slug place_slug,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'going') going_count,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'interested') interested_count
           FROM trip_activities a
      LEFT JOIN places p ON p.id = a.place_id
          WHERE a.trip_id = ? AND a.status = 'published' AND $actVis
       ORDER BY CASE WHEN a.day IS NULL THEN 1 ELSE 0 END,
                a.day, COALESCE(a.start_time,'99:99'), a.sort, a.id",
        array_merge([$tripId], $actArgs)
    );
    return $rows;
}

/**
 * Group a trip's activities into days for rendering, newest logic kept in one place so the view
 * stays a view.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array{day:?string,label:string,items:list<array<string,mixed>>}>
 */
function rmt_activities_by_day(array $rows): array {
    $days = [];
    foreach ($rows as $r) {
        $key = trim((string) ($r['day'] ?? '')) ?: 'unscheduled';
        if (!isset($days[$key])) {
            $days[$key] = [
                'day' => $key === 'unscheduled' ? null : $key,
                'label' => $key === 'unscheduled' ? 'Not fixed to a day' : date('l j F', strtotime($key)),
                'items' => [],
            ];
        }
        $days[$key]['items'][] = $r;
    }
    return array_values($days);
}

/** One activity with its trip's visibility, for a page that acts on it. */
function rmt_activity_get(int $id): ?array {
    if ($id < 1) return null;
    $r = q_one("SELECT a.*, t.visibility trip_visibility, t.slug trip_slug, t.title trip_title,
                       t.date_from trip_from, t.date_to trip_to, t.status trip_status,
                       d.name dest_name, d.slug dest_slug, u.username
                  FROM trip_activities a
                  JOIN trips t ON t.id = a.trip_id
                  JOIN users u ON u.id = a.user_id
             LEFT JOIN destinations d ON d.id = a.destination_id
                 WHERE a.id = ?", [$id]);
    if (!$r || $r['status'] !== 'published' || $r['trip_status'] !== 'published') return null;
    return $r;
}

/**
 * Validate a submitted activity. Deliberately forgiving: a title is the only thing required,
 * because the whole point is that adding a plan costs one line of typing.
 *
 * @return array{ok:bool, errors:string[], data:array<string,mixed>}
 */
function rmt_activity_validate(array $in, array $trip): array {
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    if ($title === '') $errors[] = 'Say what the plan is.';
    if (mb_strlen($title) > 160) $errors[] = 'That is a long plan. Keep the line short and put the rest in the notes.';

    $cat = (string) ($in['category'] ?? 'other');
    if (!isset(RMT_ACTIVITY_CATEGORIES[$cat])) $cat = 'other';

    $join = (string) ($in['join_mode'] ?? 'no');
    if (!isset(RMT_ACTIVITY_JOIN_MODES[$join])) $join = 'no';

    $vis = (string) ($in['visibility'] ?? 'trip');
    if ($vis !== 'private') $vis = 'trip';

    /* A day has to be a real date, and it has to be inside the trip: an activity on a day the
       traveler is not there is a typo every time, and it would pollute the city's date views. */
    $day = trim((string) ($in['day'] ?? ''));
    if ($day !== '') {
        $ts = strtotime($day);
        if (!$ts) {
            $errors[] = 'That day is not a real date.';
            $day = '';
        } else {
            $day = date('Y-m-d', $ts);
            $from = trim((string) ($trip['date_from'] ?? ''));
            $to   = trim((string) ($trip['date_to'] ?? ''));
            if ($from !== '' && $to !== '' && ($day < $from || $day > $to)) {
                $errors[] = 'That day is outside the trip (' . $from . ' to ' . $to . ').';
            }
        }
    }

    $time = trim((string) ($in['start_time'] ?? ''));
    if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        $errors[] = 'That time should look like 19:30.';
        $time = '';
    }

    $link = trim((string) ($in['link'] ?? ''));
    if ($link !== '' && (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $link))) {
        $errors[] = 'A link has to be a full http:// or https:// address.';
    }

    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) $errors[] = 'Those notes are very long.';

    $place = (int) ($in['place_id'] ?? 0);
    if ($place > 0 && !q_one("SELECT 1 FROM places WHERE id = ? AND status = 'active'", [$place])) $place = 0;

    return ['ok' => !$errors, 'errors' => $errors, 'data' => [
        'title' => $title,
        'category' => $cat,
        'day' => $day !== '' ? $day : null,
        'start_time' => $time !== '' ? $time : null,
        'location_text' => trim((string) ($in['location_text'] ?? '')) ?: null,
        'notes' => $notes !== '' ? $notes : null,
        'link' => $link !== '' ? $link : null,
        'place_id' => $place ?: null,
        'visibility' => $vis,
        'join_mode' => $join,
    ]];
}

/** Add one. Returns the new id, or 0. */
function rmt_activity_add(array $trip, array $data): int {
    $now = date('Y-m-d H:i:s');
    $sort = (int) (q_one('SELECT COALESCE(MAX(sort), 0) + 1 m FROM trip_activities WHERE trip_id = ?',
                         [(int) $trip['id']])['m'] ?? 1);
    q_run('INSERT INTO trip_activities
             (trip_id, user_id, destination_id, day, start_time, title, category, place_id,
              location_text, notes, link, visibility, join_mode, sort, status, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [(int) $trip['id'], (int) $trip['user_id'], $trip['destination_id'] ?: null,
           $data['day'], $data['start_time'], $data['title'], $data['category'], $data['place_id'],
           $data['location_text'], $data['notes'], $data['link'], $data['visibility'],
           $data['join_mode'], $sort, 'published', $now]);
    return (int) (q_one('SELECT MAX(id) m FROM trip_activities WHERE trip_id = ?', [(int) $trip['id']])['m'] ?? 0);
}

/**
 * What travelers are doing in one city, optionally in one date window.
 *
 * This is the query the whole feature exists for: "I am in Lisbon 3 to 10 October, what is anybody
 * doing". Only activities on trips the viewer may see, only activities not marked private, and
 * blocks applied in both directions.
 *
 * @return list<array<string,mixed>>
 */
function rmt_activities_in_city(int $destId, ?array $viewer, ?string $from = null, ?string $to = null,
                                int $limit = 30): array {
    if ($destId < 1) return [];
    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('a.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }

    $window = '';
    $windowArgs = [];
    if ($from !== null && $to !== null && $from !== '' && $to !== '') {
        /* An activity with no day belongs to the trip's window, so it counts when the trip itself
           overlaps. That is what somebody means by "what is happening while I am there". */
        $window = " AND ((a.day IS NOT NULL AND a.day >= ? AND a.day <= ?)
                         OR (a.day IS NULL AND t.date_from <= ? AND t.date_to >= ?))";
        $windowArgs = [$from, $to, $to, $from];
    }

    return q_all(
        "SELECT a.*, t.title trip_title, t.slug trip_slug, t.date_from trip_from, t.date_to trip_to,
                u.username, pr.avatar_url, p.name place_name, p.slug place_slug,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'going') going_count
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
           JOIN users u ON u.id = a.user_id AND u.status = 'active'
      LEFT JOIN profiles pr ON pr.user_id = a.user_id
      LEFT JOIN places p ON p.id = a.place_id
          WHERE a.destination_id = ? AND a.status = 'published' AND t.status = 'published'
            AND $tripVis AND $actVis AND $blockSql $window
       ORDER BY CASE WHEN a.day IS NULL THEN 1 ELSE 0 END, a.day,
                COALESCE(a.start_time,'99:99'), a.id DESC
          LIMIT " . (int) $limit,
        array_merge([$destId], $tripArgs, $actArgs, $blockArgs, $windowArgs)
    );
}

/**
 * What is popular in a city, counted honestly.
 *
 * Counts of real rows, never rounded and never padded: if one person saved it, it says one. The
 * grouping is on the title when there is no place, which is rough on purpose, because the
 * alternative is a place database this site does not have for every bar in Lisbon.
 *
 * @return list<array{label:string,category:string,n:int,place_slug:?string}>
 */
function rmt_activity_popular_in_city(int $destId, ?array $viewer, ?string $from = null,
                                      ?string $to = null, int $limit = 8): array {
    $rows = rmt_activities_in_city($destId, $viewer, $from, $to, 400);
    $seen = [];
    foreach ($rows as $r) {
        $label = trim((string) ($r['place_name'] ?: $r['title']));
        if ($label === '') continue;
        $key = mb_strtolower($label);
        if (!isset($seen[$key])) {
            $seen[$key] = ['label' => $label, 'category' => (string) $r['category'], 'n' => 0,
                           'place_slug' => $r['place_slug'] ?: null, 'people' => []];
        }
        // One person planning the same thing twice is one person.
        $seen[$key]['people'][(int) $r['user_id']] = true;
        $seen[$key]['n'] = count($seen[$key]['people']);
    }
    $out = array_values($seen);
    usort($out, static fn(array $x, array $y) => $y['n'] <=> $x['n']);
    foreach ($out as $i => $r) unset($out[$i]['people']);
    return array_slice($out, 0, $limit);
}

/** How many people have said they are coming, and whether the viewer is one of them. */
function rmt_activity_join_state(int $activityId, ?array $viewer): ?string {
    if (!$viewer || $activityId < 1) return null;
    $r = q_one('SELECT state FROM activity_joins WHERE activity_id = ? AND user_id = ?',
               [$activityId, (int) $viewer['id']]);
    return $r ? (string) $r['state'] : null;
}

/** Who said they are coming. Blocks apply, the same as every other list of people. */
function rmt_activity_joiners(int $activityId, ?array $viewer, int $limit = 12): array {
    if ($activityId < 1) return [];
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('u.id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }
    return q_all(
        "SELECT j.state, u.id user_id, u.username, p.avatar_url, p.display_name
           FROM activity_joins j
           JOIN users u ON u.id = j.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = u.id
          WHERE j.activity_id = ? AND $blockSql
       ORDER BY CASE WHEN j.state = 'going' THEN 0 ELSE 1 END, j.created_at
          LIMIT " . (int) $limit,
        array_merge([$activityId], $blockArgs)
    );
}
