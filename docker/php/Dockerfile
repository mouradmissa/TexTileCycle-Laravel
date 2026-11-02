FROM php:8.2-fpm-bookworm

ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=local \
    APP_KEY=base64:dGV4dGlsZWN5Y2xlLWRldm9wcy1sb2NhbC1rZXkhISE= \
    APP_DEBUG=true \
    DB_CONNECTION=mongodb \
    MONGODB_URI=mongodb://mongodb:27017 \
    MONGODB_DATABASE=textilecycle \
    ATLAS_IP_SYNC=false \
    CACHE_DRIVER=file \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log

RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        ca-certificates \
        curl \
        git \
        libcurl4-openssl-dev \
        libssl-dev \
        nginx \
        pkg-config \
        supervisor \
        unzip \
    && pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install opcache \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /var/log/supervisor \
    && printf '[www]\nclear_env = no\ncatch_workers_output = yes\n' > /usr/local/etc/php-fpm.d/zz-env.conf \
    && printf 'upload_max_filesize=12M\npost_max_size=14M\nmemory_limit=512M\n' > /usr/local/etc/php/conf.d/textilecycle.ini \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .
COPY docker/php/nginx.conf /etc/nginx/templates/default.conf.template
COPY docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/php/entrypoint.sh /entrypoint.sh

RUN sed -i 's/\r$//' /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && composer dump-autoload --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
