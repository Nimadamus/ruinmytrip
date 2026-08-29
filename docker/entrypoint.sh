#!/bin/bash
# Bind Apache to Render's $PORT (default 10000), run an idempotent DB migrate, then serve.
set -e
PORT="${PORT:-10000}"

# Idempotent migrate: creates tables IF NOT EXISTS, seeds only when empty.
# Non-fatal so a transient DB issue never crash-loops the web tier (health stays up; /healthz reports db).
php /var/www/html/database/migrate.php || echo "entrypoint: migrate step failed, continuing (see /healthz)"

# Apply any committed place-enrichment proposal. Idempotent by construction: it only fills fields
# that are still empty, never overwrites a value already held, never creates a place, and skips a
# low-confidence match instead of writing it -- so a container restart re-runs it and writes
# nothing. Non-fatal for the same reason as the migrate above: a data problem in a proposal file
# belongs in the log, not in a crash loop.
PROPOSAL=/var/www/html/database/enrichment/proposal.json
if [ -f "$PROPOSAL" ]; then
  php /var/www/html/scripts/apply_place_enrichment.php --file "$PROPOSAL" --apply     || echo "entrypoint: place enrichment reported errors, continuing"
fi

# Give neighborhoods their canonical identity, then attach places whose raw area text matches a
# known alias exactly. Runs AFTER enrichment because enrichment is what writes the raw text in the
# first place. Idempotent in both halves: an area that already exists is updated rather than
# duplicated, an alias already recorded is skipped, and a place is only ever attached when its
# neighborhood_id is still null -- so a restart re-runs it and changes nothing.
NBSEED=/var/www/html/database/neighborhoods.json
if [ -f "$NBSEED" ]; then
  php /var/www/html/scripts/seed_neighborhoods.php --apply   || echo "entrypoint: neighborhood seed reported errors, continuing"
fi

# Keep the autocomplete index in step: fill any missing normalised names and seed destination
# aliases. Idempotent and fast, and it has to run AFTER enrichment, because enrichment is what
# may have just changed a place's name.
php /var/www/html/scripts/search_maintenance.php --quiet   || echo "entrypoint: search maintenance reported errors, continuing"

# Rebuild the sitemap last, because everything above can change what is indexable: a migration
# adds a page type, enrichment gives a place the address that makes it worth indexing, the
# neighborhood seed decides which areas have enough density. One query per entity group.
php /var/www/html/scripts/sitemap_build.php --quiet   || echo "entrypoint: sitemap build reported errors, continuing"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
