<?php
declare(strict_types=1);

/**
 * Trip first: the visitor says where and when before anybody asks them for an account.
 *
 * Measured before this existed (2026-09-25): 833 views of the join form in seven days and not one
 * press of Create account. Every "Post your dates" link on the site went to /trip/new, which sent a
 * signed out visitor to a sign in form, so the only way to tell us about a trip was to make an
 * account first for a site that had not yet shown you anything. That is the wrong order: the trip
 * is what makes the site worth joining, because it is what we answer with the people who overlap.
 *
 * So the order is now: city, dates, who is coming, what you are into, whether you want to meet
 * people or find a buddy. Then one screen that says "this is ready, and here is how many travelers
 * it would put you in front of", with the account form on it. Nothing is published before the
 * address is confirmed; that gate is exactly where it was. The held work rides through the
 * existing pending slot (app/onboarding_pending.php), which now also survives a confirm link opened
 * in another browser.
 *
 * Nothing here writes a row for a signed out visitor. The draft lives in their own session until
 * they make an account, and a draft nobody finishes costs nothing and is visible to nobody.
 */

const RMT_PLAN_DRAFT_KEY = 'plan_draft';
/** Who is coming. Same keys as the buddy form; the four that are also travel styles carry over. */
const RMT_PLAN_MEET = ['yes' => 'Yes, show me to travelers on my dates', 'maybe' => 'Maybe, I will decide per person',
                       'no' => 'No, I just want to plan'];

/**
 * Turn the one short form into the inputs the two existing validators already understand, so the
 * rules for a trip and a buddy post live in one place each and cannot drift from this form.
 *
 * @return array{trip:array<string,mixed>, buddy:?array<string,mixed>, interests:list<string>, party:string, meet:string}
 */
function rmt_plan_first_inputs(array $in): array {
    $party = (string) ($in['party'] ?? '');
    if (!isset(RMT_BUDDY_PARTIES[$party])) $party = '';
    $meet = (string) ($in['meet'] ?? 'yes');
    if (!isset(RMT_PLAN_MEET[$meet])) $meet = 'yes';
    $ints = [];
    foreach ((array) ($in['interests'] ?? []) as $k) {
        $k = (string) $k;
        if (isset(RMT_INTERESTS[$k]) && !in_array($k, $ints, true)) $ints[] = $k;
    }
    $vis = (string) ($in['visibility'] ?? 'public');
    if (!in_array($vis, RMT_PLAN_VISIBILITIES, true)) $vis = 'public';
    $destId = (int) ($in['destination_id'] ?? 0);
    $from = trim((string) ($in['date_from'] ?? ''));
    $to = trim((string) ($in['date_to'] ?? ''));
    $note = trim((string) ($in['note'] ?? ''));

    $trip = [
        'destination_id' => $destId, 'date_from' => $from, 'date_to' => $to,
        'title' => '', 'body' => $note, 'visibility' => $vis, 'trip_type' => 'trip',
        // A party of "group" is not one of the four profile styles, and is left unstated rather than guessed.
        'travel_style' => isset(RMT_TRAVEL_STYLES[$party]) ? $party : '',
        'open_to_meeting' => $meet === 'yes' ? '1' : ($meet === 'no' ? '0' : ''),
    ];
    $buddy = null;
    if (!empty($in['want_buddy'])) {
        $buddy = [
            'trip_type' => 'trip', 'destination_id' => $destId, 'date_from' => $from, 'date_to' => $to,
            'flexible' => empty($in['flexible']) ? '' : '1', 'spots' => '1', 'budget' => 'any',
            'travel_party' => $party, 'interests' => $ints,
            'age_min' => (string) ($in['age_min'] ?? ''), 'age_max' => (string) ($in['age_max'] ?? ''),
            'description' => trim((string) ($in['buddy_note'] ?? '')),
            'safety_ack' => empty($in['safety_ack']) ? '' : '1',
        ];
    }
    return ['trip' => $trip, 'buddy' => $buddy, 'interests' => $ints, 'party' => $party, 'meet' => $meet];
}

/**
 * Validate the whole form. A trip here is always a plan: a city and both dates, still ahead.
 *
 * @return array{ok:bool, errors:list<string>, inputs:array}
 */
