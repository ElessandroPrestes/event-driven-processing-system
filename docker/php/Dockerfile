FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libpq-dev \
        libsqlite3-dev \
        librabbitmq-dev \
        libzip-dev \
        netcat-openbsd \
        postgresql-client \
        redis-tools \
        unzip \
    && pecl install amqp pcov redis \
    && docker-php-ext-enable amqp pcov redis \
    && docker-php-ext-install bcmath intl pcntl pdo_pgsql pdo_sqlite pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install sockets

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . /var/www/html
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX,g+rwX /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data
