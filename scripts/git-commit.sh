#!/bin/bash
# Автоматический коммит и пуш в GitHub

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/load-env.sh"

cd "$PROJECT_ROOT"

COMMIT_MSG="${1:-Auto commit from Cursor AI}"

echo "📝 Коммичу изменения..."
git add .

# Проверяем, есть ли изменения для коммита
if git diff --staged --quiet; then
    echo "ℹ️  Нет изменений для коммита"
    exit 0
fi

git commit -m "$COMMIT_MSG"

echo "🚀 Пушаю в GitHub..."
git push origin ${GITHUB_BRANCH:-main}

echo "✅ Изменения отправлены в GitHub"

