FROM node:22-alpine AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources resources
RUN npm run build

FROM php:8.5.12-fpm-alpine

# No opcache here: since PHP 8.5 it is compiled into PHP itself, and
# docker-php-ext-install opcache fails ("can't stat 'modules/*'").
RUN apk add --no-cache \
    git curl libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev mysql-client fcgi \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath

# Bind-mounted source (dev) can be slow to stat/read — opcache is essential,
# not optional, or every request recompiles the whole framework from disk.
# validate_timestamps=1 + revalidate_freq=0 makes opcache re-stat every
# compiled file on every single request, which is what dev needs (live
# reload of host file edits without restarting the container) but is a real
# stability/perf liability in production under concurrent FPM workers — the
# image is immutable there, a deploy always means a fresh build, so prod
# passes OPCACHE_VALIDATE_TIMESTAMPS=0 as a build arg to skip it entirely.
ARG OPCACHE_VALIDATE_TIMESTAMPS=1
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo "opcache.validate_timestamps=${OPCACHE_VALIDATE_TIMESTAMPS}"; \
    echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# pm.max_requests (worker recycling) + catch_workers_output (surface a
# dying worker's error instead of a silent bare 500) — see the comments
# in this file for why. Filename sorts after www.conf so its directives
# for the [www] pool win.
COPY docker/php-fpm/www-overrides.conf /usr/local/etc/php-fpm.d/zz-www-overrides.conf

# No php.ini is ever activated in this base image — see the comments in
# error-logging.ini for why that means PHP-level fatals vanish with zero
# trace. conf.d/ (not php-fpm.d/) so it applies regardless of SAPI.
COPY docker/php-fpm/error-logging.ini /usr/local/etc/php/conf.d/zz-error-logging.ini
COPY docker/php-fpm/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=assets /var/www/html/public/build public/build
# This image's own folder for compiled Blade views (docker/entrypoint.sh): changes with every build of new code.
RUN date +%s > .build-id

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache

RUN chmod +x docker/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker/entrypoint.sh"]
