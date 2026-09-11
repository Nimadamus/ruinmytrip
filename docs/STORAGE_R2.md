# Turning on R2

Photographs work today. They are stored as rows in the `media` table with the bytes in a `data`
column, served from `/media/{key}`, and everything on the site that shows a photo goes through
`rmt_media_url()`. Nothing here is waiting on R2 to function; R2 is where the bytes belong.

## The one step that cannot be done from a session

In the Cloudflare dashboard, switch R2 on for the account. Until somebody does, every API call to
create a bucket answers:

    10042 Please enable R2 through the Cloudflare Dashboard

That is the whole blocker. Everything on this side is written, and the signing is tested against
AWS's own published Signature Version 4 vector in `tests/storage_r2_test.php`.

## Then, in order

1. Create a bucket (any name, `ruinmytrip-media` is the obvious one) and an R2 API token with
   **Object Read and Write** on it.
2. Give the bucket a public hostname: either `r2.dev` for a first run, or a custom domain such as
   `media.ruinmytrip.com`. This is what browsers fetch from.
3. Set five environment variables on the Render service:

       STORAGE_DRIVER=r2
       R2_ACCOUNT_ID=<the account id>
       R2_ACCESS_KEY_ID=<from the token>
       R2_SECRET_ACCESS_KEY=<from the token>
       R2_BUCKET=<the bucket name>
       R2_PUBLIC_BASE_URL=https://<the public hostname>

4. Check the credentials without moving anything:

       php scripts/storage_migrate.php --check

   It writes a probe object, reads it back, deletes it, and reports what is where. If the
   credentials are wrong it says so and stops.

5. Move what is already stored, a batch at a time while the site stays up:

       php scripts/storage_migrate.php --limit=50
       php scripts/storage_migrate.php --all

6. Confirm:

       php scripts/storage_migrate.php --verify

## Why this is safe to run live

Nothing changes what a URL means. A `media` row records which driver holds its bytes, and
`rmt_storage_get()` reads from the bucket or from the column accordingly, so `/media/{key}` keeps
working for every key in either place during and after the move. Pages that render a photo call
`rmt_media_url()`, which points at the bucket's public host only once `STORAGE_DRIVER=r2` and
`R2_PUBLIC_BASE_URL` are both set.

Every object is verified by SHA-256 before its bytes are dropped: the migration writes to R2, reads
it back, compares the hash against the row, and only then clears `data`. An object that does not
match is deleted from the bucket and left exactly as it was in the database.

## What does not change

Uploads keep their existing posture, whichever driver is in use: size capped before decoding, type
sniffed from content rather than from the filename, dimension capped, and re-encoded through GD,
which is what strips EXIF. Travel photographs routinely carry GPS coordinates and this product
promises destination-level location only, so that re-encode is not an optimisation, it is the
privacy guarantee.
