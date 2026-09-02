<?php
/**
 * Share cards: the picture a link turns into when somebody pastes it into a chat or a timeline.
 *
 * A young social site is found by one person sending a link to another, and on WhatsApp, X,
 * Discord, Reddit and iMessage that link is judged by its preview before anyone reads a word. A
 * post with no photo used to preview as the same stock beach as every other page; a profile
 * previewed as a 96px avatar stretched to a banner. Now every shareable thing renders its own
 * 1200x630 card from what it actually says: the line somebody wrote, who wrote it, where it is
 * about, and the site's name.
 *
 * Rendered on demand with GD (already a build dependency for photo re-encoding), cached by the
 * browser and the scrapers for a day, never stored. The spec is built from the same visibility
 * rules as the page: a removed post or a suspended member has no card.
 */
declare(strict_types=1);

const RMT_CARD_W = 1200;
const RMT_CARD_H = 630;

function rmt_card_font(bool $bold = false): string {
    return BASE_PATH . '/public/assets/fonts/' . ($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf');
}

function rmt_card_available(): bool {
    return function_exists('imagecreatetruecolor') && function_exists('imagettftext') && is_file(rmt_card_font());
}

/** Width in pixels of $text at $size using $font. */
function rmt_card_text_width(string $text, string $font, float $size): int {
    $b = imagettfbbox($size, 0, $font, $text);
    return $b ? (int) (max($b[2], $b[4]) - min($b[0], $b[6])) : 0;
}

/**
 * Greedy word wrap to at most $maxLines lines; the last line gets an ellipsis when text is cut.
 * A single word wider than the line is broken by character so nothing overflows the card.
 *
 * @return string[]
 */
function rmt_card_wrap(string $text, string $font, float $size, int $maxWidth, int $maxLines): array {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') return [];
    $words = explode(' ', $text);
    $lines = [];
    $cur = '';
    $fits = fn(string $s) => rmt_card_text_width($s, $font, $size) <= $maxWidth;
    foreach ($words as $w) {
        if (!$fits($w)) {
            // Break a runaway word (a URL, a hashtag chain) into pieces that fit.
            $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $piece = '';
            foreach ($chars as $ch) {
                $try = ($cur === '' ? '' : $cur . ' ') . $piece . $ch;
                if ($fits($try)) { $piece .= $ch; continue; }
                $lines[] = $cur === '' ? $piece : $cur . ' ' . $piece;
                $cur = ''; $piece = $ch;
            }
            $w = $piece;
        }
        $try = $cur === '' ? $w : $cur . ' ' . $w;
        if ($fits($try)) { $cur = $try; continue; }
        $lines[] = $cur;
        $cur = $w;
    }
    if ($cur !== '') $lines[] = $cur;
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $last = $lines[$maxLines - 1];
        while ($last !== '' && !$fits($last . '…')) $last = mb_substr($last, 0, -1);
        $lines[$maxLines - 1] = rtrim($last) . '…';
    }
    return $lines;
}

/**
 * Render a card to PNG bytes.
 *
 * @param array{kicker?:string, title:string, meta?:string, rating?:int, pills?:string[]} $spec
 */
