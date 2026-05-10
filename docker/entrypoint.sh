#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    echo "No .env found, copying .env.example"
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "APP_KEY is empty, generating a new key"
    php artisan key:generate --no-interaction --force 2>/dev/null || true
fi

php artisan config:clear --no-interaction
php artisan cache:clear --no-interaction 2>/dev/null || true

php artisan storage:link --no-interaction 2>/dev/null || true

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDER}" = "true" ]; then
    echo "Running database seeders..."
    php artisan db:seed --force --no-interaction
fi

exec "$@"
