#!/bin/bash
# Bind Apache to Render's $PORT (default 10000), run an idempotent DB migrate, then serve.
set -e
PORT="${PORT:-10000}"

# Idempotent migrate: creates tables IF NOT EXISTS, seeds only when empty.
# Non-fatal so a transient DB issue never crash-loops the web tier (health stays up; /healthz reports db).
php /var/www/html/database/migrate.php || echo "entrypoint: migrate step failed, continuing (see /healthz)"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
