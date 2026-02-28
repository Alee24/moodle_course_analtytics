FROM php:8.1-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd intl mysqli opcache soap xmlrpc zip

# Enable apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# The plugin should be mounted or copied into local/courseanalytics
# For development/quick setup, we assume the user will mount the code
