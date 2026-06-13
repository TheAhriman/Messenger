#!/bin/sh
set -e

wait_for() {
    until nc -z "$1" "$2"; do
        echo "Waiting for $3 ($1:$2)..."
        sleep 1
    done
}

wait_for "${DB_HOST:-postgres}" "${DB_PORT:-5432}" PostgreSQL
wait_for "${RABBITMQ_HOST:-rabbitmq}" "${RABBITMQ_PORT:-5672}" RabbitMQ
wait_for "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" Redis

# Миграции применяет только один контейнер (API), чтобы воркеры не гонялись за ними.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
    php artisan l5-swagger:generate
fi

exec "$@"