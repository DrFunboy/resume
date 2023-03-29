FROM php:7.4-apache
WORKDIR /app
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip
RUN apt update && \
    apt upgrade && \
    apt install -y libmagickwand-dev --no-install-recommends && \
    pecl install imagick && docker-php-ext-enable imagick && \
    rm -rf /var/lib/apt/lists/*
ADD policy.xml /etc/ImageMagick-6/policy.xml
COPY ./index.php .
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer require aws/aws-sdk-php
CMD [ "php", "index.php", "exit"]
