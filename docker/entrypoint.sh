#!/bin/sh
set -e

cd /var/www/html


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
