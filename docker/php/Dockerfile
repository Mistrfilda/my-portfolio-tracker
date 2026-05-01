FROM php:8.5-fpm AS php-build

RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    git \
    zip \
    unzip \
    libxml2-dev \
    libicu-dev \
    nano \
    wget \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install simplexml pdo_mysql intl sockets

WORKDIR /var

COPY composer.json composer.lock ./
COPY patches/ ./patches
COPY package.json package-lock.json ./
RUN curl -sS https://getcomposer.org/installer | php \
    && php composer.phar install --no-dev --optimize-autoloader \
    && npm ci --omit=dev

COPY . .

COPY docker/config-docker.local.neon /var/config/config-docker.local.neon

COPY docker/php/custom-php.ini /usr/local/etc/php/conf.d/

RUN rm -rf /var/www/build/admin
RUN mv /var/www/prod-build/build/admin /var/www/build/admin

RUN php composer.phar doctrine-proxies

RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www
RUN chown -R www-data:www-data /var/log
RUN chown -R www-data:www-data /var/temp

WORKDIR /var/puppeter

RUN apt-get update && apt-get install -y \
    chromium \
    libnss3 \
    libnspr4 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libgbm1 \
    libgtk-3-0 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libxkbcommon0 \
    fonts-liberation \
    xdg-utils \
    && apt-get clean

ENV PUPPETEER_SKIP_DOWNLOAD=true
RUN npm ci --omit=dev

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
