<?php
/**
 * Прямое исправление wp-config.php на сервере
 * Удаляет add_action() из wp-config.php
 */

$wp_config_path = __DIR__ . '/wp-config.php';

if (!file_exists($wp_config_path)) {
    die("wp-config.php не найден!");
}

$content = file_get_contents($wp_config_path);

// Проверяем, есть ли add_action
if (strpos($content, 'add_action') === false) {
    die("add_action() не найден в wp-config.php. Файл уже исправлен.");
}

// Удаляем блок с add_action
$content = preg_replace(
    '/add_action\s*\(\s*[\'"]wp_head[\'"]\s*,\s*function\s*\([^)]*\)\s*\{[^}]*echo\s*[\'"]<meta[^>]*>[\'"]\s*;[^}]*\}\s*,\s*\d+\s*\)\s*;/s',
    '// Мета-тег robots будет добавлен через тему (нельзя использовать add_action() в wp-config.php)',
    $content
);

// Альтернативный способ - удаляем строки с add_action
$lines = explode("\n", $content);
$new_lines = [];
$skip_next = false;

foreach ($lines as $line) {
    if (strpos($line, 'add_action') !== false && strpos($line, 'wp_head') !== false) {
        // Пропускаем эту строку и следующие строки до закрывающей скобки
        $skip_next = true;
        $new_lines[] = '// Мета-тег robots будет добавлен через тему (нельзя использовать add_action() в wp-config.php)';
        continue;
    }
    
    if ($skip_next) {
        // Пропускаем строки внутри функции
        if (strpos($line, '});') !== false || (strpos($line, '}') !== false && substr_count($line, '}') > substr_count($line, '{'))) {
            $skip_next = false;
        }
        continue;
    }
    
    $new_lines[] = $line;
}

$new_content = implode("\n", $new_lines);

// Создаем резервную копию
$backup_path = $wp_config_path . '.backup.' . date('Y-m-d_H-i-s');
file_put_contents($backup_path, $content);

// Сохраняем исправленный файл
file_put_contents($wp_config_path, $new_content);

echo "✅ wp-config.php исправлен!\n";
echo "Резервная копия сохранена: " . basename($backup_path) . "\n";
echo "\nПроверьте содержимое:\n";
echo "---\n";
echo substr($new_content, 0, 2000);
echo "\n---\n";

