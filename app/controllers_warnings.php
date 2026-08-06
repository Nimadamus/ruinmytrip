<?php
declare(strict_types=1);

/**
 * Warning routes: browsing, submitting, editing, voting, right-of-reply, staleness reports.
 *
 * Domain rules live in app/warnings.php; this file is HTTP plumbing plus the two things that
 * genuinely belong at the request layer — spam control and moderation-state transitions on
 * user edits (an edit to an approved warning sends it back to the queue, so a report cannot be
 * approved as one thing and then quietly rewritten into another).
 */

/* ---------------------------------------------------------------- filters */

/**
 * Parse the shared warning filter/sort query string once, so /warnings, a destination page and
 * a category page all filter identically and a bookmarked URL means the same thing everywhere.
 */
function rmt_warning_filters_from_query(): array {
    $g = static fn(string $k): string => trim((string) ($_GET[$k] ?? ''));
    $f = [];
    if (($c = $g('category')) !== '' && isset(RMT_WARNING_CATEGORIES[$c])) $f['category'] = $c;
    if (($s = (int) $g('severity')) >= 1 && $s <= 4) $f['severity_min'] = $s;
    if (($v = $g('verification')) !== '' && in_array($v, RMT_WARNING_VERIFICATION, true)) $f['verification'] = $v;
    if (($t = $g('traveler')) !== '' && isset(RMT_TRAVELER_TYPES[$t])) $f['traveler_type'] = $t;
    if (($m = (int) $g('month')) >= 1 && $m <= 12) $f['month'] = $m;
    if (($q = $g('q')) !== '') $f['q'] = mb_substr($q, 0, 120);
    $sort = $g('sort');
    $f['sort'] = in_array($sort, ['recent', 'helpful', 'severity', 'experienced', 'oldest', 'verified'], true)
        ? $sort : 'recent';
    return $f;
}

/** Rebuild the current query string with one key changed — used by every filter control. */
function rmt_query_with(array $overrides): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') unset($q[$k]); else $q[$k] = $v;
    }
    unset($q['page']);
    return $q ? '?' . http_build_query($q) : '';
}

function rmt_page_param(): int { return max(1, (int) ($_GET['page'] ?? 1)); }

/* ------------------------------------------------------------- list pages */

/** GET /warnings — every approved warning, filterable. The site's spine for discovery. */
function warnings_index(array $a): void {
    $f = rmt_warning_filters_from_query();
    $perPage = 20;
    $page = rmt_page_param();
    $res = rmt_warning_query($f, $perPage, ($page - 1) * $perPage);
    $counts = q_all("SELECT category, COUNT(*) c FROM warnings WHERE status='approved' GROUP BY category");
    $byCat = [];
    foreach ($counts as $r) $byCat[(string) $r['category']] = (int) $r['c'];

    $title = 'Travel warnings from real travelers';
    if (!empty($f['category'])) $title = rmt_warning_category_label($f['category']) . ' warnings from real travelers';

    view('warnings_index', [
        'rows' => $res['rows'], 'total' => $res['total'], 'f' => $f,
        'page' => $page, 'perPage' => $perPage, 'byCat' => $byCat, 'category' => $f['category'] ?? null,
    ], [
        'title' => $title . ' — RuinMyTrip',
        'description' => 'Browse traveler-submitted warnings about scams, hidden costs, transport problems, '
                       . 'closures and crowds — filtered by destination, category, severity and season.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Warnings', 'url' => url('warnings')]],
    ]);
}

/**
 * GET /warnings/{category} — one of the ten "what can ruin a trip" categories.
 * A real page with its own copy, not a redirect into a query string, because these are the
 * pages people link to and search for.
 */
