<?php
/**
 * Прямое исправление staging через SQL (без WordPress)
 */
// Настройки для staging базы данных
$db_host = 'localhost';
$db_name = 'u850527203_stg'; // staging база
$db_user = 'u850527203_stg';
$db_pass = 'hiLKov15!'; // используйте правильный пароль
$table_prefix = 'staging_'; // префикс staging

// Подключаемся к базе
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    die('Ошибка подключения: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Прямое исправление Staging</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; font-weight: bold; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; font-weight: bold; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        .button-danger { background: #dc3545; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔧 Прямое исправление Staging (через SQL)</h1>
    
    <?php
    $action = $_GET['action'] ?? '';
    
    // Получаем текущие активные плагины
    $options_table = $table_prefix . 'options';
    $result = $mysqli->query("SELECT option_value FROM $options_table WHERE option_name = 'active_plugins'");
    $row = $result->fetch_assoc();
    $active_plugins = unserialize($row['option_value'] ?? '');
    if (!is_array($active_plugins)) {
        $active_plugins = array();
    }
    
    if ($action === 'disable_checkout_editor') {
        // Отключаем Checkout Field Editor
        $new_plugins = array();
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo-checkout-field-editor-pro') === false) {
                $new_plugins[] = $plugin;
            }
        }
        
        $serialized = serialize($new_plugins);
        $stmt = $mysqli->prepare("UPDATE $options_table SET option_value = ? WHERE option_name = 'active_plugins'");
        $stmt->bind_param('s', $serialized);
        
        if ($stmt->execute()) {
            echo '<div class="success">';
            echo '<h2>✓ Плагин Checkout Field Editor отключен в STAGING!</h2>';
            echo '<p>Отключено плагинов: ' . (count($active_plugins) - count($new_plugins)) . '</p>';
            echo '<p>Осталось активных: ' . count($new_plugins) . '</p>';
            echo '<p><strong>Теперь попробуйте зайти в админку staging:</strong></p>';
            echo '<p><a href="https://staging.gustolocal.es/wp-admin/" class="button" target="_blank">Открыть админку staging</a></p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h2>✗ Ошибка при обновлении</h2>';
            echo '<p>' . $mysqli->error . '</p>';
            echo '</div>';
        }
        $stmt->close();
        
    } elseif ($action === 'show_plugins') {
        echo '<h2>Текущие активные плагины в STAGING:</h2>';
        echo '<table><tr><th>Плагин</th></tr>';
        foreach ($active_plugins as $plugin) {
            echo '<tr><td>' . esc_html($plugin) . '</td></tr>';
        }
        echo '</table>';
        
    } elseif ($action === 'check_user') {
        // Проверяем пользователя
        $users_table = $table_prefix . 'users';
        $usermeta_table = $table_prefix . 'usermeta';
        
        $result = $mysqli->query("SELECT ID, user_login, user_email FROM $users_table WHERE ID = 1");
        $user = $result->fetch_assoc();
        
        echo '<h2>Пользователь ID=1 в STAGING:</h2>';
        if ($user) {
            echo '<table>';
            echo '<tr><th>ID</th><td>' . $user['ID'] . '</td></tr>';
            echo '<tr><th>Логин</th><td>' . esc_html($user['user_login']) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . esc_html($user['user_email']) . '</td></tr>';
            echo '</table>';
            
            // Проверяем права
            $result = $mysqli->query("SELECT meta_value FROM $usermeta_table WHERE user_id = 1 AND meta_key = '{$table_prefix}capabilities'");
            $capabilities = $result->fetch_assoc();
            if ($capabilities) {
                $caps = unserialize($capabilities['meta_value']);
                echo '<p><strong>Роли:</strong> ' . implode(', ', array_keys($caps)) . '</p>';
            }
        } else {
            echo '<p class="error">Пользователь не найден</p>';
        }
        
    } else {
        // Показываем текущее состояние
        echo '<div class="info">';
        echo '<h2>Текущее состояние STAGING базы данных</h2>';
        echo '<p><strong>База данных:</strong> ' . $db_name . '</p>';
        echo '<p><strong>Префикс таблиц:</strong> ' . $table_prefix . '</p>';
        echo '</div>';
        
        // Проверяем, есть ли Checkout Field Editor
        $has_checkout_editor = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo-checkout-field-editor-pro') !== false) {
                $has_checkout_editor = true;
                break;
            }
        }
        
        if ($has_checkout_editor) {
            echo '<div class="warning">';
            echo '<h2>⚠ Проблема найдена!</h2>';
            echo '<p>Плагин <strong>Checkout Field Editor</strong> активен в STAGING базе данных.</p>';
            echo '<p>Этот плагин может редиректить админку.</p>';
            echo '<p><a href="?action=disable_checkout_editor" class="button button-danger" onclick="return confirm(\'Отключить Checkout Field Editor в STAGING?\')">Отключить плагин в STAGING</a></p>';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<h2>✓ Checkout Field Editor не активен</h2>';
            echo '</div>';
        }
        
        echo '<hr>';
        echo '<h2>Дополнительные действия:</h2>';
        echo '<p><a href="?action=show_plugins" class="button">Показать все активные плагины</a></p>';
        echo '<p><a href="?action=check_user" class="button">Проверить пользователя ID=1</a></p>';
    }
    
    $mysqli->close();
    ?>
    
    <hr style="margin: 30px 0;">
    <p><strong>Важно:</strong> Этот скрипт работает напрямую с STAGING базой данных, не затрагивая основной сайт.</p>
    <p><a href="https://staging.gustolocal.es/wp-admin/" class="button" target="_blank">Попробовать открыть админку staging</a></p>
</body>
</html>

