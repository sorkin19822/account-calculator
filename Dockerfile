FROM php:8.2-apache

# Установка расширений PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Включение mod_rewrite
RUN a2enmod rewrite

# Копирование конфигурации Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Установка рабочей директории
WORKDIR /var/www/html

# Копирование файлов приложения
COPY src/ /var/www/html/

# Установка прав доступа
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80