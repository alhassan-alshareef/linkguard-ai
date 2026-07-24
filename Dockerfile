FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-calendar

FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libsqlite3-dev \
        libxml2-dev \
    && docker-php-ext-install calendar curl dom mbstring pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . .

RUN mkdir -p database storage/logs storage/rate-limits storage/sessions \
    && chown -R www-data:www-data database storage

USER www-data
ENV PORT=10000
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/router.php"]
