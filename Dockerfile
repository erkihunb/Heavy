# syntax=docker/dockerfile:1.7
#
# Production image (spec §16). Four targets:
#   app  — PHP-FPM with the application; also runs the queue worker and the scheduler
#   web  — nginx serving public/ and passing PHP requests to "app"
#   ssr  — Node drawing each first page on the server (used when INERTIA_SSR_ENABLED=true)
#   build — internal: Composer packages and the front-end build
#
# See compose.production.yaml and the "Deploying" section of README.md.

# ---- PHP with the extensions the application needs ----
FROM php:8.4-fpm-alpine AS php-base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# gd: the logo upload is re-encoded, and the web pages get a small WebP copy of it.
RUN install-php-extensions pdo_mysql intl zip bcmath opcache redis pcntl gd \
    && apk add --no-cache fcgi tzdata

ENV TZ=UTC
WORKDIR /var/www/html

# ---- build: production Composer packages and the Vite build ----
FROM php-base AS build

RUN apk add --no-cache nodejs npm git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

# The Vite build runs `php artisan wayfinder:generate`, so PHP must be able to boot
# (no database is needed: settings fall back to their defaults).
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && npm run build:ssr \
    && rm -rf node_modules tests

# ---- app: PHP-FPM ----
FROM php-base AS app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- web: nginx ----
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public

# ---- ssr: Node with the server-rendering bundle ----
# The bundle carries its packages (vite.config.ts, ssr.noExternal), so nothing
# is installed here. "app" sends each first page to http://ssr:13714/render and
# sends the page without the pre-drawn HTML when this container does not answer.
FROM node:22-alpine AS ssr

ENV NODE_ENV=production
WORKDIR /app

# package.json only says that the bundle is an ES module.
COPY --from=build /var/www/html/package.json ./package.json
COPY --from=build /var/www/html/bootstrap/ssr ./bootstrap/ssr

USER node
EXPOSE 13714

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1:13714/health || exit 1

CMD ["node", "bootstrap/ssr/ssr.js"]
