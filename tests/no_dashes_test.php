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
