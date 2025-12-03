<?php
/**
 * Прямое исправление прав пользователя через SQL
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

$users_table = $table_prefix . 'users';
$usermeta_table = $table_prefix . 'usermeta';

$action = $_GET['action'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Исправление прав пользователя</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        .button-danger { background: #dc3545; }
        .button-success { background: #28a745; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔧 Исправление прав пользователя (напрямую через SQL)</h1>
    
    <?php
    if ($action === 'fix_rights') {
        // Исправляем права для пользователя ID=1
        $user_id = 1;
        
        // 1. Устанавливаем capabilities
        $capabilities = serialize(array('administrator' => true));
        $stmt = $mysqli->prepare("UPDATE $usermeta_table SET meta_value = ? WHERE user_id = ? AND meta_key = ?");
        $meta_key = $table_prefix . 'capabilities';
        $stmt->bind_param('sis', $capabilities, $user_id, $meta_key);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo '<div class="success">✓ Обновлены capabilities</div>';
            } else {
                // Если записи нет, создаем
                $stmt2 = $mysqli->prepare("INSERT INTO $usermeta_table (user_id, meta_key, meta_value) VALUES (?, ?, ?)");
                $stmt2->bind_param('iss', $user_id, $meta_key, $capabilities);
                if ($stmt2->execute()) {
                    echo '<div class="success">✓ Созданы capabilities</div>';
                }
                $stmt2->close();
            }
        } else {
            echo '<div class="error">✗ Ошибка обновления capabilities: ' . $mysqli->error . '</div>';
        }
        $stmt->close();
        
        // 2. Устанавливаем user_level
        $user_level = 10;
        $meta_key_level = $table_prefix . 'user_level';
        $stmt = $mysqli->prepare("UPDATE $usermeta_table SET meta_value = ? WHERE user_id = ? AND meta_key = ?");
        $stmt->bind_param('sis', $user_level, $user_id, $meta_key_level);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo '<div class="success">✓ Обновлен user_level</div>';
            } else {
                $stmt2 = $mysqli->prepare("INSERT INTO $usermeta_table (user_id, meta_key, meta_value) VALUES (?, ?, ?)");
                $stmt2->bind_param('iss', $user_id, $meta_key_level, $user_level);
                if ($stmt2->execute()) {
                    echo '<div class="success">✓ Создан user_level</div>';
                }
                $stmt2->close();
            }
        } else {
            echo '<div class="error">✗ Ошибка обновления user_level: ' . $mysqli->error . '</div>';
        }
        $stmt->close();
        
        // 3. Проверяем результат
        $result = $mysqli->query("SELECT meta_key, meta_value FROM $usermeta_table WHERE user_id = 1 AND meta_key IN ('{$table_prefix}capabilities', '{$table_prefix}user_level')");
        echo '<div class="success">';
        echo '<h2>✓ Права исправлены!</h2>';
        echo '<p>Текущие настройки:</p>';
        echo '<table><tr><th>Ключ</th><th>Значение</th></tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr><td>' . esc_html($row['meta_key']) . '</td><td><pre>' . esc_html($row['meta_value']) . '</pre></td></tr>';
        }
        echo '</table>';
        echo '<p><strong>Теперь попробуйте зайти в админку:</strong></p>';
        echo '<p><a href="https://staging.gustolocal.es/wp-admin/" class="button button-success" target="_blank">Открыть админку</a></p>';
        echo '</div>';
        
    } elseif ($action === 'check_user') {
        // Проверяем текущее состояние
        $result = $mysqli->query("SELECT ID, user_login, user_email FROM $users_table WHERE ID = 1");
        $user = $result->fetch_assoc();
        
        echo '<h2>Текущее состояние пользователя ID=1:</h2>';
        if ($user) {
            echo '<table>';
            echo '<tr><th>ID</th><td>' . $user['ID'] . '</td></tr>';
            echo '<tr><th>Логин</th><td>' . esc_html($user['user_login']) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html($user['user_email']) . '</td></tr>';
            echo '</table>';
            
            // Проверяем права
            $result = $mysqli->query("SELECT meta_key, meta_value FROM $usermeta_table WHERE user_id = 1 AND meta_key IN ('{$table_prefix}capabilities', '{$table_prefix}user_level')");
            echo '<h3>Права пользователя:</h3>';
            echo '<table><tr><th>Ключ</th><th>Значение</th></tr>';
            $has_caps = false;
            $has_level = false;
            while ($row = $result->fetch_assoc()) {
                $has_caps = $has_caps || ($row['meta_key'] === $table_prefix . 'capabilities');
                $has_level = $has_level || ($row['meta_key'] === $table_prefix . 'user_level');
                echo '<tr><td>' . esc_html($row['meta_key']) . '</td><td><pre>' . esc_html($row['meta_value']) . '</pre></td></tr>';
            }
            if (!$has_caps) {
                echo '<tr><td colspan="2" class="error">✗ capabilities отсутствуют!</td></tr>';
            }
            if (!$has_level) {
                echo '<tr><td colspan="2" class="error">✗ user_level отсутствует!</td></tr>';
            }
            echo '</table>';
            
            if (!$has_caps || !$has_level) {
                echo '<div class="warning">';
                echo '<h3>⚠ Проблема найдена!</h3>';
                echo '<p>У пользователя отсутствуют необходимые права.</p>';
                echo '<p><a href="?action=fix_rights" class="button button-danger" onclick="return confirm(\'Исправить права для пользователя ID=1?\')">Исправить права</a></p>';
                echo '</div>';
            } else {
                echo '<div class="success">';
                echo '<h3>✓ Права установлены</h3>';
                echo '<p>Если админка все равно не работает, проблема может быть в другом месте.</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="error">';
            echo '<h3>✗ Пользователь ID=1 не найден!</h3>';
            echo '</div>';
        }
        
    } else {
        echo '<div class="warning">';
        echo '<h2>⚠ ВАЖНО</h2>';
        echo '<p>Этот скрипт исправит права пользователя ID=1 напрямую через SQL, обходя WordPress.</p>';
        echo '<p>Это должно решить проблему "Извините, вам не разрешено просматривать эту страницу".</p>';
        echo '</div>';
        
        echo '<h2>Действия:</h2>';
        echo '<p><a href="?action=check_user" class="button">Проверить текущее состояние</a></p>';
        echo '<p><a href="?action=fix_rights" class="button button-danger" onclick="return confirm(\'Исправить права для пользователя ID=1?\')">Исправить права</a></p>';
    }
    
    $mysqli->close();
    ?>
    
    <hr>
    <h2>Если это не поможет:</h2>
    <p>Можно пересоздать базу данных staging:</p>
    <ol>
        <li>Экспортировать только структуру таблиц с продакшна (без данных)</li>
        <li>Импортировать в staging</li>
        <li>Скопировать только нужные данные (пользователи, настройки)</li>
    </ol>
    <p>Но сначала попробуйте исправить права выше - это должно помочь.</p>
</body>
</html>