function warning_category(array $a): void {
    $key = (string) $a['category'];
    if (!isset(RMT_WARNING_CATEGORIES[$key])) not_found();
    $cat = RMT_WARNING_CATEGORIES[$key];

    $f = rmt_warning_filters_from_query();
    $f['category'] = $key;
    $perPage = 20;
    $page = rmt_page_param();
    $res = rmt_warning_query($f, $perPage, ($page - 1) * $perPage);

    // Destinations most affected by this category — the useful "where is this worst?" cut.
    $dests = q_all("SELECT d.id, d.name, d.slug, d.country, COUNT(*) c
                    FROM warnings w JOIN destinations d ON d.id = w.destination_id
                    WHERE w.status = 'approved' AND w.category = ?
                    GROUP BY d.id, d.name, d.slug, d.country ORDER BY c DESC, d.name LIMIT 12", [$key]);
    // Editorially reviewed guides on this theme, so the category page is useful even before
    // travelers have filed reports against it.
    $guides = q_all("SELECT p.*, d.name dest_name, d.slug dest_slug FROM seo_landing_pages p
                     LEFT JOIN destinations d ON d.id = p.destination_id
                     WHERE p.status = 'published' AND p.category = ?
                     ORDER BY p.id DESC LIMIT 12", [$key]);

    view('warning_category', [
        'key' => $key, 'cat' => $cat, 'rows' => $res['rows'], 'total' => $res['total'],
        'f' => $f, 'page' => $page, 'perPage' => $perPage, 'dests' => $dests, 'guides' => $guides,
    ], [
        'title' => $cat['label'] . ' that ruin trips — traveler warnings | RuinMyTrip',
        'description' => $cat['blurb'] . ' Real traveler reports, by destination, severity and season.',
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Warnings', 'url' => url('warnings')],
                          ['name' => $cat['label'], 'url' => url('warnings/' . $key)]],
    ]);
}

/** GET /d/{slug}/warnings — the full, filterable warning list for one destination. */
function destination_warnings(array $a): void {
    $d = dest_by_slug($a['slug']); if (!$d) not_found();
    $f = rmt_warning_filters_from_query();
    $f['destination_id'] = (int) $d['id'];
    $perPage = 20;
    $page = rmt_page_param();
    $res = rmt_warning_query($f, $perPage, ($page - 1) * $perPage);
    $counts = rmt_warning_category_counts((int) $d['id']);

    view('destination_warnings', [
        'd' => $d, 'rows' => $res['rows'], 'total' => $res['total'], 'f' => $f,
        'page' => $page, 'perPage' => $perPage, 'counts' => $counts,
    ], [
        'title' => 'All travel warnings for ' . $d['name'] . ', ' . $d['country'] . ' — RuinMyTrip',
        'description' => 'Every traveler-submitted warning for ' . $d['name']
                       . ' — scams, hidden costs, transport, closures and crowds, filtered by severity and season.',
        'og_image' => abs_url($d['hero_url']),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Destinations', 'url' => url('explore')],
                          ['name' => $d['name'], 'url' => url('d/' . $d['slug'])],
                          ['name' => 'Warnings', 'url' => url('d/' . $d['slug'] . '/warnings')]],
    ]);
}

/* ----------------------------------------------------------------- detail */

