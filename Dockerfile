# Neuro Codez — production image (PHP-FPM + nginx in one container)
#
# One container rather than two services because Render's free tier gives you a
# single web service. nginx serves static files directly and hands PHP to FPM
# over a local socket.

# ---------------------------------------------------------------- asset build
FROM node:24-alpine AS assets

WORKDIR /app

# Copy manifests first so a code-only change reuses the npm layer.
COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ------------------------------------------------------------ composer vendor
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# --no-scripts: artisan is not present yet, and package discovery needs the
# full app. It runs in the final stage instead.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# -------------------------------------------------------------------- runtime
FROM php:8.4-fpm-alpine

# Runtime libraries and build headers are installed SEPARATELY, and only the
# headers are removed afterwards.
#
# Removing the -dev packages also removes the runtime libs they pulled in, which
# leaves gd.so, intl.so and zip.so on disk but unloadable ("libpng16.so.16: No
# such file or directory"). PHP then starts with a warning rather than an error,
# so the failure is silent — gd missing would simply drop the logo from every
# invoice PDF. The build must not be trusted without checking `php -m`.
RUN apk add --no-cache \
        nginx supervisor \
        icu-libs libzip libpng libjpeg-turbo freetype oniguruma \
    && apk add --no-cache --virtual .build-deps \
        icu-dev libzip-dev oniguruma-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && apk del --no-network .build-deps \
    # Fail the build here rather than ship an image that warns on every request.
    && php -m | grep -q '^gd$' \
    && php -m | grep -q '^intl$' \
    && php -m | grep -q '^zip$'

# OPcache matters on a small instance: without it every request recompiles the
# whole framework.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=25M'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/99-neuro.ini

WORKDIR /var/www/html

# The composer BINARY, not just the vendor directory. The runtime image is a
# bare PHP image with no composer on PATH, and the autoloader still has to be
# regenerated below now that the full source is present.
COPY --from=vendor /usr/bin/composer /usr/bin/composer

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# Regenerate the autoloader now the full source is present, and run the package
# discovery that was skipped in the vendor stage.
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && rm -f /usr/bin/composer

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
