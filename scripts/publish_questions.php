<?php
declare(strict_types=1);

/**
 * Publish the ten editorial questions from database/editorial/questions.json.
 *
 * What this is: ten questions about ten cities, asked in the open by the site, under the editorial
 * account that already owns the destination reviews and carries the Editorial badge everywhere it
 * appears. Every one begins "RuinMyTrip asks:" so a reader cannot mistake who wrote it.
 *
 * What this is NOT, and what it must never become: a member. There are no answers in the file and
 * none will ever be written by us. Fabricating a reply, or asking a question under a name that
 * looks like a traveler, is the line this project does not cross, and the whole value of an honest
 * empty community is lost the moment it is.
 *
 * Idempotent: a question is matched on its destination and its first line, so re-running edits the
 * same row rather than stacking duplicates. It runs at deploy alongside publish_editorial.php.
 *
 *   php scripts/publish_questions.php            dry run
 *   php scripts/publish_questions.php --apply    write
 */

define('RMT_NO_AUTOSEED', true);
require dirname(__DIR__) . '/app/bootstrap.php';

$apply = in_array('--apply', array_slice($argv, 1), true);
$file  = BASE_PATH . '/database/editorial/questions.json';

if (!is_file($file)) { fwrite(STDERR, "questions: no file at $file\n"); exit(1); }
$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data) || empty($data['questions'])) { fwrite(STDERR, "questions: unreadable file\n"); exit(1); }

$author = rmt_editorial_user();
if (!$author) {
    /* Without the editorial account there is no honest byline, and a question posted under any
       other account would read as a member asking it. Refuse rather than improvise. */
    fwrite(STDERR, "questions: no editorial account, nothing written\n");
    exit($apply ? 1 : 0);
}

$made = 0; $updated = 0; $skipped = 0;
foreach ($data['questions'] as $q) {
    $slug = (string) ($q['slug'] ?? '');
    $body = trim((string) ($q['body'] ?? ''));
    if ($slug === '' || $body === '') { $skipped++; continue; }

    /* The label is the whole safety mechanism, so it is checked here rather than trusted to the
       file: a question that does not say who is asking does not go up. */
    if (!str_starts_with($body, 'RuinMyTrip asks:')) {
        fwrite(STDERR, "questions: refused, does not say who is asking: " . substr($body, 0, 60) . "\n");
        $skipped++; continue;
    }

    $d = q_one('SELECT id, name FROM destinations WHERE slug = ?', [$slug]);
    if (!$d) { fwrite(STDERR, "questions: no destination $slug\n"); $skipped++; continue; }

    $firstLine = strtok($body, "\n");
    $existing = q_one("SELECT id, body FROM posts
                        WHERE user_id = ? AND destination_id = ? AND body LIKE ?
                        ORDER BY id LIMIT 1",
                      [(int) $author['id'], (int) $d['id'], $firstLine . '%']);

    if ($existing) {
        if ((string) $existing['body'] === $body) { $skipped++; continue; }
        if ($apply) {
            q_run('UPDATE posts SET body = ?, updated_at = ? WHERE id = ?',
                  [$body, date('Y-m-d H:i:s'), (int) $existing['id']]);
        }
        $updated++;
        printf("  %-24s update  %s\n", $slug, substr($firstLine, 0, 60));
        continue;
    }

    if ($apply) {
        /* updated_at left empty: a question that has not been edited must not say it was. */
        q_run("INSERT INTO posts (user_id, destination_id, body, status, created_at)
               VALUES (?,?,?,'published',?)",
              [(int) $author['id'], (int) $d['id'], $body, date('Y-m-d H:i:s')]);
    }
    $made++;
    printf("  %-24s ask     %s\n", $slug, substr($firstLine, 0, 60));
}

printf("questions: %d new, %d updated, %d unchanged or refused%s\n",
       $made, $updated, $skipped, $apply ? '' : ' (dry run, nothing written)');
