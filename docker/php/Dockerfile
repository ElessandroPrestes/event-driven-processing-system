FROM php:8.4-cli-bookworm

WORKDIR /var/www/html

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
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && docker-php-ext-install bcmath intl pcntl pdo_pgsql pdo_sqlite pgsql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh
