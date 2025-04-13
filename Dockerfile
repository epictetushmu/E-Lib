FROM php:8.0-apache

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY public/ ./public/
COPY App/ ./App/

# Install dependencies (if any)
# RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80