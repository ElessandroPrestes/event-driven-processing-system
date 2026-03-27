#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

mkdir -p "${COMPOSER_HOME:-/tmp/composer}" \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

until pg_isready \
    --host="${DB_HOST:-postgres}" \
    --port="${DB_PORT:-5432}" \
    --username="${DB_USERNAME:-eventflow}" \
    --dbname="${DB_DATABASE:-eventflow}" >/dev/null 2>&1; do
    echo "Waiting for PostgreSQL..."
    sleep 2
done

until redis-cli -h "${REDIS_HOST:-redis}" -p "${REDIS_PORT:-6379}" ping >/dev/null 2>&1; do
    echo "Waiting for Redis..."
    sleep 2
done

until nc -z "${RABBITMQ_HOST:-rabbitmq}" "${RABBITMQ_PORT:-5672}"; do
    echo "Waiting for RabbitMQ..."
    sleep 2
done

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --ansi --force
fi

php artisan optimize:clear --ansi
php artisan migrate --force --ansi

exec "$@"
