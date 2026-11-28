<?php
/**
 * Phase 1: RuinMyTrip as a single player utility (owner's plan, 2026-11-27).
 *
 * One visitor gets the whole value on their own: warnings for a city sorted by what they cost,
 * a "Helpful / Saved Me" vote with no account, and a trip checklist that is the reason to join.
 * The social features (travel talk, who is going, meetups, collections) stay built and keep their
 * URLs; only the links to them are hidden, and they come back city by city with two env flags.
 *
 * Flags (all optional):
 *   RMT_PHASE1=0                      everything here is off and the site renders as before
 *   RMT_SOCIAL_ON=talk,going          show these social links everywhere again ("all" = every one)
 *   RMT_SOCIAL_CITY_ON=lisbon-portugal=talk|going,paris-france=all
 *                                     show them again on one city's page only
 *
 * Every warning is built from data the site already publishes: the tourist tax table
 * (app/tourist_tax.php), the official scam list (app/city_scams.php), the before you book list
 * (app/before_you_book.php) and traveler stories an editor has published (app/traveler_stories.php).
 * A cost badge is only drawn when that text itself carries a figure.
 */

const RMT_P1_SOCIAL = ['talk', 'going', 'meetups', 'collections', 'events', 'communities', 'reviewers'];

/** Tabs in the order the owner gave them. */
const RMT_P1_CATS = [
    'fees'      => 'Hidden Fees & Taxes',
    'scams'     => 'Scams & Ripoffs',
    'overhyped' => 'Overhyped & Crowded',
    'rules'     => 'Local Rules & Logistics',
];
const RMT_P1_IMPACT = [
    'fees' => 'Costly Surprise', 'scams' => 'Scam Alert', 'overhyped' => 'Time Waster', 'rules' => 'Local Rule',
];
/** What a traveler picks on the report form, and where it lands. */
const RMT_P1_REPORT_CATS = [
    'fee' => ['label' => 'Hidden Fee', 'cat' => 'fees'],
    'scam' => ['label' => 'Scam', 'cat' => 'scams'],
    'overhyped' => ['label' => 'Overhyped', 'cat' => 'overhyped'],
    'rule' => ['label' => 'Local Rule', 'cat' => 'rules'],
];
const RMT_P1_VOTE_KINDS = ['helpful', 'saved'];
const RMT_P1_PENDING_KEY = 'rmt_p1_pending';
const RMT_P1_REPORT_KEY = 'rmt_p1_report';
const RMT_P1_VOTER_COOKIE = 'rmt_v';
const RMT_P1_REPORT_MIN = 20;
const RMT_P1_REPORT_MAX = 1000;

function rmt_phase1_on(): bool {
    $v = getenv('RMT_PHASE1');
    return $v === false || !in_array(strtolower(trim((string) $v)), ['0', 'off', 'false', 'no'], true);
}

/** @return array<string,true> */
function rmt_p1_flag_list(string $raw): array {
    $out = [];
    foreach (preg_split('/[\s,|]+/', strtolower($raw)) ?: [] as $f) {
        if ($f === 'all') { foreach (RMT_P1_SOCIAL as $s) $out[$s] = true; }
        elseif (in_array($f, RMT_P1_SOCIAL, true)) $out[$f] = true;
    }
    return $out;
}

/**
 * Is a social feature's link shown? Always true with Phase 1 off. With it on, only when switched
 * back on everywhere (RMT_SOCIAL_ON) or for this city (RMT_SOCIAL_CITY_ON).
 */
function rmt_social_on(string $feature, ?string $citySlug = null): bool {
    if (!rmt_phase1_on()) return true;
    if (isset(rmt_p1_flag_list((string) getenv('RMT_SOCIAL_ON'))[$feature])) return true;
    if ($citySlug === null || $citySlug === '') return false;
    foreach (explode(',', (string) getenv('RMT_SOCIAL_CITY_ON')) as $pair) {
        $pair = trim($pair);
        if ($pair === '' || !str_contains($pair, '=')) continue;
        [$slug, $feats] = array_map('trim', explode('=', $pair, 2));
        if (strtolower($slug) === strtolower($citySlug) && isset(rmt_p1_flag_list($feats)[$feature])) return true;
    }
    return false;
}

