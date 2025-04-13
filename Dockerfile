FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Configure Apache document root to point to the public directory
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-available/000-default.conf

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libssl-dev \
    pkg-config \
    libzip-dev \
    libpng-dev

# Install PHP extensions
RUN pecl install mongodb && docker-php-ext-enable mongodb
RUN docker-php-ext-install zip

# Configure Apache for .htaccess usage
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction || \
    (composer init --name=makis/e-lib --no-interaction && \
     composer config autoload.psr-4.App\\\\ App/ && \
     composer dump-autoload)

# Set permissions
RUN chown -R www-data:www-data .

EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
