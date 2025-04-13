FROM php:8.2-apache-bullseye-slim

# Set working directory
WORKDIR /var/www/html

# Configure Apache document root to point to the public directory
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Enable Apache mod_rewrite (needed for Laravel routing or clean URLs)
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
RUN docker-php-ext-install zip pdo pdo_mysql

# Configure Apache to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy only composer files first to optimize Docker layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

# Now copy the rest of the application code
COPY . .

# Re-run autoload in case app code added more classes
RUN composer dump-autoload

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose Apache port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
