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
    libssl-dev \
    imagemagick \
    default-jre \
    fonts-liberation \
    fontconfig \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install zip pdo_mysql exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# OpenSSL is already built into PHP, just verify it's enabled
RUN php -r 'if(!extension_loaded("openssl")) exit(1);'

# Install LibreOffice with Java support and ImageMagick with Imagick PHP extension
RUN apt-get update && apt-get install -y \
    libreoffice \
    libreoffice-java-common \
    imagemagick \
    libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Configure fontconfig cache directories
RUN mkdir -p /var/cache/fontconfig && chmod 777 /var/cache/fontconfig

# Configure ImageMagick policy to allow PDF operations
RUN set -x \
    && find /etc -name policy.xml -type f | while read file; do \
        echo "Modifying policy file: $file"; \
        sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/' "$file" || true; \
    done \
    # Create additional policy files to ensure PDF processing is allowed
    && mkdir -p /etc/ImageMagick-6 \
    && echo '<?xml version="1.0" encoding="UTF-8"?><policymap><policy domain="coder" rights="read|write" pattern="PDF" /></policymap>' > /etc/ImageMagick-6/policy-pdf.xml \
    && mkdir -p /usr/local/etc/ImageMagick-6 \
    && echo '<?xml version="1.0" encoding="UTF-8"?><policymap><policy domain="coder" rights="read|write" pattern="PDF" /></policymap>' > /usr/local/etc/ImageMagick-6/policy.xml \
    # Verify ImageMagick installation and policy
    && convert -version || echo "ImageMagick convert not found"

# Install MongoDB extension with SSL support
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install cURL extension with SSL support
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && docker-php-ext-enable curl

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

# Create necessary directories with proper permissions
RUN mkdir -p /var/www/html/certificates \
    /var/www/html/storage/logs \
    /var/www/html/public/uploads \
    /var/www/html/public/assets/uploads/documents \
    /var/www/html/public/assets/uploads/thumbnails \
    /var/www/html/cache \
    # Create LibreOffice user profile with proper permissions
    /var/www/.config \
    /var/www/.cache \
    # Set proper permissions
    && chmod -R 777 /var/www/html/certificates \
    /var/www/html/storage \
    /var/www/html/public/uploads \
    /var/www/html/public/assets \
    /var/www/html/cache \
    /var/www/.config \
    /var/www/.cache \
    && chown -R www-data:www-data /var/www/

# Copy the MongoDB certificate setup script and entrypoint
COPY setup-mongodb-cert.php docker-entrypoint.php ./

# Try multiple certificate download methods during build
RUN echo "Attempting certificate download during build..." \
    && php -r 'file_put_contents("certificates/mongodb-ca.pem", file_get_contents("https://truststore.pki.mongodb.com/atlas-root-ca.pem") ?: "");' \
    || echo "Primary certificate download method failed, will try alternatives..."

# Run the certificate setup with fallback methods during build
RUN php setup-mongodb-cert.php

# Copy the rest of the application
COPY . .

# Set the certificate path in environment
ENV MONGO_CERT_FILE=/var/www/html/certificates/mongodb-ca.pem

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Environment variable indicating we're in Docker
ENV DOCKER_ENV=true

# Set HOME for LibreOffice user profile
ENV HOME=/var/www

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Use our custom entrypoint
CMD ["php", "docker-entrypoint.php"]
