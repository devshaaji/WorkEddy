FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP upload/post limits for video files (200 MB) + safe defaults
RUN echo 'upload_max_filesize = 200M' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'post_max_size = 210M'        >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'memory_limit = 256M'         >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'max_execution_time = 300'    >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'display_errors = Off'        >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'log_errors = On'             >> /usr/local/etc/php/conf.d/workeddy.ini

WORKDIR /var/www/html

# Copy dependency manifests and install (separate layer for cache efficiency)
COPY composer.json ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Copy application source (.dockerignore excludes vendor/ so image vendor is preserved)
COPY . .

CMD ["php-fpm", "-F"]