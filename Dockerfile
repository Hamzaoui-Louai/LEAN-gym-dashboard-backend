# syntax=docker/dockerfile:1

# ---------- Build stage: install composer dependencies ----------
FROM composer:2 AS build

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

COPY . .
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

# ---------- Runtime stage: PHP-FPM + Nginx ----------
FROM php:8.4-fpm-alpine AS runtime

# System dependencies + PHP extensions required by Laravel & Resend
RUN apk add --no-cache \
        curl \
        nginx \
        sqlite \
        supervisor \
        oniguruma-dev \
        sqlite-dev \
        curl-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_sqlite \
        mbstring \
        curl \
        opcache \
    && rm -rf /var/cache/apk/*

# Non-root user (php-fpm runs workers as this user)
RUN set -eux; \
    if ! id www-data >/dev/null 2>&1; then \
        addgroup -S www-data; \
        adduser -S -G www-data www-data; \
    fi

# Copy the application from the build stage
COPY --from=build --chown=www-data:www-data /app /app
WORKDIR /app

# ---------- Nginx configuration ----------
COPY <<'EOF' /etc/nginx/nginx.conf
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    client_max_body_size 20m;

    gzip on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        application/json
        application/javascript
        application/xml
        image/svg+xml;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent"';
    access_log /dev/stdout main;
    error_log /dev/stderr warn;

    server {
        listen 80;
        server_name _;
        root /app/public;
        index index.php;

        location / {
            try_files $uri /index.php$is_args$args;
        }

        location ~ \.php$ {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_param PHP_VALUE "upload_max_filesize=20M post_max_size=20M";
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
EOF

# ---------- Supervisor configuration (nginx + php-fpm + queue) ----------
COPY <<'EOF' /etc/supervisord.conf
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
startretries=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
startretries=5
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:queue]
command=php artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# ---------- PHP production configuration ----------
COPY <<'EOF' /usr/local/etc/php/conf.d/zz-app.ini
[opcache]
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0

[PHP]
expose_php=Off
memory_limit=256M
max_execution_time=120
upload_max_filesize=20M
post_max_size=20M
EOF

# ---------- Entrypoint ----------
COPY <<'EOF' /usr/local/bin/entrypoint
#!/bin/sh
set -e

cd /app

echo "==> Ensuring writable storage/bootstrap directories"
mkdir -p storage/framework/{cache,views}
chown -R www-data:www-data storage bootstrap/cache database

echo "==> Ensuring SQLite database file exists"
touch database/database.sqlite

if [ -z "${APP_KEY}" ]; then
    echo "==> WARNING: APP_KEY is not set. Generating an ephemeral key."
    echo "    Sessions/encrypted data will be invalidated on the next restart."
    echo "    Set APP_KEY (e.g. 'base64:...') in your deployment environment."
    export APP_KEY="$(php artisan key:generate --show)"
fi

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Linking storage"
php artisan storage:link --force || true

echo "==> Clearing stale configuration cache"
php artisan config:clear || true

echo "==> Starting services"
exec "$@"
EOF

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /run/nginx \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database

# Persist SQLite database + storage between restarts
VOLUME ["/app/database", "/app/storage"]

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
