<?php
/**
 * Проверка темы и путей
 * Загрузите на сервер и откройте в браузере
 */

$theme_dir = '/home/u850527203/domains/gustolocal.es/public_html/staging/wp-content/themes/gustolocal';
$functions_file = $theme_dir . '/functions.php';

echo "<h1>Проверка темы gustolocal</h1>";

echo "<h2>1. Проверка существования папки темы:</h2>";
if (is_dir($theme_dir)) {
    echo "<p style='color: green;'>✅ Папка темы существует: $theme_dir</p>";
} else {
    echo "<p style='color: red;'>❌ Папка темы НЕ существует: $theme_dir</p>";
}

echo "<h2>2. Проверка файла functions.php:</h2>";
if (file_exists($functions_file)) {
    echo "<p style='color: green;'>✅ Файл functions.php существует</p>";
    echo "<p>Размер файла: " . filesize($functions_file) . " байт</p>";
} else {
    echo "<p style='color: red;'>❌ Файл functions.php НЕ существует</p>";
}

echo "<h2>3. Проверка прав доступа:</h2>";
if (is_readable($theme_dir)) {
    echo "<p style='color: green;'>✅ Папка читаема</p>";
} else {
    echo "<p style='color: red;'>❌ Папка НЕ читаема</p>";
}

if (is_readable($functions_file)) {
    echo "<p style='color: green;'>✅ Файл functions.php читаем</p>";
} else {
    echo "<p style='color: red;'>❌ Файл functions.php НЕ читаем</p>";
}

echo "<h2>4. Список файлов в папке темы:</h2>";
if (is_dir($theme_dir)) {
    $files = scandir($theme_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filepath = $theme_dir . '/' . $file;
            $perms = substr(sprintf('%o', fileperms($filepath)), -4);
            echo "<li>$file (права: $perms)</li>";
        }
    }
    echo "</ul>";
}

echo "<h2>5. Проверка синтаксиса PHP в functions.php:</h2>";
if (file_exists($functions_file)) {
    $output = [];
    $return_var = 0;
    exec("php -l $functions_file 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "<p style='color: green;'>✅ Синтаксис PHP корректен</p>";
    } else {
        echo "<p style='color: red;'>❌ Ошибка синтаксиса PHP:</p>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
}

?>

