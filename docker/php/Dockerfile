# Použijeme oficiální PHP runtime s PHP-FPM
FROM php:8.3-fpm AS php-build

# Nainstalujeme závislosti včetně libxml2
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    git \
    zip \
    unzip \
    libxml2-dev \
    chromium \
    nano \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean

# Nainstalujeme požadované PHP extenze
RUN docker-php-ext-install simplexml dom pdo_mysql intl

# Nastavíme pracovní adresář
WORKDIR /var

# Zkopírujeme Composer a package.json/package-lock.json soubory a nainstalujeme závislosti
COPY composer.json composer.lock ./
COPY package.json package-lock.json ./
RUN curl -sS https://getcomposer.org/installer | php \
    && php composer.phar install --no-dev --optimize-autoloader \
    && npm install

# Zkopírujeme celý obsah aplikace do kontejneru
COPY . .

COPY docker/config-docker.local.neon /var/config/config-docker.local.neon

COPY docker/php/custom-php.ini /usr/local/etc/php/conf.d/

RUN npm run build-docker

# Nastavíme oprávnění
RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www
RUN chown -R www-data:www-data /var/log
RUN chown -R www-data:www-data /var/temp

WORKDIR /var/puppeter

RUN npm install

# Nastavíme pracovní adresář pro PHP aplikaci
WORKDIR /var/www

# Otevřeme port pro PHP-FPM
EXPOSE 9000

# Příkaz pro spuštění PHP-FPM
CMD ["php-fpm"]
