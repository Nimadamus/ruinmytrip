# RuinMyTrip — Production Deployment (Namecheap cPanel)

Target hosting account (confirmed on file): **cPanel `chatnpm` @ `server125.web-hosting.com`**, shared IP **`198.187.29.27`**. This plan already runs addon domains (chatgptdisaster.com + mlbprops/betmlbprops/betmodel), so **addon domains are supported**. Adding `ruinmytrip.com` is isolated — it does not touch any other domain.

> DNS is NOT changed until the temporary deployment is proven healthy (see step 8).

## 0. Directory layout (app OUTSIDE web root)
```
/home/chatnpm/ruinmytrip.com/
├── app/         (config.php lives here — gitignored, server-only)
├── views/
├── database/    (schema.mysql.sql; no sqlite in prod)
└── public/      ← addon-domain DOCUMENT ROOT (index.php, .htaccess, assets, uploads)
```

## 1. Required PHP (cPanel > Select PHP Version)
- **PHP 8.1+** (built & tested on 8.3).
- Extensions: **pdo**, **pdo_mysql**, **mbstring**, **openssl**, **json**, **session**, **filter**, **fileinfo**. All are standard on Namecheap; enable pdo_mysql + mbstring if not already ticked. (No SQLite needed in production.)

## 2. Create the addon domain
cPanel > **Domains** > Create A New Domain:
- Domain: `ruinmytrip.com`
- **Uncheck** "share document root"; set Document Root: `ruinmytrip.com/public`
- This creates `/home/chatnpm/ruinmytrip.com/public`.

## 3. Create the database (least privilege)
cPanel > **MySQL Databases**:
- Database: `chatnpm_ruinmytrip`
- User: `chatnpm_rmt` + a strong generated password.
- Add user to DB with **ALL PRIVILEGES** on that DB only (no global perms).

## 4. Import schema
cPanel > **phpMyAdmin** > select `chatnpm_ruinmytrip` > Import > upload `database/schema.mysql.sql`.
(Or run `scripts/import_schema.php` once from the browser, then delete it — see that file's header.)

## 5. Deploy files
Upload the repo to `/home/chatnpm/ruinmytrip.com/` (FTP `deploy@chatgptdisaster.com` has full home access), keeping the layout in step 0. Do **not** upload `app/config.php` from your machine; create it on the server in step 6.

## 6. Production config (server-only, never committed)
Copy `app/config.production.sample.php` → `app/config.php` on the server and set:
- `mysql.name/user/pass` from step 3
- `security_salt` = 64 random hex chars (`head -c32 /dev/urandom | xxd -p`)
`app/config.php` is gitignored and must contain the only copy of the DB password.

## 7. Writable directories
- `public/uploads/` → **0755** (needs write for future media). Everything else read-only (0644 files / 0755 dirs).

## 8. Test BEFORE DNS (temporary access)
- Namecheap shared hosting has no per-account temp URL by default; use a **local hosts-file** override:
  - Add to your machine's hosts file: `198.187.29.27  ruinmytrip.com www.ruinmytrip.com`
  - Then run `scripts/smoke_test.sh https://ruinmytrip.com` (accept the not-yet-matched cert with `-k` while testing).
- Confirm: DB connectivity (homepage renders seed/live data), all routes 200, 404 works, register/login works.
- Remove the hosts entry when done.

## 9. AutoSSL / HTTPS
cPanel > **SSL/TLS Status** > select `ruinmytrip.com` + `www` > **Run AutoSSL**. Let's Encrypt issues once the A record resolves to `198.187.29.27` (after DNS cutover) — for pre-DNS testing use `-k`.

## 10. Cron
**None required** for the MVP. (Future: a nightly `sitemap` warmer or notification digest — not needed now.)

## 11. Backup & rollback
- **Before deploy:** cPanel > phpMyAdmin > Export the new DB (empty is fine) and keep a copy of any prior `app/config.php`.
- **Code rollback:** `git checkout <prev-hash>` and re-upload, or redeploy the previous commit. Every commit is reversible.
- **DB rollback:** re-import the pre-change SQL dump.
- **Full undo of go-live:** delete the addon domain (removes routing) and the DB — no other domain is affected.

## 12. DNS (only after step 8 passes — separate approval)
Current live (Namecheap BasicDNS) — records to **PRESERVE**: SPF TXT and MX `eforward1-5.registrar-servers.com`.
| Action | Type | Host | Value |
|--------|------|------|-------|
| EDIT | A | @ | `198.187.29.27` (replaces parking `162.255.119.126`) |
| EDIT | CNAME | www | `ruinmytrip.com.` (replaces `parkingpage.namecheap.com`) |
| KEEP | TXT | @ | `v=spf1 include:spf.efwd.registrar-servers.com ~all` |
| KEEP | MX | @ | eforward1-5.registrar-servers.com (10/10/10/15/20) |
| KEEP | any DKIM/verification TXT if present |
Propagation: TTL ~30–60 min. Run AutoSSL after A resolves.