/** GET /w/{id}/{slug} — a single warning. */
function warning_show(array $a): void {
    $w = rmt_warning_get((int) $a['id']);
    if (!$w) not_found();
    $me = current_user();
    if (!rmt_warning_can_view($w, $me)) not_found();

    // Canonicalise the slug rather than serving the same content on many URLs.
    $slug = ($w['slug'] ?: rmt_warning_slug($w));
    if (($a['slug'] ?? '') !== $slug && $w['status'] === 'approved') {
        redirect(url(ltrim(rmt_warning_path($w), '/')));
    }

    $w['author'] = author((int) $w['user_id']);
    $photos = q_all('SELECT * FROM warning_photos WHERE warning_id = ? ORDER BY sort, id', [(int) $w['id']]);
    $responses = rmt_warning_responses((int) $w['id']);
    $myVote = rmt_warning_my_vote((int) $w['id'], $me ? (int) $me['id'] : null);
    $contributor = rmt_contributor_stats((int) $w['user_id']);
    $related = rmt_warning_query([
        'destination_id' => (int) $w['destination_id'], 'category' => (string) $w['category'], 'sort' => 'helpful',
    ], 6)['rows'];
    $related = array_values(array_filter($related, static fn($r) => (int) $r['id'] !== (int) $w['id']));
    $modLog = rmt_is_moderator($me) ? rmt_warning_moderation_log((int) $w['id']) : [];

    if ($w['status'] === 'approved') {
        // Denormalised counter, best-effort: a view count is not worth failing a page render for.
        try { q_exec('UPDATE warnings SET view_count = view_count + 1 WHERE id = ?', [(int) $w['id']]); }
        catch (Throwable $e) {}
        rmt_track('warning_view', ['destination_id' => (int) $w['destination_id'],
                                   'target_type' => 'warning', 'target_id' => (int) $w['id']]);
    }

    // Structured data: a warning is a first-person report, so it is marked up as a Report by a
    // Person, NOT as a Review with a rating. Claiming a star rating we do not have would be
    // structured-data spam, and rating a destination one star because of one taxi driver would
    // be dishonest besides.
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'Report',
        'name' => $w['title'],
        'about' => ['@type' => 'TouristDestination', 'name' => $w['dest_name'] . ', ' . $w['dest_country'],
                    'url' => url('d/' . $w['dest_slug'])],
        'datePublished' => substr((string) $w['created_at'], 0, 10),
        'dateModified' => substr((string) ($w['updated_at'] ?: $w['created_at']), 0, 10),
        'author' => ['@type' => 'Person', 'name' => '@' . $w['username']],
        'publisher' => ['@type' => 'Organization', 'name' => 'RuinMyTrip', 'url' => cfg('app_url')],
        'abstract' => mb_substr(strip_tags((string) $w['body']), 0, 300),
        'url' => url(ltrim(rmt_warning_path($w), '/')),
    ];

    view('warning_show', compact('w', 'photos', 'responses', 'myVote', 'related', 'me', 'contributor', 'modLog'), [
        'title' => $w['title'] . ' — ' . $w['dest_name'] . ' travel warning | RuinMyTrip',
        'description' => mb_substr(strip_tags((string) $w['body']), 0, 155),
        'og_image' => $photos ? abs_url($photos[0]['url']) : abs_url(dest_by_id((int) $w['destination_id'])['hero_url'] ?? ''),
        'jsonld' => jsonld($ld),
        'breadcrumbs' => [['name' => 'Home', 'url' => url()],
                          ['name' => $w['dest_name'], 'url' => url('d/' . $w['dest_slug'])],
                          ['name' => 'Warnings', 'url' => url('d/' . $w['dest_slug'] . '/warnings')],
                          ['name' => $w['title'], 'url' => url(ltrim(rmt_warning_path($w), '/'))]],
    ]);
}

/* ------------------------------------------------------------ submission */

function warning_new_form(array $a): void {
    require_login();
    $preselect = (int) input('destination');
    $w = ($preselect && dest_by_id($preselect)) ? ['destination_id' => $preselect] : null;
    if ($cat = input('category')) {
        if (isset(RMT_WARNING_CATEGORIES[$cat])) { $w ??= []; $w['category'] = $cat; }
    }
    view('warning_new', ['dests' => all_dests(), 'errors' => [], 'w' => $w],
         ['title' => 'Share a travel warning — RuinMyTrip',
          'description' => 'Report a scam, hidden cost, closure or transport problem so the next traveler can plan around it.']);
}

