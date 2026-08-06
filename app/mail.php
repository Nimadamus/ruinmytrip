<?php
declare(strict_types=1);

/**
 * Transactional email via the Resend HTTPS API.
 *
 * Why HTTPS and not SMTP: Render blocks ALL outbound SMTP (ports 465 and 587 both time out /
 * ENETUNREACH from inside a Render service). Any SMTP-based mailer silently never delivers.
 *
 * Config (env only, never in the repo):
 *   RESEND_API_KEY   required. Without it, mail is DISABLED and rmt_mail_send() returns false.
 *   MAIL_FROM        sender. Defaults to Resend's shared onboarding@resend.dev, which works
 *                    with no DNS setup. Move to a verified send.ruinmytrip.com address for
 *                    real deliverability before broad launch.
 *   MAIL_REPLY_TO    optional.
 *
 * Never throws: a mail failure must not break registration. Callers decide what to tell the user.
 */

function rmt_mail_enabled(): bool { return (getenv('RESEND_API_KEY') ?: '') !== ''; }

function rmt_mail_from(): string { return getenv('MAIL_FROM') ?: 'RuinMyTrip <onboarding@resend.dev>'; }

/**
 * Send one email. Returns [ok, detail] — detail is an id on success, an error string on failure.
 * @return array{0:bool,1:string}
 */
function rmt_mail_send(string $to, string $subject, string $html, string $text = ''): array {
    $key = getenv('RESEND_API_KEY') ?: '';
    if ($key === '') return [false, 'RESEND_API_KEY not set — mail disabled'];

    $payload = [
        'from'    => rmt_mail_from(),
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
    ];
    if ($text !== '') $payload['text'] = $text;
    if ($rt = getenv('MAIL_REPLY_TO')) $payload['reply_to'] = $rt;
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    // ext-curl is not guaranteed to be compiled into every PHP build we might run on, and a
    // missing extension would turn every verification email into a fatal error. Use curl when
    // it exists, fall back to a plain stream POST when it does not.
    $res = function_exists('curl_init')
        ? rmt_mail_post_curl($key, $json)
        : rmt_mail_post_stream($key, $json);

    // A failed transactional email is invisible to the user and to us unless we say so. Log the
    // reason to stderr (Render captures it). Never log the API key or the message body.
    if (!$res[0]) error_log('[rmt_mail] send FAILED: ' . $res[1]);
    return $res;
}

/**
 * Mail transport diagnostics — no secrets, safe to surface to an admin.
 * @return array<string,string|bool>
 */
function rmt_mail_diagnostics(): array {
    return [
        'key_present'     => rmt_mail_enabled(),
        'key_len'         => strlen(getenv('RESEND_API_KEY') ?: ''),
        'from'            => rmt_mail_from(),
        'has_curl'        => function_exists('curl_init'),
        'has_openssl'     => extension_loaded('openssl'),
        'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
        'https_wrappers'  => in_array('https', stream_get_wrappers(), true),
    ];
}

/** @return array{0:bool,1:string} */
function rmt_mail_post_curl(string $key, string $json): array {
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,   // never hang a web request on the mail provider
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => $json,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) return [false, 'curl: ' . $err];
    return rmt_mail_interpret($code, (string) $body, 'curl');
}

/** @return array{0:bool,1:string} */
function rmt_mail_post_stream(string $key, string $json): array {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Authorization: Bearer {$key}\r\nContent-Type: application/json\r\n",
        'content'       => $json,
        'timeout'       => 15,
        'ignore_errors' => true,   // read the body on 4xx/5xx instead of returning false
    ]]);
    $body = @file_get_contents('https://api.resend.com/emails', false, $ctx);
    if ($body === false) return [false, 'stream: request failed (allow_url_fopen / TLS?)'];

    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int) $m[1];
    }
    return rmt_mail_interpret($code, $body, 'stream');
}

/** @return array{0:bool,1:string} */
function rmt_mail_interpret(int $code, string $body, string $via): array {
    $json = json_decode($body, true);
    if ($code >= 200 && $code < 300) return [true, (string) ($json['id'] ?? 'sent') . " (via {$via})"];
    return [false, "resend http {$code} (via {$via}): " . (string) ($json['message'] ?? substr($body, 0, 200))];
}

/* ---------------------------------------------------------------- *
 * Templates. Plain, minimal, no tracking pixels, no external assets.
 * ---------------------------------------------------------------- */

