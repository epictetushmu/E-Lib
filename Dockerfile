FROM php:8.1-apache

# Install dependencies and OpenSSL dev libraries
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    libssl-dev \
    && docker-php-ext-install zip

# Install Imagick and its dependencies
RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Install Ghostscript (required for PDF processing)
RUN apt-get update && apt-get install -y \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Ensure proper PDF policy for Imagick
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
        sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml; \
    fi

# Install MongoDB extension (with OpenSSL support automatically included)
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Verify OpenSSL is enabled (it's usually built-in with PHP)
RUN php -m | grep -q openssl || (echo "OpenSSL extension is not available!" && exit 1)

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache document root
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install

# Copy the MongoDB certificate setup script
COPY setup-mongodb-cert.php docker-entrypoint.php ./

# Run the certificate setup during build for basic setup
RUN php setup-mongodb-cert.php

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Create directories for runtime files
RUN mkdir -p certificates storage/logs public/uploads cache \
    && chmod -R 777 certificates storage public/uploads cache

# Create directories for asset uploads
RUN mkdir -p /var/www/public/assets/uploads/pdfs /var/www/public/assets/uploads/thumbnails \
    && chown -R www-data:www-data /var/www/public/assets/uploads \
    && chmod -R 755 /var/www/public/assets/uploads

# Environment variable indicating we're in Docker
ENV DOCKER_ENV=true

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Use our custom entrypoint
CMD ["php", "docker-entrypoint.php"]
