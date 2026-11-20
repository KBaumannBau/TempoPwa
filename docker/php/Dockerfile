# PHP-FPM für die Tempo-API (Symfony + API Platform)
FROM php:8.4-fpm

WORKDIR /var/www/html

# Systempakete nur für die benötigten Erweiterungen und Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        libpq-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        opcache \
        bcmath \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Xdebug (Einstellungen in myPhp.ini)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y --no-install-recommends symfony-cli \
    && rm -rf /var/lib/apt/lists/*

# Benutzer www (UID/GID 1000) wie in den anderen Projekten
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

USER www

EXPOSE 9000
CMD ["php-fpm"]
