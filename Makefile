.PHONY: help build up down restart logs clean install test composer-install reset-db

# Default target
help:
	@echo "Доступные команды:"
	@echo "  build           - Сборка контейнеров"
	@echo "  up              - Запуск приложения"
	@echo "  down            - Остановка приложения"
	@echo "  restart         - Перезапуск приложения"
	@echo "  logs            - Просмотр логов"
	@echo "  clean           - Полная очистка (удаление данных)"
	@echo "  reset-db        - Пересоздание базы данных"
	@echo "  install         - Первоначальная установка"
	@echo "  composer-install - Установка зависимостей Composer"
	@echo "  test            - Запуск тестов API"

# Установка зависимостей Composer
composer-install:
	docker-compose exec web composer install --optimize-autoloader --working-dir=/var/www/project

# Сборка контейнеров
build:
	docker-compose build

# Запуск приложения
up:
	docker-compose up -d
	@echo "Приложение запущено на http://localhost:8080"

# Остановка приложения
down:
	docker-compose down

# Перезапуск приложения
restart:
	docker-compose restart

# Просмотр логов
logs:
	docker-compose logs -f

# Пересоздание базы данных
reset-db:
	@echo "Пересоздание базы данных..."
	docker-compose down
	docker volume rm account-calculator_mysql_data || true
	docker-compose up -d
	@echo "База данных пересоздана!"

# Полная очистка
clean:
	docker-compose down -v
	docker system prune -f

# Первоначальная установка
install: build up composer-install
	@echo "Ожидание инициализации базы данных..."
	@sleep 30
	@echo "Установка завершена! Откройте http://localhost:8080"

# Тестирование API
test:
	@echo "Тестирование API endpoints..."
	@curl -s http://localhost:8080/api/?action=accounts | jq . || echo "Установите jq для красивого вывода JSON"
	@echo "\nТест расчета распределения:"
	@curl -s "http://localhost:8080/api/?action=calculate&amount=100" | jq . || echo "Результат получен"