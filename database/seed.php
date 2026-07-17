<?php
declare(strict_types=1);

/**
 * Create schema (SQLite) + insert demo/seed content so the site runs out of the box locally.
 * Production uses MySQL: import schema.mysql.sql and manage real content — do NOT run this seed in prod.
 * Seed images are distinct, real, licensed travel photos (Unsplash). Marked as demo content.
 */
/** Apply the schema for the given driver (idempotent for pgsql via IF NOT EXISTS). */
function rmt_apply_schema(PDO $pdo, string $driver): void {
    $file = ['pgsql' => 'schema.pgsql.sql', 'mysql' => 'schema.mysql.sql'][$driver] ?? 'schema.sqlite.sql';
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/' . $file));
}

/** Local convenience: build + seed a fresh SQLite DB. */
function rmt_migrate_and_seed(PDO $pdo): void {
    rmt_apply_schema($pdo, 'sqlite');
    rmt_seed_data($pdo);
}

/** Insert seed/demo content. Portable across sqlite/mysql/pgsql (prepared statements). */
function rmt_seed_data(PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $img = fn(string $id) => "https://images.unsplash.com/photo-$id?w=1400&q=80&auto=format&fit=crop";

    // --- Destinations (8 distinct real photos) ---
    $dests = [
        ['kyoto-japan','Kyoto','Japan','Kansai',35.0116,135.7681,'Temples, tea houses, and quiet bamboo mornings.','1493976040374-85c8e12f0c0e','culture'],
        ['lisbon-portugal','Lisbon','Portugal','Lisboa',38.7223,-9.1393,'Tiled hills, trams, and Atlantic light.','1585208798174-6cedd86e019a','city'],
        ['queenstown-nz','Queenstown','New Zealand','Otago',-45.0312,168.6626,'Adventure capital between lake and alps.','1507699622108-4be3abd695ad','adventure'],
        ['marrakech-morocco','Marrakech','Morocco','Marrakesh-Safi',31.6295,-7.9811,'Souks, riads, and desert gateways.','1597211833712-5e41faa5e5f7','culture'],
        ['banff-canada','Banff','Canada','Alberta',51.1784,-115.5708,'Turquoise lakes under Rocky Mountain peaks.','1609825488888-3a766db05542','nature'],
        ['oaxaca-mexico','Oaxaca','Mexico','Oaxaca',17.0732,-96.7266,'Mezcal, markets, and mole country.','1518638150340-f706e86654de','food'],
        ['reykjavik-iceland','Reykjavik','Iceland','Capital Region',64.1466,-21.9426,'Northern lights and volcanic coastlines.','1504893524553-b855bce32c67','nature'],
        ['hoi-an-vietnam','Hoi An','Vietnam','Quang Nam',15.8801,108.3380,'Lantern-lit old town on the river.','1528127269322-539801943592','culture'],
    ];
    $ins = $pdo->prepare('INSERT INTO destinations (slug,name,country,region,lat,lng,summary,hero_url,category) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($dests as $d) { $d[7] = $img($d[7]); $ins->execute($d); }

    // --- Demo users ---
    $hash = password_hash('travel1234', PASSWORD_BCRYPT);
    $users = [
        ['maya_wanders','maya@example.com','user','1994-03-12','Slow traveler. 41 countries, still counting.','Lisbon, PT','1544005313-94ddf0286df2',180],
        ['diego_trails','diego@example.com','creator','1990-07-02','Trail runner & guidebook writer.','Queenstown, NZ','1500648767791-00dcc994a43e',260],
        ['sana_eats','sana@example.com','creator','1996-11-25','I follow the food. Markets over museums.','Oaxaca, MX','1438761681033-6461ffad8d80',220],
        ['admin','admin@ruinmytrip.com','admin','1988-01-01','RuinMyTrip team.','—','1502685104226-ee32379fefbe',0],
        ['mod_kai','kai@ruinmytrip.com','mod','1992-05-16','Community & safety.','Reykjavik, IS','1507003211169-0a1dd7228f2d',60],
    ];
    $iu = $pdo->prepare('INSERT INTO users (username,email,password_hash,role,birthdate,status,created_at) VALUES (?,?,?,?,?,?,?)');
    $ip = $pdo->prepare('INSERT INTO profiles (user_id,display_name,bio,home_city,avatar_url,credibility_score) VALUES (?,?,?,?,?,?)');
    foreach ($users as $i => $u) {
        $iu->execute([$u[0],$u[1],$hash,$u[2],$u[3],'active',$now]);
        $uid = (int)$pdo->lastInsertId();
        $ip->execute([$uid, ucwords(str_replace('_',' ',$u[0])), $u[4], $u[5], $img($u[6]), $u[7]]);
    }

    // --- Trips / stories ---
    $trips = [
        [1,1,'Three quiet mornings in Kyoto','What temple hopping taught me about slowing down. We started before dawn at Fushimi Inari and had the lower gates almost to ourselves.','1493976040374-85c8e12f0c0e','2026-04-18',1],
        [2,3,'Queenstown to the Routeburn, on foot','A creator log of the alpine crossing: gear, permits, and the three climbs that actually hurt.','1507699622108-4be3abd695ad','2026-03-09',1],
        [3,6,'Eating Oaxaca for a week straight','Seven markets, one very good mole negro, and the mezcal palenque that changed my mind.','1518638150340-f706e86654de','2026-05-02',1],
        [1,2,'Lisbon by tram and by accident','Getting pleasantly lost in Alfama, and the miradouro worth every step of the hill.','1585208798174-6cedd86e019a','2026-02-21',0],
    ];
    $it = $pdo->prepare('INSERT INTO trips (user_id,destination_id,title,slug,body,cover_url,visited_on,verified,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($trips as $t) {
        $it->execute([$t[0],$t[1],$t[2],slugify($t[2]),$t[3],$img($t[4]),$t[5],$t[6],'published',$now]);
    }

    // --- Reviews ---
    $revs = [
        [1,1,'destination','Kyoto','Kyoto',5,'Worth the early alarms','Go at sunrise. By 9am the crowds arrive and the magic thins out.',1],
        [2,3,'attraction','Skyline Gondola','Queenstown',4,'Touristy but the view earns it','Do it once at sunset, then hike back down.',1],
        [3,6,'restaurant','Mercado 20 de Noviembre','Oaxaca',5,'Smoke, meat, and joy','Point at what looks good on the grill. You will not regret it.',1],
        [1,2,'destination','Lisbon','Lisbon',4,'Charming, hilly, alive','Wear real shoes. The 28 tram is a pickpocket buffet at rush hour.',0],
    ];
    $ir = $pdo->prepare('INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,title,rating,body,verified,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($revs as $r) {
        $ir->execute([$r[0],$r[1],$r[2],$r[3],$r[5],$r[4]==='Kyoto'?5:$r[4] ?? 4,$r[7]??'', 0, 'published',$now]);
    }
    // (simpler explicit insert to avoid index confusion)
    $pdo->exec("DELETE FROM reviews");
    $revs2 = [
        [1,1,'destination','Kyoto',5,'Worth the early alarms','Go at sunrise. By 9am the crowds arrive and the magic thins out.',1],
        [2,3,'attraction','Skyline Gondola, Queenstown',4,'Touristy but the view earns it','Do it once at sunset, then hike back down.',1],
        [3,6,'restaurant','Mercado 20 de Noviembre, Oaxaca',5,'Smoke, meat, and joy','Point at what looks good on the grill. You will not regret it.',1],
        [1,2,'destination','Lisbon',4,'Charming, hilly, alive','Wear real shoes and watch your pockets on the 28 tram at rush hour.',0],
        [3,4,'hotel','Riad in the Medina, Marrakech',5,'A calm oasis behind a loud door','The souk noise vanishes the second the riad door closes.',1],
    ];
    $ir = $pdo->prepare('INSERT INTO reviews (user_id,destination_id,subject_type,subject_name,rating,title,body,verified,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($revs2 as $r) { $ir->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],'published',$now]); }

    // --- Guides ---
    $guides = [
        [2,3,'queenstown-adventure-4-day','Queenstown in 4 Days: The Adventure Loop','Gondola, Routeburn day hike, Glenorchy, and the best cheap eats between adrenaline hits.','1507699622108-4be3abd695ad',0],
        [3,6,'oaxaca-food-itinerary','The Oaxaca Food Itinerary (5 Days)','Markets, mezcal, mole, and where to eat when everything is closed on Monday.','1518638150340-f706e86654de',1],
        [1,1,'kyoto-first-timer','Kyoto for First-Timers, Without the Crowds','A calm route through the temples, timed to dodge the tour buses.','1493976040374-85c8e12f0c0e',0],
    ];
    $ig = $pdo->prepare('INSERT INTO guides (user_id,destination_id,slug,title,summary,body,cover_url,premium,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($guides as $g) {
        $body = "<p>{$g[4]}</p><h2>Day by day</h2><p>A practical, walkable plan with timings, transit notes, and honest warnings. Full itinerary detail expands as the community contributes.</p>";
        $ig->execute([$g[0],$g[1],$g[2],$g[3],$g[4],$body,$img($g[5]),$g[6],'published',$now]);
    }

    // --- Follows ---
    foreach ([[1,2],[1,3],[3,2],[2,1]] as $f) {
        $pdo->prepare('INSERT INTO follows (follower_id,followee_id,created_at) VALUES (?,?,?)')->execute([$f[0],$f[1],$now]);
    }

    // --- Meetups (safety-first, destination-level only) ---
    $meets = [
        [2,3,'Sunrise hike + coffee — Queenstown','Public, beginner-friendly group hike then coffee in town. Meet in a public spot; details shared in-app after RSVP. All welcome, come as you are.','2026-08-02 07:00:00','2026-08-02 11:00:00',12],
        [3,6,'Oaxaca market food crawl','A public, all-ages-16+ walking food crawl through the central markets. Group of travelers, split the bill, no pressure.','2026-08-15 17:00:00','2026-08-15 20:00:00',10],
    ];
    $im = $pdo->prepare('INSERT INTO meetups (host_id,destination_id,title,description,date_start,date_end,visibility,capacity,safety_ack,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($meets as $m) { $im->execute([$m[0],$m[1],$m[2],$m[3],$m[4],$m[5],'public',$m[6],1,'published',$now]); }

    // --- "Who's going" (destination + date range only) ---
    $go = [
        [1,3,'2026-08-01','2026-08-05','public'],
        [3,6,'2026-08-14','2026-08-18','public'],
        [2,7,'2026-09-10','2026-09-16','followers'],
    ];
    $ign = $pdo->prepare('INSERT INTO going (user_id,destination_id,date_from,date_to,visibility,created_at) VALUES (?,?,?,?,?,?)');
    foreach ($go as $g) { $ign->execute([$g[0],$g[1],$g[2],$g[3],$g[4],$now]); }
}
