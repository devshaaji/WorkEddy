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

# Raise FPM pool capacity – default is 5 which is exhausted when both workers
# poll concurrently alongside regular HTTP traffic.
# zz- prefix ensures this loads after www.conf and wins the override.
RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 5'; \
    echo 'pm.min_spare_servers = 5'; \
    echo 'pm.max_spare_servers = 15'; \
} > /usr/local/etc/php-fpm.d/zz-workeddy-pool.conf

WORKDIR /var/www/html

# Copy dependency manifests and install (separate layer for cache efficiency)
COPY composer.json ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Copy application source (.dockerignore excludes vendor/ so image vendor is preserved)
COPY . .

# Startup script handles retries + dependency install + migrations
COPY infrastructure/docker/api-entrypoint.sh /usr/local/bin/api-entrypoint.sh
RUN chmod +x /usr/local/bin/api-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/api-entrypoint.sh"]
CMD ["php-fpm", "-F"]
