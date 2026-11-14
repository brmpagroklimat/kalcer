# Meniru Hostinger shared hosting: PHP 8.3 + pdo_mysql + gd (jpeg/webp) + zip + exif
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev libzip-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install pdo_mysql gd zip exif \
 && a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/site/public_html!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && printf 'upload_max_filesize=16M\npost_max_size=20M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/uploads.ini \
 && rm -rf /var/lib/apt/lists/*
