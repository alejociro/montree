#!/usr/bin/env bash
set -e

# ------------------------------------------------------------
# Entrypoint de montree en Railway
#
# Una misma imagen, tres roles segun CONTAINER_ROLE:
#   web        (defecto) Apache + migraciones + permisos.
#   worker     cola: cobros de comision (listener ShouldQueue), correos.
#   scheduler  tareas programadas (recordatorios, vencimiento de reservas
#              pendientes, consulta de pagos cada 10 min).
# Sin worker ni scheduler, esas tareas nunca se ejecutan en produccion.
# ------------------------------------------------------------

ROLE="${CONTAINER_ROLE:-web}"

# Caches de arranque (config, rutas, vistas, eventos). Seguro: no hay env()
# fuera de config/ ni closures en las rutas. Se regeneran en cada arranque.
warm_caches() {
    php artisan optimize:clear >/dev/null 2>&1 || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
}

if [ "$ROLE" = "worker" ]; then
    php artisan package:discover --ansi || true
    warm_caches
    # --max-time: reinicia el proceso cada hora para liberar memoria; Railway
    # lo vuelve a levantar por la politica de reinicio.
    exec php artisan queue:work --tries=3 --backoff=10 --max-time=3600 --sleep=3
fi

if [ "$ROLE" = "scheduler" ]; then
    php artisan package:discover --ansi || true
    warm_caches
    exec php artisan schedule:work
fi

# Asegurar UN SOLO MPM (prefork, requerido por mod_php) en cada arranque.
# Evita el fallo "AH00534: apache2: More than one MPM loaded" pase lo que
# pase con el cache de build. Se ejecuta en runtime, sin cache posible.
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

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

# Catalogo de permisos y matriz rol->permiso (idempotente, sin datos demo).
# Sin esto, role_has_permissions queda vacio tras un despliegue nuevo y el menu
# del panel se muestra en blanco para todas las agencias.
php artisan montree:sync-permissions 2>&1 \
    || echo "[entrypoint] AVISO: sync-permissions fallo, revisar logs/DB"

# Limpiar cualquier cache stale del build y calentar las de produccion
warm_caches || echo "[entrypoint] AVISO: no se pudieron generar las caches"

exec apache2-foreground