function warning_create(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $render = static function (array $errors, ?array $w = null): void {
        view('warning_new', ['dests' => all_dests(), 'errors' => $errors, 'w' => $w ?? $_POST],
             ['title' => 'Share a travel warning — RuinMyTrip']);
    };

    if (!rmt_submit_ok('warning_new', input('_submit'))) {
        flash('That warning was already submitted.'); redirect('/dashboard'); return;
    }
    if (!rmt_rate_ok('warning_create', (string) $me['id'], RMT_WARNING_RATE_PER_HOUR, 3600)) {
        $render(['You have submitted several warnings in the last hour. Try again a little later — '
               . 'the queue is reviewed by a person and a burst of reports slows everyone down.']);
        return;
    }

    $isDraft = input('action') === 'draft';
    if (!$isDraft && !email_is_verified($me)) {
        flash('Confirm your email address before submitting a warning. Your draft is safe in the meantime.');
        redirect('/verify-email');
    }

    $v = rmt_warning_validate($_POST, $isDraft);
    if (!$v['ok']) { $render($v['errors']); return; }
    $d = $v['data'];

    $hash = rmt_warning_dedupe_hash((int) $d['destination_id'], $d['category'], $d['title']);
    if (!$isDraft && ($dupe = rmt_warning_duplicate_id((int) $me['id'], $hash))) {
        $render(['You have already filed a very similar warning for this destination. '
               . 'Edit that one instead so the two do not compete.'], $_POST);
        return;
    }

    $now = date('Y-m-d H:i:s');
    // Everything enters the queue as 'pending'. There is no auto-publish path, for anyone.
    $status = $isDraft ? 'draft' : 'pending';
    $id = (int) q_run('INSERT INTO warnings
        (user_id,destination_id,title,slug,category,body,advice,severity,date_experienced,season_month,
         location_detail,cost_impact_usd,provider_type,provider_name,traveler_type,attested,status,
         verification,dedupe_hash,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        (int) $me['id'], $d['destination_id'], $d['title'], '', $d['category'], $d['body'], $d['advice'],
        $d['severity'], $d['date_experienced'], $d['season_month'], $d['location_detail'], $d['cost_impact_usd'],
        $d['provider_type'], $d['provider_name'], $d['traveler_type'], $d['attested'], $status,
        'unverified', $hash, $now, $now,
    ]);
    $slug = rmt_warning_slug($d + ['id' => $id]);
    q_exec('UPDATE warnings SET slug = ? WHERE id = ?', [$slug, $id]);

    $photoErrors = rmt_attach_warning_photos($id, (int) $me['id']);
    rmt_track('warning_submitted', ['destination_id' => (int) $d['destination_id'],
                                    'target_type' => 'warning', 'target_id' => $id,
                                    'meta' => ['category' => $d['category'], 'severity' => $d['severity']]]);

    $msg = $isDraft
        ? 'Draft saved. Only you can see it until you submit it.'
        : 'Thank you — your warning is in the moderation queue. We review submissions by hand, usually within a day or two.';
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect('/dashboard?tab=reports');
}

/**
 * Store photos attached to a warning. Same contract as reviews: an upload failure is reported
 * but never discards the written report.
 * @return string[]
 */
function rmt_attach_warning_photos(int $warningId, int $ownerId): array {
    $errors = [];
    if (empty($_FILES['photos']) || !is_array($_FILES['photos']['name'] ?? null)) return $errors;

    $existing = (int) (q_one('SELECT COUNT(*) c FROM warning_photos WHERE warning_id = ?', [$warningId])['c'] ?? 0);
    $slots = max(0, 4 - $existing);

    $n = count($_FILES['photos']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ((int) $_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($slots <= 0) { $errors[] = 'You can attach up to 4 photos per warning.'; break; }
        if (!rmt_rate_ok('upload', (string) $ownerId, 40, 3600)) { $errors[] = 'Too many uploads. Try again later.'; break; }

        $file = [
            'name' => $_FILES['photos']['name'][$i], 'type' => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i],
            'size' => $_FILES['photos']['size'][$i],
        ];
        $res = rmt_upload_image($file, $ownerId);
        if (!$res['ok']) { $errors[] = $res['error']; continue; }
        q_run('INSERT INTO warning_photos (warning_id, url, storage_key, caption, width, height, bytes, sort, created_at)
               VALUES (?,?,?,?,?,?,?,?,?)',
              [$warningId, $res['url'], $res['key'], null, $res['w'], $res['h'], $res['bytes'],
               $existing + $i, date('Y-m-d H:i:s')]);
        $slots--;
    }
    return $errors;
}

function warning_edit_form(array $a): void {
    require_login();
    $w = rmt_warning_get((int) $a['id']); if (!$w) not_found();
    if (!rmt_warning_can_edit($w, current_user())) forbidden('You can only edit your own warnings.');
    $photos = q_all('SELECT * FROM warning_photos WHERE warning_id = ? ORDER BY sort, id', [(int) $w['id']]);
    view('warning_edit', ['w' => $w, 'dests' => all_dests(), 'errors' => [], 'photos' => $photos],
         ['title' => 'Edit warning — RuinMyTrip']);
}

/**
 * Editing an approved warning returns it to the queue.
 *
 * Without this, "get approved, then rewrite" is an open door: the badge and the placement stay
 * while the words change. The user is told this happens, so it is a rule rather than a surprise.
 */
