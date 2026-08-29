<?php
/**
 * Travel lists that can hold places, not only cities.
 *
 * Collections already existed and worked; what they could hold was a destination, so "Weekend in
 * New York" was expressible and "Favourite restaurants in Paris" was not. This tests the widening
 * -- and, more importantly, that widening it did not break the lists that already exist or let an
 * item become something that renders as a blank row on a public page.
 *
 *   php tests/collection_places_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-58s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec("PRAGMA foreign_keys = ON");
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)");
$pdo->exec("CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT)");
$pdo->exec("CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT, type TEXT, status TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/025_collections.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,status) VALUES (1,'ada','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country,hero_url) VALUES
    (1,'paris-france','Paris','France','/x.jpg'), (2,'rome-italy','Rome','Italy','/y.jpg')");
$pdo->exec("INSERT INTO places (id,destination_id,slug,name,type,status) VALUES
    (1,1,'le-procope','Le Procope','restaurant','active'),
    (2,1,'ritz','Ritz Paris','hotel','active')");
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at) VALUES
    (1,1,'my-list','My List','published','2026-08-01')");

// A list that already exists, holding cities, exactly as it did before the migration.
$pdo->exec("INSERT INTO collection_items (collection_id,destination_id,note,sort) VALUES (1,1,'first',0),(1,2,NULL,1)");
check('the existing list has two cities',
      (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE collection_id = 1")['c'], 2);

/* ------------------------------------------------------------- the migration */

$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/056_collection_places.sqlite.sql'));

// The rebuild copies rows; losing somebody's list to a schema change would be unforgivable.
check('both rows survived the rebuild',
      (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE collection_id = 1")['c'], 2);
check('and kept their order and notes',
      q_all("SELECT destination_id, note, sort FROM collection_items ORDER BY sort"),
      [['destination_id' => 1, 'note' => 'first', 'sort' => 0],
       ['destination_id' => 2, 'note' => null, 'sort' => 1]]);

/* ----------------------------------------------------------------- widening */

q_run("INSERT INTO collection_items (collection_id,place_id,note,sort) VALUES (?,?,?,?)", [1, 1, 'the old one', 2]);
check('a place can now be on a list',
      (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE place_id IS NOT NULL")['c'], 1);
check('cities and places coexist on one list',
      (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE collection_id = 1")['c'], 3);

/* ------------------------------------------------- what the database refuses */

// An item that is neither would render as a blank row on a public page. The check constraint is
// there so no code path can produce one, not merely the ones we remembered to guard.
$threw = false;
try { q_run("INSERT INTO collection_items (collection_id,sort) VALUES (1,9)"); }
catch (Throwable $e) { $threw = true; }
check('an item that is neither is refused', $threw, true);

// An item that is both is ambiguous: which one does the row link to?
$threw = false;
try { q_run("INSERT INTO collection_items (collection_id,destination_id,place_id,sort) VALUES (1,1,2,9)"); }
catch (Throwable $e) { $threw = true; }
check('an item that is both is refused', $threw, true);

// The same protection cities always had, now for places.
$threw = false;
try { q_run("INSERT INTO collection_items (collection_id,place_id,sort) VALUES (1,1,9)"); }
catch (Throwable $e) { $threw = true; }
check('the same place twice on one list is refused', $threw, true);

$threw = false;
try { q_run("INSERT INTO collection_items (collection_id,destination_id,sort) VALUES (1,1,9)"); }
catch (Throwable $e) { $threw = true; }
check('the same city twice on one list is still refused', $threw, true);

// Different lists are free to hold the same place -- that is the whole point of lists.
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at) VALUES (2,1,'other','Other','published','2026-08-01')");
q_run("INSERT INTO collection_items (collection_id,place_id,sort) VALUES (2,1,0)");
check('two lists may hold the same place',
      (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE place_id = 1")['c'], 2);

/* --------------------------------------------------------------- reading it */

// The public page LEFT JOINs both sides. An inner join on destinations -- what the code did before
// -- silently drops every place, so the list renders as though the places were never added.
$items = q_all("SELECT ci.*, d.name dest_name, pl.name place_name, pl.slug place_slug
                  FROM collection_items ci
                  LEFT JOIN destinations d ON d.id = ci.destination_id
                  LEFT JOIN places pl ON pl.id = ci.place_id AND pl.status = 'active'
                 WHERE ci.collection_id = 1 ORDER BY ci.sort, ci.id");
check('all three items are readable', count($items), 3);
check('the place item resolves its name', (string) $items[2]['place_name'], 'Le Procope');
check('the city items keep theirs', (string) $items[0]['dest_name'], 'Paris');

// A place taken off the site leaves an item pointing at nothing. It is dropped on read rather than
// rendered as a blank card, and rather than being deleted out of somebody's list behind their back.
q_run("UPDATE places SET status = 'closed' WHERE id = 1");
$items = q_all("SELECT ci.*, d.slug dest_slug, pl.slug place_slug
                  FROM collection_items ci
                  LEFT JOIN destinations d ON d.id = ci.destination_id
                  LEFT JOIN places pl ON pl.id = ci.place_id AND pl.status = 'active'
                 WHERE ci.collection_id = 1 ORDER BY ci.sort, ci.id");
$visible = array_values(array_filter($items, static fn(array $i) =>
    ($i['destination_id'] !== null && $i['dest_slug'] !== null) ||
    ($i['place_id'] !== null && $i['place_slug'] !== null)));
check('a closed place drops out of the rendered list', count($visible), 2);
check('but the row is still on the list', (int) q_one("SELECT COUNT(*) c FROM collection_items WHERE collection_id = 1")['c'], 3);

echo $fail ? "\n$fail FAIL(S)\n" : "\nALL PASS\n";
exit($fail ? 1 : 0);
