FROM php:8.2-zts-alpine AS builder
RUN apk add autoconf
RUN apk add --update libzip-dev
RUN apk --update add gcc make g++ zlib-dev
RUN pecl install parallel
RUN docker-php-ext-install zip
RUN echo "extension=parallel.so" >> /usr/local/etc/php/conf.d/docker-php-ext-zip.ini
RUN composer install