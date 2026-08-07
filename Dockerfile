# syntax=docker/dockerfile:1

FROM php:8.4-cli-alpine

# Postgres headers needed to build pdo_pgsql, then the extension itself
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && rm -rf /var/cache/apk/*

# Composer, copied from the official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# App source only (vendor, node_modules, .env, tests excluded via .dockerignore)
COPY --chown=www-data:www-data . .

# Production dependencies + autoloader
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --no-progress

# ---------- Entrypoint: migrate, then start Laravel ----------
COPY <<'EOF' /usr/local/bin/entrypoint
#!/bin/sh
set -e

cd /app

php artisan migrate --force

exec "$@"
EOF

RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
