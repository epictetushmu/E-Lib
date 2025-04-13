FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libssl-dev pkg-config libzip-dev libpng-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Optional: Install Composer (if needed)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy your application code
COPY . /var/www/html

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Expose Apache
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
