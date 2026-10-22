# Reveille – local MantisBT test image.
# Builds a known-good MantisBT 2.x on a supported, patched PHP (8.2.35, fixes
# php2shell CVE-2026-109618/-109619). PHP 7.4 compatibility of the plugin is
# covered by the lint workflow.
FROM php:8.2.35-apache

ARG MANTIS_VERSION=2.26.1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget ca-certificates libpng-dev libzip-dev unzip \
    && docker-php-ext-install mysqli gd \
    && a2enmod rewrite \
    && wget -q "https://downloads.sourceforge.net/project/mantisbt/mantis-stable/${MANTIS_VERSION}/mantisbt-${MANTIS_VERSION}.tar.gz" -O /tmp/mantis.tar.gz \
    && tar xzf /tmp/mantis.tar.gz -C /var/www/html --strip-components=1 \
    && rm /tmp/mantis.tar.gz \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80
