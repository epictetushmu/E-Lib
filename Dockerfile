FROM php:8.1-apache

# Install dependencies and OpenSSL dev libraries
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libssl-dev \
    && docker-php-ext-install zip

# Install MongoDB extension (with OpenSSL support automatically included)
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Verify OpenSSL is enabled (it's usually built-in with PHP)
RUN php -m | grep -q openssl || (echo "OpenSSL extension is not available!" && exit 1)

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html


# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache document root
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