function rmt_mail_layout(string $heading, string $bodyHtml, string $ctaText = '', string $ctaUrl = ''): string {
    $cta = $ctaUrl === '' ? '' :
        '<p style="margin:28px 0"><a href="' . e($ctaUrl) . '" style="background:#0f1b2d;color:#fff;'
        . 'text-decoration:none;padding:12px 22px;border-radius:8px;display:inline-block;'
        . 'font-weight:600">' . e($ctaText) . '</a></p>'
        . '<p style="color:#667;font-size:13px;margin:0">Or paste this link into your browser:<br>'
        . '<span style="color:#356">' . e($ctaUrl) . '</span></p>';

    return '<div style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:520px;margin:0 auto;'
        . 'padding:32px 24px;color:#1a2430;line-height:1.55">'
        . '<p style="font-size:18px;font-weight:700;margin:0 0 24px">◈ RuinMyTrip</p>'
        . '<h1 style="font-size:22px;margin:0 0 12px">' . e($heading) . '</h1>'
        . $bodyHtml . $cta
        . '<hr style="border:0;border-top:1px solid #e6ebf0;margin:32px 0 16px">'
        . '<p style="color:#8895a3;font-size:12px;margin:0">If you did not expect this email you can '
        . 'safely ignore it. Nothing will change on your account.</p></div>';
}

function rmt_mail_verification(string $to, string $username, string $link): array {
    $html = rmt_mail_layout(
        'Confirm your email',
        '<p>Hi @' . e($username) . ' — confirm this address to finish setting up your RuinMyTrip '
        . 'account. The link expires in 24 hours.</p>',
        'Confirm email', $link
    );
    $text = "Hi @{$username},\n\nConfirm your email to finish setting up your RuinMyTrip account:\n{$link}\n\n"
          . "This link expires in 24 hours. If you didn't sign up, ignore this email.";
    return rmt_mail_send($to, 'Confirm your RuinMyTrip email', $html, $text);
}

function rmt_mail_password_reset(string $to, string $username, string $link): array {
    $html = rmt_mail_layout(
        'Reset your password',
        '<p>Hi @' . e($username) . ' — use the button below to choose a new password. '
        . 'The link expires in 1 hour and can only be used once.</p>',
        'Reset password', $link
    );
    $text = "Hi @{$username},\n\nReset your RuinMyTrip password:\n{$link}\n\n"
          . "This link expires in 1 hour and can only be used once. If you didn't request it, ignore this email.";
    return rmt_mail_send($to, 'Reset your RuinMyTrip password', $html, $text);
}

/**
 * Stateless, non-expiring unsubscribe token: an HMAC of the user id, not a stored/burnable value
 * like auth_tokens. A digest link must keep working for months without a re-issued token every
 * send, and unsubscribing is not a security-sensitive action worth an expiry window.
 */
function rmt_unsubscribe_token(int $userId): string {
    return hash_hmac('sha256', (string) $userId, (string) cfg('security_salt'));
}

function rmt_unsubscribe_verify(int $userId, string $token): bool {
    return hash_equals(rmt_unsubscribe_token($userId), $token);
}

function rmt_unsubscribe_url(int $userId): string {
    return url('unsubscribe?u=' . $userId . '&t=' . rmt_unsubscribe_token($userId));
}

/**
 * Weekly activity digest. Only sent when there is real, non-zero activity to report — see
 * scripts/send_digest.php, which skips a user entirely rather than emailing an empty summary.
 * @param array{followers:int,follower_names:string[],votes:int,compliments:int,reviews:array} $activity
 */
function rmt_mail_digest(string $to, string $username, array $activity, string $unsubscribeUrl): array {
    $lines = [];
    if ($activity['followers'] > 0) {
        $names = $activity['follower_names'] ? ' (' . implode(', ', array_map(fn($n) => '@' . $n, $activity['follower_names'])) . ')' : '';
        $lines[] = '<li>' . (int) $activity['followers'] . ' new ' . ($activity['followers'] === 1 ? 'follower' : 'followers') . e($names) . '</li>';
    }
    if ($activity['votes'] > 0) {
        $lines[] = '<li>' . (int) $activity['votes'] . ' useful/funny/cool ' . ($activity['votes'] === 1 ? 'vote' : 'votes') . ' on your reviews</li>';
    }
    if ($activity['compliments'] > 0) {
        $lines[] = '<li>' . (int) $activity['compliments'] . ' ' . ($activity['compliments'] === 1 ? 'compliment' : 'compliments') . ' on your profile</li>';
    }
    $reviewsHtml = '';
    if ($activity['reviews']) {
        $items = array_map(fn($r) => '<li><a href="' . e($r['url']) . '">' . e($r['title']) . '</a> — ' . e($r['author']) . '</li>', $activity['reviews']);
        $reviewsHtml = '<p style="margin:20px 0 8px;font-weight:600">New from travelers you follow</p><ul style="margin:0;padding-left:20px">' . implode('', $items) . '</ul>';
    }

    $bodyHtml = '<p>Hi @' . e($username) . ' — here is what happened on RuinMyTrip this week.</p>'
              . '<ul style="margin:0;padding-left:20px">' . implode('', $lines) . '</ul>'
              . $reviewsHtml
              . '<p style="color:#8895a3;font-size:12px;margin:24px 0 0">'
              . '<a href="' . e($unsubscribeUrl) . '" style="color:#8895a3">Unsubscribe from these emails</a></p>';
    $html = rmt_mail_layout('Your week on RuinMyTrip', $bodyHtml);

    $text = "Hi @{$username},\n\nHere is what happened on RuinMyTrip this week.\n\n"
          . ($activity['followers'] > 0 ? $activity['followers'] . " new follower(s)\n" : '')
          . ($activity['votes'] > 0 ? $activity['votes'] . " vote(s) on your reviews\n" : '')
          . ($activity['compliments'] > 0 ? $activity['compliments'] . " compliment(s)\n" : '')
          . "\nUnsubscribe: {$unsubscribeUrl}";

    return rmt_mail_send($to, 'Your RuinMyTrip week', $html, $text);
}

