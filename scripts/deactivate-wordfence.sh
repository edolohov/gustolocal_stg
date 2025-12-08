#!/bin/bash
# Скрипт для отключения Wordfence на staging через WP-CLI

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/load-env.sh"

echo "🔧 Отключаю Wordfence на staging..."

# Отключаем Wordfence через WP-CLI
./scripts/wp-cli.sh stg "plugin deactivate wordfence" 2>/dev/null || true
./scripts/wp-cli.sh stg "plugin deactivate wordfence-security" 2>/dev/null || true

echo "✅ Wordfence отключен (или уже был отключен)"
echo ""
echo "📋 Список активных плагинов:"
./scripts/wp-cli.sh stg "plugin list --status=active"

