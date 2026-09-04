FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p /var/www/html/uploads/admin_profiles \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

ENV PORT=10000
EXPOSE ${PORT}
ENTRYPOINT ["docker-entrypoint.sh"]
