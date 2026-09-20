FROM php:8.2-apache

# Install unzip (needed by Composer) and zip extension dependencies, then PHP extensions
RUN apt-get update && apt-get install -y unzip libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer itself into the image (copied from the official Composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

# Install PHP dependencies (PHPMailer, etc.) — builds vendor/ inside the image
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p /var/www/html/uploads/admin_profiles \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

ENV PORT=10000
EXPOSE ${PORT}
ENTRYPOINT ["docker-entrypoint.sh"]
