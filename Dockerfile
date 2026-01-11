FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install simplexml

EXPOSE 80
