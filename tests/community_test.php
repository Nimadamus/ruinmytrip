<?php
/**
 * Communities: a collection other people can join.
 *
 * What this is really testing is that adding a door to collections did not change what a
 * collection is. Every list that exists today is 'closed', owned by one person, and must behave
 * exactly as it did before migration 060 ran. The rest is the door itself: who may come in, who
 * may write, what a removal means, and the rule that keeps an empty room out of the index.
 *
 *   php tests/community_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = [
    'app_env' => 'test', 'app_url' => 'https://example.test', 'app_name' => 'RuinMyTrip',
    'db_driver' => 'sqlite', 'sqlite_path' => ':memory:',
];

require BASE_PATH . '/app/db.php';
require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/communities.php';

$fail = 0;
function check(string $name, $got, $expect): void {
    global $fail;
    $ok = $got === $expect;
    if (!$ok) $fail++;
    printf("  [%s] %-62s expected=%s got=%s\n", $ok ? 'PASS' : 'FAIL', $name,
           var_export($expect, true), var_export($got, true));
}

$pdo = db();
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, status TEXT)');
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, avatar_url TEXT)');
$pdo->exec('CREATE TABLE destinations (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, country TEXT, hero_url TEXT)');
$pdo->exec('CREATE TABLE places (id INTEGER PRIMARY KEY, destination_id INT, slug TEXT, name TEXT, type TEXT, status TEXT)');
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/025_collections.sqlite.sql'));
$pdo->exec("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, collection_id INT, body TEXT, status TEXT, created_at TEXT)");
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/056_collection_places.sqlite.sql'));

$pdo->exec("INSERT INTO users (id,username,status) VALUES
    (1,'ada','active'), (2,'grace','active'), (3,'linus','active'), (4,'edsger','active')");
$pdo->exec("INSERT INTO destinations (id,slug,name,country,hero_url) VALUES
    (1,'paris-france','Paris','France','/x.jpg'), (2,'rome-italy','Rome','Italy','/y.jpg'),
    (3,'lisbon-portugal','Lisbon','Portugal','/z.jpg')");

// A list that already existed before communities were a concept.
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at) VALUES
    (1,1,'my-list','My List','published','2026-08-01')");
$pdo->exec('INSERT INTO collection_items (collection_id,destination_id,sort) VALUES (1,1,0),(1,2,1)');

/* ------------------------------------------------------------------ the migration */

$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/060_communities.sqlite.sql'));

$old = q_one('SELECT * FROM collections WHERE id=1');
check('an existing list is still closed', $old['join_policy'], 'closed');
check('and is not a community', rmt_is_community($old), false);
check('its two items survived', (int) q_one('SELECT COUNT(*) n FROM collection_items WHERE collection_id=1')['n'], 2);
check('its owner was backfilled as a member', rmt_community_role(1, 1), 'owner');
check('its items were attributed to the owner',
      (int) q_one('SELECT COUNT(*) n FROM collection_items WHERE collection_id=1 AND added_by=1')['n'], 2);
check('a closed list has one member', rmt_community_member_count(1), 1);

// Running it twice must not double the owner rows. Migrations get re-run.
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at)
            SELECT c.id, c.user_id, 'owner', 'active', c.created_at FROM collections c
             WHERE NOT EXISTS (SELECT 1 FROM collection_members m WHERE m.collection_id=c.id AND m.user_id=c.user_id)");
check('the owner backfill is idempotent', rmt_community_member_count(1), 1);

/* ------------------------------------------------------------------ closed stays shut */

$ada  = ['id' => 1, 'username' => 'ada'];
$grace = ['id' => 2, 'username' => 'grace'];
$linus = ['id' => 3, 'username' => 'linus'];

check('a stranger cannot join a personal list', rmt_community_join_state($old, $grace), 'not_a_community');
check('and joining one does nothing', rmt_community_join($old, $grace), 'not_a_community');
check('a stranger cannot add to a personal list', rmt_community_can_add($old, $grace), false);
check('the owner can add to their own list', rmt_community_can_add($old, $ada), true);

