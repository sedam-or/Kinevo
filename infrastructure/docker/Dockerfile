FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        zip \
        git \
        nodejs \
        npm \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        pcntl \
        bcmath \
        opcache \
    && docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY kinevo-entrypoint.sh /usr/local/bin/kinevo-entrypoint
RUN chmod +x /usr/local/bin/kinevo-entrypoint
ENTRYPOINT ["/usr/local/bin/kinevo-entrypoint"]

EXPOSE 9000
