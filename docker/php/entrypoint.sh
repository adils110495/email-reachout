#!/bin/bash
set -e

# Wait for MySQL to be ready (extra safety on top of Docker healthcheck)
echo "[entrypoint] Waiting for MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] MySQL is ready."

# Generate app key if missing
if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run migrations
echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

# Clear & rebuild caches for production
if [ "${APP_ENV}" = "production" ]; then
    echo "[entrypoint] Caching config/routes/views for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "[entrypoint] Starting PHP-FPM..."
exec php-fpm
