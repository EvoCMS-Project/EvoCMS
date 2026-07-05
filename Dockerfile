FROM php:8.1-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        gd \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/evocms.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .

RUN mkdir -p upload/avatars upload/thumbs logs backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 upload logs backups

# Persistent data (survives container rebuilds/redeploys)
VOLUME ["/var/www/html/.persistent", "/var/www/html/upload", "/var/www/html/logs", "/var/www/html/backups"]

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
