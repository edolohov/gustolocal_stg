<?php
/**
 * Прямая проверка ошибок PHP без загрузки WordPress
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Проверка ошибок</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.error { color: #dc3232; font-weight: bold; }
.ok { color: #46b450; font-weight: bold; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; }
</style></head><body><div class='container'>";

echo "<h1>Проверка ошибок PHP</h1>";

$wp_root = __DIR__;

// Проверка debug.log
$debug_log = $wp_root . '/wp-content/debug.log';
echo "<h2>1. Проверка debug.log</h2>";

if (file_exists($debug_log)) {
    $size = filesize($debug_log);
    echo "<p>Размер: " . number_format($size) . " байт</p>";
    
    if ($size > 0) {
        $lines = file($debug_log);
        $last_lines = array_slice($lines, -50);
        echo "<h3>Последние 50 строк:</h3>";
        echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
    } else {
        echo "<p class='ok'>✅ debug.log пуст</p>";
    }
} else {
    echo "<p class='warning'>⚠️ debug.log не найден</p>";
}

// Проверка PHP error log
echo "<h2>2. Проверка PHP error log</h2>";
$php_error_log = ini_get('error_log');
if ($php_error_log && file_exists($php_error_log)) {
    echo "<p>Путь: $php_error_log</p>";
    if (filesize($php_error_log) > 0) {
        $lines = file($php_error_log);
        $last_lines = array_slice($lines, -30);
        echo "<h3>Последние 30 строк:</h3>";
        echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
    }
} else {
    echo "<p>PHP error log: " . ($php_error_log ?: 'не настроен') . "</p>";
}

// Проверка .htaccess
echo "<h2>3. Проверка .htaccess</h2>";
$htaccess = $wp_root . '/.htaccess';
if (file_exists($htaccess)) {
    echo "<p class='ok'>✅ .htaccess существует</p>";
    $content = file_get_contents($htaccess);
    if (strlen($content) > 0) {
        echo "<h3>Содержимое:</h3>";
        echo "<pre>" . htmlspecialchars($content) . "</pre>";
    }
} else {
    echo "<p class='warning'>⚠️ .htaccess не найден</p>";
}

// Попытка прочитать wp-config.php и найти проблемы
echo "<h2>4. Проверка wp-config.php</h2>";
$wp_config = $wp_root . '/wp-config.php';
if (file_exists($wp_config)) {
    $content = file_get_contents($wp_config);
    
    // Проверяем на add_action
    if (preg_match_all('/add_action\s*\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        echo "<p class='error'>❌ Найдено использование add_action() в wp-config.php!</p>";
        $lines = explode("\n", $content);
        foreach ($matches[0] as $match) {
            $pos = $match[1];
            $line_num = substr_count(substr($content, 0, $pos), "\n") + 1;
            echo "<p>Строка $line_num:</p>";
            echo "<pre>" . htmlspecialchars($lines[$line_num - 1]) . "</pre>";
        }
    } else {
        echo "<p class='ok'>✅ add_action() не найден</p>";
    }
    
    // Проверяем на другие проблемные функции
    $problematic_functions = ['add_filter', 'wp_enqueue_script', 'wp_enqueue_style', 'register_post_type'];
    foreach ($problematic_functions as $func) {
        if (strpos($content, $func) !== false) {
            echo "<p class='error'>❌ Найдено использование $func() в wp-config.php!</p>";
        }
    }
} else {
    echo "<p class='error'>❌ wp-config.php не найден!</p>";
}

echo "</div></body></html>";
?>

