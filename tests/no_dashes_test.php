<?php
/**
 * No dashes in anything a visitor reads.
 *
 *   php tests/no_dashes_test.php
 *
 * The house style has no em dash and no en dash in copy. It kept coming back because it is easy
 * to type and invisible in review: 208 of them were live on the site at once, in page titles, in
 * the option that says "Select a destination", in the date ranges on every trip, and in the
 * weekly digest email. Fixing them by hand fixes today and nothing else, so the rule is a test.
 *
 * It reads only what reaches the browser. Tokenising means a dash inside a PHP comment is not a
 * finding, because a comment is written for whoever edits the file, and a dash in a character
 * class that strips dashes is the opposite of a violation.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dashes = ["\u{2014}", "\u{2013}", "\u{2012}", "\u{2015}"];
/* The same character, spelled as HTML. Twenty five of these survived the first sweep precisely
   because they are not the character: &mdash; renders as an em dash and greps as an ampersand. */
$entities = ['&mdash;', '&ndash;', '&#8212;', '&#8211;', '&#x2014;', '&#x2013;'];

/* Lines that hold a dash on purpose. Each one has to say why, because the point of an allowlist
   that anybody can append to without a reason is to grow until it means nothing. */
$allow = [
    // rtrim() character classes: these exist to REMOVE a trailing dash from a truncated title.
    'app/seo.php',
];

$files = [];
foreach (['app', 'views'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir"));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
        }
    }
}
sort($files);

$findings = [];
foreach ($files as $rel) {
    if (in_array($rel, $allow, true)) continue;
    $src = file_get_contents("$root/$rel");
    if ($src === false) continue;
    foreach (token_get_all($src) as $tok) {
        if (!is_array($tok)) continue;
        [$id, $text, $line] = $tok;
        // A comment is not copy. Everything else that carries text can reach the page.
        if ($id === T_COMMENT || $id === T_DOC_COMMENT) continue;
        /* Two hyphens typed where an em dash was meant. Not the character, so the sweep above
           never saw it, and it reaches the reader looking like exactly the thing the house style
           bans: "Your trip is live -- here it is" shipped in a flash message this way. Only
           checked outside comments, like everything else here, because the asides in this
           codebase use it freely and are written for whoever edits the file. */
        /* Two hyphens with a space on each side: an em dash typed by somebody who could not
           type one. "Your trip is live -- here it is" shipped in a flash message this way,
           invisible to the sweep above because it is not the character.

           Narrowed to the two token kinds a reader can actually receive, with three exclusions
           that are not prose: CSS custom properties (var(--line)) carry no spaces and never
           match; a SQL comment inside a query string is written for whoever reads the query; and
           a JavaScript comment inside an inline <script> is written for whoever edits the view. */
        $proseish = $id === T_CONSTANT_ENCAPSED_STRING || $id === T_INLINE_HTML || $id === T_ENCAPSED_AND_WHITESPACE;
        if ($proseish) {
            $look = $text;
            if ($id === T_INLINE_HTML) {
                $look = preg_replace('#<script.*?</script>#is', ' ', $look) ?? $look;
            }
            /* Two known fragments where the exclusions above cannot see enough context.
               token_get_all() splits a heredoc and an interrupted <script> into pieces, so the
               chunk holding the comment no longer carries the SELECT or the opening tag that
               would identify it. Named rather than guessed at, with the reason, because an
               allowlist anybody can append to without one grows until it means nothing. */
            $notProse = ($rel === 'app/destination_modules.php')      // SQL comments inside one query
                     || ($rel === 'views/_review_form.php');          // JS comments in an inline script
            if (!$notProse && !preg_match('/(SELECT|INSERT|UPDATE|DELETE)\s/i', $look)
                && preg_match('/\s--\s/', $look)) {
                $findings[] = $rel . ':' . $line . ' ' . mb_substr(trim(preg_replace('/\s+/', ' ', $look) ?? ''), 0, 120);
                continue;
            }
        }
        foreach (array_merge($dashes, $entities) as $d) {
            if (str_contains($text, $d)) {
                $findings[] = $rel . ':' . $line . ' ' . trim(preg_replace('/\s+/', ' ', $text) ?? '');
                break;
            }
        }
    }
}

if ($findings) {
    echo "no_dashes_test: " . count($findings) . " failed\n";
    foreach (array_slice($findings, 0, 40) as $f) echo "  FAIL $f\n";
    exit(1);
}

echo 'no_dashes_test: ' . count($files) . " files clean, 0 failed\n";
