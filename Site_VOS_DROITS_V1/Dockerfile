# ---- Stage 1 : Build (PHP CLI + Composer) ----
FROM php:8.3-cli AS build
WORKDIR /app

# Installer Composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Installer dépendances système et extensions PHP nécessaires à Composer
RUN apt-get update && apt-get install -y \
      git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd zip

# Copier les fichiers Composer
COPY composer.json composer.lock ./

# Installer les dépendances PHP (sans dev)
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction --no-progress

# Copier le reste du projet
COPY . .

# Optimiser l’autoloader
RUN composer dump-autoload --classmap-authoritative --no-dev


# ---- Stage 2 : Runtime (PHP + Apache) ----
FROM php:8.3-apache
WORKDIR /var/www/html

# Installer dépendances système et extensions PHP nécessaires à Symfony
RUN apt-get update && apt-get install -y \
      libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev git unzip \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl zip opcache pdo_mysql \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd \
  && a2enmod rewrite headers \
  && rm -rf /var/lib/apt/lists/*

# Définir le DocumentRoot sur /public + autoriser les .htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}>!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && printf '\n<Directory "${APACHE_DOCUMENT_ROOT}">\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Copier le code compilé depuis le stage build
COPY --from=build /app ./

# Créer les dossiers cache/logs et ajuster les droits
RUN mkdir -p var/cache var/log \
  && chown -R www-data:www-data var \
  && find var -type d -exec chmod 775 {} \; \
  && find var -type f -exec chmod 664 {} \;

# Variables d'environnement Symfony
ENV APP_ENV=prod \
    APP_DEBUG=0

EXPOSE 80
CMD ["apache2-foreground"]
