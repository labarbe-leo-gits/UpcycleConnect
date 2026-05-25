# Front Office PHP site Dockerfile
FROM php:8.2-cli AS builder
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/download/latest-stable/composer.phar -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev zip unzip libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo_mysql curl \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html

COPY . /var/www/html/
COPY --from=builder /app/vendor /var/www/html/vendor
COPY --from=builder /app/composer.lock /var/www/html/composer.lock

RUN mkdir -p /var/www/html/files/logs \
    && chown -R www-data:www-data /var/www/html/files \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
CMD ["apache2-foreground"]
