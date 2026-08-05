#!/bin/sh

cd /var/www/html

php artisan config:clear --no-interaction 2>/dev/null || true
php artisan cache:clear --no-interaction 2>/dev/null || true

php artisan storage:link --no-interaction 2>/dev/null || true

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running database migrations..."
    (php artisan migrate --force --no-interaction 2>/dev/null || true) &
fi

# Run seeder in background to prevent container boot delay
(php artisan db:seed --class=ProductionUserSeeder --force --no-interaction 2>/dev/null || true) &

if [ "${RUN_SEEDER}" = "true" ]; then
    echo "Running database seeders..."
    (php artisan db:seed --force --no-interaction 2>/dev/null || true) &
fi

# Pre-compile Laravel route and config caches for maximum response speed (10ms)
php artisan config:cache --no-interaction 2>/dev/null || true
php artisan route:cache --no-interaction 2>/dev/null || true
php artisan event:cache --no-interaction 2>/dev/null || true

exec "$@"
