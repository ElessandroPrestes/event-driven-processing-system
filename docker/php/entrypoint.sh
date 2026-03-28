#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

environment_file=".env"
shared_runtime_dir="${SHARED_RUNTIME_DIR:-/var/run/eventflow}"
shared_app_key_file="${shared_runtime_dir}/app_key"
shared_app_key_lock_dir="${shared_app_key_file}.lock"

if [ -n "${APP_ENV:-}" ] && [ -f ".env.${APP_ENV}" ]; then
    environment_file=".env.${APP_ENV}"
fi

mkdir -p "${COMPOSER_HOME:-/tmp/composer}" \
    bootstrap/cache \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    "${shared_runtime_dir}"

if [ ! -f "${environment_file}" ]; then
    cp .env.example "${environment_file}"
fi

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php nao encontrado. Reconstrua a imagem Docker antes de iniciar o container." >&2
    exit 1
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

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' "${environment_file}"; then
    while ! mkdir "${shared_app_key_lock_dir}" 2>/dev/null; do
        if [ -f "${shared_app_key_file}" ]; then
            break
        fi

        sleep 1
    done

    if [ ! -f "${shared_app_key_file}" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "${shared_app_key_file}"
        chmod 600 "${shared_app_key_file}"
    fi

    export APP_KEY="$(cat "${shared_app_key_file}")"
    rmdir "${shared_app_key_lock_dir}" 2>/dev/null || true
fi

php artisan optimize:clear --ansi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --ansi
fi

exec "$@"
