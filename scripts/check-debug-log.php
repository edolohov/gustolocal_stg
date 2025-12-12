<?php
/**
 * Проверка debug.log
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>🔍 Проверка debug.log</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.error { color: #dc3232; font-weight: bold; }
.warning { color: #ffb900; font-weight: bold; }
.ok { color: #46b450; font-weight: bold; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Проверка debug.log</h1>";

$wp_root = __DIR__;
$debug_log = $wp_root . '/wp-content/debug.log';

if (file_exists($debug_log)) {
    $size = filesize($debug_log);
    echo "<p><strong>Размер файла:</strong> " . number_format($size) . " байт</p>";
    
    if ($size > 0) {
        $lines = file($debug_log);
        $total_lines = count($lines);
        echo "<p><strong>Всего строк:</strong> $total_lines</p>";
        
        // Показываем последние 100 строк
        $last_lines = array_slice($lines, -100);
        
        // Подсчитываем ошибки
        $error_count = 0;
        $fatal_count = 0;
        $warning_count = 0;
        
        foreach ($lines as $line) {
            if (stripos($line, 'fatal') !== false || stripos($line, 'Fatal') !== false) {
                $fatal_count++;
            }
            if (stripos($line, 'error') !== false || stripos($line, 'Error') !== false) {
                $error_count++;
            }
            if (stripos($line, 'warning') !== false || stripos($line, 'Warning') !== false) {
                $warning_count++;
            }
        }
        
        echo "<h2>Статистика ошибок:</h2>";
        echo "<ul>";
        echo "<li class='error'>Fatal ошибок: $fatal_count</li>";
        echo "<li class='error'>Ошибок: $error_count</li>";
        echo "<li class='warning'>Предупреждений: $warning_count</li>";
        echo "</ul>";
        
        if ($fatal_count > 0 || $error_count > 0) {
            echo "<h2 class='error'>Последние ошибки (последние 100 строк):</h2>";
            echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
        } else {
            echo "<p class='ok'>✅ Критических ошибок не найдено</p>";
            echo "<h2>Последние записи (последние 50 строк):</h2>";
            echo "<pre>" . htmlspecialchars(implode('', array_slice($lines, -50))) . "</pre>";
        }
    } else {
        echo "<p class='ok'>✅ debug.log пуст (нет ошибок)</p>";
    }
} else {
    echo "<p class='warning'>⚠️ debug.log не найден</p>";
    echo "<p>Возможные причины:</p>";
    echo "<ul>";
    echo "<li>Логирование отключено (WP_DEBUG_LOG = false)</li>";
    echo "<li>Файл еще не создан (нет ошибок)</li>";
    echo "<li>Неправильный путь к файлу</li>";
    echo "</ul>";
    echo "<p>Путь, где должен быть файл: <code>" . htmlspecialchars($debug_log) . "</code></p>";
}

// Проверка прав доступа
if (file_exists($debug_log)) {
    $perms = substr(sprintf('%o', fileperms($debug_log)), -4);
    echo "<p><strong>Права доступа:</strong> $perms</p>";
    
    if (!is_readable($debug_log)) {
        echo "<p class='error'>❌ Файл не читаем!</p>";
    } else {
        echo "<p class='ok'>✅ Файл читаем</p>";
    }
    
    if (!is_writable($debug_log)) {
        echo "<p class='warning'>⚠️ Файл не доступен для записи (WordPress не сможет писать логи)</p>";
    } else {
        echo "<p class='ok'>✅ Файл доступен для записи</p>";
    }
}

echo "</div></body></html>";
?>

