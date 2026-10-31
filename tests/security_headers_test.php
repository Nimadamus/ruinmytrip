<?php
/**
 * The response headers production sends come from docker/000-default.conf (Apache mod_headers)
 * and the PHP ini the Dockerfile writes. This pins what they promise, and the one subtle rule:
 * the frame-ancestors CSP must be "always set", so it is added beside the sandbox CSP that media
 * responses set from PHP instead of replacing it.
 *
 *   php tests/security_headers_test.php
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$conf = (string) file_get_contents($root . '/docker/000-default.conf');
$docker = (string) file_get_contents($root . '/Dockerfile');
$ctl = (string) file_get_contents($root . '/app/controllers.php');

$fails = 0;
function ok(string $name, bool $cond): void {
    global $fails;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $name . "\n";
    if (!$cond) $fails++;
}

ok('nosniff', str_contains($conf, 'Header set X-Content-Type-Options "nosniff"'));
ok('X-Frame-Options SAMEORIGIN', str_contains($conf, 'Header set X-Frame-Options "SAMEORIGIN"'));
ok('Referrer-Policy', str_contains($conf, 'Header set Referrer-Policy "strict-origin-when-cross-origin"'));
ok('HSTS without preload', (bool) preg_match('/Strict-Transport-Security "max-age=\d+"/', $conf) && !preg_match('/Strict-Transport-Security "[^"]*preload/', $conf));
ok('Permissions-Policy turns off camera, microphone and location',
   (bool) preg_match('/Permissions-Policy "[^"]*camera=\(\)[^"]*microphone=\(\)[^"]*geolocation=\(\)/', $conf));
ok('Permissions-Policy leaves clipboard and share alone (the site uses both)',
   !preg_match('/Permissions-Policy "[^"]*(clipboard|web-share)/', $conf));
ok('frame-ancestors CSP is added with "always set"',
   str_contains($conf, "Header always set Content-Security-Policy \"frame-ancestors 'self'\""));
ok('no enforced script/style CSP is set at the server (it would need an audited allow-list)',
   !preg_match('/Content-Security-Policy "[^"]*(script-src|default-src)/', $conf));
ok('media keeps its sandbox CSP from PHP',
   preg_match_all('/Content-Security-Policy: default-src [^;]*none[^;]*; sandbox/', $ctl) >= 2);
ok('X-Powered-By is removed', str_contains($conf, 'Header unset X-Powered-By'));
ok('expose_php is off', str_contains($docker, "expose_php = Off"));

echo $fails ? "\n$fails FAILED\n" : "\nAll security header tests passed.\n";
exit($fails ? 1 : 0);
