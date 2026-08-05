# syntax=docker/dockerfile:1

# ------------------------------------------------------------
# Stage 1: Install PHP dependencies
# ------------------------------------------------------------
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-autoloader \
    --no-ansi \
    --no-scripts

# ------------------------------------------------------------
# Stage 2: Runtime image
# ------------------------------------------------------------
FROM php:8.4-cli

LABEL maintainer="spentaru-archive"

# Defaults that make the image easy to run locally.
# You can override any of these at runtime with Docker Compose `environment`
# or `env_file`.


RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libfreetype6-dev \
    libjpeg-dev \
    libpq-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        mbstring \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        opcache \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer-deps /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=composer-deps /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:${PORT:-8000}/up >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD sh -c "php -S 0.0.0.0:\${PORT:-8000} -t public"
