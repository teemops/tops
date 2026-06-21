# syntax=docker/dockerfile:1

# Build context is the repo root. Run docker/scripts/prepare-build.sh first so
# app/vendor and app/public/build exist on the host.

FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        curl \
    && docker-php-ext-install \
        bcmath \
        opcache \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/app/nginx.conf /etc/nginx/nginx.conf
COPY docker/app/default-site.conf /etc/nginx/conf.d/default.conf
COPY docker/app/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/app/worker-supervisord.conf /etc/supervisor/worker-supervisord.conf
COPY docker/app/php-overrides.ini /usr/local/etc/php/conf.d/99-teemops.ini
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/app/worker-entrypoint.sh /usr/local/bin/worker-entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/worker-entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

COPY app/ .

# Belt-and-suspenders: never ship host .env into the image (see .dockerignore)
RUN rm -f .env .env.backup .env.production \
    && find . -maxdepth 1 -name '.env.*' ! -name '.env.example' -delete

RUN test -d vendor || (echo "ERROR: app/vendor missing. Run: ./docker/scripts/prepare-build.sh" && exit 1) \
    && test -d public/build || (echo "ERROR: app/public/build missing. Run: ./docker/scripts/prepare-build.sh" && exit 1) \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
