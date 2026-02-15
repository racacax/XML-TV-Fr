FROM php:8.4-cli

RUN apt update && apt install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    libicu-dev \
 && docker-php-ext-install \
    mbstring \
    curl \
    zip \
    pcntl \
    intl \
    xml \
 && pecl install pcov \
 && docker-php-ext-enable pcov \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --no-progress --prefer-dist

CMD ["php", "manager.php", "export"]