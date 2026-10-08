#!/bin/sh
# Runs before the web server, worker or scheduler starts.
set -e

# Cache settings and routes for speed (rebuilt on every start, so an update always takes effect).
php artisan config:cache
php artisan route:cache

if [ "$1" = "frankenphp" ]; then
    # Only the web container updates the database and the shared page cache, so the containers never clash.
    php artisan migrate --force
    php artisan view:cache
fi

exec "$@"