/* ---------- warnings ---------- */

/** The first money or percent figure written in the source text, or null. Never computed. */
function rmt_p1_cost_from(string $text): ?string {
    $unit = '(?:euros?|forints?|koruna|crowns?|CZK|HUF|EUR|USD|baht|pesos?|dollars?|pounds?|dirhams?|AED|lira|reais|rupees?|dong|soles|percent)';
    $num = '\d[\d,.]*';
    if (preg_match('/(?:up to |from )?' . $num . '(?:\s(?:to|and)\s' . $num . ')?\s' . $unit . '\b/iu', $text, $m)) return trim($m[0]);
    if (preg_match('/[€£$]\s?' . $num . '(?:\s?(?:to|-)\s?[€£$]?\s?' . $num . ')?/u', $text, $m)) return trim($m[0]);
    return null;
}

/** At most $n sentences, so a card reads in a few seconds. */
function rmt_p1_sentences(string $text, int $n = 3): string {
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($text === '') return '';
    $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"])/u', $text) ?: [$text];
    return implode(' ', array_slice($parts, 0, $n));
}

/** "[Hidden Fee] what happened (Extra cost: about 40 euros)" as a report is written. */
function rmt_p1_report_message(string $catKey, string $what, string $cost): string {
    $label = RMT_P1_REPORT_CATS[$catKey]['label'] ?? 'Trap';
    $msg = '[' . $label . '] ' . trim((string) preg_replace('/\s+/u', ' ', $what));
    $cost = trim((string) preg_replace('/\s+/u', ' ', $cost));
    if ($cost !== '') $msg .= ' (Extra cost: ' . mb_substr($cost, 0, 60) . ')';
    return $msg;
}

/** Read a published traveler story back into a category and an optional cost. */
function rmt_p1_parse_story(string $body): array {
    $cat = null; $cost = null;
    if (preg_match('/^\[(Hidden Fee|Scam|Overhyped|Local Rule)\]\s*/u', $body, $m)) {
        foreach (RMT_P1_REPORT_CATS as $c) if ($c['label'] === $m[1]) $cat = $c['cat'];
        $body = substr($body, strlen($m[0]));
    }
    if (preg_match('/\s*\(Extra cost: ([^)]{1,60})\)\s*$/u', $body, $m)) {
        $cost = trim($m[1]);
        $body = substr($body, 0, -strlen($m[0]));
    }
    return ['cat' => $cat, 'cost' => $cost, 'body' => trim($body)];
}

/**
 * Every warning for a city, from published data only.
 * @return list<array{key:string,cat:string,impact:string,cost:?string,title:string,body:string,url:string,source:string,traveler:bool}>
 */
