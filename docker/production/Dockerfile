# syntax=docker/dockerfile:1

# Production image. One app image runs the queue worker, the scheduler and,
# for the optional web interface, PHP-FPM. The web interface also uses the
# nginx image built by the "web" stage. See compose.production.yaml.

FROM node:24-trixie-slim AS node

# The PHP runtime shared by every stage, with Node for the Bitwarden CLI.
FROM php:8.5-fpm-trixie AS php

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath intl opcache pcntl pdo_mysql zip

COPY --from=node /usr/local/bin/node /usr/local/bin/node

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/php/production.ini "$PHP_INI_DIR/conf.d/zz-production.ini"

WORKDIR /var/www/html

# Install dependencies, build the frontend, then drop everything only the
# build needs.
FROM php AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && npm run build \
    && npm prune --omit=dev --no-audit --no-fund \
    && rm -rf resources/js/routes resources/js/wayfinder

# The image for the worker, scheduler and PHP-FPM.
FROM php AS app

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/production/entrypoint.sh /usr/local/bin/entrypoint

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# nginx for the optional web interface, serving the built frontend itself and
# passing everything else to PHP-FPM.
FROM nginx:1.30-alpine AS web

COPY docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /var/www/html/public /var/www/html/public
