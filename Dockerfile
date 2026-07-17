# RuinMyTrip — PHP 8.3 + Apache on Render (Docker web service).
FROM php:8.3-apache

# Postgres PDO driver + rewrite module + GD.
# GD is required, not optional: uploaded photos are re-encoded through it, which is what strips
# EXIF metadata. Travel photos routinely carry GPS coordinates, and this product promises
# destination-level location only — publishing raw EXIF would leak a user's exact position.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpq-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo pdo_pgsql gd \
 && a2enmod rewrite \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Uploads are re-encoded in memory; allow enough headroom for a large photo.
RUN { \
      echo 'upload_max_filesize = 10M'; \
      echo 'post_max_size = 12M'; \
      echo 'memory_limit = 256M'; \
    } > /usr/local/etc/php/conf.d/rmt-uploads.ini

# Document root -> public/
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html/public/uploads

# Render provides $PORT (default 10000). Apache is reconfigured to it at start.
EXPOSE 10000
CMD ["/usr/local/bin/entrypoint.sh"]