function rmt_plan_first_validate(array $in, ?string $today = null): array {
    $today = $today ?? date('Y-m-d');
    $x = rmt_plan_first_inputs($in);
    $t = $x['trip'];
    $errors = [];
    if ((int) $t['destination_id'] < 1) $errors[] = 'Pick the city you are going to.';
    if ($t['date_from'] === '' || $t['date_to'] === '') $errors[] = 'Add the day you arrive and the day you leave.';
    if (!$errors) {
        $v = rmt_trip_validate($t);
        foreach ($v['errors'] as $er) $errors[] = $er;
        if ($v['ok']) {
            if ((string) $v['data']['date_to'] < $today) $errors[] = 'Those dates are over. Post a trip that is still ahead.';
            if ((string) $v['data']['date_from'] > date('Y-m-d', strtotime($today . ' +730 days'))) {
                $errors[] = 'Post trips starting within the next two years.';
            }
        }
    }
    if ($x['buddy'] !== null) {
        $b = rmt_buddy_validate($x['buddy'], $today);
        foreach ($b['errors'] as $er) {
            // The date and city problems were already said once, above.
            if (str_contains($er, 'dates') || str_contains($er, 'Say where')) continue;
            $errors[] = $er === 'Describe the trip and who you are hoping to go with (20 to 4000 characters).'
                ? 'Say a line or two about who you hope to travel with (20 characters or more).' : $er;
        }
    }
    return ['ok' => !$errors, 'errors' => array_values(array_unique($errors)), 'inputs' => $x];
}

/** "Lisbon, 3 to 10 October 2026 · Solo · Food and markets · Looking for a travel buddy" */
function rmt_plan_first_summary(array $x): array {
    $t = $x['trip'];
    $d = dest_by_id((int) $t['destination_id']);
    $lines = [];
    $lines[] = rmt_plan_title((int) $t['destination_id'], (string) $t['date_from'], (string) $t['date_to']);
    if ($x['party'] !== '') $lines[] = RMT_BUDDY_PARTIES[$x['party']];
    if ($x['interests']) $lines[] = implode(', ', array_map(static fn($k) => RMT_INTERESTS[$k], $x['interests']));
    if ($x['buddy'] !== null) $lines[] = 'Looking for a travel buddy';
    elseif ($x['meet'] === 'yes') $lines[] = 'Open to meeting travelers';
    return ['dest' => $d, 'lines' => $lines];
}

/**
 * How many real people this trip would put the visitor in front of: other travelers with public
 * dates in that city that cross theirs, open buddy posts that cross them, and locals who said they
 * are happy to meet. Counted, never estimated, and only ever shown when it is above zero.
 *
 * @return array{travelers:int, buddies:int, locals:int}
 */
function rmt_plan_first_overlap(int $destId, string $from, string $to): array {
    $out = ['travelers' => 0, 'buddies' => 0, 'locals' => 0];
    if ($destId < 1 || $from === '' || $to === '') return $out;
    $out['travelers'] = (int) (q_one("SELECT COUNT(DISTINCT t.user_id) c FROM trips t
                                        JOIN users u ON u.id = t.user_id AND u.status = 'active'
                                       WHERE t.destination_id = ? AND t.status = 'published'
                                         AND COALESCE(t.visibility, 'public') = 'public'
                                         AND t.date_from IS NOT NULL AND t.date_from <= ? AND t.date_to >= ?
                                         AND (t.open_to_meeting IS NULL OR t.open_to_meeting <> 0)",
                                     [$destId, $to, $from])['c'] ?? 0);
    try {
        $out['buddies'] = (int) (q_one("SELECT COUNT(*) c FROM buddy_posts b
                                          JOIN users u ON u.id = b.user_id AND u.status = 'active'
                                         WHERE b.destination_id = ? AND b.status = 'open'
                                           AND b.date_from <= ? AND b.date_to >= ?",
                                       [$destId, $to, $from])['c'] ?? 0);
        $out['locals'] = (int) (q_one("SELECT COUNT(*) c FROM profiles p
                                         JOIN users u ON u.id = p.user_id AND u.status = 'active'
                                        WHERE p.home_destination_id = ? AND p.open_to_meeting = 1",
                                      [$destId])['c'] ?? 0);
    } catch (Throwable $e) {
        // An older schema without the buddy tables still gets the traveler count.
    }
    return $out;
}

