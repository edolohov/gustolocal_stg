#!/bin/bash
# Подключение к базе данных через SSH туннель или напрямую

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/load-env.sh"

ENV=${1:-stg}  # prod или stg

if [ "$ENV" = "prod" ]; then
    DB_NAME=$DB_PROD_NAME
    DB_USER=$DB_PROD_USER
    DB_PASS=$DB_PROD_PASS
    DB_HOST=$DB_PROD_HOST
    echo "🔌 Подключаюсь к PRODUCTION БД..."
elif [ "$ENV" = "stg" ]; then
    DB_NAME=$DB_STG_NAME
    DB_USER=$DB_STG_USER
    DB_PASS=$DB_STG_PASS
    DB_HOST=$DB_STG_HOST
    echo "🔌 Подключаюсь к STAGING БД..."
else
    echo "❌ Неверный параметр. Используйте: prod или stg"
    exit 1
fi

# Проверяем наличие mysql клиента
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL клиент не найден"
    echo "   Установите: brew install mysql-client  # для macOS"
    exit 1
fi

echo "📊 База данных: $DB_NAME"
echo "👤 Пользователь: $DB_USER"
echo ""

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

