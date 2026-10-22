# Reveille – local MantisBT test image.
# Builds a known-good MantisBT 2.x on a supported, patched PHP (8.4.27; PHP 8.2
# reaches security EOL on 2026-12-31). Syntax of the plugin on other PHP
# versions is covered by the lint workflow.
FROM php:8.4.27-apache

ARG MANTIS_VERSION=2.28.4
# SHA-256 from the official mantisbt-2.28.4.tar.gz.digests file on SourceForge.
# Update together with MANTIS_VERSION.
ARG MANTIS_SHA256=a400bd2957154baad3412130031e893d5c18cf1ed1cfb38b77ee64afd1ed2a33

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget ca-certificates libpng-dev libzip-dev unzip \
    && docker-php-ext-install mysqli gd \
    && a2enmod rewrite \
    && wget -q "https://downloads.sourceforge.net/project/mantisbt/mantis-stable/${MANTIS_VERSION}/mantisbt-${MANTIS_VERSION}.tar.gz" -O /tmp/mantis.tar.gz \
    && echo "${MANTIS_SHA256}  /tmp/mantis.tar.gz" | sha256sum -c - \
    && tar xzf /tmp/mantis.tar.gz -C /var/www/html --strip-components=1 \
    && rm /tmp/mantis.tar.gz \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80
