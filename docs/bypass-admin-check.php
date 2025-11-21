<?php
/**
 * Временный обход проверки прав для доступа к админке
 * ВАЖНО: Используй только для диагностики!
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

// Загружаем WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Обход проверки прав</title>
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
        .btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #005a87; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Обход проверки прав и диагностика</h1>
        
        <?php
        global $wpdb;
        
        $user_id = 1;
        $prefix = $wpdb->get_blog_prefix();
        
        // Шаг 1: Проверяем активные плагины, которые могут блокировать доступ
        echo '<h2>Шаг 1: Проверка блокирующих плагинов</h2>';
        $active_plugins = get_option('active_plugins', array());
        $blocking_plugins = array('wordfence', 'ithemes-security', 'better-wp-security', 'all-in-one-wp-security');
        
        $found_blocking = array();
        foreach ($active_plugins as $plugin) {
            foreach ($blocking_plugins as $blocking) {
                if (stripos($plugin, $blocking) !== false) {
                    $found_blocking[] = $plugin;
                }
            }
        }
        
        if (!empty($found_blocking)) {
            echo '<div class="warning">⚠ Найдены плагины безопасности, которые могут блокировать доступ:</div>';
            echo '<ul>';
            foreach ($found_blocking as $plugin) {
                echo '<li><code>' . htmlspecialchars($plugin) . '</code></li>';
            }
            echo '</ul>';
            echo '<div class="info">Попробуй временно деактивировать эти плагины через базу данных:</div>';
            echo '<pre>UPDATE staging_options SET option_value = \'a:0:{}\' WHERE option_name = \'active_plugins\';</pre>';
        } else {
            echo '<div class="success">✓ Блокирующие плагины не найдены</div>';
        }
        
        // Шаг 2: Проверяем константы в wp-config.php
        echo '<h2>Шаг 2: Проверка констант безопасности</h2>';
        $security_constants = array('DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'AUTOMATIC_UPDATER_DISABLED');
        $found_constants = array();
        
        foreach ($security_constants as $constant) {
            if (defined($constant) && constant($constant)) {
                $found_constants[] = $constant;
            }
        }
        
        if (!empty($found_constants)) {
            echo '<div class="info">Найдены константы безопасности (это нормально):</div>';
            echo '<ul>';
            foreach ($found_constants as $const) {
                echo '<li><code>' . htmlspecialchars($const) . '</code></li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="success">✓ Константы безопасности не блокируют доступ</div>';
        }
        
        // Шаг 3: Создаем временный обход через фильтр
        echo '<h2>Шаг 3: Временный обход проверки прав</h2>';
        
        // Добавляем фильтр, который всегда возвращает true для user_id = 1
        add_filter('user_has_cap', function($allcaps, $caps, $args, $user) use ($user_id) {
            if ($user->ID == $user_id) {
                foreach ($caps as $cap) {
                    $allcaps[$cap] = true;
                }
            }
            return $allcaps;
        }, 999, 4);
        
        // Принудительно устанавливаем текущего пользователя
        wp_set_current_user($user_id);
        
        echo '<div class="success">✓ Временный обход активирован для user_id = ' . $user_id . '</div>';
        echo '<div class="warning">⚠ Это временное решение только для диагностики!</div>';
        
        // Шаг 4: Проверяем доступ
        echo '<h2>Шаг 4: Проверка доступа</h2>';
        $current_user = wp_get_current_user();
        $can_manage = current_user_can('manage_options');
        
        echo '<div class="info">';
        echo 'Текущий пользователь: <code>' . htmlspecialchars($current_user->user_login) . '</code> (ID: ' . $current_user->ID . ')<br>';
        echo 'Может управлять опциями: ' . ($can_manage ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>');
        echo '</div>';
        
        if ($can_manage) {
            echo '<div class="success">';
            echo '<strong>✓ Доступ к админке должен работать!</strong><br><br>';
            echo '<a href="' . admin_url() . '" class="btn" target="_blank">Открыть админку</a>';
            echo '</div>';
        }
        
        // Шаг 5: Создаем постоянное решение через wp-config.php
        echo '<h2>Шаг 5: Постоянное решение</h2>';
        echo '<div class="warning">';
        echo '<strong>Добавь в wp-config.php перед строкой "/* That\'s all, stop editing! */":</strong><br>';
        echo '<pre>// Временный обход проверки прав для user_id = 1
add_filter(\'user_has_cap\', function($allcaps, $caps, $args, $user) {
    if ($user->ID == 1) {
        foreach ($caps as $cap) {
            $allcaps[$cap] = true;
        }
    }
    return $allcaps;
}, 999, 4);</pre>';
        echo '<strong>⚠️ ВАЖНО:</strong> Это временное решение! После восстановления прав удали этот код!';
        echo '</div>';
        
        // Шаг 6: Альтернатива - создать нового пользователя
        echo '<h2>Шаг 6: Альтернативное решение - новый пользователь</h2>';
        echo '<div class="info">';
        echo 'Если ничего не помогает, создай нового администратора через SQL:<br>';
        echo '<pre>-- Создаем нового пользователя
INSERT INTO staging_users (user_login, user_pass, user_nicename, user_email, user_registered, user_status) 
VALUES (\'admin_new\', MD5(\'временный_пароль\'), \'admin_new\', \'2eugene46@gmail.com\', NOW(), 0);

-- Получаем ID
SET @new_id = LAST_INSERT_ID();

-- Устанавливаем права
INSERT INTO staging_usermeta (user_id, meta_key, meta_value) 
VALUES 
(@new_id, \'staging_capabilities\', \'a:1:{s:13:"administrator";b:1;}\'),
(@new_id, \'staging_user_level\', \'10\');</pre>';
        echo '</div>';
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После решения проблемы удали этот файл с сервера для безопасности!
        </think>
        
        <div class="info">
            <h3>Рекомендации:</h3>
            <ol>
                <li><strong>Попробуй открыть админку сейчас</strong> - обход должен работать</li>
                <li><strong>Если работает</strong> - добавь код в wp-config.php для постоянного решения</li>
                <li><strong>Если не работает</strong> - проверь .htaccess и деактивируй плагины безопасности</li>
                <li><strong>В крайнем случае</strong> - создай нового пользователя через SQL</li>
            </ol>
        </div>
    </div>
</body>
</html>

