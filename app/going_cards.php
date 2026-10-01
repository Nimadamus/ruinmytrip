<?php
declare(strict_types=1);

/**
 * "I'm going" cards (2026-10-01, migration 105).
 *
 * A traveler going to Chiang Mai for Yi Peng knows people who might be going too, and is in group
 * chats full of them. The card is the thing they send: a page and a picture that say where, when,
 * and "who else is going?", with two buttons for whoever opens it: I'm going too, and find
 * travelers. The person who opens it and says "me too" is the other half of the sender's match.
 *
 * What is on it is what the traveler chose: the city, the occasion if there is one, their dates,
 * and their name and photo only if they ticked the box. A card made from a signed out alert never
 * carries a name, because there is no profile behind it to show.
 *
 * Shares are tagged with rmt_share_url(..., 'im-going'), so everything the visitor does afterwards
 * (an alert, an account, a trip) is attributed to the card in the funnel without storing who they are.
 */

const RMT_CARD_CAMPAIGN = 'im-going';
const RMT_CARD_CHANNELS = ['whatsapp', 'facebook', 'x', 'telegram', 'email'];

function rmt_going_code(): string {
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < 10; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return $s;
}

/**
 * Make a card, or hand back the same one if this traveler already made it: one link per trip
 * means the visit count on it means something.
 */