function warning_edit_submit(array $a): void {
    require_login(); csrf_check();
    $w = rmt_warning_get((int) $a['id']); if (!$w) not_found();
    $me = current_user();
    if (!rmt_warning_can_edit($w, $me)) forbidden('You can only edit your own warnings.');

    $isDraft = input('action') === 'draft' && $w['status'] === 'draft';
    $v = rmt_warning_validate($_POST, $isDraft);
    if (!$v['ok']) {
        $photos = q_all('SELECT * FROM warning_photos WHERE warning_id = ? ORDER BY sort, id', [(int) $w['id']]);
        view('warning_edit', ['w' => array_merge($w, $_POST), 'dests' => all_dests(),
                              'errors' => $v['errors'], 'photos' => $photos],
             ['title' => 'Edit warning — RuinMyTrip']);
        return;
    }
    $d = $v['data'];
    $wasApproved = $w['status'] === 'approved';
    $status = $isDraft ? 'draft' : 'pending';
    $now = date('Y-m-d H:i:s');
    $slug = rmt_warning_slug($d + ['id' => (int) $w['id']]);

    q_exec('UPDATE warnings SET destination_id=?, title=?, slug=?, category=?, body=?, advice=?, severity=?,
                   date_experienced=?, season_month=?, location_detail=?, cost_impact_usd=?, provider_type=?,
                   provider_name=?, traveler_type=?, attested=?, status=?, verification=?, dedupe_hash=?,
                   updated_at=? WHERE id=?', [
        $d['destination_id'], $d['title'], $slug, $d['category'], $d['body'], $d['advice'], $d['severity'],
        $d['date_experienced'], $d['season_month'], $d['location_detail'], $d['cost_impact_usd'],
        $d['provider_type'], $d['provider_name'], $d['traveler_type'], $d['attested'], $status,
        // Any edit invalidates a previous verification — it verified different words.
        'unverified',
        rmt_warning_dedupe_hash((int) $d['destination_id'], $d['category'], $d['title']),
        $now, (int) $w['id'],
    ]);
    $photoErrors = rmt_attach_warning_photos((int) $w['id'], (int) $me['id']);

    $msg = $isDraft ? 'Draft updated.'
         : ($wasApproved
            ? 'Updated. Because the wording changed, this warning has gone back to the moderation queue.'
            : 'Updated. Your warning is in the moderation queue.');
    if ($photoErrors) $msg .= ' Some photos were not added: ' . implode(' ', array_unique($photoErrors));
    flash($msg);
    redirect('/dashboard?tab=reports');
}

function warning_delete(array $a): void {
    require_login(); csrf_check();
    $w = rmt_warning_get((int) $a['id']); if (!$w) not_found();
    $me = current_user();
    if (!rmt_warning_can_edit($w, $me) && !rmt_is_moderator($me)) {
        forbidden('You can only delete your own warnings.');
    }
    // Free the stored image bytes as well as the row; orphaned media is a real cost on a
    // database-backed store.
    foreach (q_all('SELECT storage_key FROM warning_photos WHERE warning_id = ?', [(int) $w['id']]) as $p) {
        if (!empty($p['storage_key'])) rmt_storage_delete((string) $p['storage_key']);
    }
    q_exec('DELETE FROM warnings WHERE id = ?', [(int) $w['id']]);
    flash('Warning deleted.');
    redirect('/dashboard?tab=reports');
}

/* ---------------------------------------------------------------- voting */

