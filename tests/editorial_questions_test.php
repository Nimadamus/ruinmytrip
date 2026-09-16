<?php
/**
 * Ten questions asked in the open by the site.
 *
 * Every assertion here is about the one thing that makes this acceptable rather than the thing
 * everybody was told never to do: the reader must know the site asked, and no answer may ever be
 * written by us. A question under a name that looks like a traveler, or a single fabricated reply,
 * and the honest empty community is worth nothing.
 */
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));

$pass = 0; $fail = 0;
function ok(string $what, $got, $want = true): void {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "  [PASS] $what\n"; }
    else { $fail++; echo "  [FAIL] $what  expected=" . var_export($want, true)
                      . " got=" . var_export($got, true) . "\n"; }
}

$json = json_decode((string) file_get_contents(BASE_PATH . '/database/editorial/questions.json'), true);
$src  = (string) file_get_contents(BASE_PATH . '/scripts/publish_questions.php');

echo "\n-- a pilot, not a factory --\n";
ok('the file parses',            is_array($json) && isset($json['questions']), true);
$qs = $json['questions'] ?? [];
ok('there are at least five',    count($qs) >= 5, true);
ok('and no more than ten',       count($qs) <= 10, true);

echo "\n-- every one says who is asking --\n";
foreach ($qs as $i => $q) {
    ok("question $i names the asker", str_starts_with((string) ($q['body'] ?? ''), 'RuinMyTrip asks:'), true);
    ok("question $i names a city",    (bool) preg_match('/^[a-z0-9\-]+$/', (string) ($q['slug'] ?? '')), true);
    ok("question $i actually asks",   str_contains((string) ($q['body'] ?? ''), '?'), true);
}
ok('the script checks the label itself, rather than trusting the file',
   str_contains($src, "str_starts_with(\$body, 'RuinMyTrip asks:')"), true);
ok('and refuses without the editorial account',
   str_contains($src, 'questions: no editorial account, nothing written'), true);

echo "\n-- nothing here can fabricate a reply --\n";
ok('the file holds no answers', (bool) preg_match('/"(answer|reply|comment)s?"\s*:/i',
   (string) file_get_contents(BASE_PATH . '/database/editorial/questions.json')), false);
ok('the script writes no comment',  (bool) preg_match('/INSERT INTO comments/i', $src), false);
ok('the script writes no reaction', (bool) preg_match('/INSERT INTO reactions/i', $src), false);
ok('the script writes no user',     (bool) preg_match('/INSERT INTO users/i', $src), false);
ok('it posts only as the editorial account', str_contains($src, 'rmt_editorial_user()'), true);

echo "\n-- a reader can see who asked, in the byline as well as the body --\n";
/* The body says "RuinMyTrip asks:" and that is the safety condition. The byline is what somebody
   actually skims, and it read as a plain member handle until this was added. */
$cc    = (string) file_get_contents(BASE_PATH . '/views/_city_community.php');
$ps    = (string) file_get_contents(BASE_PATH . '/views/post_show.php');
$posts = (string) file_get_contents(BASE_PATH . '/app/posts.php');
ok('the city page badges an editorial byline',      str_contains($cc, 'rmt_editorial_badge()'), true);
ok('...using the role that travelled with the row', str_contains($cc, 'author_role'), true);
ok('the query carries that role',                   str_contains($posts, 'u.role author_role'), true);
ok('the question page badges it too',               str_contains($ps, 'rmt_is_editorial($p)'), true);


echo "\n-- somebody who wants to answer gets back to the question --\n";
$eng = (string) file_get_contents(BASE_PATH . '/views/_engagement.php');
ok('signup from a question carries the page back', str_contains($eng, "url('register' . \$rmt_eng_q)"), true);
ok('...and so does sign in',                      str_contains($eng, "url('login' . \$rmt_eng_q)"), true);
ok('a question asks to be answered, not commented on', str_contains($eng, "'to answer.'"), true);
/* A question nobody edited must not say "edited". */
ok('the page only says edited after a real edit',
   str_contains((string) file_get_contents(BASE_PATH . '/views/post_show.php'),
                "strtotime((string) \$p['updated_at']) - strtotime((string) \$p['created_at']) > 60"), true);
ok('and the publisher no longer stamps an edit time on a new question',
   (bool) preg_match('/INSERT INTO posts \(user_id, destination_id, body, status, created_at\)\s/', $src), true);


echo "\n-- running it twice does not double it --\n";
ok('it matches an existing row first', str_contains($src, 'SELECT id, body FROM posts'), true);
ok('...on destination and first line', str_contains($src, "\$firstLine . '%'"), true);
ok('...and updates rather than inserts', str_contains($src, 'UPDATE posts SET body = ?'), true);
ok('it runs at deploy',
   str_contains((string) file_get_contents(BASE_PATH . '/docker/entrypoint.sh'), 'publish_questions.php --apply'), true);

echo "\n";
if ($fail > 0) { echo "FAIL: {$fail} case(s) failed, {$pass} passed\n"; exit(1); }
echo "ALL EDITORIAL QUESTION TESTS PASS ({$pass})\n";