function rmt_card_render(array $spec): string {
    $W = RMT_CARD_W; $H = RMT_CARD_H;
    $im = imagecreatetruecolor($W, $H);
    $ink    = imagecolorallocate($im, 15, 27, 45);      // --ink
    $panel  = imagecolorallocate($im, 22, 37, 60);
    $brand  = imagecolorallocate($im, 20, 184, 166);    // brighter --brand for dark ground
    $white  = imagecolorallocate($im, 255, 255, 255);
    $muted  = imagecolorallocate($im, 170, 184, 200);
    $amber  = imagecolorallocate($im, 251, 191, 36);
    $regular = rmt_card_font(false); $bold = rmt_card_font(true);

    imagefilledrectangle($im, 0, 0, $W, $H, $ink);
    imagefilledrectangle($im, 0, 0, 14, $H, $brand);          // the brand bar, same as the site's accent edge
    $pad = 72;

    // Wordmark + kicker
    imagettftext($im, 26, 0, $pad, 78, $brand, $bold, 'RuinMyTrip');
    $kicker = mb_strtoupper(trim((string) ($spec['kicker'] ?? '')));
    if ($kicker !== '') {
        $x = $pad + rmt_card_text_width('RuinMyTrip', $bold, 26) + 26;
        imagettftext($im, 20, 0, $x, 76, $muted, $regular, $kicker);
    }

    // Title: scale the size down until it fits in four lines, so a short line is big and a long
    // one is still whole.
    $maxW = $W - $pad * 2;
    $size = 60; $lines = [];
    foreach ([60, 52, 46, 40] as $size) {
        $lines = rmt_card_wrap((string) $spec['title'], $bold, $size, $maxW, 4);
        $probe = rmt_card_wrap((string) $spec['title'], $bold, $size, $maxW, 12);
        if (count($probe) <= 4) break;
    }
    $lineH = (int) round($size * 1.32);
    $y = 178;
    if (!empty($spec['rating'])) {
        $stars = str_repeat('★', max(0, min(5, (int) $spec['rating']))) . str_repeat('☆', 5 - max(0, min(5, (int) $spec['rating'])));
        imagettftext($im, 34, 0, $pad, $y - 20, $amber, $regular, $stars);
        $y += 52;
    }
    foreach ($lines as $ln) {
        imagettftext($im, $size, 0, $pad, $y, $white, $bold, $ln);
        $y += $lineH;
    }

    // Meta line (who / where)
    $meta = trim((string) ($spec['meta'] ?? ''));
    if ($meta !== '') {
        $mLines = rmt_card_wrap($meta, $regular, 28, $maxW, 1);
        imagettftext($im, 28, 0, $pad, min($y + 26, $H - 120), $muted, $regular, $mLines[0] ?? '');
    }

    // Pills along the bottom left, domain bottom right.
    $px = $pad; $py = $H - 62;
    foreach (array_slice((array) ($spec['pills'] ?? []), 0, 4) as $pill) {
        $pill = (string) $pill;
        $tw = rmt_card_text_width($pill, $regular, 22);
        imagefilledrectangle($im, $px, $py - 34, $px + $tw + 32, $py + 12, $panel);
        imagettftext($im, 22, 0, $px + 16, $py, $white, $regular, $pill);
        $px += $tw + 46;
    }
    $dom = 'ruinmytrip.com';
    $dw = rmt_card_text_width($dom, $regular, 24);
    imagettftext($im, 24, 0, $W - $pad - $dw, $H - 52, $brand, $regular, $dom);

    ob_start();
    imagepng($im, null, 6);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/** Trim a body to one card-sized quote. */
function rmt_card_quote(string $body, int $max = 180): string {
    $t = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');
    return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max)) . '…' : $t;
}

/**
 * Build the spec for one shareable thing, or null when it must not have a card (removed, hidden,
 * unknown). Every branch applies the same visibility rule as the page it previews.
 */
