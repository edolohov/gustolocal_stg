<?php
/**
 * Временно отключает .htaccess для диагностики
 * ВАЖНО: Этот файл должен быть доступен напрямую, не через .htaccess редиректы
 */

$htaccess = __DIR__ . '/.htaccess';
$backup = __DIR__ . '/.htaccess.backup.' . date('Y-m-d_H-i-s');

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Отключение .htaccess</title></head><body>";
echo "<h1>Отключение .htaccess</h1>";

if (file_exists($htaccess)) {
    if (copy($htaccess, $backup)) {
        echo "<p>✅ Резервная копия создана: " . basename($backup) . "</p>";
        
        if (unlink($htaccess)) {
            echo "<p style='color: green; font-size: 18px;'>✅ .htaccess отключен!</p>";
            echo "<p>Попробуйте открыть сайт сейчас.</p>";
            echo "<p><a href='/'>Открыть главную страницу</a></p>";
            echo "<p><a href='/test-html.php'>Открыть тестовый скрипт</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Не удалось удалить .htaccess (нет прав доступа)</p>";
            echo "<p>Попробуйте переименовать файл вручную через FTP/File Manager:</p>";
            echo "<pre>.htaccess → .htaccess.disabled</pre>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Не удалось создать резервную копию</p>";
    }
} else {
    echo "<p>ℹ️ .htaccess не найден</p>";
}

echo "</body></html>";
?>

