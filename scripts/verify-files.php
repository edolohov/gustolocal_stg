<?php
/**
 * Проверка содержимого файлов на сервере
 */

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Проверка файлов</title>";
echo "<style>body{font-family:Arial;margin:20px;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;} .ok{color:green;} .error{color:red;}</style></head><body>";

echo "<h1>Проверка файлов на сервере</h1>";

$wp_root = __DIR__;

// Проверка wp-config.php
echo "<h2>1. wp-config.php</h2>";
$wp_config = $wp_root . '/wp-config.php';
if (file_exists($wp_config)) {
    $content = file_get_contents($wp_config);
    $has_add_action = strpos($content, 'add_action') !== false;
    
    if ($has_add_action) {
        echo "<p class='error'>❌ В wp-config.php НАЙДЕНО add_action() - это ошибка!</p>";
        echo "<p>Строки с add_action:</p>";
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (strpos($line, 'add_action') !== false) {
                echo "<pre>" . ($num + 1) . ": " . htmlspecialchars($line) . "</pre>";
            }
        }
    } else {
        echo "<p class='ok'>✅ В wp-config.php НЕТ add_action() - правильно!</p>";
    }
    
    echo "<h3>Строки 40-45:</h3>";
    $lines = explode("\n", $content);
    echo "<pre>";
    foreach (array_slice($lines, 39, 6) as $num => $line) {
        echo ($num + 40) . ": " . htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p class='error'>❌ wp-config.php не найден!</p>";
}

// Проверка functions.php темы
echo "<h2>2. functions.php темы gustolocal</h2>";
$functions_file = $wp_root . '/wp-content/themes/gustolocal/functions.php';
if (file_exists($functions_file)) {
    $content = file_get_contents($functions_file);
    $has_robots = strpos($content, 'robots') !== false && strpos($content, 'noindex') !== false;
    
    if ($has_robots) {
        echo "<p class='ok'>✅ В functions.php найден мета-тег robots - правильно!</p>";
    } else {
        echo "<p class='error'>❌ В functions.php НЕТ мета-тега robots!</p>";
    }
    
    echo "<h3>Строки 40-45:</h3>";
    $lines = explode("\n", $content);
    echo "<pre>";
    foreach (array_slice($lines, 39, 6) as $num => $line) {
        echo ($num + 40) . ": " . htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
    
    // Проверка даты изменения
    $mtime = filemtime($functions_file);
    echo "<p>Дата изменения файла: " . date('Y-m-d H:i:s', $mtime) . "</p>";
} else {
    echo "<p class='error'>❌ functions.php не найден!</p>";
}

echo "</body></html>";
?>

