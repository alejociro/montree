# syntax=docker/dockerfile:1

# ============================================================
#  montree — imagen de produccion para Railway (una sola etapa)
#  PHP 8.4 + Apache + Node 22 en la misma imagen, porque el
#  build de Vite ejecuta `php artisan wayfinder:generate`
#  (plugin @laravel/vite-plugin-wayfinder) y necesita PHP.
# ============================================================
FROM php:8.4-apache-bookworm

# ---- 1) Librerias de sistema + extensiones PHP ----
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libonig-dev \
        libgmp-dev \
        unzip \
        git \
        ca-certificates \
        curl \
        gnupg; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gmp \
        intl \
        zip \
        exif \
        pcntl \
        pdo_mysql \
        gd; \
    php -m | grep -qi '^mbstring$' || docker-php-ext-install -j"$(nproc)" mbstring; \
    rm -rf /var/lib/apt/lists/*

# ---- 2) Node 22 (para compilar los assets con Vite) ----
RUN set -eux; \
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -; \
    apt-get install -y --no-install-recommends nodejs; \
    rm -rf /var/lib/apt/lists/*

# ---- 3) Composer ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- 4) Apache: docroot -> public, habilitar rewrite ----
RUN a2enmod rewrite \
    && rm -f /etc/apache2/sites-enabled/000-default.conf
COPY docker/vhost.conf /etc/apache2/sites-available/montree.conf
RUN a2ensite montree

WORKDIR /var/www/html

# ---- 5) Dependencias PHP (mejor cache). wayfinder queda en vendor ----
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-progress

# ---- 6) Codigo de la app ----
COPY . .

# ---- 7) Assets: npm ci + npm run build.
#      Vite ejecuta `php artisan wayfinder:generate`, que necesita un
#      APP_KEY para bootear artisan -> generamos un .env de build temporal.
#      Al final borramos node_modules y el .env (runtime usa las env de Railway).
RUN set -eux; \
    cp .env.example .env; \
    php artisan key:generate --force; \
    npm ci --no-audit --no-fund; \
    npm run build; \
    rm -rf node_modules .env

# ---- 8) Autoload optimizado + permisos ----
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

# ---- 9) Entrypoint (ajusta $PORT, storage:link, migrate --force, arranca Apache) ----
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
