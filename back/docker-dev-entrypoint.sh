#!/bin/sh
# Entrypoint para el modo dev (docker-compose.override.yml).
#
# Espera a la base, prepara storage/, limpia caches y arranca
# `php artisan serve`. Como los fuentes vienen por bind mount, cada
# cambio en app/, routes/ o config/ se ve en el siguiente request
# sin rebuild.

set -e

echo "[dev] Preparando storage y bootstrap..."
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

echo "[dev] Esperando a la base de datos..."
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT")) ? 0 : 1);' 2>/dev/null; do
    sleep 2
done

echo "[dev] Limpiando caches de Laravel..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

echo "[dev] Listo. Iniciando php artisan serve en 0.0.0.0:8000..."
exec php artisan serve --host=0.0.0.0 --port=8000