/* ---------------------------------------------------------------------- an open door */

$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at,join_policy,members_can_add)
            VALUES (2,1,'ruined-honeymoons','Places that ruined my honeymoon','published','2026-08-02','open',0)");
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at)
            VALUES (2,1,'owner','active','2026-08-02')");
$open = q_one('SELECT * FROM collections WHERE id=2');

check('an open community is a community', rmt_is_community($open), true);
check('a signed out visitor is asked to sign in', rmt_community_join_state($open, null), 'sign_in_required');
check('a signed in traveler can join', rmt_community_join_state($open, $grace), 'can_join');
check('joining works', rmt_community_join($open, $grace), 'joined');
check('joining twice is not an error', rmt_community_join($open, $grace), 'already_member');
check('the member count reflects it', rmt_community_member_count(2), 2);
check('the new member is a member, not an owner', rmt_community_role(2, 2), 'member');

// members_can_add is off, so joining a room does not hand anybody the pen.
check('a member cannot add while the pen is withheld', rmt_community_can_add($open, $grace), false);
$pdo->exec('UPDATE collections SET members_can_add=1 WHERE id=2');
$open = q_one('SELECT * FROM collections WHERE id=2');
check('a member can add once the founder allows it', rmt_community_can_add($open, $grace), true);
check('a non member still cannot add', rmt_community_can_add($open, $linus), false);

/* ------------------------------------------------------------------------- leaving */

check('a member can leave', rmt_community_leave($open, $grace), 'left');
check('leaving twice says so', rmt_community_leave($open, $grace), 'not_a_member');
check('and they can come back through an open door', rmt_community_join($open, $grace), 'joined');
check('the founder cannot leave their own community', rmt_community_leave($open, $ada), 'owner_cannot_leave');
check('so the founder is still the owner', rmt_community_role(2, 1), 'owner');

/* ------------------------------------------------------------------------ removal */

check('a member cannot remove another member', rmt_community_remove_member($open, $grace, 3), 'forbidden');
check('the founder can remove a member', rmt_community_remove_member($open, $ada, 2), 'removed');
check('a removed member is nobody', rmt_community_role(2, 2), null);
check('a removed member cannot walk back in', rmt_community_join_state($open, $grace), 'removed');
check('not even by trying', rmt_community_join($open, $grace), 'removed');
check('the founder cannot remove themselves', rmt_community_remove_member($open, $ada, 1), 'owner_cannot_leave');
check('a removal can be undone', rmt_community_reinstate_member($open, $ada, 2), 'reinstated');
check('and then they are a member again', rmt_community_role(2, 2), 'member');

/* ------------------------------------------------------------------------ invites */

$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at,join_policy,members_can_add)
            VALUES (3,1,'solo-女-asia','Solo in Southeast Asia','published','2026-08-03','invite',1)");
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at)
            VALUES (3,1,'owner','active','2026-08-03')");
$inviteOnly = q_one('SELECT * FROM collections WHERE id=3');

check('invite only refuses a stranger', rmt_community_join_state($inviteOnly, $grace), 'invite_required');
check('a member cannot issue invites', rmt_community_rotate_invite($inviteOnly, $grace), null);
$token = rmt_community_rotate_invite($inviteOnly, $ada);
check('the founder can issue one', is_string($token) && strlen($token) === 32, true);
check('the link opens the door', rmt_community_join_state($inviteOnly, $grace, $token), 'can_join');
check('a wrong token does not', rmt_community_join_state($inviteOnly, $grace, str_repeat('0', 32)), 'invite_required');
check('the link finds its community', (int) rmt_community_by_invite($token)['id'], 3);
check('joining with it works', rmt_community_join($inviteOnly, $grace, $token), 'joined');