function rmt_p1_warnings(string $destSlug): array {
    static $memo = [];
    if (isset($memo[$destSlug])) return $memo[$destSlug];
    $w = [];
    $add = static function (string $key, string $cat, ?string $cost, string $title, string $body, string $u, string $src, bool $traveler = false) use (&$w): void {
        $w[] = ['key' => $key, 'cat' => $cat, 'impact' => RMT_P1_IMPACT[$cat] ?? 'Traveler report', 'cost' => $cost,
                'title' => $title, 'body' => rmt_p1_sentences($body), 'url' => $u, 'source' => $src, 'traveler' => $traveler];
    };
    $ttKey = function_exists('rmt_tt_key_for_dest') ? rmt_tt_key_for_dest($destSlug) : null;
    if ($ttKey !== null) {
        $c = rmt_tt_cities()[$ttKey];
        $add('tax', 'fees', (string) $c['rate'], 'Tourist tax on every night in ' . $c['name'],
             'Hotels and rentals add it on top of the room price, usually at check in or check out. ' . trim((string) $c['tip']),
             url('tourist-tax-calculator?city=' . $ttKey), 'Tourist tax calculator');
        if (function_exists('rmt_tt27_rows')) {
            foreach (rmt_tt27_rows() as $r) {
                if ($r['key'] !== $ttKey) continue;
                $add('tax2027', 'fees', (string) $r['rate_2027'], 'The tax changes in 2027',
                     ($r['date'] !== '' ? 'From ' . date('j F Y', (int) strtotime($r['date'])) . ' the rate becomes ' . $r['rate_2027'] . '. ' : '') . $r['notes'],
                     url('tourist-tax-2027#' . $ttKey), (string) ($r['sources'][0]['publisher'] ?? 'Official source'));
            }
        }
    }
    foreach ((function_exists('rmt_byb') ? (rmt_byb($destSlug)['items'] ?? []) : []) as $it) {
        $kind = (string) $it['kind'];
        $cat = in_array($kind, ['tax', 'money'], true) ? 'fees' : ($kind === 'tickets' ? 'overhyped' : 'rules');
        $body = trim((string) $it['detail']);
        if ((string) $it['check'] !== '') $body = rmt_p1_sentences($body, 2) . ' ' . $it['check'];
        $add('byb:' . substr(md5((string) $it['title']), 0, 10), $cat, rmt_p1_cost_from($it['title'] . ' ' . $it['detail']),
             (string) $it['title'], $body, (string) ($it['sources'][0]['url'] ?? ''), (string) ($it['sources'][0]['publisher'] ?? 'Official source'));
    }
    foreach ((rmt_city_scams($destSlug)['items'] ?? []) as $s) {
        $body = rmt_p1_sentences((string) $s['what'], 2);
        if ((string) $s['avoid'] !== '') $body .= ' ' . rmt_p1_sentences((string) $s['avoid'], 1);
        $add('scam:' . substr(md5((string) $s['name']), 0, 10), 'scams', rmt_p1_cost_from($s['name'] . ' ' . $s['what']),
             (string) $s['name'], $body, (string) ($s['sources'][0]['url'] ?? url('warnings/' . $destSlug)),
             (string) ($s['sources'][0]['publisher'] ?? 'Official warning'));
    }
    if (function_exists('rmt_stories_for') && function_exists('rmt_story_topics_for_dest')) {
        try {
            foreach (rmt_stories_for(rmt_story_topics_for_dest($destSlug), 20) as $st) {
                $p = rmt_p1_parse_story((string) $st['body']);
                $title = mb_strlen($p['body']) > 70 ? rtrim(mb_substr($p['body'], 0, 66)) . '...' : $p['body'];
                $w[] = ['key' => 'story:' . (int) $st['id'], 'cat' => $p['cat'] ?? 'other',
                        'impact' => $p['cat'] ? RMT_P1_IMPACT[$p['cat']] : 'Traveler report', 'cost' => $p['cost'],
                        'title' => $title, 'body' => rmt_p1_sentences($p['body']), 'url' => '', 'source' => 'A traveler, checked by an editor',
                        'traveler' => true];
            }
        } catch (Throwable $e) { /* migration 134 not applied */ }
    }
    return $memo[$destSlug] = $w;
}

/** @return array<string,int> count per tab, plus 'all' */
function rmt_p1_cat_counts(array $warnings): array {
    $n = ['all' => count($warnings)] + array_fill_keys(array_keys(RMT_P1_CATS), 0);
    foreach ($warnings as $x) if (isset($n[$x['cat']])) $n[$x['cat']]++;
    return $n;
}

