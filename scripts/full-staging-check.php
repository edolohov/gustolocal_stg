<?php
/**
 * Полная диагностика staging окружения
 * Проверяет все возможные проблемы
 * Загрузите на сервер в папку staging и откройте в браузере
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 60);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>🔍 Полная диагностика Staging</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; border-left: 4px solid #0073aa; padding-left: 10px; }
.ok { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
.warning { color: #ffb900; font-weight: bold; }
.info { color: #0073aa; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #0073aa; color: white; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Полная диагностика Staging окружения</h1>";
echo "<p class='info'>Время проверки: " . date('Y-m-d H:i:s') . "</p>";

$wp_root = __DIR__;
$errors_found = [];
$warnings_found = [];

// ============================================
// 1. ПРОВЕРКА ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ
// ============================================
echo "<h2>1. Проверка подключения к базе данных</h2>";

// Варианты пользователя БД (проверяем оба)
$db_configs = [
    ['user' => 'u850527203_stg', 'name' => 'u850527203_stg', 'label' => 'u850527203_stg (текущий в wp-config.php)'],
    ['user' => 'u850527203', 'name' => 'u850527203_stg', 'label' => 'u850527203 (из документации)'],
];

$db_connected = false;
$working_config = null;

foreach ($db_configs as $config) {
    echo "<h3>Проверка: {$config['label']}</h3>";
    $mysqli = @new mysqli('localhost', $config['user'], 'hiLKov15!', $config['name']);
    
    if ($mysqli->connect_error) {
        echo "<p class='error'>❌ Ошибка подключения: " . htmlspecialchars($mysqli->connect_error) . "</p>";
    } else {
        echo "<p class='ok'>✅ Подключение успешно!</p>";
        $db_connected = true;
        $working_config = $config;
        
        // Проверка таблиц
        $result = $mysqli->query("SHOW TABLES LIKE 'staging_%'");
        if ($result) {
            $table_count = $result->num_rows;
            echo "<p class='ok'>✅ Найдено таблиц с префиксом staging_: $table_count</p>";
            
            if ($table_count == 0) {
                $errors_found[] = "Нет таблиц с префиксом staging_ в базе данных";
                echo "<p class='error'>❌ КРИТИЧНО: Нет таблиц в базе данных!</p>";
            } else {
                // Проверка ключевых таблиц
                $required_tables = ['staging_options', 'staging_users', 'staging_posts'];
                $missing_tables = [];
                foreach ($required_tables as $table) {
                    $check = $mysqli->query("SHOW TABLES LIKE '$table'");
                    if (!$check || $check->num_rows == 0) {
                        $missing_tables[] = $table;
                    }
                }
                if (!empty($missing_tables)) {
                    $errors_found[] = "Отсутствуют таблицы: " . implode(', ', $missing_tables);
                    echo "<p class='error'>❌ Отсутствуют таблицы: " . implode(', ', $missing_tables) . "</p>";
                } else {
                    echo "<p class='ok'>✅ Все ключевые таблицы присутствуют</p>";
                }
            }
        }
        
        // Проверка опций WordPress
        $result = $mysqli->query("SELECT option_name, option_value FROM staging_options WHERE option_name IN ('siteurl', 'home', 'template', 'stylesheet')");
        if ($result) {
            echo "<table><tr><th>Опция</th><th>Значение</th></tr>";
            while ($row = $result->fetch_assoc()) {
                $value = htmlspecialchars($row['option_value']);
                $is_prod_url = (strpos($value, 'gustolocal.es') !== false && strpos($value, 'staging') === false);
                if ($is_prod_url && in_array($row['option_name'], ['siteurl', 'home'])) {
                    $warnings_found[] = "URL в опции {$row['option_name']} указывает на production: $value";
                    echo "<tr><td>{$row['option_name']}</td><td class='warning'>⚠️ $value (production URL!)</td></tr>";
                } else {
                    echo "<tr><td>{$row['option_name']}</td><td>$value</td></tr>";
                }
            }
            echo "</table>";
        }
        
        $mysqli->close();
        break; // Используем первый рабочий конфиг
    }
}

if (!$db_connected) {
    $errors_found[] = "Не удалось подключиться к базе данных ни с одним из пользователей";
    echo "<p class='error'>❌ КРИТИЧНО: Не удалось подключиться к базе данных!</p>";
}

// ============================================
// 2. ПРОВЕРКА wp-config.php
// ============================================
echo "<h2>2. Проверка wp-config.php</h2>";

$wp_config = $wp_root . '/wp-config.php';
if (!file_exists($wp_config)) {
    $errors_found[] = "wp-config.php не найден";
    echo "<p class='error'>❌ wp-config.php НЕ найден!</p>";
} else {
    echo "<p class='ok'>✅ wp-config.php существует</p>";
    
    $config_content = file_get_contents($wp_config);
    
    // Проверка префикса таблиц
    if (strpos($config_content, "table_prefix = 'staging_'") !== false) {
        echo "<p class='ok'>✅ Префикс таблиц: staging_</p>";
    } else {
        $errors_found[] = "Неправильный префикс таблиц в wp-config.php";
        echo "<p class='error'>❌ Префикс таблиц не найден или неправильный</p>";
    }
    
    // Проверка DB_USER
    if (preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $config_content, $matches)) {
        $db_user_in_config = $matches[1];
        echo "<p>DB_USER в wp-config.php: <code>$db_user_in_config</code></p>";
        if ($working_config && $db_user_in_config !== $working_config['user']) {
            $warnings_found[] = "DB_USER в wp-config.php ($db_user_in_config) не совпадает с рабочим пользователем ({$working_config['user']})";
            echo "<p class='warning'>⚠️ DB_USER в конфиге не совпадает с рабочим пользователем БД!</p>";
        }
    }
    
    // Проверка URL
    if (strpos($config_content, "WP_HOME") !== false && strpos($config_content, "staging.gustolocal.es") !== false) {
        echo "<p class='ok'>✅ WP_HOME настроен на staging.gustolocal.es</p>";
    } else {
        $warnings_found[] = "WP_HOME не настроен или указывает не на staging";
        echo "<p class='warning'>⚠️ WP_HOME не настроен правильно</p>";
    }
    
    // Проверка WP_DEBUG
    if (strpos($config_content, "WP_DEBUG") !== false && strpos($config_content, "WP_DEBUG', true") !== false) {
        echo "<p class='ok'>✅ WP_DEBUG включен</p>";
    } else {
        echo "<p class='info'>ℹ️ WP_DEBUG не включен (не критично для staging)</p>";
    }
}

// ============================================
// 3. ПРОВЕРКА ФАЙЛОВ И ПУТЕЙ
// ============================================
echo "<h2>3. Проверка файлов и путей</h2>";

$required_files = [
    'wp-load.php' => 'Критический файл WordPress',
    'wp-settings.php' => 'Критический файл WordPress',
    'wp-content/themes' => 'Папка тем',
    'wp-content/plugins' => 'Папка плагинов',
];

foreach ($required_files as $file => $description) {
    $path = $wp_root . '/' . $file;
    if (file_exists($path)) {
        echo "<p class='ok'>✅ $description существует</p>";
    } else {
        $errors_found[] = "Отсутствует: $file ($description)";
        echo "<p class='error'>❌ $description НЕ найден: $file</p>";
    }
}

// ============================================
// 4. ПРОВЕРКА ТЕМЫ
// ============================================
echo "<h2>4. Проверка темы</h2>";

$theme_dirs = [
    'gustolocal' => $wp_root . '/wp-content/themes/gustolocal',
    'twentytwentyfour' => $wp_root . '/twentytwentyfour',
];

foreach ($theme_dirs as $theme_name => $theme_dir) {
    echo "<h3>Тема: $theme_name</h3>";
    if (is_dir($theme_dir)) {
        echo "<p class='ok'>✅ Папка темы существует</p>";
        
        $functions_file = $theme_dir . '/functions.php';
        if (file_exists($functions_file)) {
            echo "<p class='ok'>✅ functions.php существует</p>";
            
            // Проверка синтаксиса (без exec, так как может быть отключен)
            if (function_exists('exec')) {
                $output = [];
                $return_var = 0;
                exec("php -l \"$functions_file\" 2>&1", $output, $return_var);
                if ($return_var === 0) {
                    echo "<p class='ok'>✅ Синтаксис PHP корректен</p>";
                } else {
                    $errors_found[] = "Ошибка синтаксиса в $theme_name/functions.php";
                    echo "<p class='error'>❌ Ошибка синтаксиса PHP:</p>";
                    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
                }
            } else {
                // Альтернативная проверка: попытка включить файл
                echo "<p class='info'>ℹ️ exec() отключен, проверяю синтаксис через include...</p>";
                $old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$syntax_error) {
                    if ($errno === E_PARSE || $errno === E_COMPILE_ERROR) {
                        $syntax_error = $errstr;
                        return true;
                    }
                    return false;
                });
                $syntax_error = null;
                ob_start();
                try {
                    include_once($functions_file);
                    $output = ob_get_clean();
                    if ($syntax_error) {
                        echo "<p class='error'>❌ Ошибка синтаксиса: " . htmlspecialchars($syntax_error) . "</p>";
                    } else {
                        echo "<p class='ok'>✅ Файл загружается без синтаксических ошибок</p>";
                    }
                } catch (ParseError $e) {
                    ob_end_clean();
                    echo "<p class='error'>❌ Ошибка парсинга: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                restore_error_handler();
            }
            
            // Проверка размера файла (слишком большой может быть проблемой)
            $size = filesize($functions_file);
            if ($size > 500000) { // > 500KB
                $warnings_found[] = "functions.php темы $theme_name очень большой ($size байт)";
                echo "<p class='warning'>⚠️ functions.php очень большой: " . number_format($size) . " байт</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ functions.php НЕ найден</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ Папка темы не существует (может быть не нужна)</p>";
    }
}

// ============================================
// 5. ПРОВЕРКА ПРАВ ДОСТУПА
// ============================================
echo "<h2>5. Проверка прав доступа</h2>";

if (file_exists($wp_config)) {
    $perms = substr(sprintf('%o', fileperms($wp_config)), -4);
    echo "<p>Права доступа к wp-config.php: $perms</p>";
    if (is_readable($wp_config)) {
        echo "<p class='ok'>✅ wp-config.php читаем</p>";
    } else {
        $errors_found[] = "wp-config.php не читаем";
        echo "<p class='error'>❌ wp-config.php НЕ читаем</p>";
    }
}

// ============================================
// 6. ПРОВЕРКА DEBUG.LOG
// ============================================
echo "<h2>6. Проверка debug.log</h2>";

$debug_log = $wp_root . '/wp-content/debug.log';
if (file_exists($debug_log)) {
    $size = filesize($debug_log);
    echo "<p>debug.log существует, размер: " . number_format($size) . " байт</p>";
    
    if ($size > 0) {
        $lines = file($debug_log);
        $last_lines = array_slice($lines, -20); // Последние 20 строк
        echo "<h3>Последние ошибки:</h3>";
        echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
        
        // Подсчет критических ошибок
        $critical_count = 0;
        foreach ($lines as $line) {
            if (stripos($line, 'fatal') !== false || stripos($line, 'error') !== false) {
                $critical_count++;
            }
        }
        if ($critical_count > 0) {
            $warnings_found[] = "Найдено $critical_count критических ошибок в debug.log";
            echo "<p class='warning'>⚠️ Найдено критических ошибок: $critical_count</p>";
        }
    } else {
        echo "<p class='ok'>✅ debug.log пуст (нет ошибок)</p>";
    }
} else {
    echo "<p class='info'>ℹ️ debug.log не найден (может быть, ошибок нет или логирование отключено)</p>";
}

// ============================================
// 7. ПОПЫТКА ЗАГРУЗИТЬ WORDPRESS
// ============================================
echo "<h2>7. Попытка загрузить WordPress</h2>";

if (file_exists($wp_root . '/wp-load.php')) {
    echo "<p class='ok'>✅ wp-load.php существует</p>";
    
    // Перехватываем фатальные ошибки
    register_shutdown_function(function() use (&$errors_found) {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errors_found[] = "Фатальная ошибка PHP: {$error['message']} в {$error['file']}:{$error['line']}";
            echo "<div class='error'>";
            echo "<h3>❌ Фатальная ошибка PHP:</h3>";
            echo "<p><strong>Тип:</strong> {$error['type']}</p>";
            echo "<p><strong>Сообщение:</strong> <pre>" . htmlspecialchars($error['message']) . "</pre></p>";
            echo "<p><strong>Файл:</strong> <code>" . htmlspecialchars($error['file']) . "</code></p>";
            echo "<p><strong>Строка:</strong> {$error['line']}</p>";
            echo "</div>";
        }
    });
    
    // Пытаемся загрузить WordPress
    try {
        define('WP_USE_THEMES', false);
        define('WP_DEBUG', true);
        define('WP_DEBUG_LOG', true);
        define('WP_DEBUG_DISPLAY', false);
        
        ob_start();
        require_once($wp_root . '/wp-load.php');
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "<p class='warning'>⚠️ Есть вывод при загрузке WordPress:</p>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        } else {
            echo "<p class='ok'>✅ WordPress загружен успешно</p>";
            if (function_exists('get_bloginfo')) {
                echo "<p>Версия WordPress: " . get_bloginfo('version') . "</p>";
            }
        }
    } catch (Exception $e) {
        $errors_found[] = "Ошибка при загрузке WordPress: " . $e->getMessage();
        echo "<p class='error'>❌ Ошибка при загрузке WordPress:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n";
        echo "Файл: " . htmlspecialchars($e->getFile()) . "\n";
        echo "Строка: " . $e->getLine() . "</pre>";
    } catch (Error $e) {
        $errors_found[] = "Критическая ошибка: " . $e->getMessage();
        echo "<p class='error'>❌ Критическая ошибка:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n";
        echo "Файл: " . htmlspecialchars($e->getFile()) . "\n";
        echo "Строка: " . $e->getLine() . "</pre>";
    }
} else {
    $errors_found[] = "wp-load.php не найден";
    echo "<p class='error'>❌ wp-load.php НЕ найден</p>";
}

// ============================================
// 8. ИТОГОВАЯ СВОДКА
// ============================================
echo "<h2>8. Итоговая сводка</h2>";

if (empty($errors_found) && empty($warnings_found)) {
    echo "<p class='ok' style='font-size: 18px;'>✅ Все проверки пройдены успешно! Staging должен работать.</p>";
} else {
    if (!empty($errors_found)) {
        echo "<h3 class='error'>❌ Критические ошибки (" . count($errors_found) . "):</h3>";
        echo "<ul>";
        foreach ($errors_found as $error) {
            echo "<li class='error'>$error</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($warnings_found)) {
        echo "<h3 class='warning'>⚠️ Предупреждения (" . count($warnings_found) . "):</h3>";
        echo "<ul>";
        foreach ($warnings_found as $warning) {
            echo "<li class='warning'>$warning</li>";
        }
        echo "</ul>";
    }
    
    echo "<h3>Рекомендации по исправлению:</h3>";
    echo "<ol>";
    if (!$db_connected) {
        echo "<li><strong>Проблема с БД:</strong> Проверьте настройки в wp-config.php (DB_NAME, DB_USER, DB_PASSWORD, DB_HOST)</li>";
    }
    if (!empty($errors_found)) {
        echo "<li><strong>Критические ошибки:</strong> Исправьте все ошибки из списка выше</li>";
    }
    if (!empty($warnings_found)) {
        echo "<li><strong>Предупреждения:</strong> Проверьте предупреждения, они могут указывать на потенциальные проблемы</li>";
    }
    echo "<li>Проверьте файл wp-content/debug.log для детальной информации об ошибках</li>";
    echo "<li>Убедитесь, что все файлы загружены на сервер правильно</li>";
    echo "</ol>";
}

echo "</div></body></html>";
?>

