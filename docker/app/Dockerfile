FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install bcmath mbstring pcntl pdo_pgsql xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

CMD ["php", "-v"]
