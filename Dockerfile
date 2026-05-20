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
FROM php:8.5-cli

LABEL maintainer="spentaru-archive"

# Defaults that make the image easy to run locally.
# You can override any of these at runtime with Docker Compose `environment`
# or `env_file`.
ENV APP_ENV=local \
    APP_DEBUG=true \
    APP_URL=http://localhost:8000 \
    APP_TIMEZONE=Asia/Jakarta \
    APP_LOCALE=en \
    APP_FALLBACK_LOCALE=en \
    APP_FAKER_LOCALE=en_US \
    LOG_CHANNEL=stderr \
    LOG_STACK=single \
    LOG_LEVEL=warning \
    CACHE_STORE=file \
    SESSION_DRIVER=file \
    SESSION_LIFETIME=120 \
    SESSION_SECURE_COOKIE=false \
    SESSION_EXPIRE_ON_CLOSE=false \
    QUEUE_CONNECTION=sync \
    FILESYSTEM_DISK=local \
    DB_CONNECTION=mysql \
    DB_HOST=127.0.0.1 \
    DB_PORT=3306 \
    DB_DATABASE=spentaru_archive_db \
    DB_USERNAME=root \
    REDIS_CLIENT=predis \
    REDIS_HOST=127.0.0.1 \
    REDIS_PORT=6379 \
    SCOUT_DRIVER=database \
    AI_SERVICE_BASE_URL=http://localhost:5000 \
    AI_SERVICE_TIMEOUT=30 \
    AI_TOOL_ACCESS_HEADER=X-AI-Tool-Key \
    SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000,localhost:5173,127.0.0.1:5173 \
    CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000,http://localhost:5173,http://127.0.0.1:5173 \
    RUN_MIGRATIONS=false \
    RUN_SEEDER=false

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libfreetype6-dev \
    libjpeg-dev \
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
    CMD curl -fsS http://127.0.0.1:8000/up >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
