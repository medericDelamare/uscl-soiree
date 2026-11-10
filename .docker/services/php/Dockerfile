FROM php:8.4-apache-bookworm

ENV TZ=Europe/Paris

WORKDIR /var/www

ADD conf/vhost.conf /etc/apache2/sites-enabled/000-default.conf
ADD conf/php.ini /usr/local/etc/php/conf.d/override.ini

RUN a2enmod rewrite headers

RUN apt-get update && apt-get install -y \
    git unzip \
    libicu-dev \
    libzip-dev \
    libfreetype-dev libjpeg62-turbo-dev libpng-dev \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) pdo_mysql intl zip gd opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG DEV_UID=1000
ARG DEV_GID=1000
RUN usermod -u ${DEV_UID} www-data && groupmod -g ${DEV_GID} www-data && chown -R www-data:www-data /var/www
