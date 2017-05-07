FROM php:7.1-apache

WORKDIR /usr/local/vufind
EXPOSE 80

RUN apt-get update && apt-get install -y git zip unzip libmcrypt-dev libldap2-dev libpng12-dev libxslt-dev libicu-dev && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap && \
    docker-php-ext-install -j$(nproc) json gd && \
    docker-php-ext-install mcrypt xsl intl mysqli && \
    a2enmod rewrite && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY docker-vufind-entrypoint /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-vufind-entrypoint
COPY . /usr/local/vufind

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction

ENTRYPOINT ["docker-vufind-entrypoint"]
CMD ["apache2-foreground"]