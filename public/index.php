<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/app/controllers.php';
require BASE_PATH . '/app/controllers_warnings.php';
require BASE_PATH . '/app/controllers_watchlist.php';
require BASE_PATH . '/app/controllers_landing.php';
require BASE_PATH . '/app/controllers_admin.php';

$path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$path = '/' . trim(rawurldecode($path), '/');
if ($path === '/') $path = '/';

// Route table: [method, pattern(regex with named groups), handler]
$routes = [
    ['GET',  '#^/$#',                          'home'],
    ['GET',  '#^/explore$#',                   'explore'],
    ['GET',  '#^/discover$#',                  'discover'],
    ['GET',  '#^/d/(?<slug>[a-z0-9\-]+)$#',    'destination'],
    ['GET',  '#^/d/(?<slug>[a-z0-9\-]+)/photos$#', 'destination_photos'],
    ['GET',  '#^/d/(?<slug>[a-z0-9\-]+)/warnings$#', 'destination_warnings'],
    ['POST', '#^/destination/save$#',          'destination_save_action'],
    ['POST', '#^/destination/follow$#',        'destination_follow_action'],

    /* ---- warnings: the core entity ---- */
    ['GET',  '#^/warnings$#',                  'warnings_index'],
    ['GET',  '#^/warnings/(?<category>[a-z][a-z\-]{2,30})$#', 'warning_category'],
    ['GET',  '#^/warning/new$#',               'warning_new_form'],
    ['POST', '#^/warning/new$#',               'warning_create'],
    ['GET',  '#^/warning/(?<id>\d+)/edit$#',   'warning_edit_form'],
    ['POST', '#^/warning/(?<id>\d+)/edit$#',   'warning_edit_submit'],
    ['POST', '#^/warning/(?<id>\d+)/delete$#', 'warning_delete'],
    ['POST', '#^/warning/(?<id>\d+)/helpful$#','warning_vote_action'],
    // /w/{id}/respond must precede the permalink, whose slug group would otherwise swallow it.
    ['GET',  '#^/w/(?<id>\d+)/respond$#',      'warning_respond_form'],
    ['POST', '#^/w/(?<id>\d+)/respond$#',      'warning_respond_submit'],
    ['GET',  '#^/w/(?<id>\d+)(?:/(?<slug>[a-z0-9\-]*))?$#', 'warning_show'],
    ['POST', '#^/outdated$#',                  'outdated_report_action'],

    /* ---- trip watchlist, alerts, dashboard ---- */
    ['GET',  '#^/dashboard$#',                 'dashboard'],
    ['POST', '#^/watchlist/add$#',             'watchlist_add'],
    ['GET',  '#^/watchlist/(?<id>\d+)/edit$#', 'watchlist_edit_form'],
    ['POST', '#^/watchlist/(?<id>\d+)/edit$#', 'watchlist_edit_submit'],
    ['POST', '#^/watchlist/(?<id>\d+)/delete$#','watchlist_delete'],
    ['GET',  '#^/alerts$#',                    'alerts_form'],
    ['POST', '#^/alerts/subscribe$#',          'alerts_subscribe'],
    ['GET',  '#^/alerts/confirm$#',            'alerts_confirm'],
    ['GET',  '#^/alerts/unsubscribe$#',        'alerts_unsubscribe'],

    /* ---- editorial guide pages + monetization ---- */
    ['GET',  '#^/warning-guides$#',            'landing_index'],
    ['GET',  '#^/go/(?<slug>[a-z0-9\-]+)$#',   'affiliate_go'],
    ['GET',  '#^/api/suggest$#',               'api_suggest'],
    ['GET',  '#^/u/(?<username>[A-Za-z0-9_]+)/edit$#',      'profile_edit_form'],
    ['POST', '#^/u/(?<username>[A-Za-z0-9_]+)/edit$#',      'profile_edit_submit'],
    ['GET',  '#^/u/(?<username>[A-Za-z0-9_]+)/followers$#', 'profile_followers'],
    ['GET',  '#^/u/(?<username>[A-Za-z0-9_]+)/following$#', 'profile_following'],
    ['GET',  '#^/u/(?<username>[A-Za-z0-9_]+)$#','profile'],
    ['GET',  '#^/feed$#',                      'feed'],
    ['GET',  '#^/trip/new$#',                  'trip_new_form'],
    ['POST', '#^/trip/new$#',                  'trip_create'],
    ['GET',  '#^/trip/(?<id>\d+)/edit$#',      'trip_edit_form'],
    ['POST', '#^/trip/(?<id>\d+)/edit$#',      'trip_edit_submit'],
    ['POST', '#^/trip/(?<id>\d+)/delete$#',    'trip_delete'],
    ['GET',  '#^/trip/(?<id>\d+)(?:/[a-z0-9\-]+)?$#', 'trip_show'],
    ['GET',  '#^/reviews$#',                   'reviews_index'],
    ['GET',  '#^/review/new$#',                'review_new_form'],
    ['POST', '#^/review/new$#',                'review_create'],
    ['GET',  '#^/review/(?<id>\d+)/edit$#',   'review_edit_form'],
    ['POST', '#^/review/(?<id>\d+)/edit$#',   'review_edit_submit'],
    ['POST', '#^/review/(?<id>\d+)/delete$#', 'review_delete'],
    ['POST', '#^/review/(?<id>\d+)/vote$#', 'review_vote_action'],
    ['GET',  '#^/review/(?<id>\d+)(?:/(?<slug>[a-z0-9\-]*))?$#', 'review_show'],
    ['GET',  '#^/guides$#',                    'guides_index'],
    ['GET',  '#^/guide/new$#',                 'guide_new_form'],
    ['POST', '#^/guide/new$#',                 'guide_create'],
    ['GET',  '#^/guide/(?<id>\d+)/edit$#',     'guide_edit_form'],
    ['POST', '#^/guide/(?<id>\d+)/edit$#',     'guide_edit_submit'],
    ['POST', '#^/guide/(?<id>\d+)/delete$#',   'guide_delete'],
    ['GET',  '#^/g/(?<slug>[a-z0-9\-]+)$#',    'guide_show'],
    ['GET',  '#^/blog$#',                      'blog_index'],
    ['GET',  '#^/blog/new$#',                  'blog_new_form'],
    ['POST', '#^/blog/new$#',                  'blog_create'],
    ['GET',  '#^/blog/(?<id>\d+)/edit$#',      'blog_edit_form'],
    ['POST', '#^/blog/(?<id>\d+)/edit$#',      'blog_edit_submit'],
    ['POST', '#^/blog/(?<id>\d+)/delete$#',    'blog_delete'],
    ['GET',  '#^/blog/(?<slug>[a-z0-9\-]+)$#', 'blog_show'],
    ['GET',  '#^/collections$#',                          'collections_index'],
    ['GET',  '#^/collection/new$#',                       'collection_new_form'],
    ['POST', '#^/collection/new$#',                       'collection_create'],
    ['GET',  '#^/collection/(?<id>\d+)/edit$#',           'collection_edit_form'],
    ['POST', '#^/collection/(?<id>\d+)/edit$#',           'collection_edit_submit'],
    ['POST', '#^/collection/(?<id>\d+)/delete$#',         'collection_delete'],
    ['POST', '#^/collection/(?<id>\d+)/items$#',          'collection_item_add'],
    ['POST', '#^/collection/(?<id>\d+)/items/(?<item_id>\d+)/delete$#', 'collection_item_remove'],
    ['GET',  '#^/c/(?<slug>[a-z0-9\-]+)$#',               'collection_show'],
    ['GET',  '#^/meetups$#',                   'meetups_index'],
    ['GET',  '#^/meetup/(?<id>\d+)$#',         'meetup_show'],
    ['POST', '#^/meetup/(?<id>\d+)/rsvp$#',    'meetup_rsvp'],
    ['GET',  '#^/going$#',                     'going_index'],
    ['GET',  '#^/leaderboard$#',               'leaderboard'],
    ['GET',  '#^/tags$#',                      'tags_index'],
    ['GET',  '#^/tag/(?<name>[a-z0-9][a-z0-9_\-]{1,29})$#', 'tag_show'],
    ['GET',  '#^/search$#',                    'search'],
    ['GET',  '#^/notifications$#',             'notifications'],
    ['GET',  '#^/messages$#',                  'messages_index'],
    ['GET',  '#^/messages/(?<username>[A-Za-z0-9_]+)$#',      'messages_thread'],
    ['POST', '#^/messages/(?<username>[A-Za-z0-9_]+)/send$#','messages_send'],
    ['POST', '#^/block$#',                     'block_action'],
    ['POST', '#^/unblock$#',                   'unblock_action'],
    ['GET',  '#^/unsubscribe$#',                'unsubscribe_action'],
    ['GET',  '#^/settings$#',                  'settings_form'],
    ['POST', '#^/settings$#',                  'settings_save'],
    ['GET',  '#^/login$#',                     'login_form'],
    ['POST', '#^/login$#',                     'login_submit'],
    ['GET',  '#^/register$#',                  'register_form'],
    ['POST', '#^/register$#',                  'register_submit'],
    ['GET',  '#^/logout$#',                    'logout_action'],
    ['GET',  '#^/verify-email$#',              'verify_email'],
    ['POST', '#^/verify-email/confirm$#',      'verify_email_confirm'],
    ['POST', '#^/verify-email/resend$#',       'verify_email_resend'],
    ['GET',  '#^/forgot-password$#',           'forgot_form'],
    ['POST', '#^/forgot-password$#',           'forgot_submit'],
    ['GET',  '#^/reset-password$#',            'reset_form'],
    ['POST', '#^/reset-password$#',            'reset_submit'],
    ['POST', '#^/follow$#',                    'follow_action'],
    ['POST', '#^/compliment$#',                'compliment_action'],
    ['POST', '#^/react$#',                     'react_action'],   // like/save
    ['POST', '#^/comment$#',                   'comment_action'],
    ['POST', '#^/comment/(?<id>\d+)/delete$#', 'comment_delete'],
    ['GET',  '#^/report$#',                    'report_form'],
    ['POST', '#^/report$#',                    'report_submit'],
    // /admin is now the overview; the original abuse-report queue kept its handler at
    // /admin/reports so no bookmark or moderator habit breaks. See docs/ROUTES.md.
    ['GET',  '#^/admin$#',                     'admin_overview'],
    ['GET',  '#^/admin/reports$#',             'admin_dashboard'],
    ['POST', '#^/admin/resolve$#',             'admin_resolve'],
    ['GET',  '#^/admin/mail-check$#',          'admin_mail_check'],
    ['GET',  '#^/admin/warnings$#',            'admin_warnings'],
    ['POST', '#^/admin/warnings/(?<id>\d+)/moderate$#', 'admin_warning_moderate'],
    ['GET',  '#^/admin/destinations$#',        'admin_destinations'],
    ['GET',  '#^/admin/destination/(?<id>\d+)$#',        'admin_destination_edit'],
    ['POST', '#^/admin/destination/(?<id>\d+)$#',        'admin_destination_save'],
    ['POST', '#^/admin/destination/(?<id>\d+)/section$#','admin_section_save'],
    ['POST', '#^/admin/destination/(?<id>\d+)/faq$#',    'admin_faq_save'],
    ['GET',  '#^/admin/pages$#',               'admin_pages'],
    ['GET',  '#^/admin/page/new$#',            'admin_page_edit'],
    ['GET',  '#^/admin/page/(?<id>\d+)$#',     'admin_page_edit'],
    ['POST', '#^/admin/page$#',                'admin_page_save'],
    ['POST', '#^/admin/page/(?<id>\d+)/delete$#','admin_page_delete'],
    ['GET',  '#^/admin/responses$#',           'admin_responses'],
    ['POST', '#^/admin/response/(?<id>\d+)$#', 'admin_response_action'],
    ['GET',  '#^/admin/outdated$#',            'admin_outdated'],
    ['POST', '#^/admin/outdated/(?<id>\d+)$#', 'admin_outdated_resolve'],
    ['GET',  '#^/admin/alerts$#',              'admin_alerts'],
    ['GET',  '#^/admin/affiliates$#',          'admin_affiliates'],
    ['POST', '#^/admin/affiliate$#',           'admin_affiliate_save'],
    ['POST', '#^/admin/affiliate/(?<id>\d+)/delete$#', 'admin_affiliate_delete'],
    ['GET',  '#^/admin/users$#',               'admin_users'],
    ['POST', '#^/admin/user/(?<id>\d+)$#',     'admin_user_save'],
    ['GET',  '#^/admin/analytics$#',           'admin_analytics'],
    ['GET',  '#^/admin/homepage$#',            'admin_homepage'],
    ['POST', '#^/admin/homepage$#',            'admin_homepage_save'],
    ['GET',  '#^/terms$#',                     'page_terms'],
    ['GET',  '#^/privacy$#',                   'page_privacy'],
    ['GET',  '#^/guidelines$#',                'page_guidelines'],
    ['GET',  '#^/affiliate$#',                 'page_affiliate'],
    ['GET',  '#^/safety$#',                    'page_safety'],
    ['GET',  '#^/editorial-policy$#',          'page_editorial'],
    ['GET',  '#^/sitemap\.xml$#',              'sitemap'],
    ['GET',  '#^/feed\.xml$#',                 'feed_rss'],
    ['GET',  '#^/media/(?<key>[a-f0-9]{32}\.(?:jpg|png|webp))$#', 'media_show'],
    ['GET',  '#^/healthz$#',                    'healthz'],
    ['GET',  '#^/readyz$#',                     'readyz'],

    // LAST BY DESIGN: the editorial landing-page resolver. It only ever resolves a slug that is a
    // real, published row in seo_landing_pages (admin/controllers_landing.php), so it cannot
    // shadow any route above it and cannot render a stub for an arbitrary path — an unknown slug
    // falls through to the same 404 as before.
    ['GET',  '#^/(?<slug>[a-z0-9][a-z0-9\-]{3,90})$#', 'landing_page'],
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