/** Interests a brand new member picked on the trip form become their profile's, if it has none yet. */
function rmt_plan_first_interests(int $uid, array $ints): void {
    if ($uid < 1 || !$ints) return;
    try {
        if ((int) (q_one('SELECT COUNT(*) c FROM profile_interests WHERE user_id = ?', [$uid])['c'] ?? 0) > 0) return;
        foreach ($ints as $k) {
            if (isset(RMT_INTERESTS[$k])) q_run('INSERT INTO profile_interests (user_id, interest) VALUES (?,?)', [$uid, $k]);
        }
    } catch (Throwable $e) {
        // A profile nicety, never worth failing a trip over.
    }
}

/**
 * Publish a validated plan for a confirmed member: the trip, and the buddy post if they asked.
 *
 * @return array{trip_id:int, buddy_id:int}
 */
function rmt_plan_first_publish(array $me, array $x): array {
    $uid = (int) $me['id'];
    $v = rmt_trip_validate($x['trip']);
    $out = ['trip_id' => 0, 'buddy_id' => 0];
    if (!$v['ok']) return $out;
    $d = $v['data'];
    $dupe = q_one("SELECT id FROM trips WHERE user_id = ? AND destination_id = ? AND date_from = ? AND date_to = ?
                     AND status = 'published'", [$uid, (int) $d['destination_id'], $d['date_from'], $d['date_to']]);
    $out['trip_id'] = $dupe ? (int) $dupe['id'] : rmt_trip_create_row($uid, $d);
    rmt_plan_first_interests($uid, $x['interests']);
    if ($x['buddy'] !== null && can_host_meetups($me)) {
        $b = rmt_buddy_validate($x['buddy']);
        if ($b['ok']) {
            $out['buddy_id'] = rmt_buddy_insert($uid, $b['data']);
            if ($out['buddy_id'] > 0) {
                rmt_buddy_notify_matches('buddy', $out['buddy_id']);
                rmt_track('buddy_post_created', ['source' => 'plan', 'destination_id' => $b['data']['destination_id']]);
            }
        }
    }
    return $out;
}

/** Hand a held draft to the account that now exists: publish it, or hold it for the confirm click. */
function rmt_plan_first_hand_over(array $me, array $draft): void {
    if (!empty($draft['post'])) {
        if (email_is_verified($me)) {
            $pv = rmt_post_validate(['body' => (string) $draft['post']['body'],
                                     'destination_id' => (int) $draft['post']['destination_id']], $me);
            if ($pv['ok']) {
                $pid = rmt_post_create((int) $me['id'], $pv['data']);
                rmt_track('post_created', ['destination_id' => $pv['data']['destination_id'] ?? null]);
                if (!empty($pv['data']['destination_id'])) {
                    rmt_track('question_posted', ['source' => 'destination', 'destination_id' => (int) $pv['data']['destination_id']]);
                }
                $dest = dest_by_id((int) ($pv['data']['destination_id'] ?? 0));
                flash('Posted. Travelers following ' . ($dest['name'] ?? 'the city') . ' will see it.');
                redirect($dest ? '/d/' . $dest['slug'] . '#city-talk' : '/post/' . $pid);
            }
            flash('That question could not be posted. Try it again from the city page.');
            redirect('/talk');
        }
        rmt_pending_stash(['post' => $draft['post']]);
        flash('Your question is saved. It goes live the moment you confirm your email address.');
        redirect('/verify-email');
    }
    $x = $draft['inputs'] ?? null;
    if (!is_array($x)) redirect('/plan');
    if (email_is_verified($me)) {
        $r = rmt_plan_first_publish($me, $x);
        if ($r['trip_id'] > 0) {
            flash('Your trip is live. Here is who else will be there.');
            redirect('/matches?new=' . $r['trip_id']);
        }
        flash('That trip could not be saved. Check the dates and try again.');
        redirect('/plan');
    }
    $hold = ['trip' => $x['trip'], 'interests' => $x['interests']];
    if ($x['buddy'] !== null) $hold['buddy'] = $x['buddy'];
    rmt_pending_stash($hold);
    flash('Your trip is saved. It goes live the moment you confirm your email address.');
    redirect('/verify-email');
}

/* ---------- controllers ---------- */

/** GET /plan  the form. ?d=slug or ?destination_id=, ?from, ?to, ?buddy=1, ?cta= prefill it. */
function plan_form(array $a): void {
    $pre = ['destination_id' => (int) input('destination_id'), 'date_from' => (string) input('from'),
            'date_to' => (string) input('to'), 'want_buddy' => input('buddy') === '1' ? '1' : ''];
    $slug = (string) input('d');
    if ($slug !== '' && ($d = q_one('SELECT id FROM destinations WHERE slug = ?', [$slug]))) $pre['destination_id'] = (int) $d['id'];
    foreach (['date_from', 'date_to'] as $k) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pre[$k])) $pre[$k] = '';
    }
    // A draft they already started, when they come back to change it.
    $draft = $_SESSION[RMT_PLAN_DRAFT_KEY]['raw'] ?? null;
    if (is_array($draft) && input('edit') === '1') $pre = array_merge($pre, $draft);
    $cta = (string) input('cta');
    rmt_track_once_for('plan_view', (string) $pre['destination_id'], [
        'source' => 'plan', 'destination_id' => $pre['destination_id'] ?: null,
        'detail' => $cta,
    ]);
    $dest = $pre['destination_id'] ? dest_by_id((int) $pre['destination_id']) : null;
    view('plan', ['dests' => all_dests(), 'errors' => [], 'p' => $pre, 'dest' => $dest], [
        'title' => $dest ? 'Going to ' . $dest['name'] . '? Post your trip | RuinMyTrip' : 'Post your trip | RuinMyTrip',
        'description' => 'Say where and when you are going and see which travelers will be there on the same days. Free, and nobody can message you until you say yes.',
        // A form, not a page anybody searches for. Kept out of the index so it never competes with a city.
        'robots' => 'noindex,follow', 'canonical' => '',
    ]);
}

