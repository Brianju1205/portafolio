FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && { echo 'upload_max_filesize=5M'; echo 'post_max_size=6M'; } > /usr/local/etc/php/conf.d/subidas.ini
