# Production image: FrankenPHP (Caddy web server + PHP in one) serves the app and gets HTTPS by itself.
# The same image also runs the queue worker and the scheduler (different command in docker-compose.prod.yml).

# 1. Screens (CSS/JS), built once here so the server needs no Node at runtime.
FROM node:24-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY app/Livewire ./app/Livewire
RUN npm run build

# 2. The app.
FROM dunglas/frankenphp:1-php8.4-bookworm

# gd: images in quotation PDFs (Dompdf) and the company stamp upload.
RUN install-php-extensions pdo_mysql zip intl bcmath pcntl opcache gd \
 && apt-get update && apt-get install -y --no-install-recommends ca-certificates unzip \
 && rm -rf /var/lib/apt/lists/* \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf '%s\n' 'opcache.enable=1' 'opcache.enable_cli=1' 'opcache.validate_timestamps=0' \
      'memory_limit=256M' 'upload_max_filesize=12M' 'post_max_size=16M' 'expose_php=Off' \
      > "$PHP_INI_DIR/conf.d/zz-production.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# Dependencies first (cached between builds unless composer.lock changes).
# The MongoDB driver is only for the one-time import from the old tms-v2 collector, so it is not installed here.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-mongodb

COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --ignore-platform-req=ext-mongodb \
 && php artisan package:discover --ansi \
 && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chmod +x docker/prod/start.sh

ENTRYPOINT ["docker/prod/start.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