/* ---------------------------------------------------------------- *
 * Travel-warning alerts.
 *
 * Two templates, both deliberately plain: the confirmation for a new email-only subscriber, and
 * the alert itself. The alert always names the destination in the subject line, always leads
 * with severity, and always carries a one-click unsubscribe — the sender in
 * scripts/send_alerts.php refuses to build a batch at all when there is nothing new, so an
 * empty "here is your update" email can never be produced.
 * ---------------------------------------------------------------- */

/** Double opt-in confirmation for an email-only alert subscription. */
function rmt_mail_alert_confirm(string $to, ?string $destName, string $link): array {
    $where = $destName ? e($destName) : 'your destinations';
    $html = rmt_mail_layout(
        'Confirm your travel warning alerts',
        '<p>Confirm this address and we will email you important new warnings for <strong>' . $where . '</strong>.</p>'
        . '<p style="color:#4a5a6a">Weekly at most, only warnings serious enough to change plans, '
        . 'and one click to stop at any time.</p>',
        'Confirm alerts', $link
    );
    $text = "Confirm your RuinMyTrip travel warning alerts for {$where}:\n{$link}\n\n"
          . "Weekly at most. One click to stop at any time.";
    return rmt_mail_send($to, 'Confirm your RuinMyTrip alerts', $html, $text);
}

/**
 * One alert email covering every new warning for one recipient.
 *
 * @param array<int,array{dest:string,title:string,severity:string,category:string,url:string,when:string}> $items
 */
function rmt_mail_warning_alert(string $to, string $greeting, array $items, string $unsubscribeUrl,
                                string $subjectHint = ''): array {
    if (!$items) return ['ok' => false, 'error' => 'nothing to send'];

    $rows = '';
    foreach ($items as $i) {
        $rows .= '<li style="margin-bottom:14px">'
              . '<a href="' . e($i['url']) . '" style="font-weight:600;color:#0f1b2d">' . e($i['title']) . '</a><br>'
              . '<span style="color:#667;font-size:13px">' . e($i['dest']) . ' · ' . e($i['category'])
              . ' · ' . e($i['severity']) . ($i['when'] !== '' ? ' · experienced ' . e($i['when']) : '')
              . '</span></li>';
    }
    $bodyHtml = '<p>' . e($greeting) . '</p>'
              . '<ul style="margin:16px 0;padding-left:20px">' . $rows . '</ul>'
              . '<p style="color:#4a5a6a;font-size:13px">These are traveler-submitted reports reviewed by our '
              . 'moderators. Unverified reports are labelled as such on the site.</p>'
              . '<p style="color:#8895a3;font-size:12px;margin:24px 0 0">'
              . '<a href="' . e($unsubscribeUrl) . '" style="color:#8895a3">Change how often you hear from us, or unsubscribe</a></p>';

    $n = count($items);
    $subject = $subjectHint !== ''
        ? $n . ' new travel ' . ($n === 1 ? 'warning' : 'warnings') . ' for ' . $subjectHint
        : $n . ' new travel ' . ($n === 1 ? 'warning' : 'warnings') . ' for your trips';

    $text = $greeting . "\n\n";
    foreach ($items as $i) {
        $text .= "- {$i['title']} ({$i['dest']}, {$i['category']}, {$i['severity']})\n  {$i['url']}\n";
    }
    $text .= "\nUnsubscribe or change frequency: {$unsubscribeUrl}";

    return rmt_mail_send($to, $subject, rmt_mail_layout('New warnings for your trip', $bodyHtml), $text);
}
