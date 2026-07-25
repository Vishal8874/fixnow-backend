FROM dunglas/frankenphp:php8.2 AS base

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions \
        bcmath \
        exif \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0


FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --no-progress \
    && composer clear-cache


FROM base AS app

COPY --chown=www-data:www-data --from=vendor /app /app
COPY Caddyfile /etc/caddy/Caddyfile

RUN mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chmod -R ug=rwX,o= /app/storage /app/bootstrap/cache

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-8080}/api/up" || exit 1

CMD ["sh", "-c", "php artisan config:cache && exec frankenphp run --config /etc/caddy/Caddyfile"]
