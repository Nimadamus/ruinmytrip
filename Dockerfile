# RuinMyTrip — PHP 8.3 + Apache on Render (Docker web service).
FROM php:8.3-apache

# Postgres PDO driver + rewrite module.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && a2enmod rewrite \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Document root -> public/
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/public/uploads

# Render provides $PORT (default 10000). Apache is reconfigured to it at start.
EXPOSE 10000
CMD ["/usr/local/bin/entrypoint.sh"]