/** The punchiest preview: a fee or scam with a figure first, then any fee, then any scam. */
function rmt_p1_preview(array $warnings): ?array {
    foreach ([['fees', true], ['scams', true], ['fees', false], ['scams', false], [null, false]] as [$cat, $needCost]) {
        foreach ($warnings as $x) {
            if ($cat !== null && $x['cat'] !== $cat) continue;
            if ($needCost && $x['cost'] === null) continue;
            if ($x['key'] === 'tax2027') continue;
            return $x;
        }
    }
    return null;
}

/**
 * The cities with the most published warnings, for the home page. Only cities that exist and have
 * a photograph; the count is the count of warnings, nothing else.
 * @return list<array{slug:string,name:string,country:string,hero_url:string,count:int,preview:?array}>
 */
function rmt_p1_top_cities(int $limit = 6): array {
    $slugs = array_keys(rmt_city_scams_all());
    if (function_exists('rmt_byb_all')) $slugs = array_merge($slugs, array_keys(rmt_byb_all()));
    foreach (rmt_tt_cities() as $c) $slugs[] = (string) $c['dest'];
    $slugs = array_values(array_unique($slugs));
    if (!$slugs) return [];
    $rows = q_all('SELECT slug, name, country, hero_url FROM destinations WHERE hero_url IS NOT NULL AND hero_url <> \'\' AND slug IN ('
                  . implode(',', array_fill(0, count($slugs), '?')) . ')', $slugs);
    $out = [];
    foreach ($rows as $r) {
        $w = rmt_p1_warnings((string) $r['slug']);
        if (!$w) continue;
        $out[] = $r + ['count' => count($w), 'preview' => rmt_p1_preview($w)];
    }
    usort($out, static fn(array $a, array $b): int => ($b['count'] <=> $a['count']) ?: strcmp((string) $a['name'], (string) $b['name']));
    return array_slice($out, 0, $limit);
}

/* ---------- guest votes ---------- */

/** A stable, anonymous id for this browser: a random cookie, stored only as a hash. */
function rmt_p1_voter_id(): string {
    $me = function_exists('current_user') ? current_user() : null;
    if ($me) return 'u:' . (int) $me['id'];
    $tok = (string) ($_COOKIE[RMT_P1_VOTER_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $tok)) {
        $tok = bin2hex(random_bytes(16));
        $_COOKIE[RMT_P1_VOTER_COOKIE] = $tok;
        if (!headers_sent()) {
            setcookie(RMT_P1_VOTER_COOKIE, $tok, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true,
                      'samesite' => 'Lax', 'secure' => (($GLOBALS['config']['app_env'] ?? '') === 'production')]);
        }
    }
    return 'c:' . substr(hash('sha256', $tok . '|' . ($GLOBALS['config']['security_salt'] ?? '')), 0, 40);
}

/**
 * Record one vote. The same browser voting the same way twice counts once.
 * @return array{ok:bool, counted:bool, error?:string}
 */
function rmt_p1_vote(string $destSlug, string $key, string $kind, string $voter, string $ip): array {
    if (!in_array($kind, RMT_P1_VOTE_KINDS, true)) return ['ok' => false, 'counted' => false, 'error' => 'kind'];
    if (!in_array($key, array_column(rmt_p1_warnings($destSlug), 'key'), true)) return ['ok' => false, 'counted' => false, 'error' => 'key'];
    if (!rmt_rate_ok('warn_vote', $ip, 60, 3600)) return ['ok' => false, 'counted' => false, 'error' => 'rate'];
    $driver = $GLOBALS['config']['db_driver'] ?? 'sqlite';
    $sql = $driver === 'mysql'
        ? 'INSERT IGNORE INTO warning_votes (dest_slug, item_key, voter, kind, created_at) VALUES (?,?,?,?,?)'
        : 'INSERT INTO warning_votes (dest_slug, item_key, voter, kind, created_at) VALUES (?,?,?,?,?) ON CONFLICT DO NOTHING';
    $st = db()->prepare($sql);
    $st->execute([$destSlug, $key, $voter, $kind, date('Y-m-d H:i:s')]);
    return ['ok' => true, 'counted' => $st->rowCount() > 0];
}

