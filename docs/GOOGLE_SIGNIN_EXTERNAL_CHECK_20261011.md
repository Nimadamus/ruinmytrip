# Google sign in: outside account check (procedure)

Purpose: prove that a Google account that is not one of our own developer or test accounts can sign
in on production, without creating test data. Nothing here changes configuration.

## Already verified (2026-10-11)

- OAuth consent screen: publishing status **In production**, user type **External** (Google Cloud
  console, read 2026-10-11). An app still in "Testing" would refuse outside accounts; this one does
  not have that limit.
- `GET https://ruinmytrip.com/auth/google` answers 302 to `accounts.google.com/o/oauth2/v2/auth` with
  `redirect_uri=https://ruinmytrip.com/auth/google/callback`, scopes `openid email profile`, a state
  and a nonce, and `prompt=select_account` (checked 2026-10-11 09:08 PDT; client id and state not
  recorded).

## The check (needs the owner, about 3 minutes)

The flow has a safe stopping point: a new Google user is sent to `/join/google` ("choose your
username") and **no account exists until that form is submitted**. Stopping there proves the whole
Google side works and leaves nothing in the database.

1. Claude opens a new tab at `https://ruinmytrip.com/login` in the owner's Chrome (only when the
   owner says go) and notes the time.
2. The owner clicks **Continue with Google** and picks a Google account that has **never** been added
   as a test user or developer on the Google Cloud project (a personal Gmail, or a friend at the
   desk with their consent).
3. Google shows the consent screen. Expected: app name RuinMyTrip, no "unverified app" wall that
   blocks continuing. Screenshot it.
4. Expected landing: `https://ruinmytrip.com/join/google` with the username form.
   **Stop here.** Do not submit the form unless the owner wants that account to be a real member.
5. Claude closes the tab.

## What Claude checks at the same time (read only)

- Render logs for the window: `GET /auth/google` 302, `GET /auth/google/callback` 302 to
  `/join/google`, `GET /join/google` 200, no PHP warnings or errors.
- Analytics: a `join_view` event with source `google` in the window, and no `join_failure`.
- Users table count unchanged (read through the app's admin pages, not the database).

## Pass, fail, and what each means

| Result | Meaning | Next step |
|---|---|---|
| Lands on `/join/google` | Outside accounts can sign in | Done. Record the time and screenshots |
| Google says the app is in testing or access is blocked | Consent screen setting is not what the console showed | Owner checks the consent screen; nothing on the site changes |
| Google error `redirect_uri_mismatch` | Authorized redirect URI differs from `https://ruinmytrip.com/auth/google/callback` | Owner adds that exact URI in the Cloud console |
| Back on the site with "Google sign in did not finish" | State or cookie problem; the log shows `join_failure` with a reason | Claude reads the reason and fixes it on a branch |

If anything has to be turned off quickly: removing `GOOGLE_CLIENT_ID` or `GOOGLE_CLIENT_SECRET` from
the Render environment hides every Google button (`rmt_google_enabled()`); email sign up keeps
working. That is a production change and needs the owner's go.

Not part of this check: writing a review as a guest and finishing with Google on production. That
would put a real review on the site and needs separate approval.
