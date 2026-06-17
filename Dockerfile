# syntax=docker/dockerfile:1
# Stage 1: build PHP dependencies + JS assets
FROM php:8.5-cli-bookworm AS builder

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl git zip unzip libicu-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install intl bcmath zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN npm install && npm run build --workspace=resources/js/v2

# Stage 2: production runtime
FROM serversideup/php:8.5-fpm-nginx

ENV FIREFLY_III_PATH=/var/www/html \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_ERROR_REPORTING=24575 \
    SHOW_WELCOME_MESSAGE=false

USER root

RUN set -eux; \
    apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && install-php-extensions intl bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder --chown=www-data:www-data /app $FIREFLY_III_PATH

RUN mkdir -p \
    $FIREFLY_III_PATH/storage/app/public \
    $FIREFLY_III_PATH/storage/build \
    $FIREFLY_III_PATH/storage/database \
    $FIREFLY_III_PATH/storage/debugbar \
    $FIREFLY_III_PATH/storage/export \
    $FIREFLY_III_PATH/storage/framework/cache/data \
    $FIREFLY_III_PATH/storage/framework/sessions \
    $FIREFLY_III_PATH/storage/framework/testing \
    $FIREFLY_III_PATH/storage/framework/views/twig \
    $FIREFLY_III_PATH/storage/framework/views/v1 \
    $FIREFLY_III_PATH/storage/framework/views/v2 \
    $FIREFLY_III_PATH/storage/logs \
    $FIREFLY_III_PATH/storage/upload \
    && chown -R www-data:www-data $FIREFLY_III_PATH/storage

VOLUME $FIREFLY_III_PATH/storage/upload

USER www-data
