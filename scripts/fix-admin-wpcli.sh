#!/bin/bash
# Исправление пользователя admin через WP-CLI
# Это более надежный способ, чем через SQL

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Загружаем переменные окружения
source "$SCRIPT_DIR/load-env.sh"

# Подключаемся к серверу и выполняем WP-CLI команды
ssh -p "$SFTP_PORT" "$SFTP_USER@$SFTP_HOST" << 'EOF'
cd /home/u850527203/domains/gustolocal.es/public_html/staging

# Устанавливаем пароль для admin
wp user update admin --user_pass='hiLKov15!' --allow-root

# Проверяем пользователя
wp user get admin --allow-root

# Проверяем capabilities
wp user meta get admin staging_capabilities --allow-root
EOF

