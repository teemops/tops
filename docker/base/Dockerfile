# syntax=docker/dockerfile:1

# Base image for the app container: teem/tops-base.
#
# This is everything docker/app/Dockerfile used to rebuild on every release —
# nginx, supervisor, and four compiled PHP extensions — for both amd64 and
# arm64, where the arm64 leg runs under QEMU in CI and compiling is slow.
#
# None of it changes release to release, so it lives here and is published
# separately. Rebuild only when its own inputs change: the PHP version, the
# package list, or the extension list.
#
# Publishing: .github/workflows/publish-base.yml (manual). See
# docs/processes/release.md, "Base image for the app container". Tags are
# immutable — bump the revision rather than republishing one.
#
# Build context is this directory; the image copies nothing in.

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
