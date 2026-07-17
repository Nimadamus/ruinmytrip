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
    return function_exists('curl_init')
        ? rmt_mail_post_curl($key, $json)
        : rmt_mail_post_stream($key, $json);
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
