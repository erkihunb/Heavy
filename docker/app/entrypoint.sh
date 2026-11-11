#!/bin/sh
# Starts every container built from the "app" image (PHP-FPM, queue worker,
# scheduler, one-off artisan commands). In production the configuration,
# routes, events and views are cached first, with this container's environment.
set -e

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan optimize --no-interaction >/dev/null
fi

exec "$@"
