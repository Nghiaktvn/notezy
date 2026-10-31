FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli pdo_mysql

# Install mbstring, intl, and other useful extensions
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libonig-dev \
    curl \
    --no-install-recommends && \
    docker-php-ext-install mbstring intl && \
    rm -rf /var/lib/apt/lists/*

# The attachment API accepts videos up to 25 MiB and up to 10 files per request.
RUN printf 'upload_max_filesize=25M\npost_max_size=256M\nmax_file_uploads=20\nmax_execution_time=120\n' \
    > /usr/local/etc/php/conf.d/notezy-uploads.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Apache config: allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Ensure uploads directory exists and is writable
# (Also done at runtime via PHP, but set here as baseline)
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 775 /var/www/html/uploads

# Set ownership of the whole project
RUN chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/entrypoint.sh

# Start using entrypoint to bind dynamic PORT
CMD ["/var/www/html/entrypoint.sh"]
