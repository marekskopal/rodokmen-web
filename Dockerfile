# rodokmen-web – private family tree website, PHP 8.5 + Apache.
# The data are not part of the image: they live in the var/ volume and are uploaded with bin/rodokmen-sync.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.5-apache

# OPcache is part of PHP since 8.5 (no docker-php-ext-install needed).
RUN a2enmod rewrite headers remoteip \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=64'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=0'; \
    } > /usr/local/etc/php/conf.d/site.ini \
    && { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'RemoteIPHeader X-Forwarded-For'; \
        echo 'RemoteIPInternalProxy 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16'; \
    } > /etc/apache2/conf-available/site.conf \
    && a2enconf site \
    && sed -ri 's!/var/www/html!/var/www/site/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!<Directory /var/www/>!<Directory /var/www/site/public/>!; /<Directory \/var\/www\/site\/public\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/site
COPY --from=vendor /app/vendor ./vendor
COPY public ./public
COPY src ./src
COPY templates ./templates

RUN mkdir -p var/cache && chown -R www-data:www-data var

ENV SITE_TRUST_PROXY=1
EXPOSE 80

# var/ is usually a bind mount owned by the host user: let Apache write into it (releases, uploads, cache).
CMD ["sh", "-c", "mkdir -p var/cache && chown www-data:www-data var var/cache && exec apache2-foreground"]
