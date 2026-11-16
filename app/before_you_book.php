<?php
declare(strict_types=1);

/**
 * "Check before you book" lists and the "Did something go wrong here? Tell us" form (2026-11-16).
 *
 * Plan step 2: each city we push gets 15 to 20 solid, sourced items before it is promoted. The tax
 * note, the calculator and the official scam warnings already exist; this adds the practical facts a
 * visitor should check before booking (rental licences, ticket rules, transport, entry rules), each
 * with the same evidence rule as city_scams.json: a source on an official https page and a quote
 * copied from it. An item without that is dropped, never shown bare.
 *
 * Data: database/scams/before_you_book.json, keyed by destination slug.
 *
 * The story form writes to the feedback queue (kind trip_story) and nothing else. A person checks
 * every story before anything about it is published.
 */

const RMT_BYB_FILE = BASE_PATH . '/database/scams/before_you_book.json';
const RMT_BYB_KINDS = [
    'tax' => 'Tax', 'booking' => 'Booking', 'entry' => 'Entry rules', 'transport' => 'Getting around',
    'rules' => 'Local rules', 'tickets' => 'Tickets', 'money' => 'Money', 'safety' => 'Safety',
];
const RMT_STORY_MIN = 20;
const RMT_STORY_MAX = 3000;

/** @return array<string, array{items: list<array>, checked: string}> */
function rmt_byb_all(): array {
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    if (!is_file(RMT_BYB_FILE)) return $all;
    $raw = json_decode((string) file_get_contents(RMT_BYB_FILE), true);
    if (!is_array($raw)) return $all;
    foreach ($raw as $slug => $city) {
        if (!is_string($slug) || !is_array($city)) continue;
        $items = array_values(array_filter(array_map('rmt_byb_item', (array) ($city['checklist'] ?? []))));
        if ($items) $all[$slug] = ['items' => $items, 'checked' => (string) ($city['checked'] ?? '')];
    }
    return $all;
}

function rmt_byb(string $slug): ?array {
    return rmt_byb_all()[$slug] ?? null;
}

/** A display-ready checklist item, or null when it is missing its evidence. */
function rmt_byb_item($it): ?array {
    if (!is_array($it)) return null;
    $title = trim((string) ($it['title'] ?? ''));
    $detail = trim((string) ($it['detail'] ?? ''));
    if ($title === '' || $detail === '') return null;
    $sources = [];
    foreach ((array) ($it['sources'] ?? []) as $s) {
        if (!is_array($s)) continue;
        $url = trim((string) ($s['url'] ?? ''));
        $quote = trim((string) ($s['quote'] ?? ''));
        $pub = trim((string) ($s['publisher'] ?? ''));
        if (!rmt_city_scam_url_ok($url) || $quote === '' || $pub === '') continue;
        $sources[] = ['url' => $url, 'quote' => $quote, 'publisher' => $pub];
    }
    if (!$sources) return null;
    $kind = (string) ($it['kind'] ?? '');
    return [
        'title' => $title, 'detail' => $detail, 'check' => trim((string) ($it['check'] ?? '')),
        'kind' => isset(RMT_BYB_KINDS[$kind]) ? $kind : 'booking',
        'sources' => $sources, 'checked' => (string) ($it['checked'] ?? ''),
    ];
}

/**
 * Every sourced item we hold for a city: the tax note, the calculator, the official warnings and
 * the checklist. The number the plan's "15 to 20 items" bar is measured against.
 *
 * @return array{tax: int, warnings: int, checklist: int, total: int}
 */
function rmt_byb_count(string $slug): array {
    $tax = rmt_tt_key_for_dest($slug) !== null ? 2 : 0;
    $warn = count(rmt_city_scams($slug)['items'] ?? []);
    $list = count(rmt_byb($slug)['items'] ?? []);
    return ['tax' => $tax, 'warnings' => $warn, 'checklist' => $list, 'total' => $tax + $warn + $list];
}

/** POST /story  the "Did something go wrong here?" form on tax and warnings pages. */
function story_submit(array $a): void {
    csrf_check();
    $back = rmt_return_to('/');
    $anchor = str_contains($back, '#') ? '' : '#story';
    if (rmt_honeypot_tripped()) { flash('Thanks. We read every story before anything is published.'); redirect($back . $anchor); }
    $topic = (string) input('topic');
    if (!preg_match('/^(tax|warnings):[a-z0-9-]{2,80}$/', $topic)) $topic = '';
    $msg = trim((string) input('message'));
    $len = mb_strlen($msg);
    if ($len < RMT_STORY_MIN || $len > RMT_STORY_MAX) {
        flash($len < RMT_STORY_MIN ? 'Tell us a little more: what happened, and where.' : 'That is too long. The main points are plenty.');
        redirect($back . $anchor);
    }
    if (!rmt_rate_ok('story_ip', rmt_client_ip(), 4, 3600)) {
        flash('That was a lot of stories in a short time. Try again a little later.');
        redirect($back . $anchor);
    }
    $when = trim((string) input('when'));
    if ($when !== '') $msg = 'When: ' . mb_substr($when, 0, 60) . ' | ' . $msg;
    $me = current_user();
    $r = rmt_feedback_submit('trip_story', null, $msg, $me ? (int) $me['id'] : null,
                             (string) input('contact_email'), $topic !== '' ? $topic : null);
    if ($r['ok']) rmt_track('story_submitted', ['source' => 'other']);
    flash($r['ok'] ? 'Thank you. We read every story and check it before anything from it is published.' : (string) $r['error']);
    redirect($back . $anchor);
}
