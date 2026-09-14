#!/bin/sh
set -e

if [ ! -d "vendor" ]; then
    echo "[entrypoint] Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f ".env" ]; then
    echo "[entrypoint] Copying .env.example to .env..."
    cp .env.example .env
    php artisan key:generate --ansi
    php artisan jwt:secret --ansi --force
fi

if [ "$CONTAINER_ROLE" = "app" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force
fi

exec "$@"
