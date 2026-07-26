#!/usr/bin/env bash
set -e

# ------------------------------------------------------------
# Entrypoint de montree en Railway
# ------------------------------------------------------------

# Railway inyecta $PORT en runtime. Apache debe escuchar ahi.
PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/montree.conf

# Descubrir paquetes ahora que el .env real ya esta presente
php artisan package:discover --ansi || true

# Permisos del volumen montado en storage/app/public (si existe)
chown -R www-data:www-data storage bootstrap/cache || true

# Enlace simbolico public/storage -> storage/app/public
php artisan storage:link || true

# Migraciones (idempotente: solo corre las pendientes).
# No abortamos el arranque si falla, para poder inspeccionar con la app viva.
php artisan migrate --force || echo "[entrypoint] AVISO: migrate fallo, revisar logs/DB"

# Limpiar cualquier cache stale del build
php artisan optimize:clear || true

exec apache2-foreground
