FROM php:7.1-apache

WORKDIR /usr/local/vufind
ENV APP_HOME=/usr/local/vufind2 \
VUFIND_HTTPD_CONF=local/httpd-vufind.conf
EXPOSE 80 443
COPY . /usr/local/vufind

RUN apt-get update && apt-get install -y git zip unzip libmcrypt-dev libldap2-dev libpng12-dev libxslt-dev libicu-dev && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap && \
    docker-php-ext-install -j$(nproc) json gd && \
    docker-php-ext-install mcrypt xsl intl mysqli && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN ln -s /usr/local/vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf && \
    a2enmod rewrite && \ 
    chown -R www-data:www-data /usr/local/vufind/local/cache /usr/local/vufind/local/config


RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction