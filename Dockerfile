FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-dev linux-headers \
    && docker-php-ext-install pdo_pgsql sockets pcntl opcache bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Слой зависимостей кэшируется, пока не изменятся composer.json/lock.
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .

# Вся конфигурация приходит реальными переменными окружения; пустой файл
# нужен лишь для того, чтобы phpdotenv не ругался на отсутствие .env.
RUN touch .env \
    && composer dump-autoload --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
