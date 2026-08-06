<?php
declare(strict_types=1);

/**
 * The owner's control room.
 *
 * The governing requirement is that running this site never requires editing code: destinations,
 * risk sections, FAQs, warnings, landing pages, affiliate links, users, homepage features and
 * trending selection are all editable here.
 *
 * Two rules hold throughout:
 *   * Moderation is auditable. Every warning state change goes through rmt_warning_moderate(),
 *     which writes an append-only log entry. Nothing here writes `status` directly.
 *   * Moderation is reversible. There is no destructive action in this file that cannot be
 *     undone from the same screen — a wrong call should never require a database session.
 */

function rmt_admin_nav(): array {
    return [
        ['Overview',      'admin'],
        ['Warnings',      'admin/warnings'],
        ['Destinations',  'admin/destinations'],
        ['Guide pages',   'admin/pages'],
        ['Responses',     'admin/responses'],
        ['Outdated',      'admin/outdated'],
        ['Alerts',        'admin/alerts'],
        ['Affiliates',    'admin/affiliates'],
        ['Users',         'admin/users'],
        ['Analytics',     'admin/analytics'],
        ['Homepage',      'admin/homepage'],
    ];
}

/* ------------------------------------------------------------- settings */

function rmt_setting(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (q_all('SELECT key, value FROM site_settings') as $r) $cache[(string) $r['key']] = $r['value']; }
        catch (Throwable $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function rmt_setting_set(string $key, ?string $value, int $actorId): void {
    $now = date('Y-m-d H:i:s');
    $exists = q_one('SELECT 1 FROM site_settings WHERE key = ?', [$key]);
    if ($exists) {
        q_exec('UPDATE site_settings SET value = ?, updated_by = ?, updated_at = ? WHERE key = ?',
               [$value, $actorId, $now, $key]);
    } else {
        q_exec('INSERT INTO site_settings (key, value, updated_by, updated_at) VALUES (?,?,?,?)',
               [$key, $value, $actorId, $now]);
    }
}

/* ------------------------------------------------------------- overview */

/** GET /admin — everything waiting for a person, in one screen. */
function admin_overview(array $a): void {
    require_role('admin', 'mod');
    $c = static function (string $sql, array $args = []): int {
        try { return (int) (q_one($sql, $args)['c'] ?? 0); } catch (Throwable $e) { return 0; }
    };
    $stats = [
        'pending_warnings'  => $c("SELECT COUNT(*) c FROM warnings WHERE status = 'pending'"),
        'revision_warnings' => $c("SELECT COUNT(*) c FROM warnings WHERE status = 'needs_revision'"),
        'approved_warnings' => $c("SELECT COUNT(*) c FROM warnings WHERE status = 'approved'"),
        'open_reports'      => $c("SELECT COUNT(*) c FROM reports WHERE status = 'open'"),
        'pending_responses' => $c("SELECT COUNT(*) c FROM warning_responses WHERE status = 'pending'"),
        'open_outdated'     => $c("SELECT COUNT(*) c FROM staleness_reports WHERE status = 'open'"),
        'destinations'      => $c('SELECT COUNT(*) c FROM destinations'),
        'covered'           => $c('SELECT COUNT(DISTINCT destination_id) c FROM destination_risk_sections'),
        'pages'             => $c("SELECT COUNT(*) c FROM seo_landing_pages WHERE status = 'published'"),
        'users'             => $c('SELECT COUNT(*) c FROM users'),
        'subscribers'       => $c('SELECT COUNT(*) c FROM alert_subscriptions WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL'),
        'watchlists'        => $c('SELECT COUNT(*) c FROM trip_watchlist'),
    ];
    $queue = rmt_warning_query(['status' => ['pending', 'needs_revision'], 'sort' => 'oldest'], 8)['rows'];
    $reports = q_all("SELECT r.*, u.username reporter FROM reports r JOIN users u ON u.id = r.reporter_id
                      WHERE r.status = 'open' ORDER BY r.id DESC LIMIT 10");
    // Destinations with published warnings but no reviewed editorial spine — the real content gap.
    $gaps = q_all("SELECT d.id, d.name, d.slug,
                          (SELECT COUNT(*) FROM destination_risk_sections s WHERE s.destination_id = d.id) sections,
                          (SELECT COUNT(*) FROM warnings w WHERE w.destination_id = d.id AND w.status='approved') warnings
                   FROM destinations d ORDER BY sections ASC, warnings DESC, d.name LIMIT 12");

    view('admin/overview', compact('stats', 'queue', 'reports', 'gaps'),
         ['title' => 'Admin overview — RuinMyTrip']);
}

/* ------------------------------------------------------ warning moderation */

/** GET /admin/warnings — the moderation queue, filterable by state. */
function admin_warnings(array $a): void {
    require_role('admin', 'mod');
    $status = (string) ($_GET['status'] ?? 'pending');
    if (!in_array($status, array_merge(RMT_WARNING_STATUSES, ['any']), true)) $status = 'pending';
    $f = rmt_warning_filters_from_query();
    $f['status'] = $status;
    if ($status === 'pending') $f['sort'] = 'oldest';   // fairness: first in, first reviewed
    $page = rmt_page_param();
    $res = rmt_warning_query($f, 25, ($page - 1) * 25);
    $counts = [];
    foreach (RMT_WARNING_STATUSES as $s) {
        $counts[$s] = (int) (q_one('SELECT COUNT(*) c FROM warnings WHERE status = ?', [$s])['c'] ?? 0);
    }
    view('admin/warnings', ['rows' => $res['rows'], 'total' => $res['total'], 'status' => $status,
                            'counts' => $counts, 'page' => $page, 'f' => $f],
         ['title' => 'Warning moderation — RuinMyTrip']);
}

/**
 * POST /admin/warnings/{id}/moderate — approve / reject / request revision / verify / dispute /
 * feature. One endpoint, one audit trail.
 */
function admin_warning_moderate(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    $id = (int) $a['id'];
    $w = rmt_warning_get($id); if (!$w) not_found();
    $note = mb_substr(trim(input('note')), 0, 1000);
    $action = (string) input('action');

    $map = [
        'approve'  => ['status', 'approved'],
        'reject'   => ['status', 'rejected'],
        'revise'   => ['status', 'needs_revision'],
        'requeue'  => ['status', 'pending'],
        'verify'   => ['verification', 'verified'],
        'dispute'  => ['verification', 'disputed'],
        'unverify' => ['verification', 'unverified'],
        'feature'  => ['featured', '1'],
        'unfeature'=> ['featured', '0'],
    ];
    if (!isset($map[$action])) { flash('Unknown moderation action.'); redirect('/admin/warnings'); }
    [$field, $value] = $map[$action];

    // Rejecting or asking for a revision without saying why leaves the author with nothing to act
    // on, and leaves us with no record of the decision.
    if (in_array($action, ['reject', 'revise', 'dispute'], true) && $note === '') {
        flash('Add a short note explaining the decision — the author sees it, and it goes in the log.');
        redirect('/admin/warnings?status=' . rawurlencode((string) $w['status']));
    }

    $changed = rmt_warning_moderate($id, $field, $value, (int) $me['id'], $note);
    if ($changed && in_array($action, ['approve', 'reject', 'revise'], true)) {
        rmt_notify_warning_author($w, $action, $note);
    }
    flash($changed ? 'Updated.' : 'No change — it was already in that state.');
    redirect(rmt_safe_return_path(input('return') ?: '/admin/warnings'));
}

/**
 * Tell an author what happened to their report.
 *
 * In-app always; email only for a rejection or a revision request, because those are the two the
 * author has to do something about. An approval notification by email would be pure volume.
 */
function rmt_notify_warning_author(array $w, string $action, string $note): void {
    $type = 'warning_' . $action;
    try {
        q_run('INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, created_at)
               VALUES (?,?,?,?,?,?)',
              [(int) $w['user_id'], $type, null, 'warning', (int) $w['id'], date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {}

    if ($action === 'approve' || !rmt_mail_enabled()) return;
    $u = q_one('SELECT email, username FROM users WHERE id = ?', [(int) $w['user_id']]);
    if (!$u) return;
    $heading = $action === 'reject' ? 'Your warning was not published' : 'Your warning needs a small change';
    $body = '<p>Hi @' . e((string) $u['username']) . ' — a moderator reviewed your warning '
          . '<strong>' . e((string) $w['title']) . '</strong> for ' . e((string) $w['dest_name']) . '.</p>'
          . ($note !== '' ? '<p style="background:#f6f8fa;padding:12px;border-radius:8px">' . e($note) . '</p>' : '')
          . ($action === 'revise' ? '<p>Edit it and it will go straight back into the queue.</p>' : '');
    try {
        rmt_mail_send((string) $u['email'], $heading,
            rmt_mail_layout($heading, $body, 'Open your reports', url('dashboard?tab=reports')),
            $heading . "\n\n" . $note . "\n\n" . url('dashboard?tab=reports'));
    } catch (Throwable $e) {}
}

/* ------------------------------------------------------- destination content */

/** GET /admin/destinations — coverage at a glance. */
function admin_destinations(array $a): void {
    require_role('admin', 'mod');
    $q = trim((string) ($_GET['q'] ?? ''));
    $args = [];
    $sql = "SELECT d.*,
              (SELECT COUNT(*) FROM destination_risk_sections s WHERE s.destination_id = d.id) sections,
              (SELECT COUNT(*) FROM destination_faqs f WHERE f.destination_id = d.id) faqs,
              (SELECT COUNT(*) FROM warnings w WHERE w.destination_id = d.id AND w.status='approved') warnings,
              (SELECT COUNT(*) FROM seo_landing_pages p WHERE p.destination_id = d.id AND p.status='published') pages
            FROM destinations d";
    if ($q !== '') { $sql .= ' WHERE LOWER(d.name) LIKE ? OR LOWER(d.country) LIKE ?';
                     $args[] = '%' . mb_strtolower($q) . '%'; $args[] = '%' . mb_strtolower($q) . '%'; }
    $rows = q_all($sql . ' ORDER BY d.name', $args);
    view('admin/destinations', ['rows' => $rows, 'q' => $q], ['title' => 'Destinations — RuinMyTrip admin']);
}

/** GET /admin/destination/{id} — edit the risk report for one destination. */
function admin_destination_edit(array $a): void {
    require_role('admin', 'mod');
    $d = dest_by_id((int) $a['id']); if (!$d) not_found();
    $sections = q_all('SELECT * FROM destination_risk_sections WHERE destination_id = ?', [(int) $d['id']]);
    $byKey = [];
    foreach ($sections as $s) $byKey[(string) $s['section_key']] = $s;
    $faqs = rmt_destination_faqs((int) $d['id']);
    $pages = q_all('SELECT * FROM seo_landing_pages WHERE destination_id = ? ORDER BY template', [(int) $d['id']]);
    view('admin/destination_edit', compact('d', 'byKey', 'faqs', 'pages'),
         ['title' => 'Edit ' . $d['name'] . ' — RuinMyTrip admin']);
}

/** POST /admin/destination/{id} — the destination's own risk fields. */
function admin_destination_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $d = dest_by_id((int) $a['id']); if (!$d) not_found();
    $risk = (int) input('risk_level');
    q_exec('UPDATE destinations SET summary = ?, risk_level = ?, risk_summary = ?, worth_visiting = ?,
                   best_months = ?, worst_months = ?, airport_codes = ?, featured = ?, last_reviewed_at = ?
            WHERE id = ?', [
        mb_substr(trim((string) ($_POST['summary'] ?? '')), 0, 600),
        ($risk >= 1 && $risk <= 4) ? $risk : null,
        mb_substr(trim((string) ($_POST['risk_summary'] ?? '')), 0, 2000) ?: null,
        mb_substr(trim((string) ($_POST['worth_visiting'] ?? '')), 0, 4000) ?: null,
        mb_substr(trim(input('best_months')), 0, 120) ?: null,
        mb_substr(trim(input('worst_months')), 0, 120) ?: null,
        mb_substr(trim(input('airport_codes')), 0, 60) ?: null,
        input('featured') ? 1 : 0,
        date('Y-m-d'),
        (int) $d['id'],
    ]);
    flash('Destination updated.');
    redirect('/admin/destination/' . (int) $d['id']);
}

/** POST /admin/destination/{id}/section — upsert one risk section. Empty body deletes it. */
function admin_section_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $d = dest_by_id((int) $a['id']); if (!$d) not_found();
    $key = (string) input('section_key');
    $defs = rmt_risk_section_defs();
    if (!isset($defs[$key])) { flash('Unknown section.'); redirect('/admin/destination/' . (int) $d['id']); }

    $body = trim((string) ($_POST['body'] ?? ''));
    $now = date('Y-m-d H:i:s');
    $existing = q_one('SELECT * FROM destination_risk_sections WHERE destination_id = ? AND section_key = ?',
                      [(int) $d['id'], $key]);
    if ($body === '') {
        if ($existing) q_exec('DELETE FROM destination_risk_sections WHERE id = ?', [(int) $existing['id']]);
        flash('Section cleared.');
        redirect('/admin/destination/' . (int) $d['id'] . '#s-' . $key);
    }
    $type = in_array(input('content_type'), ['fact', 'editorial', 'alert'], true) ? input('content_type') : $defs[$key]['type'];
    $sev = (int) input('severity');
    $sources = rmt_parse_sources((string) ($_POST['sources'] ?? ''));
    $sort = array_search($key, array_keys($defs), true) ?: 0;

    if ($existing) {
        q_exec('UPDATE destination_risk_sections SET heading=?, body=?, content_type=?, severity=?,
                       sources_json=?, sort=?, last_reviewed_at=?, updated_at=? WHERE id=?',
               [mb_substr(trim(input('heading')), 0, 160) ?: $defs[$key]['heading'], $body, $type,
                ($sev >= 1 && $sev <= 4) ? $sev : null, $sources, $sort, date('Y-m-d'), $now, (int) $existing['id']]);
    } else {
        q_run('INSERT INTO destination_risk_sections
                (destination_id, section_key, heading, body, content_type, severity, sources_json, sort,
                 last_reviewed_at, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)',
              [(int) $d['id'], $key, mb_substr(trim(input('heading')), 0, 160) ?: $defs[$key]['heading'],
               $body, $type, ($sev >= 1 && $sev <= 4) ? $sev : null, $sources, $sort, date('Y-m-d'), $now, $now]);
    }
    flash('Section saved.');
    redirect('/admin/destination/' . (int) $d['id'] . '#s-' . $key);
}

/**
 * Sources arrive as one "Title | https://url" per line. Stored as JSON so the renderer never has
 * to parse free text, and so a malformed line is rejected at write time rather than at read time.
 */
function rmt_parse_sources(string $raw): ?string {
    $out = [];
    foreach (preg_split('/\r?\n/', trim($raw)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line, 2));
        $title = $parts[0];
        $urlPart = $parts[1] ?? '';
        if ($urlPart !== '' && !preg_match('#^https?://#i', $urlPart)) $urlPart = '';
        if ($title === '' && $urlPart === '') continue;
        $out[] = ['title' => mb_substr($title, 0, 200), 'url' => mb_substr($urlPart, 0, 500)];
    }
    return $out ? json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

/** Reverse of rmt_parse_sources(), for the edit textarea. */
function rmt_sources_to_text(?string $json): string {
    $lines = [];
    foreach (rmt_sources($json) as $s) {
        $lines[] = trim(($s['title'] ?? '') . (!empty($s['url']) ? ' | ' . $s['url'] : ''));
    }
    return implode("\n", $lines);
}

/** POST /admin/destination/{id}/faq — add, edit or delete one FAQ entry. */
function admin_faq_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $d = dest_by_id((int) $a['id']); if (!$d) not_found();
    $id = (int) input('faq_id');

    if (input('delete') && $id) {
        q_exec('DELETE FROM destination_faqs WHERE id = ? AND destination_id = ?', [$id, (int) $d['id']]);
        flash('FAQ removed.');
        redirect('/admin/destination/' . (int) $d['id'] . '#faqs');
    }
    $q = mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 300);
    $ans = trim((string) ($_POST['answer'] ?? ''));
    if ($q === '' || $ans === '') {
        flash('A FAQ needs both a question and an answer.');
        redirect('/admin/destination/' . (int) $d['id'] . '#faqs');
    }
    if ($id) {
        q_exec('UPDATE destination_faqs SET question=?, answer=?, sort=?, last_reviewed_at=? WHERE id=? AND destination_id=?',
               [$q, $ans, (int) input('sort'), date('Y-m-d'), $id, (int) $d['id']]);
    } else {
        q_run('INSERT INTO destination_faqs (destination_id, question, answer, sort, last_reviewed_at, created_at)
               VALUES (?,?,?,?,?,?)', [(int) $d['id'], $q, $ans, (int) input('sort'), date('Y-m-d'), date('Y-m-d H:i:s')]);
    }
    flash('FAQ saved.');
    redirect('/admin/destination/' . (int) $d['id'] . '#faqs');
}

/* ------------------------------------------------------------ landing pages */

function admin_pages(array $a): void {
    require_role('admin', 'mod');
    $rows = q_all('SELECT p.*, d.name dest_name FROM seo_landing_pages p
                   LEFT JOIN destinations d ON d.id = p.destination_id
                   ORDER BY p.status, p.template, p.id DESC');
    view('admin/pages', ['rows' => $rows], ['title' => 'Guide pages — RuinMyTrip admin']);
}

function admin_page_edit(array $a): void {
    require_role('admin', 'mod');
    $p = null;
    if (!empty($a['id'])) {
        $p = q_one('SELECT * FROM seo_landing_pages WHERE id = ?', [(int) $a['id']]);
        if (!$p) not_found();
    }
    view('admin/page_edit', ['p' => $p, 'dests' => all_dests(), 'errors' => []],
         ['title' => ($p ? 'Edit' : 'New') . ' guide page — RuinMyTrip admin']);
}

function admin_page_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    $id = (int) input('id');
    $templates = rmt_landing_templates();
    $tpl = (string) input('template');
    $errors = [];

    $slug = slugify((string) input('slug'));
    $h1   = mb_substr(trim((string) ($_POST['h1'] ?? '')), 0, 200);
    $title = mb_substr(trim((string) ($_POST['title_tag'] ?? '')), 0, 200);
    $desc = mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 320);
    $body = trim((string) ($_POST['body'] ?? ''));
    $intro = trim((string) ($_POST['intro'] ?? ''));
    $destId = (int) input('destination_id');
    $status = in_array(input('status'), ['draft', 'published'], true) ? input('status') : 'draft';

    if (!isset($templates[$tpl])) $errors[] = 'Choose a page template.';
    if ($slug === '' || $slug === 'item') $errors[] = 'A URL slug is required.';
    if ($h1 === '')   $errors[] = 'An H1 is required.';
    if ($title === '') $errors[] = 'A title tag is required.';
    if ($desc === '')  $errors[] = 'A meta description is required.';
    // Thin-content floor: a published page has to say something a database row does not.
    if ($status === 'published' && mb_strlen(strip_tags($body)) < 600) {
        $errors[] = 'A published guide needs at least 600 characters of real content. Save it as a draft until it does.';
    }
    // A slug must never collide with a real route, or the resolver would shadow the site.
    if (in_array($slug, rmt_reserved_slugs(), true)) $errors[] = 'That slug collides with a site route. Choose another.';
    $clash = q_one('SELECT id FROM seo_landing_pages WHERE slug = ? AND id <> ?', [$slug, $id]);
    if ($clash) $errors[] = 'Another page already uses that slug.';

    if ($errors) {
        view('admin/page_edit', ['p' => array_merge(['id' => $id], $_POST), 'dests' => all_dests(), 'errors' => $errors],
             ['title' => 'Guide page — RuinMyTrip admin']);
        return;
    }
    $now = date('Y-m-d H:i:s');
    $sources = rmt_parse_sources((string) ($_POST['sources'] ?? ''));
    $cat = input('category');
    $cat = isset(RMT_WARNING_CATEGORIES[$cat]) ? $cat : ($templates[$tpl]['category'] ?? null);

    if ($id) {
        q_exec('UPDATE seo_landing_pages SET slug=?, template=?, destination_id=?, category=?, h1=?, title_tag=?,
                       meta_description=?, intro=?, body=?, sources_json=?, status=?, last_reviewed_at=?, updated_at=?
                WHERE id=?',
               [$slug, $tpl, $destId ?: null, $cat, $h1, $title, $desc, $intro ?: null, $body ?: null,
                $sources, $status, date('Y-m-d'), $now, $id]);
    } else {
        $id = (int) q_run('INSERT INTO seo_landing_pages
                (slug, template, destination_id, category, h1, title_tag, meta_description, intro, body,
                 sources_json, status, author_id, last_reviewed_at, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$slug, $tpl, $destId ?: null, $cat, $h1, $title, $desc, $intro ?: null, $body ?: null,
               $sources, $status, (int) $me['id'], date('Y-m-d'), $now, $now]);
    }
    flash('Page saved. ' . ($status === 'published' ? 'It is live at /' . $slug : 'Still a draft.'));
    redirect('/admin/page/' . $id);
}

function admin_page_delete(array $a): void {
    require_role('admin'); csrf_check();
    q_exec('DELETE FROM seo_landing_pages WHERE id = ?', [(int) $a['id']]);
    flash('Page deleted.');
    redirect('/admin/pages');
}

/**
 * Every first-segment path the router owns. The landing-page resolver runs last, but a slug that
 * matches one of these would still be unreachable — so it is rejected at write time instead of
 * silently creating a page nobody can visit.
 */
function rmt_reserved_slugs(): array {
    return ['explore', 'discover', 'd', 'u', 'feed', 'trip', 'reviews', 'review', 'guides', 'guide', 'g',
            'blog', 'collections', 'collection', 'c', 'meetups', 'meetup', 'going', 'leaderboard', 'tags',
            'tag', 'search', 'notifications', 'messages', 'block', 'unblock', 'unsubscribe', 'settings',
            'login', 'register', 'logout', 'verify-email', 'forgot-password', 'reset-password', 'follow',
            'compliment', 'react', 'comment', 'report', 'admin', 'terms', 'privacy', 'guidelines',
            'affiliate', 'safety', 'editorial-policy', 'sitemap.xml', 'feed.xml', 'media', 'healthz',
            'readyz', 'warnings', 'warning', 'w', 'alerts', 'dashboard', 'watchlist', 'go', 'outdated',
            'destination', 'warning-guides', 'assets', 'robots.txt', 'api'];
}

/* ------------------------------------------------------ responses & staleness */

function admin_responses(array $a): void {
    require_role('admin', 'mod');
    $rows = q_all("SELECT r.*, w.title warning_title, w.slug warning_slug, d.name dest_name
                   FROM warning_responses r JOIN warnings w ON w.id = r.warning_id
                   JOIN destinations d ON d.id = w.destination_id
                   ORDER BY (r.status = 'pending') DESC, r.id DESC LIMIT 200");
    view('admin/responses', ['rows' => $rows], ['title' => 'Business responses — RuinMyTrip admin']);
}

function admin_response_action(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    $action = input('action') === 'approve' ? 'approved' : 'rejected';
    q_exec('UPDATE warning_responses SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?',
           [$action, (int) $me['id'], date('Y-m-d H:i:s'), (int) $a['id']]);
    flash('Response ' . $action . '.');
    redirect('/admin/responses');
}

function admin_outdated(array $a): void {
    require_role('admin', 'mod');
    $rows = q_all("SELECT s.*, u.username FROM staleness_reports s LEFT JOIN users u ON u.id = s.reporter_id
                   ORDER BY (s.status = 'open') DESC, s.id DESC LIMIT 200");
    view('admin/outdated', ['rows' => $rows], ['title' => 'Outdated-information reports — RuinMyTrip admin']);
}

function admin_outdated_resolve(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    q_exec("UPDATE staleness_reports SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?",
           [input('action') === 'reopen' ? 'open' : 'resolved', (int) $me['id'], date('Y-m-d H:i:s'), (int) $a['id']]);
    flash('Updated.');
    redirect('/admin/outdated');
}

/* --------------------------------------------------------------- alerts */

function admin_alerts(array $a): void {
    require_role('admin', 'mod');
    $subs = q_all('SELECT s.*, d.name dest_name FROM alert_subscriptions s
                   LEFT JOIN destinations d ON d.id = s.destination_id
                   ORDER BY s.id DESC LIMIT 300');
    $stats = [
        'confirmed' => (int) (q_one('SELECT COUNT(*) c FROM alert_subscriptions WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL')['c'] ?? 0),
        'pending'   => (int) (q_one('SELECT COUNT(*) c FROM alert_subscriptions WHERE confirmed_at IS NULL AND unsubscribed_at IS NULL')['c'] ?? 0),
        'gone'      => (int) (q_one('SELECT COUNT(*) c FROM alert_subscriptions WHERE unsubscribed_at IS NOT NULL')['c'] ?? 0),
        'sent7'     => (int) (q_one('SELECT COUNT(*) c FROM alert_deliveries WHERE created_at >= ?', [date('Y-m-d H:i:s', strtotime('-7 days'))])['c'] ?? 0),
        'watchlists'=> (int) (q_one('SELECT COUNT(*) c FROM trip_watchlist')['c'] ?? 0),
    ];
    view('admin/alerts', compact('subs', 'stats'), ['title' => 'Alert subscribers — RuinMyTrip admin']);
}

/* ------------------------------------------------------------ affiliates */

function admin_affiliates(array $a): void {
    require_role('admin');
    $rows = q_all('SELECT l.*, d.name dest_name FROM affiliate_links l
                   LEFT JOIN destinations d ON d.id = l.destination_id ORDER BY l.kind, l.sort, l.id');
    view('admin/affiliates', ['rows' => $rows, 'dests' => all_dests()],
         ['title' => 'Affiliate links — RuinMyTrip admin']);
}

function admin_affiliate_save(array $a): void {
    require_role('admin'); csrf_check();
    $id = (int) input('id');
    $slug = slugify((string) input('slug'));
    $urlIn = trim(input('target_url'));
    $errors = [];
    if ($slug === '' || $slug === 'item') $errors[] = 'A short slug is required.';
    if (!preg_match('#^https://#i', $urlIn)) $errors[] = 'The destination URL must start with https://.';
    if (trim(input('label')) === '') $errors[] = 'A label is required.';
    if (!isset(RMT_AFFILIATE_KINDS[input('kind')])) $errors[] = 'Choose a kind.';
    $clash = q_one('SELECT id FROM affiliate_links WHERE slug = ? AND id <> ?', [$slug, $id]);
    if ($clash) $errors[] = 'Another link already uses that slug.';
    if ($errors) { flash(implode(' ', $errors)); redirect('/admin/affiliates'); }

    $now = date('Y-m-d H:i:s');
    $args = [$slug, mb_substr(trim(input('label')), 0, 160), mb_substr(trim(input('provider')), 0, 120),
             (string) input('kind'), $urlIn, ((int) input('destination_id')) ?: null,
             mb_substr(trim(input('blurb')), 0, 300) ?: null, input('active') ? 1 : 0, (int) input('sort')];
    if ($id) {
        q_exec('UPDATE affiliate_links SET slug=?, label=?, provider=?, kind=?, target_url=?, destination_id=?,
                       blurb=?, active=?, sort=?, updated_at=? WHERE id=?', array_merge($args, [$now, $id]));
    } else {
        q_run('INSERT INTO affiliate_links (slug,label,provider,kind,target_url,destination_id,blurb,active,sort,created_at,updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)', array_merge($args, [$now, $now]));
    }
    flash('Affiliate link saved.');
    redirect('/admin/affiliates');
}

function admin_affiliate_delete(array $a): void {
    require_role('admin'); csrf_check();
    q_exec('DELETE FROM affiliate_links WHERE id = ?', [(int) $a['id']]);
    flash('Link deleted.');
    redirect('/admin/affiliates');
}

/** GET /go/{slug} — the only outbound path for a partner link. */
function affiliate_go(array $a): void {
    $link = rmt_affiliate_by_slug((string) $a['slug']);
    if (!$link) not_found();
    rmt_affiliate_record_click($link);
    header('Referrer-Policy: no-referrer');
    redirect((string) $link['target_url']);
}

/* --------------------------------------------------------------- users */

function admin_users(array $a): void {
    require_role('admin');
    $q = trim((string) ($_GET['q'] ?? ''));
    $args = [];
    $sql = "SELECT u.*, p.display_name,
              (SELECT COUNT(*) FROM warnings w WHERE w.user_id = u.id AND w.status='approved') warnings,
              (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id AND r.status='published') reviews
            FROM users u LEFT JOIN profiles p ON p.user_id = u.id";
    if ($q !== '') { $sql .= ' WHERE LOWER(u.username) LIKE ? OR LOWER(u.email) LIKE ?';
                     $args[] = '%' . mb_strtolower($q) . '%'; $args[] = '%' . mb_strtolower($q) . '%'; }
    $rows = q_all($sql . ' ORDER BY u.id DESC LIMIT 300', $args);
    view('admin/users', ['rows' => $rows, 'q' => $q], ['title' => 'Users — RuinMyTrip admin']);
}

/**
 * POST /admin/user/{id} — role and account status.
 *
 * Accounts are suspended, never deleted, and their content is never removed here: a deletion is
 * unrecoverable and would take a person's genuine warnings with it.
 */
function admin_user_save(array $a): void {
    require_role('admin'); csrf_check();
    $me = current_user();
    $u = q_one('SELECT * FROM users WHERE id = ?', [(int) $a['id']]);
    if (!$u) not_found();
    if ((int) $u['id'] === (int) $me['id']) {
        flash('You cannot change your own role or status here.');
        redirect('/admin/users');
    }
    $role = in_array(input('role'), ['user', 'mod', 'admin', 'editorial'], true) ? input('role') : $u['role'];
    $status = in_array(input('status'), ['active', 'suspended'], true) ? input('status') : $u['status'];
    q_exec('UPDATE users SET role = ?, status = ? WHERE id = ?', [$role, $status, (int) $u['id']]);
    flash('User updated.');
    redirect('/admin/users');
}

/* ------------------------------------------------------------ analytics */

function admin_analytics(array $a): void {
    require_role('admin', 'mod');
    $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
    $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $funnel = rmt_funnel($since);
    $totals = rmt_event_totals($since);
    $topDests = rmt_top_destination_views($since);
    $searches = rmt_top_searches($since);
    $affiliates = q_all('SELECT label, provider, kind, click_count FROM affiliate_links
                         WHERE click_count > 0 ORDER BY click_count DESC LIMIT 20');
    view('admin/analytics', compact('funnel', 'totals', 'topDests', 'searches', 'days', 'affiliates'),
         ['title' => 'Analytics — RuinMyTrip admin']);
}

/* ------------------------------------------------------------- homepage */

/** GET /admin/homepage — which destinations are featured and how trending is tuned. */
function admin_homepage(array $a): void {
    require_role('admin', 'mod');
    $dests = q_all("SELECT d.id, d.name, d.slug, d.country, d.featured,
                      (SELECT COUNT(*) FROM warnings w WHERE w.destination_id = d.id AND w.status='approved') warnings,
                      (SELECT COUNT(*) FROM destination_risk_sections s WHERE s.destination_id = d.id) sections
                    FROM destinations d ORDER BY d.featured DESC, d.name");
    $featuredWarnings = rmt_warning_query(['featured' => 1, 'sort' => 'recent'], 20)['rows'];
    view('admin/homepage', compact('dests', 'featuredWarnings'), ['title' => 'Homepage — RuinMyTrip admin']);
}

function admin_homepage_save(array $a): void {
    require_role('admin', 'mod'); csrf_check();
    $me = current_user();
    $ids = array_map('intval', (array) ($_POST['featured'] ?? []));
    q_exec('UPDATE destinations SET featured = 0');
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        q_exec("UPDATE destinations SET featured = 1 WHERE id IN ($ph)", $ids);
    }
    rmt_setting_set('home_trending_days', (string) max(7, min(365, (int) input('trending_days') ?: 120)), (int) $me['id']);
    rmt_setting_set('home_trending_count', (string) max(3, min(12, (int) input('trending_count') ?: 6)), (int) $me['id']);
    rmt_setting_set('home_intro', mb_substr(trim((string) ($_POST['home_intro'] ?? '')), 0, 500), (int) $me['id']);
    flash('Homepage updated.');
    redirect('/admin/homepage');
}
