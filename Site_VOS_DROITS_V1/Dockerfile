FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libpng-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) pdo_mysql intl zip opcache \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 80
