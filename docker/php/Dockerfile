FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
 && docker-php-ext-install pdo_mysql zip intl bcmath pcntl opcache \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*

# Cache compiled PHP between runs (the project folder is shared from Windows, which is slow to read).
RUN mkdir -p /tmp/opcache && printf '%s\n' \
    'opcache.enable=1' 'opcache.enable_cli=1' 'opcache.file_cache=/tmp/opcache' \
    'opcache.validate_timestamps=1' 'opcache.revalidate_freq=2' \
    'realpath_cache_size=4096K' 'realpath_cache_ttl=600' \
    > /usr/local/etc/php/conf.d/zz-performance.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
