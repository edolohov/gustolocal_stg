<?php
/**
 * Проверка плагинов безопасности, которые могут блокировать админку
 */
// Подключаемся напрямую к staging базе
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

$options_table = $table_prefix . 'options';
$result = $mysqli->query("SELECT option_value FROM $options_table WHERE option_name = 'active_plugins'");
$row = $result->fetch_assoc();
$active_plugins = unserialize($row['option_value'] ?? '');

$security_plugins = array(
    'wordfence',
    'ithemes-security',
    'better-wp-security',
    'all-in-one-wp-security',
    'sucuri',
    'bulletproof-security',
    'wp-security-audit-log'
);

$found_security = array();
foreach ($active_plugins as $plugin) {
    foreach ($security_plugins as $sec_plugin) {
        if (stripos($plugin, $sec_plugin) !== false) {
            $found_security[] = $plugin;
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Проверка плагинов безопасности</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>🔒 Проверка плагинов безопасности</h1>
    
    <?php
    if (!empty($found_security)) {
        echo '<div class="warning">';
        echo '<h2>⚠ Найдены плагины безопасности!</h2>';
        echo '<p>Эти плагины могут блокировать доступ к админке:</p>';
        echo '<ul>';
        foreach ($found_security as $plugin) {
            echo '<li><strong>' . esc_html($plugin) . '</strong></li>';
        }
        echo '</ul>';
        echo '<p><a href="fix-staging-direct.php?action=disable_security" class="button">Отключить плагины безопасности</a></p>';
        echo '</div>';
    } else {
        echo '<div class="success">';
        echo '<h2>✓ Плагины безопасности не найдены</h2>';
        echo '</div>';
    }
    
    echo '<h2>Все активные плагины:</h2>';
    echo '<table><tr><th>Плагин</th></tr>';
    foreach ($active_plugins as $plugin) {
        $is_security = false;
        foreach ($security_plugins as $sec_plugin) {
            if (stripos($plugin, $sec_plugin) !== false) {
                $is_security = true;
                break;
            }
        }
        $style = $is_security ? 'style="background: #fff3cd;"' : '';
        echo '<tr ' . $style . '><td>' . esc_html($plugin) . ($is_security ? ' <strong>(БЕЗОПАСНОСТЬ)</strong>' : '') . '</td></tr>';
    }
    echo '</table>';
    
    $mysqli->close();
    ?>
    
    <hr>
    <h2>Другие возможные причины:</h2>
    <ul>
        <li><strong>COOKIE_DOMAIN</strong> в wp-config.php установлен как `.gustolocal.es` вместо `.staging.gustolocal.es`</li>
        <li>Настройки сервера (nginx/apache) могут редиректить</li>
        <li>Настройки хостинга (Hostinger может иметь свои правила)</li>
    </ul>
    
    <p><a href="force-admin-access.php" class="button">Попробовать принудительный доступ</a></p>
</body>
</html>

