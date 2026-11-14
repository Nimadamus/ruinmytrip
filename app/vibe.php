<?php
/**
 * Travel vibe: a member's usual budget and pace (migration 124).
 *
 * Two optional, self-declared answers that sit beside the ones already here (interests, and who
 * somebody travels with). They exist for one question, asked before two strangers agree to share a
 * trip: would we get along on the road? Somebody on a shoestring and somebody booking suites can
 * both be lovely and still have a miserable week together.
 *
 * Unset means not said. Nothing is inferred and nothing is displayed for a member who left them
 * blank. The budget keys are the ones a buddy post already uses (RMT_BUDDY_BUDGETS), so "a post on
 * a mid range budget" and "a member who usually travels mid range" are the same word.
 */
declare(strict_types=1);

const RMT_VIBE_BUDGETS = ['budget' => 'Shoestring', 'mid' => 'Mid range', 'luxury' => 'Luxury'];
const RMT_VIBE_PACES   = ['packed' => 'Packed days', 'flexible' => 'Flexible', 'slow' => 'Slow and easy'];

function rmt_vibe_budget(?string $k): ?string { return isset(RMT_VIBE_BUDGETS[(string) $k]) ? (string) $k : null; }
function rmt_vibe_pace(?string $k): ?string { return isset(RMT_VIBE_PACES[(string) $k]) ? (string) $k : null; }

/** @return array{budget_level:?string, travel_pace:?string} */
function rmt_vibe_for(int $userId): array {
    $r = $userId > 0 ? q_one('SELECT budget_level, travel_pace FROM profiles WHERE user_id = ?', [$userId]) : null;
    return ['budget_level' => rmt_vibe_budget($r['budget_level'] ?? null), 'travel_pace' => rmt_vibe_pace($r['travel_pace'] ?? null)];
}

/** Save from a form. Anything not on the lists is stored as not said. */
function rmt_vibe_save(int $userId, array $in): void {
    if ($userId < 1) return;
    q_run('UPDATE profiles SET budget_level = ?, travel_pace = ? WHERE user_id = ?',
          [rmt_vibe_budget((string) ($in['budget_level'] ?? '')), rmt_vibe_pace((string) ($in['travel_pace'] ?? '')), $userId]);
}

/**
 * The words for a budget and a pace, for a chip or a line of text. Empty when neither was said.
 * A post's "any budget" is not a statement and is left out.
 *
 * @return list<string>
 */
function rmt_vibe_labels(?string $budget, ?string $pace): array {
    $out = [];
    if (($b = rmt_vibe_budget($budget)) !== null) $out[] = RMT_VIBE_BUDGETS[$b] . ' budget';
    if (($p = rmt_vibe_pace($pace)) !== null) $out[] = RMT_VIBE_PACES[$p];
    return $out;
}

/**
 * What a reader and a trip have in common, said only when both sides said it. Never a score.
 *
 * @param array{budget_level:?string, travel_pace:?string} $mine
 * @return list<string>
 */
function rmt_vibe_shared(array $mine, ?string $budget, ?string $pace): array {
    $out = [];
    if ($mine['budget_level'] !== null && rmt_vibe_budget($budget) === $mine['budget_level']) $out[] = 'Same budget as you';
    if ($mine['travel_pace'] !== null && rmt_vibe_pace($pace) === $mine['travel_pace']) $out[] = 'Same pace as you';
    return $out;
}
