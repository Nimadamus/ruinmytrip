<?php
declare(strict_types=1);

/**
 * Trip watchlist, destination follows, email alerts, and the member dashboard.
 *
 * This is the answer to "why would anyone register?". Not "join our community" — a watchlist
 * that tells you what changed about your destination before you leave. Every route here is
 * built around that promise, and around not abusing the inbox it earns.
 */

/* ------------------------------------------------------------- dashboard */

/**
 * GET /dashboard — upcoming trips, what is new since last visit, saved destinations, the status
 * of your own submitted reports, and a prep checklist derived from the actual warnings.
 */
function dashboard(array $a): void {
    require_login();
    $me = current_user();
    $uid = (int) $me['id'];
    $tab = in_array(input('tab'), ['trips', 'reports', 'saved', 'alerts'], true) ? input('tab') : 'trips';

    $trips = rmt_watchlist($uid);
    // Compute "new since you last looked" BEFORE stamping last_seen_at, or the dashboard would
    // clear its own badge on the way to rendering it.
    foreach ($trips as &$t) {
        $t['new_warnings'] = rmt_new_warnings_for($t, 6);
        $t['prep'] = rmt_trip_prep_actions($t);
    }
    unset($t);

    $follows = q_all('SELECT df.*, d.name dest_name, d.slug dest_slug, d.country dest_country, d.hero_url, d.risk_level
                      FROM destination_follows df JOIN destinations d ON d.id = df.destination_id
                      WHERE df.user_id = ? ORDER BY d.name', [$uid]);
    foreach ($follows as &$f) $f['new_warnings'] = rmt_new_warnings_for($f, 4);
    unset($f);

    $saved = q_all("SELECT d.* FROM saves s JOIN destinations d ON d.id = s.target_id
                    WHERE s.user_id = ? AND s.target_type = 'destination' ORDER BY d.name", [$uid]);

    $myWarnings = rmt_warning_query(['user_id' => $uid, 'status' => 'any', 'sort' => 'recent'], 50)['rows'];
    $subs = q_all('SELECT s.*, d.name dest_name, d.slug dest_slug FROM alert_subscriptions s
                   LEFT JOIN destinations d ON d.id = s.destination_id
                   WHERE (s.user_id = ? OR LOWER(s.email) = LOWER(?)) AND s.unsubscribed_at IS NULL
                   ORDER BY s.id DESC', [$uid, (string) $me['email']]);

    // Now that the "new" sets are computed, mark everything seen.
    $now = date('Y-m-d H:i:s');
    q_exec('UPDATE trip_watchlist SET last_seen_at = ? WHERE user_id = ?', [$now, $uid]);
    q_exec('UPDATE destination_follows SET last_seen_at = ? WHERE user_id = ?', [$now, $uid]);

    view('dashboard', compact('me', 'trips', 'follows', 'saved', 'myWarnings', 'subs', 'tab'),
         ['title' => 'Your travel dashboard — RuinMyTrip',
          'description' => 'Upcoming trips, new warnings for your destinations, and the status of your reports.']);
}

/* -------------------------------------------------------------- watchlist */

/** POST /watchlist/add — save a trip from a destination page. */
function watchlist_add(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $destId = (int) input('destination_id');
    $d = dest_by_id($destId);
    if (!$d) { flash('Choose a destination first.'); redirect(rmt_safe_return_path(input('return') ?: '/explore')); }

    $dates = rmt_watchlist_validate_dates(input('date_from'), input('date_to'));
    if (!$dates['ok']) {
        flash(implode(' ', $dates['errors']));
        redirect(rmt_safe_return_path(input('return') ?: '/d/' . $d['slug']));
    }
    if (!rmt_rate_ok('watchlist_add', (string) $me['id'], 30, 3600)) {
        flash('That is a lot of trips at once. Try again shortly.');
        redirect(rmt_safe_return_path(input('return') ?: '/d/' . $d['slug']));
    }

    $freq = in_array(input('alert_frequency'), array_keys(RMT_ALERT_FREQUENCIES), true) ? input('alert_frequency') : 'weekly';
    $sev  = max(1, min(4, (int) (input('min_severity') ?: 1)));
    $cats = rmt_categories_encode((array) ($_POST['categories'] ?? []));
    $now  = date('Y-m-d H:i:s');

    $existing = q_one('SELECT id FROM trip_watchlist WHERE user_id = ? AND destination_id = ?', [(int) $me['id'], $destId]);
    if ($existing) {
        q_exec('UPDATE trip_watchlist SET date_from=?, date_to=?, alert_frequency=?, min_severity=?,
                       categories_json=?, updated_at=? WHERE id=?',
               [$dates['from'], $dates['to'], $freq, $sev, $cats, $now, (int) $existing['id']]);
        flash('Trip updated. We will keep watching ' . $d['name'] . ' for you.');
    } else {
        q_run('INSERT INTO trip_watchlist (user_id, destination_id, label, date_from, date_to, note,
                      categories_json, min_severity, alert_frequency, last_seen_at, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
              [(int) $me['id'], $destId, mb_substr(trim(input('label')), 0, 120) ?: null,
               $dates['from'], $dates['to'], mb_substr(trim(input('note')), 0, 1000) ?: null,
               $cats, $sev, $freq, $now, $now, $now]);
        flash('Trip saved. You will see new ' . $d['name'] . ' warnings on your dashboard.');
    }
    rmt_track('trip_saved', ['destination_id' => $destId, 'target_type' => 'destination', 'target_id' => $destId]);
    redirect(rmt_safe_return_path(input('return') ?: '/dashboard'));
}

function watchlist_edit_form(array $a): void {
    require_login();
    $w = rmt_watchlist_get((int) $a['id'], (int) current_user()['id']);
    if (!$w) not_found();
    view('watchlist_edit', ['w' => $w, 'errors' => []], ['title' => 'Edit trip — RuinMyTrip']);
}

function watchlist_edit_submit(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $w = rmt_watchlist_get((int) $a['id'], (int) $me['id']);
    if (!$w) not_found();

    $dates = rmt_watchlist_validate_dates(input('date_from'), input('date_to'));
    if (!$dates['ok']) {
        view('watchlist_edit', ['w' => array_merge($w, $_POST), 'errors' => $dates['errors']],
             ['title' => 'Edit trip — RuinMyTrip']);
        return;
    }
    $freq = in_array(input('alert_frequency'), array_keys(RMT_ALERT_FREQUENCIES), true) ? input('alert_frequency') : 'weekly';
    q_exec('UPDATE trip_watchlist SET label=?, date_from=?, date_to=?, note=?, categories_json=?,
                   min_severity=?, alert_frequency=?, updated_at=? WHERE id=? AND user_id=?',
           [mb_substr(trim(input('label')), 0, 120) ?: null, $dates['from'], $dates['to'],
            mb_substr(trim(input('note')), 0, 1000) ?: null,
            rmt_categories_encode((array) ($_POST['categories'] ?? [])),
            max(1, min(4, (int) (input('min_severity') ?: 1))), $freq, date('Y-m-d H:i:s'),
            (int) $w['id'], (int) $me['id']]);
    flash('Trip updated.');
    redirect('/dashboard');
}

function watchlist_delete(array $a): void {
    require_login(); csrf_check();
    q_exec('DELETE FROM trip_watchlist WHERE id = ? AND user_id = ?', [(int) $a['id'], (int) current_user()['id']]);
    flash('Trip removed from your watchlist.');
    redirect('/dashboard');
}

/* --------------------------------------------------------------- follows */

/** POST /destination/follow — follow a place with no dates attached. */
function destination_follow_action(array $a): void {
    require_login(); csrf_check();
    $me = current_user();
    $destId = (int) input('destination_id');
    $d = dest_by_id($destId); if (!$d) not_found();
    $back = rmt_safe_return_path(input('return') ?: '/d/' . $d['slug']);

    $has = q_one('SELECT 1 FROM destination_follows WHERE user_id = ? AND destination_id = ?', [(int) $me['id'], $destId]);
    if ($has) {
        q_exec('DELETE FROM destination_follows WHERE user_id = ? AND destination_id = ?', [(int) $me['id'], $destId]);
        flash('You will no longer get ' . $d['name'] . ' updates.');
    } else {
        $now = date('Y-m-d H:i:s');
        try {
            q_exec('INSERT INTO destination_follows (user_id, destination_id, min_severity, alert_frequency, last_seen_at, created_at)
                    VALUES (?,?,?,?,?,?)', [(int) $me['id'], $destId, 2, 'weekly', $now, $now]);
        } catch (Throwable $e) {
            // composite PK rejected a double submit; already following
        }
        rmt_track('destination_followed', ['destination_id' => $destId,
                                           'target_type' => 'destination', 'target_id' => $destId]);
        flash('Following ' . $d['name'] . '. New warnings will show on your dashboard.');
    }
    redirect($back);
}

/* ---------------------------------------------------------------- alerts */

/** GET /alerts — the standalone subscribe page (also the target of the homepage CTA). */
function alerts_form(array $a): void {
    $slug = trim((string) ($_GET['destination'] ?? ''));
    $d = $slug !== '' ? dest_by_slug($slug) : null;
    view('alerts', ['d' => $d, 'dests' => all_dests(), 'errors' => [], 'done' => false],
         ['title' => 'Get travel warning alerts for your destination — RuinMyTrip',
          'description' => 'Tell us where you are going and we will email you the important new warnings before your trip. '
                         . 'Weekly at most, one click to stop.',
          'breadcrumbs' => [['name' => 'Home', 'url' => url()], ['name' => 'Alerts', 'url' => url('alerts')]]]);
}

/**
 * POST /alerts/subscribe — double opt-in.
 *
 * The reply is deliberately identical whether or not the address is already subscribed: a
 * subscription list that answers "yes, that person is signed up for Bangkok" is an enumeration
 * oracle about other people's travel plans.
 */
function alerts_subscribe(array $a): void {
    csrf_check();
    $email = trim(input('email'));
    $slug  = trim(input('destination'));
    $d     = $slug !== '' ? dest_by_slug($slug) : null;
    $errors = [];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($slug !== '' && !$d) $errors[] = 'Choose a destination from the list.';
    if (!rmt_rate_ok('alert_subscribe', rmt_client_ip(), 8, 3600)) $errors[] = 'Too many requests from this connection. Try again later.';
    if ($errors) {
        view('alerts', ['d' => $d, 'dests' => all_dests(), 'errors' => $errors, 'done' => false],
             ['title' => 'Get travel warning alerts — RuinMyTrip']);
        return;
    }

    $me = current_user();
    $res = rmt_alert_subscribe($email, $d ? (int) $d['id'] : null, [
        'user_id'      => $me ? (int) $me['id'] : null,
        'frequency'    => in_array(input('frequency'), array_keys(RMT_ALERT_FREQUENCIES), true) ? input('frequency') : 'weekly',
        'min_severity' => max(1, min(4, (int) (input('min_severity') ?: 2))),
        'categories'   => (array) ($_POST['categories'] ?? []),
        'source'       => mb_substr((string) input('source'), 0, 60) ?: 'alerts_page',
    ]);
    if ($res['status'] !== 'exists' && !empty($res['row'])) {
        rmt_mail_alert_confirm($email, $d['name'] ?? null, rmt_alert_confirm_url($res['row']));
    }
    rmt_track('alert_subscribed', ['destination_id' => $d ? (int) $d['id'] : null,
                                   'meta' => ['source' => $res['status']]]);
    view('alerts', ['d' => $d, 'dests' => all_dests(), 'errors' => [], 'done' => true],
         ['title' => 'Check your email — RuinMyTrip']);
}

/** GET /alerts/confirm — the click in the confirmation email. */
function alerts_confirm(array $a): void {
    $sub = rmt_alert_by_token((string) ($_GET['e'] ?? ''), (string) ($_GET['t'] ?? ''));
    if (!$sub) {
        flash('That confirmation link is not valid or has already been used.');
        redirect('/alerts');
    }
    if (!$sub['confirmed_at']) {
        q_exec('UPDATE alert_subscriptions SET confirmed_at = ?, unsubscribed_at = NULL WHERE id = ?',
               [date('Y-m-d H:i:s'), (int) $sub['id']]);
    }
    $d = $sub['destination_id'] ? dest_by_id((int) $sub['destination_id']) : null;
    flash('Confirmed. We will email you important new warnings' . ($d ? ' for ' . $d['name'] : '') . '.');
    redirect($d ? '/d/' . $d['slug'] : '/');
}

/** GET /alerts/unsubscribe — one click, no login, always works. */
function alerts_unsubscribe(array $a): void {
    $sub = rmt_alert_by_token((string) ($_GET['e'] ?? ''), (string) ($_GET['t'] ?? ''));
    if ($sub) {
        q_exec('UPDATE alert_subscriptions SET unsubscribed_at = ? WHERE id = ?',
               [date('Y-m-d H:i:s'), (int) $sub['id']]);
    }
    // Same page either way: a valid-looking link should never reveal whether the address existed.
    view('alerts_unsubscribed', [], ['title' => 'Unsubscribed — RuinMyTrip']);
}
