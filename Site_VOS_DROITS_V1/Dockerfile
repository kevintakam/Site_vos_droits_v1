# ---- Build stage (composer)
FROM composer:2 AS build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

# ---- Runtime stage (PHP-Apache)
FROM php:8.2-apache

# Install system deps & PHP extensions
RUN apt-get update && apt-get install -y \
        libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Apache document root -> /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Ecouter le port dynamique (Koyeb)
# Koyeb expose $PORT ; on remplace Listen 80 par Listen ${PORT}
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

# Copier code
WORKDIR /var/www/html
COPY --from=build /app ./

# Permissions cache/logs
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && find var -type d -exec chmod 775 {} \; \
    && find var -type f -exec chmod 664 {} \;

# Optimisations Symfony prod
ENV APP_ENV=prod \
    APP_DEBUG=0

# (Optionnel) préchauffage cache si variables déjà présentes au build
# RUN php bin/console cache:clear --env=prod --no-warmup

# Entrypoint Apache
CMD ["apache2-foreground"]