/** @return array<string,array{helpful:int,saved:int}> */
function rmt_p1_vote_counts(string $destSlug): array {
    $out = [];
    try {
        foreach (q_all('SELECT item_key, kind, COUNT(*) n FROM warning_votes WHERE dest_slug = ? GROUP BY item_key, kind', [$destSlug]) as $r) {
            $out[(string) $r['item_key']][(string) $r['kind']] = (int) $r['n'];
        }
    } catch (Throwable $e) { /* migration 135 not applied */ }
    return $out;
}

/** @return array<string,true> what this browser already voted, as "key|kind" */
function rmt_p1_my_votes(string $destSlug, string $voter): array {
    $out = [];
    try {
        foreach (q_all('SELECT item_key, kind FROM warning_votes WHERE dest_slug = ? AND voter = ?', [$destSlug, $voter]) as $r) {
            $out[$r['item_key'] . '|' . $r['kind']] = true;
        }
    } catch (Throwable $e) {}
    return $out;
}

/* ---------- the trip checklist ---------- */

/** Save one warning (or '*' for the whole city) to a member's checklist. Saving twice is a no-op. */
function rmt_p1_save(int $uid, string $destSlug, string $key): bool {
    if ($key !== '*' && !in_array($key, array_column(rmt_p1_warnings($destSlug), 'key'), true)) return false;
    if (!dest_by_slug($destSlug)) return false;
    $driver = $GLOBALS['config']['db_driver'] ?? 'sqlite';
    $sql = $driver === 'mysql'
        ? 'INSERT IGNORE INTO checklist_saves (user_id, dest_slug, item_key, created_at) VALUES (?,?,?,?)'
        : 'INSERT INTO checklist_saves (user_id, dest_slug, item_key, created_at) VALUES (?,?,?,?) ON CONFLICT DO NOTHING';
    db()->prepare($sql)->execute([$uid, $destSlug, $key, date('Y-m-d H:i:s')]);
    return true;
}

/** Hold a save for somebody who has no account yet. It lives in their session until they join. */
function rmt_p1_hold_save(string $destSlug, string $key): void {
    $_SESSION[RMT_P1_PENDING_KEY] = ['dest' => $destSlug, 'key' => $key, 'at' => time()];
}

/**
 * Write whatever this session was holding to the member who now owns it: a held save becomes a
 * checklist row, a held report gets their name on it. Safe to call more than once.
 * @return ?string the city slug that was saved, if any
 */
function rmt_p1_claim_pending(int $uid): ?string {
    $saved = null;
    $p = $_SESSION[RMT_P1_PENDING_KEY] ?? null;
    if (is_array($p) && isset($p['dest'], $p['key'])) {
        unset($_SESSION[RMT_P1_PENDING_KEY]);
        try { if (rmt_p1_save($uid, (string) $p['dest'], (string) $p['key'])) $saved = (string) $p['dest']; }
        catch (Throwable $e) { error_log('phase1 claim save: ' . $e->getMessage()); }
    }
    $fid = (int) ($_SESSION[RMT_P1_REPORT_KEY] ?? 0);
    if ($fid > 0) {
        unset($_SESSION[RMT_P1_REPORT_KEY]);
        try { q_run('UPDATE feedback SET reported_by = ? WHERE id = ? AND reported_by IS NULL', [$uid, $fid]); }
        catch (Throwable $e) { error_log('phase1 claim report: ' . $e->getMessage()); }
    }
    return $saved;
}

/**
 * A member's checklist, grouped by city, each warning resolved from the live data (a warning that
 * was since removed from the source drops out rather than showing stale text).
 * @return list<array{dest:array,full:bool,items:list<array>}>
 */
