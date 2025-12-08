<?php
/**
 * Автоматический скрипт для переименования префикса таблиц с stg_ на staging_
 * 
 * ИСПОЛЬЗОВАНИЕ:
 * 1. Загрузи этот файл на сервер в любую папку (можно в staging)
 * 2. Открой в браузере: https://staging.gustolocal.es/rename-tables-prefix-auto.php?key=YOUR_SECRET_KEY
 * 3. После выполнения удали файл с сервера!
 */

// Настройки базы данных
$db_host = 'localhost';
$db_user = 'u850527203';
$db_password = 'hiLKov15!';
$db_name = 'u850527203_stg';

// Проверка безопасности
$security_key = 'CHANGE_THIS_TO_SOMETHING_SECRET';
if (isset($_GET['key']) && $_GET['key'] === $security_key) {
    // OK, продолжаем
} else {
    die('Доступ запрещен. Добавьте ?key=YOUR_SECRET_KEY к URL');
}

// Подключение к базе данных
$mysqli = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($mysqli->connect_error) {
    die('Ошибка подключения к базе данных: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

// Получаем список всех таблиц с префиксом stg_
$tables = array();
$result = $mysqli->query("SHOW TABLES LIKE 'stg_%'");

if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    $result->close();
}

if (empty($tables)) {
    die('Таблицы с префиксом stg_ не найдены. Возможно, они уже переименованы.');
}

$renamed = array();
$errors = array();

// Переименовываем каждую таблицу
foreach ($tables as $old_name) {
    // Заменяем префикс stg_ на staging_
    $new_name = str_replace('stg_', 'staging_', $old_name);
    
    // Проверяем, не существует ли уже таблица с новым именем
    $check = $mysqli->query("SHOW TABLES LIKE '$new_name'");
    if ($check && $check->num_rows > 0) {
        $errors[] = "Таблица $new_name уже существует, пропускаем $old_name";
        $check->close();
        continue;
    }
    
    // Переименовываем
    $query = "RENAME TABLE `$old_name` TO `$new_name`";
    if ($mysqli->query($query)) {
        $renamed[] = "$old_name → $new_name";
    } else {
        $errors[] = "Ошибка при переименовании $old_name: " . $mysqli->error;
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
    <title>Переименование префикса таблиц</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Переименование префикса таблиц</h1>
        
        <div class="success">
            <strong>Успешно переименовано:</strong> <?php echo count($renamed); ?> таблиц
        </div>
        
        <?php if (!empty($renamed)): ?>
            <h2>Переименованные таблицы:</h2>
            <ul>
                <?php foreach ($renamed as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
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
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> 
            <ul>
                <li>После проверки удали этот файл с сервера!</li>
                <li>Обнови префикс в wp-config.php: <code>$table_prefix = 'staging_';</code></li>
                <li>Обнови все скрипты, которые используют префикс stg_</li>
            </ul>
        </div>
    </div>
</body>
</html>

