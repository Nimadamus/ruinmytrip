<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';

$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$path = '/' . trim(rawurldecode($path), '/');
if ($path === '/') $path = '/';

// Route table: [method, pattern(regex with named groups), handler]
$routes = [
    ['GET',  '#^/$#',                          'home'],
    ['GET',  '#^/explore$#',                   'explore'],
    ['GET',  '#^/d/(?<slug>[a-z0-9\-]+)$#',    'destination'],
    ['GET',  '#^/u/(?<username>[A-Za-z0-9_]+)$#','profile'],
    ['GET',  '#^/feed$#',                      'feed'],
    ['GET',  '#^/trip/new$#',                  'trip_new_form'],
    ['POST', '#^/trip/new$#',                  'trip_create'],
    ['GET',  '#^/trip/(?<id>\d+)(?:/[a-z0-9\-]+)?$#', 'trip_show'],
    ['GET',  '#^/reviews$#',                   'reviews_index'],
    ['GET',  '#^/review/new$#',                'review_new_form'],
    ['POST', '#^/review/new$#',                'review_create'],
    ['GET',  '#^/guides$#',                    'guides_index'],
    ['GET',  '#^/g/(?<slug>[a-z0-9\-]+)$#',    'guide_show'],
    ['GET',  '#^/meetups$#',                   'meetups_index'],
    ['GET',  '#^/meetup/(?<id>\d+)$#',         'meetup_show'],
    ['POST', '#^/meetup/(?<id>\d+)/rsvp$#',    'meetup_rsvp'],
    ['GET',  '#^/going$#',                     'going_index'],
    ['GET',  '#^/search$#',                    'search'],
    ['GET',  '#^/notifications$#',             'notifications'],
    ['GET',  '#^/settings$#',                  'settings_form'],
    ['POST', '#^/settings$#',                  'settings_save'],
    ['GET',  '#^/login$#',                     'login_form'],
    ['POST', '#^/login$#',                     'login_submit'],
    ['GET',  '#^/register$#',                  'register_form'],
    ['POST', '#^/register$#',                  'register_submit'],
    ['GET',  '#^/logout$#',                    'logout_action'],
    ['POST', '#^/follow$#',                    'follow_action'],
    ['POST', '#^/react$#',                     'react_action'],   // like/save
    ['POST', '#^/comment$#',                   'comment_action'],
    ['GET',  '#^/report$#',                    'report_form'],
    ['POST', '#^/report$#',                    'report_submit'],
    ['GET',  '#^/admin$#',                     'admin_dashboard'],
    ['POST', '#^/admin/resolve$#',             'admin_resolve'],
    ['GET',  '#^/terms$#',                     'page_terms'],
    ['GET',  '#^/privacy$#',                   'page_privacy'],
    ['GET',  '#^/guidelines$#',                'page_guidelines'],
    ['GET',  '#^/affiliate$#',                 'page_affiliate'],
    ['GET',  '#^/safety$#',                    'page_safety'],
    ['GET',  '#^/sitemap\.xml$#',              'sitemap'],
    ['GET',  '#^/healthz$#',                    'healthz'],
    ['GET',  '#^/readyz$#',                     'readyz'],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') $method = 'GET'; // serve HEAD via the GET handler (body is discarded by the server)
foreach ($routes as [$m, $rx, $fn]) {
    if ($m !== $method) continue;
    if (preg_match($rx, $path, $params)) {
        $args = array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY);
        $fn($args);
        return;
    }
}
http_response_code(404);
view('404', [], ['title' => 'Not found — RuinMyTrip']);
