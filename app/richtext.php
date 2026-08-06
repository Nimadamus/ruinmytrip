<?php
declare(strict_types=1);

/**
 * The formatter for editorial prose (risk sections, FAQ answers, landing pages).
 *
 * Existing editorial guides are stored as raw HTML and rendered unescaped, which is only safe
 * because exactly one account writes them. The risk-report system is edited through the admin UI
 * by whoever owns the site, and will eventually be edited by staff — so it takes the opposite
 * approach: everything is escaped first, and structure is rebuilt from a tiny, closed markup.
 * There is no path from stored text to executable HTML, which means a compromised or careless
 * editor account cannot become stored XSS on every destination page.
 *
 * Supported, and nothing else:
 *   blank line          paragraph break
 *   lines starting "- " an unordered list
 *   **bold**            <strong>
 *   [text](https://…)   a link, https/http/mailto only, external links get rel="nofollow noopener"
 */

/** Render editorial prose to safe HTML. */
function rmt_rich(?string $text): string {
    $text = trim((string) $text);
    if ($text === '') return '';
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    $out = '';
    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $lines = explode("\n", $block);
        $isList = true;
        foreach ($lines as $l) { if (!preg_match('/^\s*[-•]\s+/u', $l)) { $isList = false; break; } }
        if ($isList) {
            $out .= '<ul>';
            foreach ($lines as $l) {
                $out .= '<li>' . rmt_rich_inline(preg_replace('/^\s*[-•]\s+/u', '', $l) ?? '') . '</li>';
            }
            $out .= '</ul>';
        } else {
            // Single newlines inside a paragraph become <br>, matching how the text was typed.
            $parts = array_map('rmt_rich_inline', $lines);
            $out .= '<p>' . implode('<br>', $parts) . '</p>';
        }
    }
    return $out;
}

/**
 * Inline formatting for one line. Escapes first, then re-introduces only the markup this
 * function itself generates — so no attacker-supplied character sequence can become a tag.
 */
function rmt_rich_inline(string $line): string {
    $safe = e(trim($line));

    // [text](url) — the URL is validated against a scheme allowlist and re-escaped.
    $safe = preg_replace_callback(
        '/\[([^\]\[]{1,160})\]\(([^)\s]{1,400})\)/',
        static function (array $m): string {
            $label = $m[1];
            $href  = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('#^(https?://|mailto:|/)#i', $href)) return $label;
            $self = (string) parse_url((string) cfg('app_url'), PHP_URL_HOST);
            $host = (string) (parse_url($href, PHP_URL_HOST) ?: $self);
            $rel  = ($host === $self) ? '' : ' rel="nofollow noopener" target="_blank"';
            return '<a href="' . e($href) . '"' . $rel . '>' . $label . '</a>';
        },
        $safe
    ) ?? $safe;

    // **bold**
    $safe = preg_replace('/\*\*([^*]{1,200})\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
    return $safe;
}

/**
 * First N characters of editorial prose as plain text — for meta descriptions and cards.
 *
 * Block boundaries become spaces BEFORE the tags are stripped. Without that step, a bulleted
 * section renders as "alphabeta" in the meta description, because strip_tags() simply deletes
 * `</li><li>` and leaves the words touching.
 */
function rmt_rich_excerpt(?string $text, int $len = 160): string {
    $html = preg_replace('#</(p|li|ul|h[1-6])>|<br\s*/?>#i', ' ', rmt_rich($text)) ?? '';
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    if (mb_strlen($plain) <= $len) return $plain;
    $cut = mb_substr($plain, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp !== false ? mb_substr($cut, 0, $sp) : $cut, " ,.;:") . '…';
}