function rmt_card_spec(string $kind, string $key): ?array {
    switch ($kind) {
        case 'post':
            $p = q_one("SELECT p.*, u.username, u.status ustatus, d.name dest_name, c.title community_title
                          FROM posts p JOIN users u ON u.id = p.user_id
                     LEFT JOIN destinations d ON d.id = p.destination_id
                     LEFT JOIN collections c ON c.id = p.collection_id
                         WHERE p.id = ?", [(int) $key]);
            if (!$p || $p['status'] !== 'published' || $p['ustatus'] !== 'active') return null;
            $body = trim((string) $p['body']);
            if ($body === '' && !empty($p['repost_of'])) {
                $o = q_one("SELECT body FROM posts WHERE id=? AND status='published'", [(int) $p['repost_of']]);
                $body = (string) ($o['body'] ?? '');
            }
            $meta = '@' . $p['username'];
            if ($p['dest_name']) $meta .= ' · ' . $p['dest_name'];
            $pills = [];
            if ($p['community_title']) $pills[] = (string) $p['community_title'];
            $replies = (int) (q_one("SELECT COUNT(*) c FROM comments WHERE target_type='post' AND target_id=? AND status='published'", [(int) $p['id']])['c'] ?? 0);
            if ($replies > 0) $pills[] = $replies . ($replies === 1 ? ' reply' : ' replies');
            $hasPoll = (bool) q_one('SELECT 1 x FROM post_polls WHERE post_id=?', [(int) $p['id']]);
            return ['kicker' => $hasPoll ? 'Poll' : 'Travel talk', 'title' => rmt_card_quote($body), 'meta' => $meta, 'pills' => $pills];

        case 'review':
            $r = q_one("SELECT r.*, u.username, u.status ustatus, d.name dest_name, pl.name place_name
                          FROM reviews r JOIN users u ON u.id = r.user_id
                     LEFT JOIN destinations d ON d.id = r.destination_id
                     LEFT JOIN places pl ON pl.id = r.place_id
                         WHERE r.id = ?", [(int) $key]);
            if (!$r || $r['status'] !== 'published' || $r['ustatus'] !== 'active') return null;
            $subject = (string) ($r['place_name'] ?: $r['subject_name'] ?: $r['dest_name']);
            $title = trim((string) $r['title']) !== '' ? (string) $r['title'] : rmt_card_quote((string) $r['body'], 120);
            $meta = '@' . $r['username'];
            if ($subject !== '') $meta .= ' · ' . $subject;
            if ($r['dest_name'] && $subject !== $r['dest_name']) $meta .= ', ' . $r['dest_name'];
            $pills = [];
            if (trim((string) $r['what_ruined']) !== '') $pills[] = 'What ruined it: ' . mb_strimwidth(trim((string) $r['what_ruined']), 0, 40, '…');
            return ['kicker' => 'Honest review', 'title' => $title, 'meta' => $meta, 'rating' => (int) $r['rating'], 'pills' => $pills];

        case 'c':
            $c = q_one("SELECT * FROM collections WHERE slug=? AND status='published'", [$key]);
            if (!$c) return null;
            $members = function_exists('rmt_community_member_count') ? rmt_community_member_count((int) $c['id']) : 0;
            $posts = (int) (q_one("SELECT COUNT(*) c FROM posts WHERE collection_id=? AND status='published'", [(int) $c['id']])['c'] ?? 0);
            $pills = [];
            if ($members > 0) $pills[] = $members . ($members === 1 ? ' member' : ' members');
            if ($posts > 0) $pills[] = $posts . ($posts === 1 ? ' post' : ' posts');
            $sub = trim((string) $c['summary']);
            return ['kicker' => 'Community', 'title' => (string) $c['title'], 'meta' => $sub !== '' ? rmt_card_quote($sub, 110) : 'A room for travelers on RuinMyTrip', 'pills' => $pills];

        case 'u':
            $u = q_one("SELECT u.id, u.username, u.status, p.display_name, p.bio, p.home_city
                          FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.username = ?", [$key]);
            if (!$u || $u['status'] !== 'active') return null;
            $one = static fn(string $sql, array $a) => (int) (q_one($sql, $a)['c'] ?? 0);
            $uid = (int) $u['id'];
            $reviews = $one("SELECT COUNT(*) c FROM reviews WHERE user_id=? AND status='published'", [$uid]);
            $posts = $one("SELECT COUNT(*) c FROM posts WHERE user_id=? AND status='published'", [$uid]);
            $followers = $one("SELECT COUNT(*) c FROM follows WHERE followee_id=?", [$uid]);
            $pills = [];
            if ($reviews) $pills[] = $reviews . ($reviews === 1 ? ' review' : ' reviews');
            if ($posts) $pills[] = $posts . ($posts === 1 ? ' post' : ' posts');
            if ($followers) $pills[] = $followers . ($followers === 1 ? ' follower' : ' followers');
            $name = trim((string) $u['display_name']) !== '' ? (string) $u['display_name'] : '@' . $u['username'];
            $meta = '@' . $u['username'] . (trim((string) $u['home_city']) !== '' ? ' · ' . $u['home_city'] : '');
            $bio = rmt_card_quote((string) $u['bio'], 110);
            return ['kicker' => 'Traveler', 'title' => $name, 'meta' => $bio !== '' ? $meta . ' · ' . $bio : $meta, 'pills' => $pills];

        case 'meetup':
            $m = q_one("SELECT m.*, d.name dest_name, u.username FROM meetups m
                     LEFT JOIN destinations d ON d.id = m.destination_id
                          JOIN users u ON u.id = m.host_id WHERE m.id = ?", [(int) $key]);
            if (!$m || !in_array($m['status'], RMT_MEETUP_STATUSES, true)) return null;
            $when = $m['date_start'] ? date('D j M Y', strtotime((string) $m['date_start'])) : '';
            $meta = ($m['dest_name'] ? $m['dest_name'] . ' · ' : '') . $when . ' · hosted by @' . $m['username'];
            $going = (int) (q_one("SELECT COUNT(*) c FROM meetup_rsvps WHERE meetup_id=?", [(int) $m['id']])['c'] ?? 0);
            $pills = $going > 0 ? [$going . ' going'] : [];
            if ($m['status'] === 'cancelled') $pills[] = 'Cancelled';
            return ['kicker' => 'Meetup', 'title' => (string) $m['title'], 'meta' => trim($meta, ' ·'), 'pills' => $pills];

        case 'tag':
            $t = q_one('SELECT * FROM tags WHERE name=?', [$key]);
            if (!$t) return null;
            $n = (int) (q_one('SELECT COUNT(*) c FROM taggings WHERE tag_id=?', [(int) $t['id']])['c'] ?? 0);
            return ['kicker' => 'Topic', 'title' => '#' . $t['name'], 'meta' => 'What travelers are saying about it',
                    'pills' => $n > 0 ? [$n . ($n === 1 ? ' post' : ' posts')] : []];
    }
    return null;
}

/** Absolute URL of the card for a thing; used as og:image. */
function rmt_card_url(string $kind, string $key): string {
    return url('card/' . $kind . '/' . rawurlencode($key) . '.png');
}