/** POST /warning/{id}/helpful — one vote per person, toggleable. */
function warning_vote_action(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $w = rmt_warning_get((int) $a['id']); if (!$w) not_found();
    if ($w['status'] !== 'approved') forbidden('That warning is not published yet.');
    if ((int) $w['user_id'] === (int) $me['id']) {
        flash('You cannot vote on your own warning.');
        redirect(url(ltrim(rmt_warning_path($w), '/')));
    }
    $vote = input('vote') === 'not_helpful' ? 'not_helpful' : 'helpful';
    $existing = rmt_warning_my_vote((int) $w['id'], (int) $me['id']);

    if ($existing === $vote) {
        q_exec('DELETE FROM warning_votes WHERE warning_id = ? AND user_id = ?', [(int) $w['id'], (int) $me['id']]);
    } elseif ($existing !== null) {
        q_exec('UPDATE warning_votes SET vote = ?, created_at = ? WHERE warning_id = ? AND user_id = ?',
               [$vote, date('Y-m-d H:i:s'), (int) $w['id'], (int) $me['id']]);
    } else {
        try {
            q_exec('INSERT INTO warning_votes (warning_id, user_id, vote, created_at) VALUES (?,?,?,?)',
                   [(int) $w['id'], (int) $me['id'], $vote, date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            // Composite PK rejected a double-submitted form. Nothing to do; the vote already exists.
        }
    }
    rmt_warning_recount_votes((int) $w['id']);
    redirect(rmt_safe_return_path(input('return') ?: rmt_warning_path($w)));
}

/* ----------------------------------------------------- right of reply */

/**
 * GET/POST /w/{id}/respond — the business response process.
 *
 * Named businesses get a way to answer on the page itself rather than only by legal threat.
 * Responses queue for moderation like everything else and never alter or hide the original.
 */
function warning_respond_form(array $a): void {
    $w = rmt_warning_get((int) $a['id']); if (!$w || $w['status'] !== 'approved') not_found();
    view('warning_respond', ['w' => $w, 'errors' => []],
         ['title' => 'Respond to a warning — RuinMyTrip',
          'description' => 'If your business is named in a traveler warning, you can post a response.']);
}

function warning_respond_submit(array $a): void {
    csrf_check();
    $w = rmt_warning_get((int) $a['id']); if (!$w || $w['status'] !== 'approved') not_found();

    $name  = trim(input('responder_name'));
    $role  = trim(input('responder_role'));
    $email = trim(input('contact_email'));
    $body  = trim((string) ($_POST['body'] ?? ''));
    $errors = [];
    if ($name === '')  $errors[] = 'Tell us the business or organisation you represent.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A contact email is required so we can verify who you are.';
    if (mb_strlen($body) < 40) $errors[] = 'Please write at least 40 characters.';
    if (mb_strlen($body) > 4000) $errors[] = 'That response is too long.';
    if (!rmt_rate_ok('warning_respond', rmt_client_ip(), 5, 3600)) $errors[] = 'Too many responses from this connection. Try again later.';

    if ($errors) {
        view('warning_respond', ['w' => $w, 'errors' => $errors], ['title' => 'Respond to a warning — RuinMyTrip']);
        return;
    }
    q_run('INSERT INTO warning_responses (warning_id, responder_name, responder_role, contact_email, body, status, created_at)
           VALUES (?,?,?,?,?,?,?)',
          [(int) $w['id'], $name, $role ?: null, $email, $body, 'pending', date('Y-m-d H:i:s')]);
    flash('Thank you. Your response has been sent to our moderators for verification and will appear on the page once confirmed.');
    redirect(url(ltrim(rmt_warning_path($w), '/')));
}

/* ------------------------------------------------------ outdated reports */

/**
 * POST /outdated — "this information is out of date".
 *
 * Deliberately open to logged-out visitors: the person who just discovered that a museum
 * reopened is usually not a member, and making them register first means we never hear it.
 * Rate-limited by IP instead.
 */
function outdated_report_action(array $a): void {
    csrf_check();
    $type = input('target_type');
    $id   = (int) input('target_id');
    if (!in_array($type, ['warning', 'risk_section', 'destination', 'landing_page', 'faq'], true) || $id <= 0) {
        forbidden('That is not something we can flag as outdated.');
    }
    $me = current_user();
    $who = $me ? 'u' . $me['id'] : rmt_client_ip();
    if (!rmt_rate_ok('outdated', $who, 10, 3600)) {
        flash('Thanks — you have flagged several items recently. Try again a little later.');
        redirect(rmt_safe_return_path(input('return') ?: '/'));
    }
    q_run('INSERT INTO staleness_reports (reporter_id, target_type, target_id, note, status, created_at)
           VALUES (?,?,?,?,?,?)',
          [$me ? (int) $me['id'] : null, $type, $id, mb_substr(trim(input('note')), 0, 1000) ?: null,
           'open', date('Y-m-d H:i:s')]);
    flash('Thank you — flagged for review. Travel facts go stale fast and this is how we catch it.');
    redirect(rmt_safe_return_path(input('return') ?: '/'));
}
