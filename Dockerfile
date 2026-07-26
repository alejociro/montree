# syntax=docker/dockerfile:1

# ============================================================
#  montree — imagen de produccion para Railway
#  Etapa 1: build de assets (Vite/Vue) con Node 22
#  Etapa 2: runtime PHP 8.4 + Apache con todas las extensiones
# ============================================================

# ---------- Etapa 1: assets front-end ----------
# Base Debian (glibc): el package-lock fija binarios nativos
# linux-x64-gnu (rollup / tailwind oxide). NO usar Alpine (musl).
FROM node:22-bookworm-slim AS assets
WORKDIR /app

# Instalar dependencias con el lockfile (incluye binarios linux-x64-gnu)
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

# Copiar el resto y compilar
COPY . .
RUN npm run build


# ---------- Etapa 2: runtime PHP + Apache ----------
FROM php:8.4-apache-bookworm AS app

# Librerias de sistema necesarias para compilar las extensiones PHP
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
        git; \
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
    # mbstring viene incluido en la imagen oficial; instalar solo si faltara
    php -m | grep -qi '^mbstring$' || docker-php-ext-install -j"$(nproc)" mbstring; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Composer (desde la imagen oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: habilitar rewrite y usar nuestro vhost (docroot -> /public)
RUN a2enmod rewrite \
    && rm -f /etc/apache2/sites-enabled/000-default.conf
COPY docker/vhost.conf /etc/apache2/sites-available/montree.conf
RUN a2ensite montree

WORKDIR /var/www/html

# Instalar dependencias PHP primero (mejor cache de capas).
# --no-scripts evita correr package:discover en build (sin .env);
# se ejecuta en runtime desde el entrypoint.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-progress

# Codigo de la app
COPY . .

# Assets ya compilados desde la etapa 1
COPY --from=assets /app/public/build ./public/build

# Regenerar autoload optimizado y ajustar permisos
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint: ajusta puerto ($PORT de Railway), migra y arranca Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Railway inyecta $PORT; exponemos un default informativo
EXPOSE 8080

ENTRYPOINT ["entrypoint"]
