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

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
