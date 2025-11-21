<?php
/**
 * Диагностический скрипт для проверки подключения к базе данных
 * Поможет выявить проблему с search-replace-urls.php
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Диагностика подключения к БД</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Диагностика подключения к базе данных</h1>
        
        <?php
        // Настройки базы данных
        // ВАЖНО: Используй те же данные, что и в wp-config.php!
        $db_host = 'localhost';
        $db_user = 'u850527203_stg';  // Проверь в wp-config.php - может быть u850527203 или u850527203_stg
        $db_password = 'hiLKov15!';
        $db_name = 'u850527203_stg';
        $table_prefix = 'staging_';
        
        echo '<div class="info">';
        echo '<strong>Параметры подключения:</strong><br>';
        echo 'Хост: <code>' . htmlspecialchars($db_host) . '</code><br>';
        echo 'Пользователь: <code>' . htmlspecialchars($db_user) . '</code><br>';
        echo 'База данных: <code>' . htmlspecialchars($db_name) . '</code><br>';
        echo 'Префикс таблиц: <code>' . htmlspecialchars($table_prefix) . '</code>';
        echo '</div>';
        
        // Проверка 1: Подключение к MySQL
        echo '<h2>Проверка 1: Подключение к MySQL</h2>';
        $mysqli = @new mysqli($db_host, $db_user, $db_password);
        
        if ($mysqli->connect_error) {
            echo '<div class="error">';
            echo '<strong>ОШИБКА подключения:</strong> ' . htmlspecialchars($mysqli->connect_error) . '<br>';
            echo 'Код ошибки: ' . $mysqli->connect_errno;
            echo '</div>';
        } else {
            echo '<div class="success">✓ Подключение к MySQL успешно</div>';
        }
        
        // Проверка 2: Выбор базы данных
        if (!$mysqli->connect_error) {
            echo '<h2>Проверка 2: Выбор базы данных</h2>';
            if (@$mysqli->select_db($db_name)) {
                echo '<div class="success">✓ База данных <code>' . htmlspecialchars($db_name) . '</code> выбрана успешно</div>';
            } else {
                echo '<div class="error">';
                echo '<strong>ОШИБКА:</strong> Не удалось выбрать базу данных <code>' . htmlspecialchars($db_name) . '</code><br>';
                echo 'Ошибка: ' . htmlspecialchars($mysqli->error);
                echo '</div>';
            }
        }
        
        // Проверка 3: Список таблиц
        if (!$mysqli->connect_error && @$mysqli->select_db($db_name)) {
            echo '<h2>Проверка 3: Таблицы с префиксом ' . htmlspecialchars($table_prefix) . '</h2>';
            $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}%'");
            
            if (!$result) {
                echo '<div class="error">';
                echo '<strong>ОШИБКА:</strong> Не удалось получить список таблиц<br>';
                echo 'Ошибка: ' . htmlspecialchars($mysqli->error);
                echo '</div>';
            } else {
                $tables = array();
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }
                $result->close();
                
                if (empty($tables)) {
                    echo '<div class="warning">';
                    echo '<strong>ВНИМАНИЕ:</strong> Не найдено таблиц с префиксом <code>' . htmlspecialchars($table_prefix) . '</code><br>';
                    echo 'Возможно, таблицы еще не переименованы или база данных пуста.';
                    echo '</div>';
                } else {
                    echo '<div class="success">';
                    echo '✓ Найдено таблиц: <strong>' . count($tables) . '</strong>';
                    echo '</div>';
                    echo '<details><summary>Показать список таблиц</summary><ul>';
                    foreach ($tables as $table) {
                        echo '<li><code>' . htmlspecialchars($table) . '</code></li>';
                    }
                    echo '</ul></details>';
                }
            }
        }
        
        // Проверка 4: Проверка конкретных таблиц
        if (!$mysqli->connect_error && @$mysqli->select_db($db_name)) {
            echo '<h2>Проверка 4: Проверка ключевых таблиц</h2>';
            $key_tables = array('options', 'posts', 'postmeta');
            $found_tables = array();
            
            foreach ($key_tables as $table) {
                $full_name = $table_prefix . $table;
                $check = $mysqli->query("SHOW TABLES LIKE '$full_name'");
                if ($check && $check->num_rows > 0) {
                    $found_tables[] = $full_name;
                    echo '<div class="success">✓ Таблица <code>' . htmlspecialchars($full_name) . '</code> существует</div>';
                    $check->close();
                } else {
                    echo '<div class="error">✗ Таблица <code>' . htmlspecialchars($full_name) . '</code> не найдена</div>';
                }
            }
        }
        
        // Проверка 5: Проверка колонок в таблице options
        if (!$mysqli->connect_error && @$mysqli->select_db($db_name)) {
            $options_table = $table_prefix . 'options';
            $check = $mysqli->query("SHOW TABLES LIKE '$options_table'");
            if ($check && $check->num_rows > 0) {
                echo '<h2>Проверка 5: Колонки в таблице options</h2>';
                $result = $mysqli->query("SHOW COLUMNS FROM `$options_table`");
                if ($result) {
                    echo '<div class="success">✓ Колонки в таблице <code>' . htmlspecialchars($options_table) . '</code>:</div>';
                    echo '<ul>';
                    while ($row = $result->fetch_assoc()) {
                        echo '<li><code>' . htmlspecialchars($row['Field']) . '</code> (' . htmlspecialchars($row['Type']) . ')</li>';
                    }
                    echo '</ul>';
                    $result->close();
                }
                $check->close();
            }
        }
        
        // Проверка 6: Тестовая замена
        if (!$mysqli->connect_error && @$mysqli->select_db($db_name)) {
            $options_table = $table_prefix . 'options';
            $check = $mysqli->query("SHOW TABLES LIKE '$options_table'");
            if ($check && $check->num_rows > 0) {
                echo '<h2>Проверка 6: Тестовая операция (SELECT)</h2>';
                $result = $mysqli->query("SELECT option_name, option_value FROM `$options_table` WHERE option_name IN ('siteurl', 'home') LIMIT 2");
                if ($result) {
                    echo '<div class="success">✓ Запрос выполнен успешно</div>';
                    echo '<table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
                    echo '<tr><th>option_name</th><th>option_value</th></tr>';
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr><td>' . htmlspecialchars($row['option_name']) . '</td><td>' . htmlspecialchars($row['option_value']) . '</td></tr>';
                    }
                    echo '</table>';
                    $result->close();
                } else {
                    echo '<div class="error">✗ Ошибка выполнения запроса: ' . htmlspecialchars($mysqli->error) . '</div>';
                }
                $check->close();
            }
        }
        
        // Информация о PHP
        echo '<h2>Информация о PHP</h2>';
        echo '<div class="info">';
        echo 'Версия PHP: <code>' . phpversion() . '</code><br>';
        echo 'Расширение mysqli: ' . (extension_loaded('mysqli') ? '<span style="color: green;">✓ Загружено</span>' : '<span style="color: red;">✗ Не загружено</span>') . '<br>';
        echo 'Расширение pdo_mysql: ' . (extension_loaded('pdo_mysql') ? '<span style="color: green;">✓ Загружено</span>' : '<span style="color: red;">✗ Не загружено</span>');
        echo '</div>';
        
        if (!$mysqli->connect_error) {
            $mysqli->close();
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После диагностики удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

