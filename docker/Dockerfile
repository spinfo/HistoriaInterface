FROM wordpress:4.9-php7.1-apache

RUN apt-get update && apt-get install -y libzip-dev && pecl install zlib zip

RUN docker-php-ext-install calendar
RUN docker-php-ext-install zip