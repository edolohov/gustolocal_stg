<?php
/**
 * Временно переименовывает .htaccess для диагностики
 */

$htaccess_path = __DIR__ . '/.htaccess';
$htaccess_backup = __DIR__ . '/.htaccess.disabled';

if (file_exists($htaccess_path)) {
    if (rename($htaccess_path, $htaccess_backup)) {
        echo "✅ .htaccess переименован в .htaccess.disabled\n";
        echo "Попробуйте открыть сайт сейчас.\n";
        echo "Если сайт заработает, значит проблема в .htaccess\n";
    } else {
        echo "❌ Не удалось переименовать .htaccess\n";
        echo "Возможно, нет прав доступа\n";
    }
} else {
    echo "ℹ️ .htaccess не найден\n";
}

echo "\nДля восстановления .htaccess выполните:\n";
echo "mv .htaccess.disabled .htaccess\n";

