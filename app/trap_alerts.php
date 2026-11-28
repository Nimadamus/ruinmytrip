<?php
declare(strict_types=1);

/**
 * "Get notified if new traps are reported for [Destination]" (Phase 1 signup promise).
 *
 * A trap counts as new when an editor publishes a traveler story on a city's warnings topic
 * (traveler_stories, topic 'warnings:{slug}'). Every member with that city on their trip checklist
 * (checklist_saves, any item) gets ONE email per city per run listing how many new reports landed,
 * and never hears about the same story twice (trap_alerts_sent). Mail goes through
 * rmt_notify_email_direct, so mutes, unverified addresses, opt outs and the daily cap all apply.
 */

const RMT_TRAP_ALERT_LOOKBACK_HOURS = 72;

/**
 * Who would hear about what: [user_id => [dest_slug => list<story_id>]], unsent stories only.
 * @return array<int, array<string, list<int>>>
 */
function rmt_trap_alert_plan(int $hours = RMT_TRAP_ALERT_LOOKBACK_HOURS): array {
    $since = date('Y-m-d H:i:s', time() - max(1, $hours) * 3600);
    $rows = q_all("SELECT s.id story_id, c.user_id, SUBSTR(s.topic, 10) slug
                     FROM traveler_stories s
                     JOIN checklist_saves c ON c.dest_slug = SUBSTR(s.topic, 10)
                    WHERE s.status = 'live' AND s.topic LIKE 'warnings:%' AND s.published_at >= ?
                      AND NOT EXISTS (SELECT 1 FROM trap_alerts_sent t WHERE t.user_id = c.user_id AND t.story_id = s.id)
                    GROUP BY s.id, c.user_id, s.topic
                    ORDER BY c.user_id, s.id", [$since]);
    $plan = [];
    foreach ($rows as $r) $plan[(int) $r['user_id']][(string) $r['slug']][] = (int) $r['story_id'];
    return $plan;
}

/**
 * Send (or with $dryRun, only count) the alerts.
 * @return array{members:int, emails:int, sent:int, skipped:int}
 */
function rmt_trap_alerts_run(bool $dryRun = false, int $hours = RMT_TRAP_ALERT_LOOKBACK_HOURS): array {
    $plan = rmt_trap_alert_plan($hours);
    $out = ['members' => count($plan), 'emails' => 0, 'sent' => 0, 'skipped' => 0];
    foreach ($plan as $uid => $byCity) {
        foreach ($byCity as $slug => $ids) {
            $out['emails']++;
            if ($dryRun) continue;
            $d = q_one('SELECT name FROM destinations WHERE slug = ?', [$slug]);
            $name = $d ? (string) $d['name'] : ucwords(str_replace('-', ' ', $slug));
            $n = count($ids);
            $subject = $n === 1 ? "A new trap was reported for $name" : "$n new traps were reported for $name";
            $line = ($n === 1 ? 'A traveler reported a new trap in ' : "Travelers reported $n new traps in ")
                  . $name . '. An editor checked ' . ($n === 1 ? 'it' : 'them') . ' before ' . ($n === 1 ? 'it went' : 'they went')
                  . ' on your trip checklist page.';
            $ok = rmt_notify_email_direct((int) $uid, $subject, $line, '/d/' . $slug . '#warnings',
                                          'you saved ' . $name . ' to your trip checklist');
            if (!$ok) { $out['skipped']++; continue; }
            $out['sent']++;
            $now = date('Y-m-d H:i:s');
            foreach ($ids as $sid) {
                q_run('INSERT INTO trap_alerts_sent (user_id, story_id, sent_at) VALUES (?,?,?) ON CONFLICT DO NOTHING', [$uid, $sid, $now]);
            }
        }
    }
    return $out;
}
