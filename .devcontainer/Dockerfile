FROM php:8.4-fpm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt update && apt install -y git zip unzip
