<?php
declare(strict_types=1);

/**
 * "Before you go" checklist on a trip (2026-11-17).
 *
 * The sourced reference pages bring the readers; this is the reason for one of them to make an
 * account and come back. A trip to a city with sourced data gets a personal checklist built from
 * the same evidence the public pages show: the tourist tax, the "check before you book" items and
 * the official scam warnings. Ticking items off is saved per trip, so the list is worth returning
 * to while planning, and every item links to its source.
 *
 * Nothing here invents content: an item exists only when the public page for that city already
 * shows it (rmt_tt_cities, rmt_byb, rmt_city_scams). A city with none of them gets no section.
 *
 * Item keys are stable hashes of the item title, so reordering the data file keeps ticks, and an
 * item removed from the data simply stops showing (its tick row is harmless).
 */

const RMT_TCL_MAX_SCAMS = 6;

/** @return list<array{key:string,group:string,title:string,detail:string,url:string,source:string}> */
function rmt_trip_checklist_items(string $destSlug): array {
    $items = [];
    $ttKey = rmt_tt_key_for_dest($destSlug);
    if ($ttKey !== null) {
        $c = rmt_tt_cities()[$ttKey];
        $items[] = ['key' => 'tax', 'group' => 'Money',
                    'title' => 'Budget for the ' . $c['name'] . ' tourist tax',
                    'detail' => $c['rate'] . '. ' . trim((string) $c['tip']),
                    'url' => url('tourist-tax-calculator?city=' . $ttKey), 'source' => 'Work it out for your stay'];
        foreach (rmt_tt27_rows() as $r) {
            if ($r['key'] === $ttKey) {
                $items[] = ['key' => 'tax2027', 'group' => 'Money',
                            'title' => 'Travelling in 2027? The rate changes to ' . $r['rate_2027'],
                            'detail' => ($r['date'] !== '' ? 'From ' . date('j F Y', (int) strtotime($r['date'])) . '. ' : '') . $r['notes'],
                            'url' => url('tourist-tax-2027#' . $ttKey), 'source' => $r['sources'][0]['publisher']];
            }
        }
    }
    foreach ((rmt_byb($destSlug)['items'] ?? []) as $it) {
        $items[] = ['key' => 'byb:' . substr(md5($it['title']), 0, 10), 'group' => RMT_BYB_KINDS[$it['kind']] ?? 'Booking',
                    'title' => $it['title'], 'detail' => $it['check'] !== '' ? $it['check'] : $it['detail'],
                    'url' => $it['sources'][0]['url'], 'source' => $it['sources'][0]['publisher']];
    }
    foreach (array_slice(rmt_city_scams($destSlug)['items'] ?? [], 0, RMT_TCL_MAX_SCAMS) as $s) {
        $items[] = ['key' => 'scam:' . substr(md5($s['name']), 0, 10), 'group' => 'Scams to know',
                    'title' => 'Know this one: ' . $s['name'],
                    'detail' => (string) ($s['avoid'] !== '' ? $s['avoid'] : $s['what']),
                    'url' => $s['sources'][0]['url'] ?? url('warnings/' . $destSlug), 'source' => $s['sources'][0]['publisher'] ?? 'Official warning'];
    }
    return $items;
}

/** @return array<string,true> keys ticked on this trip */
function rmt_trip_checklist_done(int $tripId): array {
    $out = [];
    foreach (q_all('SELECT item_key FROM trip_checklist WHERE trip_id = ?', [$tripId]) as $r) $out[(string) $r['item_key']] = true;
    return $out;
}

/** POST /trip/{id}/checklist  tick or untick one item. Owner and trip members only. */
function trip_checklist_toggle(array $a): void {
    csrf_check();
    require_login();
    $me = current_user();
    $t = q_one("SELECT t.*, d.slug dest_slug FROM trips t LEFT JOIN destinations d ON d.id = t.destination_id WHERE t.id = ?", [(int) $a['id']]);
    if (!$t || !rmt_trip_can_edit($t, $me)) not_found();
    $key = (string) input('item');
    $valid = array_column(rmt_trip_checklist_items((string) $t['dest_slug']), 'key');
    if (in_array($key, $valid, true)) {
        if (input('done') === '1') {
            if (!q_one('SELECT 1 FROM trip_checklist WHERE trip_id = ? AND item_key = ?', [(int) $t['id'], $key])) {
                q_run('INSERT INTO trip_checklist (trip_id, item_key, user_id, done_at) VALUES (?, ?, ?, ?)',
                      [(int) $t['id'], $key, (int) $me['id'], date('Y-m-d H:i:s')]);
                rmt_track('checklist_ticked', ['source' => 'trip', 'destination_id' => (int) $t['destination_id'] ?: null]);
            }
        } else {
            q_run('DELETE FROM trip_checklist WHERE trip_id = ? AND item_key = ?', [(int) $t['id'], $key]);
        }
    }
    if (input('ajax') === '1') { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
    redirect('/trip/' . (int) $t['id'] . '#before-you-go');
}
