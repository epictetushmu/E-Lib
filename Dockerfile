FROM php:8.1-apache

# Install dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    unzip \
    git \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_mysql

# Configure Apache
RUN a2enmod rewrite headers
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Create required directories with proper permissions
RUN mkdir -p /var/www/html/certificates uploads/books uploads/thumbnails \
    && chmod -R 755 /var/www/html/certificates uploads

# Set environment variables at build time (can be overridden at run time)
ENV MONGO_URI=mongodb://localhost:27017/LibraryDb
ENV APP_ENV=production

# Create script to check MongoDB connection on container start
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port
EXPOSE 80

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
