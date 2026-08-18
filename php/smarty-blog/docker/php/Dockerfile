FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y libzip-dev unzip git \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install

COPY . .

RUN mkdir -p templates_c cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 templates_c cache

EXPOSE 80
