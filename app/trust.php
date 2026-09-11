<?php
/**
 * Lightweight trust signals: facts, not a score.
 *
 * Strangers now arrange to meet each other through this site, and the honest way to help somebody
 * decide is to show them what is already true and public about an account, with a link to check it
 * themselves. Nothing here is computed into a number out of ten, nothing is hidden behind a
 * ranking, and nothing is inferred about the person.
 *
 * What is deliberately NOT here, and must never be added: anything about how somebody looks, their
 * background, their gender, their politics or their health; any hidden behavioural score; any
 * "quality" ranking of human beings; any signal a person cannot see and check for themselves.
 * Every line this returns is a count of public rows the viewer could go and count by hand.
 *
 * The signals are also deliberately the ones that are hard to fake in bulk: an account cannot be a
 * year old on its first day, and reviews and trips take work. Nothing here is a guarantee, and the
 * page that shows it says so.
 */
declare(strict_types=1);

/**
 * Public, checkable facts about an account.
 *
 * @param int    $userId whose account
 * @param ?array $viewer who is asking, for the mutual-connection line only
 * @return list<array{key:string,label:string,href:?string}>
 */
function rmt_trust_signals(int $userId, ?array $viewer = null): array {
    if ($userId < 1) return [];
    $u = q_one("SELECT id, username, created_at, email_verified_at, status FROM users WHERE id = ?", [$userId]);
    if (!$u || $u['status'] !== 'active') return [];

    $out = [];

    /* How long the account has been here. Said in whole months and years, because "joined 4 days
       ago" is the fact that matters and "joined 37 days ago" is noise. */
    $created = strtotime((string) ($u['created_at'] ?? '')) ?: 0;
    if ($created > 0) {
        $days = max(0, (int) floor((time() - $created) / 86400));
        if ($days < 7)        $label = 'Joined this week';
        elseif ($days < 60)   $label = 'Joined ' . max(1, (int) round($days / 7)) . ' weeks ago';
        elseif ($days < 730)  $label = 'Here ' . max(2, (int) round($days / 30)) . ' months';
        else                  $label = 'Here ' . (int) floor($days / 365) . ' years';
        $out[] = ['key' => 'age', 'label' => $label, 'href' => null];
    }

    // A confirmed address. Said only when it is true: "not verified" as a badge is a scarlet letter
    // on somebody who simply has not clicked a link yet.
    if (!empty($u['email_verified_at'])) {
        $out[] = ['key' => 'email', 'label' => 'Email confirmed', 'href' => null];
    }

    /* Trips posted, counted public only, because that is what a stranger could go and read. A
       private trip is real and is nobody else's evidence of anything. */
    $trips = (int) (q_one("SELECT COUNT(*) c FROM trips
                            WHERE user_id = ? AND status = 'published'
                              AND COALESCE(visibility,'public') = 'public'", [$userId])['c'] ?? 0);
    if ($trips > 0) {
        $out[] = ['key' => 'trips', 'label' => $trips . ($trips === 1 ? ' trip posted' : ' trips posted'),
                  'href' => url('u/' . $u['username'])];
    }

    $reviews = (int) (q_one("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND status = 'published'",
                            [$userId])['c'] ?? 0);
    if ($reviews > 0) {
        $out[] = ['key' => 'reviews', 'label' => $reviews . ($reviews === 1 ? ' review written' : ' reviews written'),
                  'href' => url('u/' . $u['username'])];
    }

    /* People who actually turned up to something this person organised. The strongest signal the
       site holds, because it is other members voting with a Friday evening rather than a click. */
    $hosted = (int) (q_one("SELECT COUNT(DISTINCT j.user_id) c
                              FROM activity_joins j
                              JOIN trip_activities a ON a.id = j.activity_id
                             WHERE a.user_id = ? AND j.state = 'going' AND j.user_id <> ?
                               AND a.status = 'published'", [$userId, $userId])['c'] ?? 0);
    if ($hosted > 0) {
        $out[] = ['key' => 'hosted',
                  'label' => $hosted . ($hosted === 1 ? ' traveler has joined their plans' : ' travelers have joined their plans'),
                  'href' => null];
    }

    /* And the other side of it: plans by other people that this person joined and that have since
       happened. Counted only once the day is past, because saying yes to a Friday is a click and
       turning up to it is not, and this site should not treat the two as the same fact. A plan
       with no date is not counted at all rather than guessed at. */
    $attended = (int) (q_one("SELECT COUNT(*) c
                                FROM activity_joins j
                                JOIN trip_activities a ON a.id = j.activity_id
                               WHERE j.user_id = ? AND j.state = 'going' AND a.user_id <> ?
                                 AND a.status = 'published'
                                 AND a.day IS NOT NULL AND a.day < ?",
                             [$userId, $userId, date('Y-m-d')])['c'] ?? 0);
    if ($attended > 0) {
        $out[] = ['key' => 'attended',
                  'label' => $attended === 1 ? 'Turned up to a plan' : 'Turned up to ' . $attended . ' plans',
                  'href' => null];
    }

    /* People you both follow. Public on both sides already, and the one signal that is about the
       two of you rather than about them. Never shown to somebody asking about themselves. */
    if ($viewer && (int) $viewer['id'] !== $userId) {
        $mutual = (int) (q_one("SELECT COUNT(*) c FROM follows a
                                  JOIN follows b ON b.followee_id = a.followee_id
                                 WHERE a.follower_id = ? AND b.follower_id = ?",
                               [(int) $viewer['id'], $userId])['c'] ?? 0);
        if ($mutual > 0) {
            $out[] = ['key' => 'mutual',
                      'label' => $mutual . ($mutual === 1 ? ' traveler you both follow' : ' travelers you both follow'),
                      'href' => null];
        }
    }

    return $out;
}

/**
 * Is there enough here to be worth drawing at all?
 *
 * A brand new account with nothing on it produces one line saying it is new, and a box containing
 * only that reads as an accusation. Below the threshold the caller shows nothing, which is the
 * truthful version of "we do not know anything about this person yet".
 */
function rmt_trust_worth_showing(array $signals): bool {
    return count($signals) >= 2;
}
