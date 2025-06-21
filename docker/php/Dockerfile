FROM php:8.4-fpm AS php-build

RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    git \
    zip \
    unzip \
    libxml2-dev \
    nano \
    wget \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean

RUN docker-php-ext-install simplexml dom pdo_mysql intl

WORKDIR /var

COPY composer.json composer.lock ./
COPY package.json package-lock.json ./
RUN curl -sS https://getcomposer.org/installer | php \
    && php composer.phar install --no-dev --optimize-autoloader \
    && npm install

COPY . .

COPY docker/config-docker.local.neon /var/config/config-docker.local.neon

COPY docker/php/custom-php.ini /usr/local/etc/php/conf.d/

RUN npm run build-docker

RUN php composer.phar doctrine-proxies

RUN chown -R www-data:www-data /var/www && chmod -R 755 /var/www
RUN chown -R www-data:www-data /var/log
RUN chown -R www-data:www-data /var/temp

WORKDIR /var/puppeter

#FIREFOX instalation
RUN install -d -m 0755 /etc/apt/keyrings
RUN wget -q https://packages.mozilla.org/apt/repo-signing-key.gpg -O- | tee /etc/apt/keyrings/packages.mozilla.org.asc > /dev/null
RUN echo "deb [signed-by=/etc/apt/keyrings/packages.mozilla.org.asc] https://packages.mozilla.org/apt mozilla main" | tee -a /etc/apt/sources.list.d/mozilla.list > /dev/null
RUN echo ' \
    Package: * \
    Pin: origin packages.mozilla.org \
    Pin-Priority: 1000 \
    ' | tee /etc/apt/preferences.d/mozilla
RUN apt-get update && apt-get install -y firefox-esr

RUN npm install

WORKDIR /var/www

EXPOSE 9000

CMD ["php-fpm"]
