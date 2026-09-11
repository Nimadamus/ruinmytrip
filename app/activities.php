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
        "SELECT a.*, p.name place_name, p.slug place_slug, u.username author_username,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'going') going_count,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'interested') interested_count
           FROM trip_activities a
      LEFT JOIN places p ON p.id = a.place_id AND p.status <> 'hidden'
      LEFT JOIN users u ON u.id = a.user_id
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

    /* Typing "Time Out Market" should attach the plan to the Time Out Market we already hold,
       not leave a string that looks like a place and links to nothing. Matched on the same
       normalised key the places table is unique on, so "the time out market." finds it too, and
       only within the city the trip is to, because half the world has a Central Park.

       Nothing is created here. An unknown name stays a piece of text, which is honest: the site
       adds places by hand after checking them, and a plan is not a back door around that. */
    $typed = trim((string) ($in['location_text'] ?? ''));
    if ($place === 0 && $typed !== '' && (int) ($trip['destination_id'] ?? 0) > 0
        && function_exists('rmt_place_name_key')) {
        $hit = q_one("SELECT id FROM places
                       WHERE destination_id = ? AND name_key = ? AND status = 'active'",
                     [(int) $trip['destination_id'], rmt_place_name_key($typed)]);
        if ($hit) $place = (int) $hit['id'];
    }

    /* The three things a plan needs once other people can come to it. All optional: most plans
       have no capacity, no meeting point and no end time, and inventing any of them would be
       theatre. */
    $cap = (int) ($in['capacity'] ?? 0);
    if ($cap < 0) $cap = 0;
    if ($cap > 100) $cap = 100;

    $end = trim((string) ($in['end_time'] ?? ''));
    if ($end !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
        $errors[] = 'That end time should look like 23:30.';
        $end = '';
    }

    $point = trim((string) ($in['meeting_point'] ?? ''));
    if (mb_strlen($point) > 300) $point = mb_substr($point, 0, 300);

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
        'capacity' => $cap ?: null,
        'end_time' => $end !== '' ? $end : null,
        'meeting_point' => $point !== '' ? $point : null,
    ]];
}

