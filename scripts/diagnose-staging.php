<?php
/**
 * Полная диагностика staging окружения
 * Загрузите на сервер и откройте в браузере
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Диагностика Staging окружения</h1>";
echo "<style>body { font-family: Arial; margin: 20px; } .ok { color: green; } .error { color: red; } .warning { color: orange; }</style>";

// 1. Проверка подключения к БД
echo "<h2>1. Проверка подключения к базе данных</h2>";
$db_name = 'u850527203_stg';
$db_user = 'u850527203_stg';
$db_pass = 'hiLKov15!';
$db_host = 'localhost';

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    echo "<p class='error'>❌ Ошибка подключения: " . $mysqli->connect_error . "</p>";
} else {
    echo "<p class='ok'>✅ Подключение к БД успешно</p>";
    
    // Проверка таблиц
    $result = $mysqli->query("SHOW TABLES LIKE 'staging_%'");
    $table_count = $result ? $result->num_rows : 0;
    echo "<p>Таблиц с префиксом staging_: $table_count</p>";
    
    // Проверка опций
    $result = $mysqli->query("SELECT option_value FROM staging_options WHERE option_name = 'siteurl'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p>Site URL: " . htmlspecialchars($row['option_value']) . "</p>";
    }
    
    // Проверка активной темы
    $result = $mysqli->query("SELECT option_value FROM staging_options WHERE option_name = 'template'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p>Активная тема: " . htmlspecialchars($row['option_value']) . "</p>";
    }
}

// 2. Проверка путей
echo "<h2>2. Проверка путей и файлов</h2>";
$wp_root = __DIR__;
echo "<p>Корень WordPress: $wp_root</p>";

$wp_config = $wp_root . '/wp-config.php';
if (file_exists($wp_config)) {
    echo "<p class='ok'>✅ wp-config.php существует</p>";
    $config_content = file_get_contents($wp_config);
    if (strpos($config_content, "table_prefix = 'staging_'") !== false) {
        echo "<p class='ok'>✅ Префикс таблиц: staging_</p>";
    } else {
        echo "<p class='error'>❌ Префикс таблиц не найден или неправильный</p>";
    }
} else {
    echo "<p class='error'>❌ wp-config.php НЕ найден</p>";
}

// 3. Проверка темы
echo "<h2>3. Проверка темы gustolocal</h2>";
$theme_dir = $wp_root . '/wp-content/themes/gustolocal';
if (is_dir($theme_dir)) {
    echo "<p class='ok'>✅ Папка темы существует</p>";
    
    $functions_file = $theme_dir . '/functions.php';
    if (file_exists($functions_file)) {
        echo "<p class='ok'>✅ functions.php существует</p>";
        
        // Проверка синтаксиса
        $output = [];
        $return_var = 0;
        exec("php -l $functions_file 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "<p class='ok'>✅ Синтаксис PHP корректен</p>";
        } else {
            echo "<p class='error'>❌ Ошибка синтаксиса PHP:</p>";
            echo "<pre>" . implode("\n", $output) . "</pre>";
        }
    } else {
        echo "<p class='error'>❌ functions.php НЕ найден</p>";
    }
} else {
    echo "<p class='error'>❌ Папка темы НЕ существует: $theme_dir</p>";
}

// 4. Проверка плагинов
echo "<h2>4. Проверка плагинов</h2>";
$plugins_dir = $wp_root . '/wp-content/plugins';
if (is_dir($plugins_dir)) {
    echo "<p class='ok'>✅ Папка plugins существует</p>";
    
    $wmb_dir = $plugins_dir . '/weekly-meal-builder';
    if (is_dir($wmb_dir)) {
        echo "<p class='ok'>✅ Плагин weekly-meal-builder существует</p>";
    } else {
        echo "<p class='warning'>⚠️ Плагин weekly-meal-builder НЕ найден</p>";
    }
} else {
    echo "<p class='error'>❌ Папка plugins НЕ существует</p>";
}

// 5. Проверка прав доступа
echo "<h2>5. Проверка прав доступа</h2>";
$perms = substr(sprintf('%o', fileperms($wp_root)), -4);
echo "<p>Права доступа к корню: $perms</p>";

if (is_readable($wp_config)) {
    echo "<p class='ok'>✅ wp-config.php читаем</p>";
} else {
    echo "<p class='error'>❌ wp-config.php НЕ читаем</p>";
}

// 6. Попытка загрузить WordPress
echo "<h2>6. Попытка загрузить WordPress</h2>";
if (file_exists($wp_root . '/wp-load.php')) {
    echo "<p class='ok'>✅ wp-load.php существует</p>";
    
    // Попытка загрузить WordPress (осторожно!)
    try {
        define('WP_USE_THEMES', false);
        require_once($wp_root . '/wp-load.php');
        echo "<p class='ok'>✅ WordPress загружен успешно</p>";
        echo "<p>Версия WordPress: " . get_bloginfo('version') . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Ошибка при загрузке WordPress: " . $e->getMessage() . "</p>";
    } catch (Error $e) {
        echo "<p class='error'>❌ Критическая ошибка: " . $e->getMessage() . "</p>";
        echo "<p>Файл: " . $e->getFile() . "</p>";
        echo "<p>Строка: " . $e->getLine() . "</p>";
    }
} else {
    echo "<p class='error'>❌ wp-load.php НЕ найден</p>";
}

if (isset($mysqli)) {
    $mysqli->close();
}
?>

