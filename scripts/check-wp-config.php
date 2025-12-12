<?php
/**
 * Проверка wp-config.php без загрузки WordPress
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Проверка wp-config.php</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.error { color: #dc3232; font-weight: bold; }
.ok { color: #46b450; font-weight: bold; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; }
</style></head><body><div class='container'>";

echo "<h1>Проверка wp-config.php</h1>";

$wp_root = __DIR__;
$wp_config = $wp_root . '/wp-config.php';

if (!file_exists($wp_config)) {
    echo "<p class='error'>❌ wp-config.php не найден!</p>";
    exit;
}

echo "<p class='ok'>✅ wp-config.php найден</p>";

// Читаем содержимое файла
$config_content = file_get_contents($wp_config);

// Проверяем на наличие add_action
if (strpos($config_content, 'add_action') !== false) {
    echo "<p class='error'>❌ В wp-config.php найдено использование add_action()!</p>";
    echo "<p>Это неправильно - add_action() нельзя использовать в wp-config.php</p>";
    
    // Находим строки с add_action
    $lines = explode("\n", $config_content);
    echo "<h2>Строки с add_action:</h2>";
    echo "<pre>";
    foreach ($lines as $num => $line) {
        if (strpos($line, 'add_action') !== false) {
            echo ($num + 1) . ": " . htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p class='ok'>✅ add_action() не найден в wp-config.php</p>";
}

// Проверяем синтаксис PHP
echo "<h2>Проверка синтаксиса PHP:</h2>";
$old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$syntax_error) {
    if ($errno === E_PARSE || $errno === E_COMPILE_ERROR) {
        $syntax_error = $errstr;
        return true;
    }
    return false;
});

ob_start();
try {
    // Пытаемся включить файл до require_once wp-settings.php
    $lines = file($wp_config);
    $before_wp_settings = [];
    foreach ($lines as $line) {
        $before_wp_settings[] = $line;
        if (strpos($line, 'require_once') !== false && strpos($line, 'wp-settings.php') !== false) {
            break;
        }
    }
    
    $test_config = implode('', $before_wp_settings);
    eval('?>' . $test_config);
    
    echo "<p class='ok'>✅ Синтаксис PHP корректен (до wp-settings.php)</p>";
} catch (ParseError $e) {
    ob_end_clean();
    echo "<p class='error'>❌ Ошибка парсинга:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n";
    echo "Файл: " . htmlspecialchars($e->getFile()) . "\n";
    echo "Строка: " . $e->getLine() . "</pre>";
} catch (Throwable $e) {
    ob_end_clean();
    echo "<p class='error'>❌ Ошибка:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

restore_error_handler();

// Показываем содержимое wp-config.php (первые 60 строк)
echo "<h2>Содержимое wp-config.php (первые 60 строк):</h2>";
$lines = file($wp_config);
echo "<pre>";
foreach (array_slice($lines, 0, 60) as $num => $line) {
    $line_num = $num + 1;
    $highlight = (strpos($line, 'add_action') !== false) ? ' style="background: #ffcccc;"' : '';
    echo sprintf("%3d: %s", $line_num, htmlspecialchars($line));
}
echo "</pre>";

echo "</div></body></html>";
?>

