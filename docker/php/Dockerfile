FROM php:8.4-cli

# gd: images in quotation PDFs (Dompdf) and the company stamp upload.
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install pdo_mysql zip intl bcmath pcntl opcache gd \
 && pecl install pcov && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*

# MongoDB driver — only used by the one-time import from the old tms-v2 collector.
RUN apt-get update && apt-get install -y --no-install-recommends libssl-dev pkg-config \
 && pecl install mongodb && docker-php-ext-enable mongodb \
 && rm -rf /var/lib/apt/lists/*

# Cache compiled PHP between runs (the project folder is shared from Windows, which is slow to read).
RUN mkdir -p /tmp/opcache && printf '%s\n' \
    'opcache.enable=1' 'opcache.enable_cli=1' 'opcache.file_cache=/tmp/opcache' \
    'opcache.validate_timestamps=1' 'opcache.revalidate_freq=2' \
    'realpath_cache_size=4096K' 'realpath_cache_ttl=600' \
    > /usr/local/etc/php/conf.d/zz-performance.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
