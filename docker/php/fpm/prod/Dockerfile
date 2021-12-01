FROM php:8.0.3-fpm-alpine

RUN set -ex \
  && apk --no-cache add \
    autoconf \
    bash    \
    bzip2-dev \
    curl-dev \
    libcurl \
    libpng-dev \
    libzip-dev \
    make \
    oniguruma-dev \
    shadow \
  && apk add --no-cache --update --virtual .phpize-deps $PHPIZE_DEPS

COPY fpm/fpm-pool.ini /usr/local/etc/php-fpm.d/www.conf
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

RUN curl -sS https://getcomposer.org/installer | php \
  && chmod +x composer.phar && mv composer.phar /usr/local/bin/composer

RUN mkdir /etc/crontab \
    && echo '*/15  *  *  *  *  /usr/local/bin/php /app/bin/console app:sync' > /etc/crontab/www-data

WORKDIR /app

CMD composer install && /usr/sbin/crond -b -d 8 -c /etc/crontab && php-fpm

EXPOSE 9000