<?php
declare(strict_types=1);

/**
 * Image upload + storage.
 *
 * Driver is chosen by env STORAGE_DRIVER: 'pg' (default) stores bytes in the media table;
 * 'r2' would push to an S3-compatible bucket. The rest of the app only ever sees a storage key
 * and a URL, so swapping drivers is a config change rather than a rewrite.
 *
 * SECURITY / PRIVACY POSTURE — every upload is:
 *   1. Size-capped BEFORE decoding (a decompression bomb is rejected on bytes, not pixels).
 *   2. Type-sniffed from CONTENT via finfo, never from the filename or the client's Content-Type.
 *      An attacker renaming shell.php to shell.jpg gets rejected here.
 *   3. Dimension-capped, then RE-ENCODED through GD. Re-encoding is what strips EXIF — travel
 *      photos routinely carry GPS coordinates and this product promises destination-level
 *      location only. It also guarantees the stored bytes are a real image, because anything
 *      that is not decodable never survives the round trip.
 *   4. Served from a dedicated route with a fixed Content-Type and nosniff, so a stored file can
 *      never be interpreted as HTML or script by a browser.
 */

const RMT_UPLOAD_MAX_BYTES  = 8 * 1024 * 1024;   // 8MB per file, checked pre-decode
const RMT_UPLOAD_MAX_DIM    = 2000;              // px, longest edge after resize
const RMT_UPLOAD_MAX_PIXELS = 50000000;          // 50MP decode ceiling (bomb guard)
const RMT_UPLOAD_MIMES      = ['image/jpeg', 'image/png', 'image/webp'];

function rmt_storage_driver(): string { return getenv('STORAGE_DRIVER') ?: 'pg'; }

/** Public URL for a stored key. */
function rmt_media_url(string $key): string {
    if (rmt_storage_driver() === 'r2' && ($base = getenv('R2_PUBLIC_BASE_URL'))) {
        return rtrim($base, '/') . '/' . $key;
    }
    return url('media/' . $key);
}

/**
 * Validate + normalise one uploaded file.
 * @param array $file one entry from $_FILES
 * @return array{ok:bool, error:string, key:string, url:string, mime:string, w:int, h:int, bytes:int}
 */
function rmt_upload_image(array $file, int $ownerId): array {
    $fail = static fn(string $m) => ['ok' => false, 'error' => $m, 'key' => '', 'url' => '',
                                     'mime' => '', 'w' => 0, 'h' => 0, 'bytes' => 0];

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) return $fail('No file was uploaded.');
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return $fail('That photo is too large (8MB max).');
    if ($err !== UPLOAD_ERR_OK) return $fail('That upload did not complete. Try again.');

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return $fail('That upload could not be read.');

    $bytes = (int) filesize($tmp);
    if ($bytes <= 0) return $fail('That file is empty.');
    if ($bytes > RMT_UPLOAD_MAX_BYTES) return $fail('That photo is too large (8MB max).');

    // Sniff the real type from content. The filename and the browser-supplied type are attacker
    // controlled and are never consulted.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    if (!in_array($mime, RMT_UPLOAD_MIMES, true)) {
        return $fail('Photos must be JPEG, PNG or WebP.');
    }

    $info = @getimagesize($tmp);
    if ($info === false) return $fail('That file is not a readable image.');
    [$w, $h] = [(int) $info[0], (int) $info[1]];
    if ($w < 1 || $h < 1) return $fail('That image has no dimensions.');
    if ($w * $h > RMT_UPLOAD_MAX_PIXELS) return $fail('That image is too large to process.');

    if (!function_exists('imagecreatefromstring')) {
        return $fail('Image processing is unavailable on the server right now.');
    }

    $raw = file_get_contents($tmp);
    if ($raw === false) return $fail('That upload could not be read.');
    $src = @imagecreatefromstring($raw);
    unset($raw);
    if ($src === false) return $fail('That image could not be decoded.');

    // Downscale to the longest-edge cap, preserving aspect ratio.
    $scale = min(1.0, RMT_UPLOAD_MAX_DIM / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    // Re-encode. The output contains ONLY pixels — no EXIF, no GPS, no maker notes, no
    // embedded payload from the original file.
    ob_start();
    $okEncode = match ($mime) {
        'image/png'  => imagepng($dst, null, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($dst, null, 82) : imagejpeg($dst, null, 82),
        default      => imagejpeg($dst, null, 82),
    };
    $out = (string) ob_get_clean();
    imagedestroy($dst);
    if (!$okEncode || $out === '') return $fail('That image could not be processed.');

    // WebP falls back to JPEG when the build lacks webp support; record what we actually wrote.
    if ($mime === 'image/webp' && !function_exists('imagewebp')) $mime = 'image/jpeg';

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'jpg';
    $key = bin2hex(random_bytes(16)) . '.' . $ext;
    $sha = hash('sha256', $out);

    $stored = rmt_storage_put($key, $out, $mime, $ownerId, $nw, $nh, $sha);
    if (!$stored) return $fail('That photo could not be saved.');

    return ['ok' => true, 'error' => '', 'key' => $key, 'url' => rmt_media_url($key),
            'mime' => $mime, 'w' => $nw, 'h' => $nh, 'bytes' => strlen($out)];
}

/** Persist bytes under $key. */
function rmt_storage_put(string $key, string $bytes, string $mime, int $ownerId,
                         int $w, int $h, string $sha): bool {
    $driver = rmt_storage_driver();
    if ($driver === 'r2') {
        // Intentionally not implemented: no R2 credentials exist yet. Fail loudly rather than
        // silently dropping a user's photo.
        error_log('[rmt_storage] STORAGE_DRIVER=r2 but the R2 driver is not implemented');
        return false;
    }
    $st = db()->prepare('INSERT INTO media (storage_key, driver, owner_id, mime, bytes, width, height, sha256, data, created_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?)');
    $st->bindValue(1, $key);
    $st->bindValue(2, 'pg');
    $st->bindValue(3, $ownerId ?: null, $ownerId ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $st->bindValue(4, $mime);
    $st->bindValue(5, strlen($bytes), PDO::PARAM_INT);
    $st->bindValue(6, $w, PDO::PARAM_INT);
    $st->bindValue(7, $h, PDO::PARAM_INT);
    $st->bindValue(8, $sha);
    $st->bindValue(9, $bytes, PDO::PARAM_LOB);
    $st->bindValue(10, date('Y-m-d H:i:s'));
    return $st->execute();
}

/**
 * Fetch a stored file.
 * @return array{mime:string, bytes:string}|null
 */
function rmt_storage_get(string $key): ?array {
    $row = q_one('SELECT mime, data FROM media WHERE storage_key = ? AND driver = ?', [$key, 'pg']);
    if (!$row) return null;
    $data = $row['data'];
    // pgsql returns bytea as a stream resource; sqlite returns a string.
    if (is_resource($data)) $data = stream_get_contents($data);
    return ['mime' => (string) $row['mime'], 'bytes' => (string) $data];
}

/** Remove a stored file. */
function rmt_storage_delete(string $key): void {
    db()->prepare('DELETE FROM media WHERE storage_key = ?')->execute([$key]);
}