/** POST /plan */
function plan_submit(array $a): void {
    csrf_check();
    $key = is_logged_in() ? 'u' . (int) current_user()['id'] : rmt_client_ip();
    if (!rmt_rate_ok('plan_submit', $key, 30, 3600)) {
        flash('That was a lot of trips in an hour. Try again a little later.');
        redirect('/plan');
    }
    $v = rmt_plan_first_validate($_POST);
    if (!$v['ok']) {
        $destId = (int) ($_POST['destination_id'] ?? 0);
        view('plan', ['dests' => all_dests(), 'errors' => $v['errors'], 'p' => $_POST,
                      'dest' => $destId ? dest_by_id($destId) : null],
             ['title' => 'Post your trip | RuinMyTrip', 'robots' => 'noindex,follow', 'canonical' => '']);
        return;
    }
    $x = $v['inputs'];
    rmt_track('plan_submitted', ['source' => 'plan', 'destination_id' => (int) $x['trip']['destination_id']]);
    $draft = ['inputs' => $x, 'raw' => array_intersect_key($_POST, array_flip([
        'destination_id', 'date_from', 'date_to', 'party', 'interests', 'meet', 'visibility', 'note',
        'want_buddy', 'buddy_note', 'age_min', 'age_max', 'flexible', 'safety_ack']))];
    $me = current_user();
    if ($me) rmt_plan_first_hand_over($me, $draft);
    $_SESSION[RMT_PLAN_DRAFT_KEY] = $draft;
    redirect('/plan/join');
}

/**
 * POST /plan/ask  a question typed into a city page by somebody with no account. Held in their
 * session like a trip, and shown back to them on the account step.
 */
