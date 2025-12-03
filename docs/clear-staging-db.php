<?php
/**
 * Очистка staging базы данных перед импортом
 */
$db_host = 'localhost';
$db_name = 'u850527203_stg';
$db_user = 'u850527203_stg';
$db_pass = 'hiLKov15!';
$table_prefix = 'staging_';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    die('Ошибка подключения: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

$action = $_GET['action'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Очистка Staging базы</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🗑️ Очистка Staging базы данных</h1>
    
    <?php
    if ($action === 'clear') {
        // Получаем список всех таблиц
        $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}%'");
        $tables = array();
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        // Отключаем проверку внешних ключей
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        
        $dropped = 0;
        $errors = array();
        
        foreach ($tables as $table) {
            if ($mysqli->query("DROP TABLE IF EXISTS `$table`")) {
                $dropped++;
            } else {
                $errors[] = $table . ': ' . $mysqli->error;
            }
        }
        
        // Включаем обратно
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        
        echo '<div class="success">';
        echo '<h2>✓ База данных очищена!</h2>';
        echo '<p>Удалено таблиц: ' . $dropped . '</p>';
        if (!empty($errors)) {
            echo '<div class="error">';
            echo '<h3>Ошибки:</h3>';
            echo '<pre>' . implode("\n", $errors) . '</pre>';
            echo '</div>';
        }
        echo '<p><strong>Теперь можно импортировать новую базу!</strong></p>';
        echo '</div>';
        
    } else {
        // Показываем текущие таблицы
        $result = $mysqli->query("SHOW TABLES LIKE '{$table_prefix}%'");
        $tables = array();
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        echo '<div class="warning">';
        echo '<h2>⚠ ВНИМАНИЕ!</h2>';
        echo '<p>Это действие <strong>УДАЛИТ ВСЕ ТАБЛИЦЫ</strong> в staging базе данных.</p>';
        echo '<p>Найдено таблиц: <strong>' . count($tables) . '</strong></p>';
        echo '<p>Это действие нельзя отменить!</p>';
        echo '</div>';
        
        if (!empty($tables)) {
            echo '<h2>Текущие таблицы:</h2>';
            echo '<table><tr><th>Таблица</th></tr>';
            foreach ($tables as $table) {
                echo '<tr><td>' . esc_html($table) . '</td></tr>';
            }
            echo '</table>';
        }
        
        echo '<p><a href="?action=clear" class="button" onclick="return confirm(\'Вы уверены? Это удалит ВСЕ таблицы в staging базе!\')">Удалить все таблицы</a></p>';
    }
    
    $mysqli->close();
    ?>
</body>
</html>

