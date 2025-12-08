<?php
/**
 * Проверка ошибок WordPress
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$wp_root = __DIR__;
$debug_log = $wp_root . '/wp-content/debug.log';

echo "<h1>🔍 Проверка ошибок WordPress</h1>";
echo "<style>body { font-family: Arial; margin: 20px; } pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }</style>";

// Проверка debug.log
echo "<h2>1. Последние ошибки из debug.log:</h2>";
if (file_exists($debug_log)) {
    $lines = file($debug_log);
    $last_lines = array_slice($lines, -50); // Последние 50 строк
    echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
} else {
    echo "<p>debug.log не найден или пуст</p>";
}

// Попытка загрузить WordPress и поймать ошибку
echo "<h2>2. Попытка загрузить WordPress:</h2>";
try {
    define('WP_USE_THEMES', false);
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    define('WP_DEBUG_DISPLAY', true);
    
    require_once($wp_root . '/wp-load.php');
    echo "<p style='color: green;'>✅ WordPress загружен успешно</p>";
} catch (Throwable $e) {
    echo "<p style='color: red;'>❌ Ошибка:</p>";
    echo "<pre>";
    echo "Сообщение: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString();
    echo "</pre>";
}

// Проверка синтаксиса functions.php
echo "<h2>3. Проверка синтаксиса functions.php:</h2>";
$functions_file = $wp_root . '/wp-content/themes/gustolocal/functions.php';
if (file_exists($functions_file)) {
    $content = file_get_contents($functions_file);
    
    // Проверяем на наличие проблемных конструкций
    if (preg_match('/\?>\s*<\?php/', $content)) {
        echo "<p style='color: red;'>❌ Найдены проблемные закрывающие теги PHP</p>";
    }
    
    // Пытаемся включить файл
    try {
        $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        
        include_once($functions_file);
        echo "<p style='color: green;'>✅ functions.php загружен без ошибок</p>";
        
        restore_error_handler();
    } catch (Throwable $e) {
        echo "<p style='color: red;'>❌ Ошибка при загрузке functions.php:</p>";
        echo "<pre>" . $e->getMessage() . "\n";
        echo "Файл: " . $e->getFile() . "\n";
        echo "Строка: " . $e->getLine() . "</pre>";
    }
} else {
    echo "<p style='color: red;'>❌ functions.php не найден</p>";
}

?>

