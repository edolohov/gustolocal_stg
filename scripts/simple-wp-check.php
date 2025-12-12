<?php
/**
 * Простая проверка загрузки WordPress
 * Без использования exec() и других функций, которые могут быть отключены
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 60);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>🔍 Проверка загрузки WordPress</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.ok { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Проверка загрузки WordPress</h1>";

$wp_root = __DIR__;

// Перехватываем все ошибки
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div class='error'>";
    echo "<strong>Ошибка PHP:</strong><br>";
    echo "Тип: $errno<br>";
    echo "Сообщение: " . htmlspecialchars($errstr) . "<br>";
    echo "Файл: " . htmlspecialchars($errfile) . "<br>";
    echo "Строка: $errline<br>";
    echo "</div>";
    return true;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div class='error'>";
        echo "<h2>❌ Фатальная ошибка PHP:</h2>";
        echo "<p><strong>Тип:</strong> {$error['type']}</p>";
        echo "<p><strong>Сообщение:</strong></p>";
        echo "<pre>" . htmlspecialchars($error['message']) . "</pre>";
        echo "<p><strong>Файл:</strong> <code>" . htmlspecialchars($error['file']) . "</code></p>";
        echo "<p><strong>Строка:</strong> {$error['line']}</p>";
        echo "</div>";
    }
});

echo "<h2>Попытка загрузить WordPress...</h2>";

try {
    define('WP_USE_THEMES', false);
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    define('WP_DEBUG_DISPLAY', false);
    
    // Подавляем вывод WordPress
    ob_start();
    
    require_once($wp_root . '/wp-load.php');
    
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<p class='error'>⚠️ Есть вывод при загрузке WordPress:</p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
    // Если дошли сюда, WordPress загружен
    echo "<p class='ok' style='font-size: 18px;'>✅ WordPress загружен успешно!</p>";
    
    if (function_exists('get_bloginfo')) {
        echo "<p><strong>Версия WordPress:</strong> " . get_bloginfo('version') . "</p>";
        echo "<p><strong>URL сайта:</strong> " . get_bloginfo('url') . "</p>";
        echo "<p><strong>Название сайта:</strong> " . get_bloginfo('name') . "</p>";
    }
    
    // Проверка активной темы
    if (function_exists('wp_get_theme')) {
        $theme = wp_get_theme();
        echo "<p><strong>Активная тема:</strong> " . $theme->get('Name') . " (" . $theme->get('Version') . ")</p>";
    }
    
    // Проверка подключения к БД
    global $wpdb;
    if (isset($wpdb)) {
        echo "<p class='ok'>✅ Подключение к БД через WordPress работает</p>";
        echo "<p><strong>Префикс таблиц:</strong> " . $wpdb->prefix . "</p>";
        
        // Проверка таблиц
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'");
        echo "<p><strong>Таблиц в БД:</strong> " . count($tables) . "</p>";
    }
    
} catch (Throwable $e) {
    ob_end_clean();
    echo "<div class='error'>";
    echo "<h2>❌ Ошибка при загрузке WordPress:</h2>";
    echo "<p><strong>Тип:</strong> " . get_class($e) . "</p>";
    echo "<p><strong>Сообщение:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Файл:</strong> <code>" . htmlspecialchars($e->getFile()) . "</code></p>";
    echo "<p><strong>Строка:</strong> {$e->getLine()}</p>";
    echo "<p><strong>Трассировка:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
?>

