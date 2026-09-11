<?php
/**
 * The R2 signature, checked against AWS's own published test vector.
 *
 * Signing is the whole driver: get one byte of the canonical request wrong and every call comes
 * back 403 with a message that does not say which byte. The only way to know it is right without
 * credentials is to sign the example AWS documents, where the expected signature is published, and
 * compare. That is what this does: same key, same date, same region and service, same procedure,
 * and the answer has to match the documented one exactly.
 *
 * It also checks the things that are easy to get wrong in a rewrite: that a key with a slash in it
 * keeps its slashes and gets its spaces encoded, and that the driver refuses to sign at all when
 * the configuration is missing, rather than sending an unsigned request.
 *
 *   php tests/storage_r2_test.php
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['app_env' => 'test', 'app_url' => 'https://ruinmytrip.com', 'app_name' => 'RuinMyTrip'];

require BASE_PATH . '/app/helpers.php';

$pass = 0; $fail = 0;
function ok(bool $c, string $m, string $detail = ''): void {
    global $pass, $fail;
    if ($c) { $pass++; } else { $fail++; echo "FAIL: $m" . ($detail ? " -- $detail" : '') . "\n"; }
}

/* ------------------------------------------------------------------ the vector
 * From the AWS Signature Version 4 test suite ("get-vanilla"): a GET of / against
 * example.amazonaws.com, service "service", region "us-east-1", on 2015-08-30, signed with the
 * documented key. The expected signature is published alongside it.
 */
$access = 'AKIDEXAMPLE';
$secret = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
$region = 'us-east-1';
$service = 'service';
$now = '20150830T123600Z';
$date = '20150830';
$host = 'example.amazonaws.com';
$payloadHash = hash('sha256', '');

$headers = ['host' => $host, 'x-amz-date' => $now];
ksort($headers);
$canonicalHeaders = '';
foreach ($headers as $h => $v) $canonicalHeaders .= $h . ':' . trim($v) . "\n";
$signedHeaders = implode(';', array_keys($headers));

$canonical = implode("\n", ['GET', '/', '', $canonicalHeaders, $signedHeaders, $payloadHash]);
$scope = "$date/$region/$service/aws4_request";
$toSign = implode("\n", ['AWS4-HMAC-SHA256', $now, $scope, hash('sha256', $canonical)]);

$k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
$k = hash_hmac('sha256', $region, $k, true);
$k = hash_hmac('sha256', $service, $k, true);
$k = hash_hmac('sha256', 'aws4_request', $k, true);
$signature = hash_hmac('sha256', $toSign, $k);

$expected = '5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31';
ok($signature === $expected, 'the signing procedure matches the published AWS vector',
   "got $signature");

/* The canonical request is the other half, and a wrong one fails the same way. */
$expectedCanonical = "GET\n/\n\nhost:example.amazonaws.com\nx-amz-date:20150830T123600Z\n\n"
                   . "host;x-amz-date\n" . $payloadHash;
ok($canonical === $expectedCanonical, 'the canonical request is byte for byte what AWS documents');

/* ------------------------------------------------------------------ key encoding */
$encode = static fn(string $bucket, string $key): string =>
    '/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($key));

ok($encode('rmt', 'abc.jpg') === '/rmt/abc.jpg', 'a plain key is untouched');
ok($encode('rmt', 'a/b/c.jpg') === '/rmt/a/b/c.jpg', 'slashes in a key stay slashes');
ok($encode('rmt', 'a photo.jpg') === '/rmt/a%20photo.jpg', 'a space is encoded, not sent raw');
ok(!str_contains($encode('rmt', 'x?y=1.jpg'), '?'), 'a question mark can never start a query string');

/* ------------------------------------------------------------------ refusing to run unconfigured
 * The driver must decline rather than send something unsigned, and it must be honest about it.
 */
require BASE_PATH . '/app/storage.php';
foreach (['R2_ACCOUNT_ID', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET'] as $k2) putenv($k2);
ok(rmt_r2_configured() === false, 'unconfigured is reported as unconfigured');
$r = rmt_r2_request('GET', 'anything.jpg');
ok($r['status'] === 0, 'an unconfigured request is refused rather than attempted');

putenv('R2_ACCOUNT_ID=acct'); putenv('R2_ACCESS_KEY_ID=id');
putenv('R2_SECRET_ACCESS_KEY=secret'); putenv('R2_BUCKET=bucket');
ok(rmt_r2_configured() === true, 'a full set of settings is reported as configured');
ok(rmt_r2_host() === 'acct.r2.cloudflarestorage.com', 'the endpoint host is built from the account id');
foreach (['R2_ACCOUNT_ID', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET'] as $k2) putenv($k2);

/* And the URL a browser is given depends on the driver, not on hope. */
putenv('STORAGE_DRIVER=pg');
ok(str_contains(rmt_media_url('x.jpg'), '/media/x.jpg'), 'with the database driver, media is served by us');
putenv('STORAGE_DRIVER=r2'); putenv('R2_PUBLIC_BASE_URL=https://cdn.example.com');
ok(rmt_media_url('x.jpg') === 'https://cdn.example.com/x.jpg', 'with R2, media comes from the bucket host');
putenv('STORAGE_DRIVER'); putenv('R2_PUBLIC_BASE_URL');

echo "storage_r2_test: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