function rmt_p1_checklist(int $uid): array {
    try {
        $rows = q_all('SELECT dest_slug, item_key, created_at FROM checklist_saves WHERE user_id = ? ORDER BY created_at DESC, item_key', [$uid]);
    } catch (Throwable $e) { return []; }
    $by = [];
    foreach ($rows as $r) $by[(string) $r['dest_slug']][] = (string) $r['item_key'];
    $out = [];
    foreach ($by as $slug => $keys) {
        $d = dest_by_slug($slug);
        if (!$d) continue;
        $all = rmt_p1_warnings($slug);
        $full = in_array('*', $keys, true);
        $items = $full ? $all : array_values(array_filter($all, static fn(array $w): bool => in_array($w['key'], $keys, true)));
        $out[] = ['dest' => $d, 'full' => $full, 'items' => $items];
    }
    return $out;
}

/* ---------- controllers ---------- */

function rmt_p1_wants_json(): bool {
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}
function rmt_p1_json(array $body, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
}

/** POST /d/{slug}/warning-vote  "Helpful" or "Saved Me", no account needed. */
function p1_vote_action(array $a): void {
    $slug = (string) $a['slug'];
    $back = url('d/' . $slug) . '#warnings';
    if (!csrf_valid()) { rmt_p1_wants_json() ? rmt_p1_json(['ok' => false, 'error' => 'expired'], 400) : redirect($back); return; }
    if (!dest_by_slug($slug)) { rmt_p1_wants_json() ? rmt_p1_json(['ok' => false], 404) : redirect('/'); return; }
    $key = (string) input('key'); $kind = (string) input('kind');
    $r = rmt_p1_vote($slug, $key, $kind, rmt_p1_voter_id(), rmt_client_ip());
    if ($r['ok'] && $r['counted']) rmt_track('warning_vote', ['source' => $kind]);
    $n = rmt_p1_vote_counts($slug)[$key][$kind] ?? 0;
    if (rmt_p1_wants_json()) { rmt_p1_json(['ok' => $r['ok'], 'counted' => $r['counted'], 'count' => $n, 'error' => $r['error'] ?? null], $r['ok'] ? 200 : 429); return; }
    if (($r['error'] ?? '') === 'rate') flash('That was a lot of votes in an hour. Try again a little later.');
    redirect(url('d/' . $slug) . '#w-' . preg_replace('/[^a-z0-9]/i', '-', $key));
}

/** POST /checklist/save  one warning, or the whole city with key "*". */
function p1_save_action(array $a): void {
    $slug = (string) input('dest'); $key = (string) input('key');
    if (!csrf_valid()) { rmt_p1_wants_json() ? rmt_p1_json(['ok' => false, 'error' => 'expired'], 400) : redirect('/d/' . $slug); return; }
    $d = dest_by_slug($slug);
    $valid = $d && ($key === '*' || in_array($key, array_column(rmt_p1_warnings($slug), 'key'), true));
    if (!$valid) { rmt_p1_wants_json() ? rmt_p1_json(['ok' => false], 404) : redirect('/'); return; }
    $me = current_user();
    if ($me) {
        rmt_p1_save((int) $me['id'], $slug, $key);
        rmt_track('checklist_saved', ['source' => $key === '*' ? 'city' : 'warning']);
        if (rmt_p1_wants_json()) { rmt_p1_json(['ok' => true, 'saved' => true]); return; }
        flash($key === '*' ? 'The full ' . $d['name'] . ' checklist is saved.' : 'Saved to your trip checklist.');
        redirect('/saved#checklist');
    }
    rmt_p1_hold_save($slug, $key);
    rmt_track('checklist_join_prompt', ['source' => $key === '*' ? 'city' : 'warning']);
    if (rmt_p1_wants_json()) { rmt_p1_json(['ok' => true, 'join' => true]); return; }
    redirect('/checklist/join');
}

