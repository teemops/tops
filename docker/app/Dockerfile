# syntax=docker/dockerfile:1

# Build context is the repo root. Run docker/scripts/prepare-build.sh first so
# app/vendor and app/public/build exist on the host.

# nginx, supervisor and the compiled PHP extensions live in the base image, so
# they are pulled rather than rebuilt on every release. Built from
# docker/base/Dockerfile; pinned so a base rebuild never silently changes what
# this produces. Bumping the pin and publishing the new base tag happen in the
# same PR — see docs/processes/release.md.
FROM teem/tops-base:php8.3-1

COPY docker/app/nginx.conf /etc/nginx/nginx.conf
COPY docker/app/default-site.conf /etc/nginx/conf.d/default.conf
COPY docker/app/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/app/worker-supervisord.conf /etc/supervisor/worker-supervisord.conf
COPY docker/app/php-overrides.ini /usr/local/etc/php/conf.d/99-teemops.ini
COPY docker/app/php-fpm-clear-env.conf /usr/local/etc/php-fpm.d/zz-clear-env.conf
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
