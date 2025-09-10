FROM php:8.2-apache

# Установка системных пакетов
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Установка расширений PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Включение mod_rewrite и mod_headers
RUN a2enmod rewrite
RUN a2enmod headers

# Копирование конфигурации Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование composer.json сначала для лучшего кеширования
COPY composer.json composer.lock* ./

# Установка зависимостей Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Копирование файлов приложения
COPY src/ /var/www/html/

# Установка прав доступа
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80