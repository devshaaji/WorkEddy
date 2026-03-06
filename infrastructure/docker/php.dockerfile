FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency manifests and install (separate layer for cache efficiency)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Copy application source (.dockerignore excludes vendor/ so image vendor is preserved)
COPY . .

CMD ["php-fpm", "-F"]