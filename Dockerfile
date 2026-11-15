# rodokmen-web – private family tree website, PHP 8.5 + Apache.
# The data are not part of the image: they live in the var/ volume and are uploaded with bin/rodokmen-sync.
# Base images are pinned to exact versions, so that a rebuild gives the same image; raise them deliberately.
FROM composer:2.11.2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

# Front-end libraries (Leaflet) from package.json / pnpm-lock.yaml → public/assets/vendor (without the test tools).
FROM node:24.22.3-alpine AS assets
WORKDIR /app
RUN npm install --global --no-fund --no-audit pnpm@12.17.0
COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --prod
COPY bin/build-assets.mjs ./bin/
RUN pnpm build

FROM mlocati/php-extension-installer:2.13.2 AS php-extension-installer

FROM php:8.5.12-apache

# GD with JPEG and WebP for the WebP thumbnails (src/Image). install-php-extensions installs the libraries it needs
# and removes the build dependencies afterwards.
COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd \
    && php -r 'exit(imagetypes() & IMG_WEBP && imagetypes() & IMG_JPG ? 0 : 1);'

# OPcache is part of PHP since 8.5 (no docker-php-ext-install needed).
RUN a2enmod rewrite headers \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=64'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=0'; \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/var/www/site/log/php-error.log'; \
    } > /usr/local/etc/php/conf.d/site.ini \
    && { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo '# The client address is resolved by PHP (SITE_TRUSTED_PROXIES), not by mod_remoteip: PHP has to see'; \
        echo '# the proxy as REMOTE_ADDR to believe its X-Forwarded-Proto. The access log shows the forwarded chain.'; \
        printf '%s\n' 'LogFormat "%h [%{X-Forwarded-For}i] %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined'; \
        echo '# The HEALTHCHECK every 30 s would fill the container log; its requests are not logged.'; \
        echo 'SetEnvIf Request_URI "^/health$" dontlog'; \
    } > /etc/apache2/conf-available/site.conf \
    && a2enconf site \
    && sed -ri 's!/var/www/html!/var/www/site/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#^(\s*CustomLog .*access\.log combined)$#\1 env=!dontlog#' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!<Directory /var/www/>!<Directory /var/www/site/public/>!; /<Directory \/var\/www\/site\/public\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/site
COPY --from=vendor /app/vendor ./vendor
COPY public ./public
COPY --from=assets /app/public/assets/vendor ./public/assets/vendor
COPY src ./src
COPY templates ./templates

RUN mkdir -p var/cache log && chown -R www-data:www-data var log

# The reverse proxy reaches the container from the Docker network (a private address).
ENV SITE_TRUSTED_PROXIES=private
EXPOSE 80

# /health answers without a login; from inside the container the HTTPS redirect does not apply (localhost).
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --start-interval=2s --retries=3 \
    CMD curl -fsS http://localhost/health > /dev/null || exit 1

# var/ and log/ are usually bind mounts owned by the host user: let Apache write into them
# (data releases, uploads, cache; Tracy's logs and php-error.log).
CMD ["sh", "-c", "mkdir -p var/cache log && chown www-data:www-data var var/cache log && exec apache2-foreground"]