function rmt_going_card_make(?int $userId, ?int $alertId, int $destId, string $occ, string $from, string $to, bool $showName): string {
    $owner = $userId ? ['user_id = ?', $userId] : ['alert_id = ?', (int) $alertId];
    $ex = q_one("SELECT code FROM going_cards WHERE {$owner[0]} AND destination_id = ? AND date_from = ? AND date_to = ?
                   AND show_name = ? AND status = 'live'", [$owner[1], $destId, $from, $to, $showName ? 1 : 0]);
    if ($ex) return (string) $ex['code'];
    $code = rmt_going_code();
    q_run('INSERT INTO going_cards (code, user_id, alert_id, destination_id, occasion, date_from, date_to, show_name, created_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
          [$code, $userId, $userId ? null : $alertId, $destId, $occ !== '' ? $occ : null, $from, $to, $showName ? 1 : 0, date('Y-m-d H:i:s')]);
    if (function_exists('rmt_track')) rmt_track('card_created', ['source' => 'share', 'destination_id' => $destId]);
    return $code;
}

/** The occasion for a city and window, when one is running over those days. */
function rmt_going_occasion_for(string $destSlug, string $from, string $to): string {
    if (!defined('RMT_OCCASIONS')) return '';
    foreach (RMT_OCCASIONS as $slug => $o) {
        if ($o['dest'] === $destSlug && $o['from'] <= $to && $o['to'] >= $from) return $slug;
    }
    return '';
}

/** A live card with everything the page and the picture need, or null. */
function rmt_going_card(string $code): ?array {
    if (!preg_match('/^[a-z0-9]{6,16}$/', $code)) return null;
    $c = q_one("SELECT g.*, d.slug dest_slug, d.name dest_name, d.country, d.hero_url,
                       u.username, u.status ustatus, p.display_name, p.avatar_url
                  FROM going_cards g JOIN destinations d ON d.id = g.destination_id
             LEFT JOIN users u ON u.id = g.user_id LEFT JOIN profiles p ON p.user_id = g.user_id
                 WHERE g.code = ? AND g.status = 'live'", [$code]);
    if (!$c) return null;
    // A suspended member's card goes with them.
    if (!empty($c['user_id']) && ($c['ustatus'] ?? '') !== 'active') return null;
    $occ = (string) ($c['occasion'] ?? '');
    $c['occ'] = ($occ !== '' && defined('RMT_OCCASIONS') && isset(RMT_OCCASIONS[$occ])) ? RMT_OCCASIONS[$occ] + ['slug' => $occ] : null;
    $c['who'] = (int) $c['show_name'] === 1 && !empty($c['username'])
        ? (trim((string) $c['display_name']) !== '' ? (string) $c['display_name'] : '@' . $c['username']) : '';
    return $c;
}

/** "I'm going to Chiang Mai for Yi Peng, 22 to 27 November 2026." */
function rmt_going_line(array $c): string {
    $where = (string) $c['dest_name'] . ($c['occ'] ? ' for ' . $c['occ']['short'] : '');
    return "I'm going to $where, " . rmt_alert_dates((string) $c['date_from'], (string) $c['date_to']) . '.';
}

function rmt_going_url(array $c): string {
    return abs_url(url('im-going/' . $c['code']));
}

/** The share links for one card, each tagged with its channel. */
function rmt_going_share_links(array $c): array {
    $line = rmt_going_line($c) . ' Who else is going?';
    $out = [];
    foreach (RMT_CARD_CHANNELS as $ch) {
        $u = function_exists('rmt_share_url') ? rmt_share_url(rmt_going_url($c), $ch, RMT_CARD_CAMPAIGN) : rmt_going_url($c);
        $out[$ch] = match ($ch) {
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($line . ' ' . $u),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($u),
            'x'        => 'https://twitter.com/intent/tweet?text=' . rawurlencode($line) . '&url=' . rawurlencode($u),
            'telegram' => 'https://t.me/share/url?url=' . rawurlencode($u) . '&text=' . rawurlencode($line),
            'email'    => 'mailto:?subject=' . rawurlencode($line) . '&body=' . rawurlencode($line . "\n\n" . $u),
        };
    }
    $out['copy'] = function_exists('rmt_share_url') ? rmt_share_url(rmt_going_url($c), 'other', RMT_CARD_CAMPAIGN) : rmt_going_url($c);
    return $out;
}

/* ---------- controllers ---------- */

/**
 * POST /im-going  make a card: a member from one of their trips, or a signed out visitor from the
 * alert they just set in this browser. Nothing else can be put on a card.
 */
function going_card_create(array $a): void {
    csrf_check();
    $me = current_user();
    if (!rmt_rate_ok('going_card', $me ? 'u' . (int) $me['id'] : rmt_client_ip(), 20, 3600)) {
        flash('That was a lot of cards in an hour. Try again a little later.');
        redirect('/');
    }
    $showName = input('show_name') === '1';
    if ($me) {
        $t = q_one("SELECT t.*, d.slug dest_slug FROM trips t JOIN destinations d ON d.id = t.destination_id
                     WHERE t.id = ? AND t.user_id = ? AND t.status = 'published' AND t.date_from IS NOT NULL AND t.date_to IS NOT NULL",
                   [(int) input('trip_id'), (int) $me['id']]);
        if (!$t) { flash('Pick one of your trips with dates to share.'); redirect('/matches'); }
        $code = rmt_going_card_make((int) $me['id'], null, (int) $t['destination_id'],
                                    rmt_going_occasion_for((string) $t['dest_slug'], (string) $t['date_from'], (string) $t['date_to']),
                                    (string) $t['date_from'], (string) $t['date_to'], $showName);
    } else {
        $al = function_exists('rmt_alert_by_id') ? rmt_alert_by_id((int) ($_SESSION[RMT_ALERT_SESSION] ?? 0)) : null;
        if (!$al) { flash('Set an alert for your dates first, then share it.'); redirect('/'); }
        $code = rmt_going_card_make(null, (int) $al['id'], (int) $al['destination_id'], (string) ($al['occasion'] ?? ''),
                                    (string) $al['date_from'], (string) $al['date_to'], false);
    }
    $_SESSION['my_cards'][$code] = 1;
    redirect('/im-going/' . $code);
}

/** GET /im-going/{code} */
function going_card_show(array $a): void {
    $c = rmt_going_card((string) ($a['code'] ?? ''));
    if (!$c) not_found();
    $me = current_user();
    $mine = isset($_SESSION['my_cards'][$c['code']]) || ($me && (int) $me['id'] === (int) ($c['user_id'] ?? 0));
    if (!$mine) {
        // Somebody the traveler sent it to. Counted once per browser session, crawlers never.
        if (!isset($_SESSION['seen_cards'][$c['code']])) {
            $_SESSION['seen_cards'][$c['code']] = 1;
            if (rmt_track('share_visit', ['source' => 'share', 'destination_id' => (int) $c['destination_id']])) {
                q_run('UPDATE going_cards SET visits = visits + 1 WHERE id = ?', [(int) $c['id']]);
            }
        }
    }
    $occLinks = $c['occ'] && function_exists('rmt_occasion') ? rmt_occasion((string) $c['occ']['slug']) : null;
    $find = $occLinks ? url('e/' . $c['occ']['slug']) : url('d/' . $c['dest_slug'] . '/travelers');
    $line = rmt_going_line($c);
    view('going_card', ['c' => $c, 'me' => $me, 'mine' => $mine, 'line' => $line, 'find' => $find,
                        'share' => rmt_going_share_links($c),
                        'tooUrl' => url('plan?' . http_build_query(['d' => $c['dest_slug'], 'from' => $c['date_from'],
                                                                    'to' => $c['date_to'], 'cta' => 'going_too']))], [
        'title' => $line . ' Who else is going? | RuinMyTrip',
        'description' => 'Going to ' . $c['dest_name'] . ' on the same days? Say so, and see which travelers overlap your dates.',
        // One person's dates, shared on purpose. Never a page for search.
        'robots' => 'noindex,follow', 'canonical' => '',
        'og_image' => rmt_card_url('going', (string) $c['code']),
    ]);
}
