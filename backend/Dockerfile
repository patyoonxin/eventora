FROM php:8.2-apache

# 1. Install system utilities, zip tools (needed by Composer), and MySQL extensions
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# 2. Install Composer globally inside the container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Copy your project code into the server directory
COPY . /var/www/html

# 4. Run Composer to generate the vendor folder without development packages
RUN composer install --no-dev --optimize-autoloader

# 5. Fix Apache document root pointing to public folder
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
