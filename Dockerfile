# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: install PHP dependencies (cached separately from app code so an
# app-only change doesn't reinstall every vendor package)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor-builder
WORKDIR /app
COPY composer.json composer.lock* ./
# --no-scripts/--no-autoloader: the post-autoload-dump hooks (package:discover,
# filament:upgrade) need the full app present (routes, config, bootstrap/app.php),
# which isn't copied into this layer yet — they run for real in the runtime
# stage below, once the complete source tree exists alongside vendor/.
# --ignore-platform-reqs: the official composer:2 image ships a bare PHP CLI
# with no ext-intl/ext-gd (filament/support and phpoffice/phpspreadsheet both
# require them) — this stage only downloads/extracts package files, it never
# executes app code, and the actual runtime image below does have both
# extensions installed, so validating against this throwaway image's PHP is a
# false positive, not a real compatibility check.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 2: build frontend assets (Tailwind v4 + Filament theme.css + app.js).
# Depends on vendor-builder, not parallel with it: resources/css/filament/
# theme.css does `@import '../../../vendor/filament/filament/resources/css/theme.css'`
# (Filament ships its own theme as source CSS, not just a prebuilt file) and
# Tailwind's `@source` directives scan app/Filament + resources/views/filament
# for utility classes used in our own Blade views (see that file's own
# comment) — the frontend build genuinely needs both vendor/ and the PHP/Blade
# source tree present, not just resources/ and package.json.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS node-builder
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY --from=vendor-builder /app/vendor ./vendor
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: runtime image — php-fpm + nginx in one container (Render runs
# exactly one container per service, so both processes are supervised
# together rather than split across two services).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        gettext \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        zlib-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
        intl \
        zip \
        gd \
        opcache
    # Deliberately not `apk del`-ing the *-dev packages afterward: gd.so
    # links against libpng16.so.16 at runtime (not just compile time), and
    # `apk del libpng-dev` pulled its runtime dependency libpng out with it
    # as an unused transitive package — a real crash caught by actually
    # running the built image, not something apparent from the Dockerfile
    # alone ("Unable to load dynamic library 'gd': ... libpng16.so.16: No
    # such file or directory"). The few MB saved isn't worth that fragility.

# Recommended production opcache settings (CLAUDE.md: this is a compliance
# app — correctness over dev-speed conveniences like opcache.validate_timestamps).
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /var/www/html

# The php:8.4-fpm-alpine base has no composer binary at all — only the
# throwaway composer:2 image used for vendor-builder does. Copying the
# binary itself (not reinstalling vendor/ again) is the standard pattern for
# needing composer once more after the app source exists, e.g. dump-autoload
# with the real classmap.
COPY --from=vendor-builder /usr/bin/composer /usr/bin/composer

COPY --from=vendor-builder /app/vendor ./vendor
COPY . .
COPY --from=node-builder /app/public/build ./public/build

# Build-time asset publishing only (icons/JS/CSS Filament ships in vendor/) —
# never config:cache/route:cache here: those bake in whatever env vars exist
# at BUILD time, but Render only injects the real DB host/password/APP_KEY at
# CONTAINER START. Caching stale/placeholder config would silently override
# the real runtime environment. See docker/entrypoint.sh for the runtime
# caching step instead.
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf.template /etc/nginx/templates/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render assigns the actual public port via $PORT at runtime (not always
# 8080) — the entrypoint templates nginx.conf to that value. 8080 here is
# only the documented default for `docker run` outside Render.
EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