function plan_ask_submit(array $a): void {
    csrf_check();
    $body = trim((string) input('body'));
    $destId = (int) input('destination_id');
    $dest = $destId ? dest_by_id($destId) : null;
    $back = $dest ? '/d/' . $dest['slug'] . '#city-ask' : '/talk';
    $max = defined('RMT_POST_MAX') ? RMT_POST_MAX : 2000;
    if (mb_strlen($body) < 3 || mb_strlen($body) > $max) {
        flash('Write your question first, then join to post it.');
        redirect($back);
    }
    $draft = ['post' => ['body' => $body, 'destination_id' => $dest ? (int) $dest['id'] : 0]];
    $me = current_user();
    if ($me) rmt_plan_first_hand_over($me, $draft);
    rmt_track('plan_submitted', ['source' => 'destination', 'destination_id' => $dest ? (int) $dest['id'] : null]);
    $_SESSION[RMT_PLAN_DRAFT_KEY] = $draft;
    redirect('/plan/join');
}

/** GET /plan/join  the account step, with the trip or question held and shown back. */
function plan_join_form(array $a): void {
    $draft = $_SESSION[RMT_PLAN_DRAFT_KEY] ?? null;
    if (!is_array($draft)) redirect('/plan');
    $me = current_user();
    if ($me) {
        // They chose "sign in instead" and came back: hand it over now.
        unset($_SESSION[RMT_PLAN_DRAFT_KEY]);
        rmt_plan_first_hand_over($me, $draft);
    }
    $destId = !empty($draft['post']) ? (int) $draft['post']['destination_id'] : (int) ($draft['inputs']['trip']['destination_id'] ?? 0);
    rmt_track_once('plan_signup_view', ['source' => 'plan', 'destination_id' => $destId ?: null]);
    rmt_track('join_view', ['source' => 'plan']);
    view('plan_join', ['draft' => $draft, 'errors' => []] + rmt_plan_join_context($draft), [
        'title' => 'Almost there | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '',
    ]);
}

/** @return array{summary:?array, overlap:array, question:?array} */
function rmt_plan_join_context(array $draft): array {
    if (!empty($draft['post'])) {
        return ['summary' => null, 'overlap' => ['travelers' => 0, 'buddies' => 0, 'locals' => 0],
                'question' => ['body' => (string) $draft['post']['body'],
                               'dest' => dest_by_id((int) $draft['post']['destination_id'])]];
    }
    $x = $draft['inputs'];
    return ['summary' => rmt_plan_first_summary($x), 'question' => null,
            'overlap' => rmt_plan_first_overlap((int) $x['trip']['destination_id'],
                                                (string) $x['trip']['date_from'], (string) $x['trip']['date_to'])];
}

