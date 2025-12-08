#!/bin/bash
# Импорт БД с пропуском ошибок через командную строку

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/load-env.sh"

SQL_FILE="${1:-/Users/eugene/Downloads/u850527203_5vYEq_staging_ready.sql}"

if [ ! -f "$SQL_FILE" ]; then
    echo "❌ Файл не найден: $SQL_FILE"
    exit 1
fi

echo "📦 Импортирую БД с пропуском ошибок..."
echo "Файл: $SQL_FILE"
echo "БД: $DB_STG_NAME"
echo ""

# Импорт с опцией --force (продолжает при ошибках)
mysql -h "$DB_STG_HOST" -u "$DB_STG_USER" -p"$DB_STG_PASS" "$DB_STG_NAME" --force < "$SQL_FILE"

echo ""
echo "✅ Импорт завершен (некоторые ошибки могли быть пропущены)"

