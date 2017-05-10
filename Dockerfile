FROM php:7.1-apache
MAINTAINER "demian.katz@villanova.edu"

WORKDIR /usr/local/vufind
EXPOSE 80

ENV VUFIND_INSTALL_SOLR=no

RUN apt-get update && apt-get install -y --no-install-recommends \
        git=1:2.1.4-* unzip=6.0-* \
        libmcrypt-dev=2.5.8-* \
        libldap2-dev=2.4.40+dfsg-* \
        libpng12-dev=1.2.50-* \
        libicu-dev=52.1-* \
        libxslt1-dev=1.1.28-* && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install -j"$(nproc)" ldap json gd mcrypt xsl intl mysqli && \
    a2enmod rewrite && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY docker-vufind-entrypoint /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-vufind-entrypoint
COPY . /usr/local/vufind/

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction

ENTRYPOINT ["docker-vufind-entrypoint"]
CMD ["apache2-foreground"]