/** Add one. Returns the new id, or 0. */
function rmt_activity_add(array $trip, array $data, ?int $authorId = null): int {
    $now = date('Y-m-d H:i:s');
    $sort = (int) (q_one('SELECT COALESCE(MAX(sort), 0) + 1 m FROM trip_activities WHERE trip_id = ?',
                         [(int) $trip['id']])['m'] ?? 1);
    q_run('INSERT INTO trip_activities
             (trip_id, user_id, destination_id, day, start_time, end_time, title, category, place_id,
              location_text, notes, link, visibility, join_mode, capacity, meeting_point, sort, status, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
          [(int) $trip['id'], $authorId ?: (int) $trip['user_id'], $trip['destination_id'] ?: null,
           $data['day'], $data['start_time'], $data['end_time'], $data['title'], $data['category'],
           $data['place_id'], $data['location_text'], $data['notes'], $data['link'],
           $data['visibility'], $data['join_mode'], $data['capacity'], $data['meeting_point'],
           $sort, 'published', $now]);
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
                                int $limit = 30, array $opts = []): array {
    if ($destId < 1) return [];
    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('a.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }

    /* Two filters, and deliberately only two. A category, because "what food is anybody doing"
       is a real question, and "open to other people", because the whole point of the page is
       finding something to join. Anything more is a filter panel, and a filter panel on a phone
       is a wall between somebody and the one plan they wanted. */
    $filter = '';
    $filterArgs = [];
    $cat = (string) ($opts['category'] ?? '');
    if ($cat !== '' && isset(RMT_ACTIVITY_CATEGORIES[$cat])) {
        $filter .= ' AND a.category = ?';
        $filterArgs[] = $cat;
    }
    if (!empty($opts['joinable'])) {
        $filter .= " AND a.join_mode IN ('ask','open') AND a.cancelled_at IS NULL";
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
      LEFT JOIN places p ON p.id = a.place_id AND p.status <> 'hidden'
          WHERE a.destination_id = ? AND a.status = 'published' AND t.status = 'published'
            AND $tripVis AND $actVis AND $blockSql $window $filter
       ORDER BY CASE WHEN a.day IS NULL THEN 1 ELSE 0 END, a.day,
                COALESCE(a.start_time,'99:99'), a.id DESC
          LIMIT " . (int) $limit,
        array_merge([$destId], $tripArgs, $actArgs, $blockArgs, $windowArgs, $filterArgs)
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

/* ------------------------------------------------------------------ joining, properly
 *
 * Four states, and no row is the fifth:
 *   interested  a soft yes on an open plan, or on one whose owner wants to be asked
 *   requested   an ask waiting for the owner
 *   going       accepted, or an instant yes on an open plan
 *   declined    answered no, remembered on purpose so the same person cannot ask again every hour
 *               and so the page can tell them what actually happened
 */

/** Everybody attached to a plan, in the states that matter to the owner. */
function rmt_activity_requests(int $activityId, ?array $viewer, string $state = 'requested', int $limit = 20): array {
    if ($activityId < 1) return [];
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('u.id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }
    return q_all(
        "SELECT j.state, j.created_at, u.id user_id, u.username, p.avatar_url, p.display_name
           FROM activity_joins j
           JOIN users u ON u.id = j.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = u.id
          WHERE j.activity_id = ? AND j.state = ? AND $blockSql
       ORDER BY j.created_at LIMIT " . (int) $limit,
        array_merge([$activityId, $state], $blockArgs)
    );
}

/** How many people are accepted. The owner is not counted: it is their plan. */
function rmt_activity_going_count(int $activityId): int {
    return (int) (q_one("SELECT COUNT(*) n FROM activity_joins WHERE activity_id = ? AND state = 'going'",
                        [$activityId])['n'] ?? 0);
}

/**
 * Is there room for one more?
 *
 * A capacity of nothing means no limit, which is what almost every plan is. A capacity that is
 * already met stops an instant join and stops an accept, and says so rather than silently failing.
 */
function rmt_activity_has_room(array $activity): bool {
    $cap = (int) ($activity['capacity'] ?? 0);
    if ($cap < 1) return true;
    return rmt_activity_going_count((int) $activity['id']) < $cap;
}

/**
 * The meeting point, which is the one piece of an activity that is not for everybody.
 *
 * "By the fountain at the top of the steps, 8pm" is exactly the information a stranger should not
 * have about a small group of people, and exactly what the people coming need. Owner and accepted
 * attendees; nobody else, ever, including people who asked and were not answered yet.
 */
function rmt_activity_meeting_point_visible(array $activity, ?array $viewer): bool {
    if (trim((string) ($activity['meeting_point'] ?? '')) === '') return false;
    if (!$viewer) return false;
    if ((int) $viewer['id'] === (int) $activity['user_id']) return true;
    return rmt_activity_join_state((int) $activity['id'], $viewer) === 'going';
}

/** Photographs of one plan, respecting the plan's own visibility through its caller. */
function rmt_activity_photos(int $activityId): array {
    if ($activityId < 1) return [];
    return q_all("SELECT * FROM activity_photos WHERE activity_id = ? AND status = 'published'
                  ORDER BY sort, id", [$activityId]);
}

/** Attach uploaded photos to a plan. Same path, same guarantees, as every other upload here. */
function rmt_activity_attach_photos(int $activityId, int $ownerId): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) return $errors;
    $existing = (int) (q_one('SELECT COUNT(*) c FROM activity_photos WHERE activity_id = ?', [$activityId])['c'] ?? 0);
    $slots = max(0, 6 - $existing);
    $n = count($_FILES['photos']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ((int) $_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($slots <= 0) { $errors[] = 'Up to six photos on one plan.'; break; }
        if (!rmt_rate_ok('upload', (string) $ownerId, 40, 3600)) { $errors[] = 'Too many uploads. Try again later.'; break; }
        $file = [
            'name' => $_FILES['photos']['name'][$i], 'type' => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i],
            'size' => $_FILES['photos']['size'][$i],
        ];
        $res = rmt_upload_image($file, $ownerId);
        if (!$res['ok']) { $errors[] = $res['error']; continue; }
        $cap = trim((string) ($_POST['photo_caption'][$i] ?? ''));
        q_run('INSERT INTO activity_photos (activity_id, user_id, url, storage_key, caption, width, height, bytes, sort, status, created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)',
              [$activityId, $ownerId, $res['url'], $res['key'], $cap !== '' ? mb_substr($cap, 0, 300) : null,
               $res['w'], $res['h'], $res['bytes'], $existing + $i, 'published', date('Y-m-d H:i:s')]);
        $slots--;
    }
    return $errors;
}

/**
 * Plans somebody could actually join, on the dates they are there.
 *
 * The whole sentence this product is built toward ends "...and I can join whichever fits me". That
 * needs one list: open or ask-to-join plans, in a city the member has a trip to, inside the days
 * their own trip covers, not their own, not cancelled, and not full.
 *
 * @return list<array<string,mixed>>
 */
function rmt_activities_joinable_for(int $uid, int $limit = 6): array {
    if ($uid < 1) return [];
    $viewer = ['id' => $uid];
    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    [$blockSql] = rmt_match_block_sql('a.user_id');

    return q_all(
        "SELECT a.id, a.title, a.day, a.start_time, a.category, a.join_mode, a.capacity,
                a.location_text, t.slug trip_slug, u.username, p.avatar_url,
                d.name dest_name, d.slug dest_slug,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'going') going_count
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
           JOIN users u ON u.id = a.user_id AND u.status = 'active'
      LEFT JOIN profiles p ON p.user_id = a.user_id
      LEFT JOIN destinations d ON d.id = a.destination_id
          WHERE a.status = 'published' AND t.status = 'published'
            AND a.user_id <> ?
            AND a.cancelled_at IS NULL
            AND a.join_mode IN ('open','ask')
            AND (a.day IS NULL OR a.day >= ?)
            /* EXISTS rather than a join: somebody with two trips to the same city would otherwise
               be offered the same plan twice, once per trip. */
            AND EXISTS (SELECT 1 FROM trips mine
                         WHERE mine.user_id = ? AND mine.status = 'published'
                           AND mine.destination_id = a.destination_id
                           AND mine.date_from IS NOT NULL AND mine.date_to IS NOT NULL
                           AND (a.day IS NULL OR (a.day >= mine.date_from AND a.day <= mine.date_to)))
            AND NOT EXISTS (SELECT 1 FROM activity_joins j2
                             WHERE j2.activity_id = a.id AND j2.user_id = ?)
            AND $tripVis AND $actVis AND $blockSql
       ORDER BY CASE WHEN a.day IS NULL THEN 1 ELSE 0 END, a.day,
                COALESCE(a.start_time,'99:99'), a.id
          LIMIT " . (int) $limit,
        array_merge([$uid, date('Y-m-d'), $uid, $uid], $tripArgs, $actArgs, [$uid, $uid])
    );
}

/**
 * Plans whose day has been and gone and which their owner has not answered for yet.
 *
 * This is the hinge of the loop the whole site depends on: somebody planned a thing, went to it,
 * and now knows something the next traveler does not. Asking once, on the days right after, is the
 * only moment that question is easy to answer. A fortnight later it is homework.
 *
 * Only the member's own plans, so there is nothing here to leak. Somebody who was accepted onto
 * another traveler's dinner is not asked, because the rating lives on that traveler's plan and
 * answering for them would be putting words in their mouth.
 *
 * @return list<array<string,mixed>>
 */
function rmt_activities_to_review(int $uid, int $limit = 3): array {
    if ($uid < 1) return [];
    $today = date('Y-m-d');
    $floor = date('Y-m-d', strtotime('-21 days'));
    return q_all(
        "SELECT a.*, t.slug trip_slug, t.title trip_title, d.name dest_name
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
      LEFT JOIN destinations d ON d.id = a.destination_id
          WHERE a.status = 'published' AND a.cancelled_at IS NULL
            AND a.day IS NOT NULL AND a.day < ? AND a.day >= ?
            AND (a.rating IS NULL OR a.rating = 0) AND (a.done IS NULL OR a.done = 0)
            AND a.user_id = ?
       ORDER BY a.day DESC, a.id DESC
          LIMIT " . (int) $limit,
        [$today, $floor, $uid]
    );
}

/**
 * What travelers who actually went say is worth it.
 *
 * The other end of the loop, and the reason the loop exists: somebody planned a thing, went to it,
 * said whether it was worth it, and the next traveler reading the city page gets that answer with
 * a real name attached. Counted in people, never rounded, and if one person said it, it says one.
 *
 * Visibility is the same rule as everywhere else, so a recommendation on a private trip stays on
 * that private trip.
 *
 * @return list<array{label:string,category:string,n:int,place_slug:?string,users:list<string>,activity_id:int}>
 */
function rmt_activity_recommended_in_city(int $destId, ?array $viewer, int $limit = 8): array {
    if ($destId < 1) return [];
    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('a.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }

    $rows = q_all(
        "SELECT a.id, a.title, a.category, a.user_id, u.username, p.name place_name, p.slug place_slug
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
           JOIN users u ON u.id = a.user_id AND u.status = 'active'
      LEFT JOIN places p ON p.id = a.place_id AND p.status <> 'hidden'
          WHERE a.destination_id = ? AND a.status = 'published' AND t.status = 'published'
            AND a.recommend = 1 AND a.cancelled_at IS NULL
            AND $tripVis AND $actVis AND $blockSql
       ORDER BY a.id DESC LIMIT 200",
        array_merge([$destId], $tripArgs, $actArgs, $blockArgs)
    );

    $seen = [];
    foreach ($rows as $r) {
        $label = trim((string) ($r['place_name'] ?: $r['title']));
        if ($label === '') continue;
        $key = mb_strtolower($label);
        if (!isset($seen[$key])) {
            $seen[$key] = ['label' => $label, 'category' => (string) $r['category'],
                           'place_slug' => $r['place_slug'] ?: null, 'activity_id' => (int) $r['id'],
                           'users' => [], 'n' => 0];
        }
        // The same person saying it twice is still one person saying it.
        $seen[$key]['users'][(int) $r['user_id']] = (string) $r['username'];
        $seen[$key]['n'] = count($seen[$key]['users']);
    }
    $out = array_values($seen);
    usort($out, static fn(array $x, array $y) => $y['n'] <=> $x['n']);
    foreach ($out as $i => $r) $out[$i]['users'] = array_values($r['users']);
    return array_slice($out, 0, $limit);
}

/**
 * Open plans, anywhere, soonest first.
 *
 * A plan somebody opened to other people and a meetup are the same thing said twice. Rather than
 * keep two tables pretending to be two features, the pages that ask "what could I turn up to" read
 * both and show one list. This is the plan half of that.
 *
 * Same visibility rule as every other read of a plan: the trip's own setting, then the plan's, then
 * blocks in both directions.
 *
 * @return list<array<string,mixed>>
 */
function rmt_open_plans_upcoming(?array $viewer, ?int $destId = null, int $limit = 30): array {
    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('a.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }
    $where = '';
    $whereArgs = [];
    if ($destId !== null && $destId > 0) {
        $where = ' AND a.destination_id = ?';
        $whereArgs = [$destId];
    }

    return q_all(
        "SELECT a.*, t.slug trip_slug, t.date_from trip_from, t.date_to trip_to,
                u.username, pr.avatar_url, d.name dest_name, d.slug dest_slug,
                (SELECT COUNT(*) FROM activity_joins j WHERE j.activity_id = a.id AND j.state = 'going') going_count
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
           JOIN users u ON u.id = a.user_id AND u.status = 'active'
      LEFT JOIN profiles pr ON pr.user_id = a.user_id
      LEFT JOIN destinations d ON d.id = a.destination_id
          WHERE a.status = 'published' AND t.status = 'published'
            AND a.join_mode IN ('ask','open') AND a.cancelled_at IS NULL
            AND (a.day IS NULL OR a.day >= ?)
            AND (a.day IS NOT NULL OR t.date_to IS NULL OR t.date_to >= ?)
            AND $tripVis AND $actVis AND $blockSql $where
       ORDER BY CASE WHEN a.day IS NULL THEN 1 ELSE 0 END, a.day,
                COALESCE(a.start_time,'99:99'), a.id DESC
          LIMIT " . (int) $limit,
        array_merge([date('Y-m-d'), date('Y-m-d')], $tripArgs, $actArgs, $blockArgs, $whereArgs)
    );
}

/**
 * Is this plan a page worth asking anybody to index?
 *
 * A title and a day is a real page for the people involved and a thin one for a search engine:
 * "Dinner" on a Friday tells a stranger nothing. It earns a place in the index when somebody wrote
 * something about it, photographed it, or is coming to it. The same question decides the robots tag
 * on the page and the row in the sitemap, so the two can never disagree.
 */
function rmt_activity_has_substance(array $a): bool {
    if (trim((string) ($a['notes'] ?? '')) !== '') return true;
    if ((int) ($a['photo_count'] ?? 0) > 0) return true;
    if ((int) ($a['going_count'] ?? 0) > 0) return true;
    return false;
}

/**
 * The people layer of a place: who is going, and who went and would go again.
 *
 * A place page was a page about a building. This is what makes it a page about a place other
 * travelers are actually going to, and every number on it is a count of real rows: if one person
 * has it planned, it says one person.
 *
 * Saves are deliberately not part of this. Who bookmarked something is their business, and the
 * place page already carries the count without the names.
 *
 * @return array{planned:list<array<string,mixed>>,recommended:list<array<string,mixed>>,
 *               planned_n:int,recommended_n:int,overlapping:int}
 */
function rmt_place_network(int $placeId, ?array $viewer): array {
    $empty = ['planned' => [], 'recommended' => [], 'planned_n' => 0, 'recommended_n' => 0,
              'trips_n' => 0, 'overlapping' => 0];
    if ($placeId < 1) return $empty;

    [$tripVis, $tripArgs] = rmt_plan_visibility_sql('t', $viewer);
    [$actVis, $actArgs] = rmt_activity_visible_sql('a', $viewer);
    $blockSql = '1=1';
    $blockArgs = [];
    if ($viewer && function_exists('rmt_match_block_sql')) {
        [$blockSql] = rmt_match_block_sql('a.user_id');
        $blockArgs = [(int) $viewer['id'], (int) $viewer['id']];
    }

    $rows = q_all(
        "SELECT a.id, a.day, a.start_time, a.join_mode, a.recommend, a.cancelled_at,
                a.user_id, a.trip_id, u.username, pr.avatar_url,
                t.date_from trip_from, t.date_to trip_to
           FROM trip_activities a
           JOIN trips t ON t.id = a.trip_id
           JOIN users u ON u.id = a.user_id AND u.status = 'active'
      LEFT JOIN profiles pr ON pr.user_id = a.user_id
          WHERE a.place_id = ? AND a.status = 'published' AND t.status = 'published'
            AND $tripVis AND $actVis AND $blockSql
       ORDER BY a.day, a.id DESC LIMIT 200",
        array_merge([$placeId], $tripArgs, $actArgs, $blockArgs)
    );

    $today = date('Y-m-d');
    $planned = [];
    $recommended = [];
    foreach ($rows as $r) {
        if ((int) ($r['recommend'] ?? 0) === 1) {
            // One person who went is one person, however many times they planned it.
            $recommended[(int) $r['user_id']] = $r;
            continue;
        }
        if (!empty($r['cancelled_at'])) continue;
        $when = (string) ($r['day'] ?: $r['trip_to'] ?: '');
        if ($when !== '' && $when < $today) continue;
        $planned[(int) $r['user_id']] = $r;
    }

    /* How many of the people planning it will be there while the viewer is. Their own dates
       against dates those travelers published themselves, and nothing more precise than that. */
    $overlapping = 0;
    if ($viewer) {
        $mine = q_all("SELECT date_from, date_to FROM trips
                        WHERE user_id = ? AND status = 'published'
                          AND date_from IS NOT NULL AND date_to IS NOT NULL AND date_to >= ?",
                      [(int) $viewer['id'], $today]);
        foreach ($planned as $uid => $r) {
            if ($uid === (int) $viewer['id']) continue;
            $from = (string) ($r['day'] ?: $r['trip_from'] ?: '');
            $to   = (string) ($r['day'] ?: $r['trip_to'] ?: '');
            if ($from === '' || $to === '') continue;
            foreach ($mine as $m) {
                if ($from <= (string) $m['date_to'] && $to >= (string) $m['date_from']) { $overlapping++; break; }
            }
        }
    }

    /* How many separate trips include this place, which is a different fact from how many people:
       one traveler who has been three times is one person and three trips. Both are true and the
       page says which is which. */
    $tripIds = [];
    foreach ($rows as $r) {
        if (!empty($r['cancelled_at'])) continue;
        $tripIds[(int) ($r['trip_id'] ?? 0)] = true;
    }
    unset($tripIds[0]);

    return [
        'planned' => array_slice(array_values($planned), 0, 8),
        'recommended' => array_slice(array_values($recommended), 0, 8),
        'planned_n' => count($planned),
        'recommended_n' => count($recommended),
        'trips_n' => count($tripIds),
        'overlapping' => $overlapping,
    ];
}

/**
 * Map points for one trip's itinerary.
 *
 * Only plans attached to a place we hold coordinates for, because a plan whose "where" is the word
 * "Alfama" cannot be a pin without this site guessing a doorway. The list on the page is still the
 * full itinerary; the map is the subset that can honestly be drawn.
 *
 * @param list<array<string,mixed>> $activities already filtered for the viewer
 * @return list<array<string,mixed>>
 */
function rmt_activity_map_points(array $activities): array {
    $ids = [];
    foreach ($activities as $a) {
        $pid = (int) ($a['place_id'] ?? 0);
        if ($pid > 0) $ids[$pid] = true;
    }
    if (!$ids) return [];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $places = [];
    foreach (q_all("SELECT id, name, slug, lat, lng FROM places
                     WHERE id IN ($in) AND lat IS NOT NULL AND lng IS NOT NULL", array_keys($ids)) as $p) {
        $places[(int) $p['id']] = $p;
    }

    $out = [];
    foreach ($activities as $a) {
        $p = $places[(int) ($a['place_id'] ?? 0)] ?? null;
        if (!$p) continue;
        $meta = [];
        if (!empty($a['day'])) $meta[] = date('D j M', strtotime((string) $a['day']));
        if (!empty($a['start_time'])) $meta[] = (string) $a['start_time'];
        $meta[] = (string) $p['name'];
        $out[] = [
            'lat'   => (float) $p['lat'],
            'lng'   => (float) $p['lng'],
            'label' => (string) $a['title'],
            'href'  => url('activity/' . (int) $a['id']),
            'meta'  => implode(' · ', $meta),
            'group' => (string) ($a['day'] ?? ''),
        ];
    }
    return $out;
}

/**
 * May this person change this plan?
 *
 * Two conditions, and both are needed. They must have written it, or own the trip it is on; and
 * they must still be somebody who may add to that trip. Authorship alone is not enough: an editor
 * who was removed from a trip kept the right to edit and cancel the lines they had added to it,
 * which is a person still holding a key to a room they were asked to leave.
 */
function rmt_activity_can_edit(array $act, ?array $viewer): bool {
    if (!$viewer) return false;
    $uid = (int) $viewer['id'];
    if (in_array($viewer['role'] ?? '', ['admin', 'mod'], true)) return true;

    $trip = q_one('SELECT * FROM trips WHERE id = ?', [(int) $act['trip_id']]);
    if (!$trip) return false;
    if (!function_exists('rmt_trip_can_edit') || !rmt_trip_can_edit($trip, $viewer)) return false;

    return (int) $act['user_id'] === $uid || (int) $trip['user_id'] === $uid;
}
