#!/bin/bash
# Выполнение WP-CLI команд на сервере через SSH

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/load-env.sh"

ENV=${1:-stg}  # prod или stg
shift  # Убираем первый аргумент

if [ -z "$1" ]; then
    echo "Использование: $0 [stg|prod] 'wp command'"
    echo "Пример: $0 stg 'plugin list'"
    echo "Пример: $0 prod 'user list'"
    exit 1
fi

if [ "$ENV" = "prod" ]; then
    REMOTE_PATH="/home/u850527203/domains/gustolocal.es/public_html"
    echo "🔧 Выполняю WP-CLI команду на PRODUCTION: wp $@"
elif [ "$ENV" = "stg" ]; then
    REMOTE_PATH=$SFTP_REMOTE_PATH
    echo "🔧 Выполняю WP-CLI команду на STAGING: wp $@"
else
    echo "❌ Неверный параметр. Используйте: prod или stg"
    exit 1
fi

COMMAND="$@"

ssh -p ${SFTP_PORT} -o StrictHostKeyChecking=no ${SFTP_USER}@${SFTP_HOST} \
  "cd ${REMOTE_PATH} && wp $COMMAND --allow-root"

