#!/bin/bash
# Загружает .env.local в текущую сессию

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

ENV_FILE="$PROJECT_ROOT/.env.local"

if [ ! -f "$ENV_FILE" ]; then
    echo "❌ Файл .env.local не найден в $PROJECT_ROOT!"
    exit 1
fi

# Загружаем переменные окружения
export $(cat "$ENV_FILE" | grep -v '^#' | grep -v '^$' | xargs)

echo "✅ Переменные окружения загружены из .env.local"