// One live link at a time: replacing it is the same action as retiring the old one.
$second = rmt_community_rotate_invite($inviteOnly, $ada);
check('a new link is different', $second !== $token, true);
check('the old link is dead', rmt_community_join_state($inviteOnly, $linus, $token), 'invite_required');
check('the new link works', rmt_community_join_state($inviteOnly, $linus, $second), 'can_join');
check('revoking kills the live link', rmt_community_revoke_invite($inviteOnly, $ada), true);
check('after which nobody gets in', rmt_community_join_state($inviteOnly, $linus, $second), 'invite_required');
check('and the link resolves to nothing', rmt_community_by_invite($second), null);

/* --------------------------------------------------------------------- the items */

q_run('INSERT INTO collection_items (collection_id,destination_id,sort,added_by) VALUES (?,?,?,?)', [2, 1, 0, 1]);
q_run('INSERT INTO collection_items (collection_id,destination_id,sort,added_by) VALUES (?,?,?,?)', [2, 2, 1, 2]);
$ownersItem  = q_one('SELECT * FROM collection_items WHERE collection_id=2 AND added_by=1');
$membersItem = q_one('SELECT * FROM collection_items WHERE collection_id=2 AND added_by=2');

check('the founder can remove anything', rmt_community_can_remove_item($open, $ada, $membersItem), true);
check('a member can retract their own', rmt_community_can_remove_item($open, $grace, $membersItem), true);
check('a member cannot remove the founder\'s', rmt_community_can_remove_item($open, $grace, $ownersItem), false);
check('a stranger can remove nothing', rmt_community_can_remove_item($open, $linus, $ownersItem), false);

check('a member cannot pin', rmt_community_set_pinned($open, $grace, (int) $membersItem['id'], true), false);
check('the founder can pin', rmt_community_set_pinned($open, $ada, (int) $membersItem['id'], true), true);
check('and the pin stuck',
      (int) q_one('SELECT pinned FROM collection_items WHERE id=?', [(int) $membersItem['id']])['pinned'], 1);

/* ------------------------------------------------------------- discoverability gate */

// Community 2 has two items and two members: one item short.
check('two items is not enough to be listed', rmt_community_is_discoverable($open), false);
q_run('INSERT INTO collection_items (collection_id,destination_id,sort,added_by) VALUES (?,?,?,?)', [2, 3, 2, 1]);
check('three items and two members is', rmt_community_is_discoverable($open), true);

// A founder alone in a full room is still an empty room to anyone who arrives.
$pdo->exec("INSERT INTO collections (id,user_id,slug,title,status,created_at,join_policy)
            VALUES (4,3,'lonely','Nobody here','published','2026-08-04','open')");
$pdo->exec("INSERT INTO collection_members (collection_id,user_id,role,status,joined_at)
            VALUES (4,3,'owner','active','2026-08-04')");
$pdo->exec('INSERT INTO collection_items (collection_id,destination_id,sort,added_by) VALUES (4,1,0,3),(4,2,1,3),(4,3,2,3)');
$lonely = q_one('SELECT * FROM collections WHERE id=4');
check('a founder alone is not discoverable', rmt_community_is_discoverable($lonely), false);

check('a personal list is never discoverable as a community', rmt_community_is_discoverable($old), false);
$pdo->exec("UPDATE collections SET status='deleted' WHERE id=2");
check('a deleted community is not discoverable',
      rmt_community_is_discoverable(q_one('SELECT * FROM collections WHERE id=2')), false);
$pdo->exec("UPDATE collections SET status='published' WHERE id=2");

// Browse shows exactly what a stranger should see: community 2, and nothing that has not earned it.
$browse = array_column(rmt_community_browse(), 'slug');
check('browse lists the community that qualifies', in_array('ruined-honeymoons', $browse, true), true);
check('and hides the one nobody joined', in_array('lonely', $browse, true), false);
check('and never lists a personal list', in_array('my-list', $browse, true), false);

/* ------------------------------------------------------------------------ profile */

$mine = array_column(rmt_community_memberships(2), 'slug');
check('a member sees the communities they are in', in_array('ruined-honeymoons', $mine, true), true);
check('including invite only ones', in_array('solo-女-asia', $mine, true), true);
check('but not ones they were never in', in_array('lonely', $mine, true), false);

echo $fail ? "\n{$fail} FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
