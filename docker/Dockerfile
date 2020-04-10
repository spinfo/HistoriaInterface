FROM wordpress:5.4.0-php7.3-apache

RUN apt-get update && apt-get install -y libzip-dev && pecl install zlib zip

RUN docker-php-ext-install calendar
RUN docker-php-ext-install zip
