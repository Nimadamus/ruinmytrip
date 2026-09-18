<?php
/**
 * Early controls against the things that turn a travel community into a notice board: link farms,
 * affiliate and crypto pitches, the same text pasted everywhere, and "travel matching" used as a
 * way to solicit dates or sell something.
 *
 * Deliberately small and explainable. Each rule returns the sentence the author sees, and none of
 * them silently drops anything: somebody who wrote a genuine post that trips a rule is told why
 * and can change it. Nothing here reads private messages; it runs on public posts and comments.
 */
declare(strict_types=1);

const RMT_QUALITY_MAX_LINKS = 2;
const RMT_QUALITY_NEW_ACCOUNT_HOURS = 24;

/* Phrases that are almost never travel talk and almost always a pitch. Matched on word boundaries,
   case insensitive. Kept short on purpose: a long list catches travelers. */
const RMT_QUALITY_PITCH_PATTERNS = [
    'crypto'   => '/\b(bitcoin|btc|usdt|crypto ?(signals?|investment|trading)|forex (signals?|trading)|binary options|investment opportunity|guaranteed (returns?|profit)|double your money)\b/i',
    'contact'  => '/\b(whats ?app me|text me on whats ?app|dm me on (telegram|whatsapp|snap|instagram)|add me on (telegram|snap(chat)?|kik)|telegram ?@\w+)\b/i',
    'dating'   => '/\b(sugar (daddy|baby|mommy)|hook ?up|looking for a (girlfriend|boyfriend|wife|husband)|escort|onlyfans|nudes?|sexy (girls?|women|men))\b/i',
    'affiliate'=> '/\b(use my (promo|referral|discount) code|promo code|referral code|affiliate link|(buy|order) (now|here) (at|on)|limited time offer|click (the )?link in (my )?bio)\b|[?&](ref|aff|affiliate|affid)=/i',
];

/**
 * @return ?string the reason to refuse, or null when the text is fine
 */
function rmt_quality_check(string $body, ?array $user, string $kind = 'post'): ?string {
    $text = trim($body);
    if ($text === '') return null;

    preg_match_all('#https?://|www\.#i', $text, $m);
    $links = count($m[0]);
    if ($links > RMT_QUALITY_MAX_LINKS) {
        return 'That has a lot of links in it. Keep it to ' . RMT_QUALITY_MAX_LINKS . ' and say in your own words what they are.';
    }
    if ($links > 0 && $user) {
        $created = strtotime((string) ($user['created_at'] ?? ''));
        if ($created && (time() - $created) < RMT_QUALITY_NEW_ACCOUNT_HOURS * 3600) {
            return 'New accounts can post links after their first day. Say it without the link for now.';
        }
    }

    foreach (RMT_QUALITY_PITCH_PATTERNS as $why => $re) {
        if (preg_match($re, $text)) {
            return match ($why) {
                'crypto'    => 'RuinMyTrip is for travel. Investment and crypto offers are not allowed here.',
                'contact'   => 'Please keep contact on RuinMyTrip. Once you both connect, messaging opens here.',
                'dating'    => 'Adult content, escort and hookup offers are not allowed here.',
                default     => 'Promo codes, referral and affiliate links are not allowed here.',
            };
        }
    }

    /* The same words twice in a day, by the same person, is pasting rather than talking. Short
       replies like "thank you" are exempt, because two people can both deserve one. */
    if ($user && mb_strlen($text) >= 40) {
        $since = date('Y-m-d H:i:s', strtotime('-1 day'));
        $uid = (int) $user['id'];
        try {
            $dup = q_one("SELECT 1 x FROM posts WHERE user_id = ? AND body = ? AND created_at > ? AND status = 'published'",
                         [$uid, $text, $since])
                ?: q_one("SELECT 1 x FROM comments WHERE user_id = ? AND body = ? AND created_at > ? AND status = 'published'",
                         [$uid, $text, $since]);
        } catch (Throwable) {
            $dup = null;
        }
        if ($dup) return 'You already posted exactly that today. Say something new, or reply where the conversation is.';
    }
    return null;
}
