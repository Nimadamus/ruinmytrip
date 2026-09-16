<?php
declare(strict_types=1);

/**
 * One question, asked once, at the moment it is worth asking.
 *
 * When real travelers arrive we will be able to see exactly what they did and nothing at all about
 * why. The most useful thing to know about an early visitor is what they came hoping to find, and
 * the only way to know that is to ask.
 *
 * What this deliberately is not: a survey tool, a popup, a modal, or anything that appears on more
 * than one screen. One question, in the flow, on a page where the honest answer to what the person
 * sees is "nothing yet", with a skip that is as easy as answering.
 *
 * The note is the person's own words, so it lives in its own table and never in the event stream.
 * Telemetry learns that somebody answered; only this table learns what they said.
 */

/** The questions we ask, and the only answers each one accepts. A closed list both ways. */
const RMT_VQ_QUESTIONS = [
    // Asked on an empty match list, which is where nearly every early visitor will end up.
    'no_match_hoped_for' => [
        'question' => 'Nothing to show you yet. What were you hoping to find?',
        'answers'  => [
            'travelers_same_dates' => 'Other travelers in the city when I am there',
            'locals'               => 'Locals who live there',
            'answers_to_questions' => 'Answers to questions about the place',
            'group_for_event'      => 'People to do a specific event with',
            'just_looking'         => 'Just looking around',
        ],
    ],
];

const RMT_VQ_NOTE_MAX = 300;

/** Is this a question we ask, with an answer it accepts. Nothing else is ever written. */
function rmt_vq_valid(string $question, string $answer): bool {
    return isset(RMT_VQ_QUESTIONS[$question]['answers'][$answer]);
}

/** Has this person already been asked this. A question asked twice is a nag. */
function rmt_vq_answered(int $userId, string $question): bool {
    if ($userId <= 0) return false;
    try {
        return (bool) q_one('SELECT 1 x FROM visitor_answers WHERE user_id = ? AND question = ?', [$userId, $question]);
    } catch (Throwable) {
        /* The table may not be there yet on a database that has not migrated. A missing answer log
           must never stop a page rendering, exactly as a missing event table does not. */
        return true;
    }
}

/**
 * Record one answer. Returns false and writes nothing if the question or the answer is not ours.
 *
 * The note is trimmed and capped and is the only free text this site stores outside a post, a review
 * or a message. It is never required and the answer is complete without it.
 */
function rmt_vq_record(int $userId, string $question, string $answer, string $note = ''): bool {
    if (!rmt_vq_valid($question, $answer)) return false;
    if ($userId > 0 && rmt_vq_answered($userId, $question)) return false;
    $note = trim($note);
    if ($note !== '') $note = mb_substr($note, 0, RMT_VQ_NOTE_MAX);
    try {
        q_run('INSERT INTO visitor_answers (user_id, question, answer, note, created_at) VALUES (?,?,?,?,?)',
              [$userId > 0 ? $userId : null, $question, $answer, $note === '' ? null : $note,
               date('Y-m-d H:i:s')]);
    } catch (Throwable) {
        return false;
    }
    return true;
}

/**
 * What the answers say so far, for the dashboard. Counts per answer, newest notes last.
 *
 * @return array{total:int, answers:array<string,int>, notes:list<array{note:string, at:string}>}
 */
function rmt_vq_summary(string $question, int $notes = 10): array {
    $out = ['total' => 0, 'answers' => [], 'notes' => []];
    if (!isset(RMT_VQ_QUESTIONS[$question])) return $out;
    try {
        foreach (q_all('SELECT answer, COUNT(*) c FROM visitor_answers WHERE question = ? GROUP BY answer',
                       [$question]) as $r) {
            $out['answers'][(string) $r['answer']] = (int) $r['c'];
            $out['total'] += (int) $r['c'];
        }
        foreach (q_all('SELECT note, created_at FROM visitor_answers
                         WHERE question = ? AND note IS NOT NULL AND note <> \'\'
                      ORDER BY id DESC LIMIT ' . max(1, min(50, $notes)), [$question]) as $r) {
            $out['notes'][] = ['note' => (string) $r['note'], 'at' => (string) $r['created_at']];
        }
    } catch (Throwable) {
        return $out;
    }
    return $out;
}
