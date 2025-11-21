<?php
/**
 * Скрипт для замены URL в базе данных staging
 * Заменяет все вхождения production URL на staging URL
 * 
 * ИСПОЛЬЗОВАНИЕ:
 * 1. Загрузи этот файл на сервер в папку staging
 * 2. Открой в браузере: https://staging.gustolocal.es/search-replace-urls.php?key=hello
 * 3. После выполнения удали файл с сервера!
 */

// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройки базы данных
// ВАЖНО: Используй те же данные, что и в wp-config.php!
$db_host = 'localhost';
$db_user = 'u850527203_stg';  // Проверь в wp-config.php - может быть u850527203 или u850527203_stg
$db_password = 'hiLKov15!';
$db_name = 'u850527203_stg';
$table_prefix = 'staging_';

// URL для замены
$old_url = 'https://gustolocal.es';
$new_url = 'https://staging.gustolocal.es';

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

// Подключение к базе данных
$mysqli = @new mysqli($db_host, $db_user, $db_password, $db_name);

if ($mysqli->connect_error) {
    die('Ошибка подключения к базе данных: ' . $mysqli->connect_error . '<br>Проверь настройки подключения в скрипте.');
}

$mysqli->set_charset('utf8mb4');

// Таблицы и колонки для замены
$tables_to_update = array(
    'options' => array('option_value'),
    'posts' => array('post_content', 'post_excerpt', 'guid'),
    'postmeta' => array('meta_value'),
    'comments' => array('comment_content'),
    'commentmeta' => array('meta_value'),
    'usermeta' => array('meta_value'),
    'termmeta' => array('meta_value'),
);

$results = array();
$total_replacements = 0;
$errors = array();

// Проверяем, существует ли база данных и есть ли таблицы
$check_db = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}%'");
if (!$check_db) {
    $errors[] = "Ошибка при проверке таблиц: " . $mysqli->error;
} elseif ($check_db->num_rows == 0) {
    $errors[] = "Не найдено таблиц с префиксом {$table_prefix}. Убедись, что база данных импортирована и префикс правильный.";
}

foreach ($tables_to_update as $table => $columns) {
    if (empty($columns)) {
        continue; // Пропускаем таблицы без колонок для замены
    }
    
    $full_table_name = $table_prefix . $table;
    
    // Проверяем существование таблицы
    $check = $mysqli->query("SHOW TABLES LIKE '$full_table_name'");
    if (!$check) {
        $errors[] = "Ошибка при проверке таблицы $full_table_name: " . $mysqli->error;
        continue;
    }
    
    if ($check->num_rows == 0) {
        $results[] = "Таблица $full_table_name не найдена, пропускаем";
        $check->close();
        continue;
    }
    $check->close();
    
    foreach ($columns as $column) {
        // Проверяем существование колонки
        $check_col = $mysqli->query("SHOW COLUMNS FROM `$full_table_name` LIKE '$column'");
        if (!$check_col) {
            $errors[] = "Ошибка при проверке колонки $column в таблице $full_table_name: " . $mysqli->error;
            continue;
        }
        
        if ($check_col->num_rows == 0) {
            $results[] = "Колонка $column в таблице $full_table_name не найдена, пропускаем";
            $check_col->close();
            continue;
        }
        $check_col->close();
        
        // Выполняем замену
        $query = "UPDATE `$full_table_name` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?";
        $stmt = $mysqli->prepare($query);
        
        if (!$stmt) {
            $errors[] = "Ошибка подготовки запроса для $full_table_name.$column: " . $mysqli->error;
            continue;
        }
        
        $search_pattern = '%' . $old_url . '%';
        $stmt->bind_param('sss', $old_url, $new_url, $search_pattern);
        
        if ($stmt->execute()) {
            $affected = $mysqli->affected_rows;
            $total_replacements += $affected;
            $results[] = "Таблица $full_table_name, колонка $column: заменено $affected строк";
        } else {
            $errors[] = "Ошибка в таблице $full_table_name, колонка $column: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Специальная замена для siteurl и home в options
$options_table = $table_prefix . 'options';
$check_options = $mysqli->query("SHOW TABLES LIKE '$options_table'");
if ($check_options && $check_options->num_rows > 0) {
    $query = "UPDATE `$options_table` SET `option_value` = ? WHERE `option_name` IN ('siteurl', 'home') AND `option_value` = ?";
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param('ss', $new_url, $old_url);
        if ($stmt->execute()) {
            $affected = $mysqli->affected_rows;
            $total_replacements += $affected;
            $results[] = "Обновлены siteurl и home в options: $affected строк";
        } else {
            $errors[] = "Ошибка при обновлении siteurl/home: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errors[] = "Ошибка подготовки запроса для siteurl/home: " . $mysqli->error;
    }
    if ($check_options) {
        $check_options->close();
    }
}

$mysqli->close();

// Выводим результаты
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Замена URL в базе данных</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        h1 {
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 8px;
            margin: 5px 0;
            background: #f8f9fa;
            border-left: 3px solid #0073aa;
        }
        .error li {
            border-left-color: #dc3545;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Замена URL в базе данных</h1>
        
        <div class="info">
            <strong>Параметры замены:</strong><br>
            Старый URL: <code><?php echo htmlspecialchars($old_url); ?></code><br>
            Новый URL: <code><?php echo htmlspecialchars($new_url); ?></code><br>
            Префикс таблиц: <code><?php echo htmlspecialchars($table_prefix); ?></code>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <h2>Ошибки:</h2>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($total_replacements > 0 || empty($errors)): ?>
            <div class="success">
                <strong>Всего заменено:</strong> <?php echo $total_replacements; ?> вхождений
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <h2>Детали выполнения:</h2>
            <ul>
                <?php foreach ($results as $result): ?>
                    <li><?php echo htmlspecialchars($result); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После проверки работы сайта удали этот файл с сервера для безопасности!
        </div>
        
        <p>
            <a href="<?php echo $new_url; ?>" target="_blank">Открыть staging сайт</a> |
            <a href="<?php echo $new_url; ?>/wp-admin/" target="_blank">Открыть админку</a>
        </p>
    </div>
</body>
</html>