/** POST /plan/join  create the account and hand the draft to it. */
function plan_join_submit(array $a): void {
    csrf_check();
    $draft = $_SESSION[RMT_PLAN_DRAFT_KEY] ?? null;
    if (!is_array($draft)) redirect('/plan');
    if (is_logged_in()) { unset($_SESSION[RMT_PLAN_DRAFT_KEY]); rmt_plan_first_hand_over(current_user(), $draft); }
    $render = static function (array $errors) use ($draft): void {
        view('plan_join', ['draft' => $draft, 'errors' => $errors] + rmt_plan_join_context($draft),
             ['title' => 'Almost there | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
    };
    if (!rmt_rate_ok('register_ip', rmt_client_ip(), 5, 3600)) {
        rmt_track('join_failure', ['source' => 'plan', 'reason' => 'rate_limit']);
        $render(['Too many accounts created from this connection. Try again later.']);
        return;
    }
    rmt_track('join_submit', ['source' => 'plan']);
    $r = register_user(input('username'), input('email'), input('password'), input('birthdate'));
    if (!$r['ok']) {
        rmt_track('join_failure', ['source' => 'plan', 'reason' => 'validation']);
        $render($r['errors']);
        return;
    }
    rmt_track('join_created', ['source' => 'plan']);
    $_SESSION['rmt_mail_ok'] = !empty($r['mail_ok']) ? '1' : '0';
    unset($_SESSION[RMT_PLAN_DRAFT_KEY]);
    rmt_plan_first_hand_over((array) current_user(), $draft);
}

/* ---------- the city's pulse and the team's prompts, for the content page module ---------- */

/**
 * The RuinMyTrip team's own questions for a city. Labelled as ours wherever they are shown, and
 * each opens the city's composer with a first few words in it; nothing here is posted as anybody.
 * 'q' is what the link says, 'start' is what lands in the box.
 */
const RMT_CITY_PROMPTS = [
    'avoid'  => ['q' => 'What should tourists avoid in %s?', 'start' => 'Tourists in %s should avoid '],
    'stay'   => ['q' => 'Which neighborhood would you stay in on a first visit to %s?', 'start' => 'For a first visit to %s, stay in '],
    'tip'    => ['q' => 'One thing you wish you had known before going to %s?', 'start' => 'What I wish I had known before %s: '],
    'skip'   => ['q' => 'What is overrated in %s, and what is worth it instead?', 'start' => 'Overrated in %s: '],
    'ruined' => ['q' => 'What went wrong on your %s trip?', 'start' => 'What went wrong on my %s trip: '],
];

/** Three prompts for a city, rotated by name so neighbouring cities do not all ask the same thing. */
function rmt_city_prompts(string $city, int $n = 3): array {
    $keys = array_keys(RMT_CITY_PROMPTS);
    $shift = abs(crc32($city)) % count($keys);
    $keys = array_merge(array_slice($keys, $shift), array_slice($keys, 0, $shift));
    $out = [];
    foreach (array_slice($keys, 0, $n) as $k) $out[$k] = sprintf(RMT_CITY_PROMPTS[$k]['q'], $city);
    return $out;
}

/** The first words for the composer when a prompt link was followed, or ''. */
function rmt_city_prompt_start(string $key, string $city): string {
    return isset(RMT_CITY_PROMPTS[$key]) ? sprintf(RMT_CITY_PROMPTS[$key]['start'], $city) : '';
}

/**
 * What is happening in a city right now, as counts of real rows. Cached per request because a
 * page can draw the module more than once.
 *
 * @return array{going:int, buddies:int, questions:int, recent:list<array<string,mixed>>}
 */
function rmt_city_pulse(int $destId, bool $withRecent = true): array {
    static $cache = [];
    $empty = ['going' => 0, 'buddies' => 0, 'questions' => 0, 'recent' => []];
    if ($destId < 1) return $empty;
    $ck = $destId . ':' . ($withRecent ? 1 : 0);
    if (isset($cache[$ck])) return $cache[$ck];
    $today = date('Y-m-d');
    $out = $empty;
    try {
        // One statement for both counts: this runs on every place page, under a query budget.
        $row = q_one("SELECT (SELECT COUNT(DISTINCT t.user_id) FROM trips t
                                JOIN users u ON u.id = t.user_id AND u.status = 'active' AND u.role <> ?
                               WHERE t.destination_id = ? AND t.status = 'published'
                                 AND COALESCE(t.visibility, 'public') = 'public'
                                 AND t.date_from IS NOT NULL AND t.date_to >= ?) AS going,
                             (SELECT COUNT(*) FROM buddy_posts b
                                JOIN users u2 ON u2.id = b.user_id AND u2.status = 'active'
                               WHERE b.destination_id = ? AND b.status = 'open' AND b.date_to >= ?) AS buddies",
                     [RMT_EDITORIAL_ROLE, $destId, $today, $destId, $today]);
        $out['going'] = (int) ($row['going'] ?? 0);
        $out['buddies'] = (int) ($row['buddies'] ?? 0);
        if (!$withRecent) return $cache[$destId . ':0'] = $out;
        $out['recent'] = q_all("SELECT p.id, p.body, u.username FROM posts p
                                  JOIN users u ON u.id = p.user_id AND u.status = 'active'
                                 WHERE p.destination_id = ? AND p.status = 'published'
                              ORDER BY p.created_at DESC, p.id DESC LIMIT 2", [$destId]);
        // Fewer than two back means that is all of them; only a full page needs the count.
        $out['questions'] = count($out['recent']) < 2 ? count($out['recent'])
            : (int) (q_one("SELECT COUNT(*) c FROM posts WHERE destination_id = ? AND status = 'published'", [$destId])['c'] ?? 0);
    } catch (Throwable $e) {
        // A module on a content page must never take the page down.
    }
    return $cache[$ck] = $out;
}
