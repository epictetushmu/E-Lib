# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    curl \
    openssl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install zip pdo_mysql exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Install Imagick
RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Install MongoDB extension with version control
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install cURL extension for better fallback options
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && docker-php-ext-enable curl

# Verify OpenSSL is enabled (it's usually built-in with PHP)
RUN php -m | grep -q openssl || (echo "OpenSSL extension is not available!" && exit 1)

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache document root and set ServerName
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install


# Create directories for runtime files
RUN mkdir -p certificates storage/logs public/uploads cache \
    && chmod -R 777 certificates storage public/uploads cache


# Copy the MongoDB certificate setup script
COPY setup-mongodb-cert.php docker-entrypoint.php ./

# Run the certificate setup during build for basic setup
RUN php setup-mongodb-cert.php

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Environment variable indicating we're in Docker
ENV DOCKER_ENV=true

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Use our custom entrypoint
CMD ["php", "docker-entrypoint.php"]