/** GET /checklist/join  the signup prompt as a page, for a browser without JavaScript. */
function p1_join_page(array $a): void {
    if (is_logged_in()) redirect('/checklist/claim');
    $p = $_SESSION[RMT_P1_PENDING_KEY] ?? null;
    $d = is_array($p) ? dest_by_slug((string) $p['dest']) : null;
    if (!$d) redirect('/explore');
    view('phase1_join', ['d' => $d], ['title' => 'Save your trip checklist | RuinMyTrip', 'robots' => 'noindex,nofollow', 'canonical' => '']);
}

/** GET /checklist/claim  where a new or returning member lands: the held save is written, then the checklist. */
function p1_claim_page(array $a): void {
    if (!is_logged_in()) redirect('/login?return=' . rawurlencode('/checklist/claim'));
    $hadReport = (int) ($_SESSION[RMT_P1_REPORT_KEY] ?? 0) > 0;
    $slug = rmt_p1_claim_pending((int) current_user()['id']);
    if ($slug !== null) flash('Saved to your trip checklist.');
    elseif ($hadReport) flash('Your trap report is now linked to your account. An editor checks it before it appears.');
    redirect('/saved#checklist');
}

/** GET /report-a-trap  the report form on its own (the header button). */
function p1_report_page(array $a): void {
    $d = dest_by_slug((string) input('d'));
    $dests = q_all('SELECT slug, name, country FROM destinations ORDER BY name');
    $done = (int) ($_SESSION[RMT_P1_REPORT_KEY] ?? 0) > 0 && input('sent') === '1';
    view('phase1_report_page', ['d' => $d, 'dests' => $dests, 'done' => $done],
         ['title' => 'Report a trap | RuinMyTrip', 'robots' => 'noindex,follow', 'canonical' => '']);
}

/**
 * POST /report-a-trap  a traveler's trap report. It goes into the same editor queue as trip stories
 * (feedback kind trip_story, topic warnings:{city}); nothing is shown on the site until an editor
 * publishes it from /admin/stories. No account needed to send it.
 */
function p1_report_submit(array $a): void {
    csrf_check();
    $slug = (string) input('dest');
    $d = dest_by_slug($slug);
    $back = $d ? '/d/' . $d['slug'] : '/report-a-trap';
    $fail = static function (string $msg) use ($back, $d): void {
        flash($msg);
        redirect($back . ($d ? '?report=1#report' : ''));
    };
    if (rmt_honeypot_tripped()) { flash('Thanks. An editor checks every report before anything is published.'); redirect($back); }
    if (!$d) { flash('Pick the city it happened in.'); redirect('/report-a-trap'); }
    $cat = (string) input('category');
    if (!isset(RMT_P1_REPORT_CATS[$cat])) { $fail('Pick what kind of trap it was.'); return; }
    $what = trim((string) input('what'));
    $len = mb_strlen($what);
    if ($len < RMT_P1_REPORT_MIN) { $fail('Tell us a little more: what happened, and how to avoid it.'); return; }
    if ($len > RMT_P1_REPORT_MAX) { $fail('That is too long. Two or three sentences is plenty.'); return; }
    if (!rmt_rate_ok('trap_report', rmt_client_ip(), 4, 3600)) { $fail('That was a lot of reports in a short time. Try again a little later.'); return; }
    $me = current_user();
    $r = rmt_feedback_submit('trip_story', null, rmt_p1_report_message($cat, $what, (string) input('cost')),
                             $me ? (int) $me['id'] : null, '', 'warnings:' . $d['slug']);
    if (!$r['ok']) { $fail((string) $r['error']); return; }
    rmt_track('trap_reported', ['source' => $cat]);
    if (!$me && !empty($r['id'])) $_SESSION[RMT_P1_REPORT_KEY] = (int) $r['id'];
    if ($me) { flash('Thank you. An editor checks every report before it goes up on ' . $d['name'] . '.'); redirect($back . '#report'); }
    redirect('/report-a-trap?sent=1&d=' . rawurlencode($d['slug']));
}
