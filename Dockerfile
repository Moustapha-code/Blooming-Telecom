FROM php:8.2-apache

# System deps: unzip for composer, poppler + tesseract for the PDF OCR fallback
RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-fra \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

# Production PHP settings: log errors instead of printing them to pages
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Interdire l'accès HTTP aux dumps SQL, à config/ et aux fichiers d'outillage.
# AllowOverride vaut None dans l'image : un .htaccess ne suffirait pas.
COPY docker/apache-security.conf /etc/apache2/conf-enabled/zz-security.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

RUN chown -R www-data:www-data /var/www/html/uploads /var/www/html/exports

# Served at the domain root in production (locally under /blooming2 via XAMPP)
ENV APP_BASE_URL=""

# Render provides PORT; rewrite Apache's listen port at startup, then run
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT:-80}/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
