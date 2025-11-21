<?php
/**
 * Скрипт для проверки доступа к админке
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
    <title>Проверка доступа к админке</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Проверка доступа к админке</h1>
        
        <?php
        // Проверка 1: URL админки
        echo '<h2>Проверка 1: URL админки</h2>';
        $admin_url = admin_url();
        $site_url = site_url();
        $home_url = home_url();
        
        echo '<div class="info">';
        echo '<strong>URL из WordPress:</strong><br>';
        echo 'admin_url(): <code>' . htmlspecialchars($admin_url) . '</code><br>';
        echo 'site_url(): <code>' . htmlspecialchars($site_url) . '</code><br>';
        echo 'home_url(): <code>' . htmlspecialchars($home_url) . '</code><br>';
        echo '</div>';
        
        // Проверяем, правильные ли URL
        if (strpos($admin_url, 'staging.gustolocal.es') === false) {
            echo '<div class="error">✗ URL админки не содержит staging.gustolocal.es!</div>';
            echo '<div class="warning">⚠ Это может быть причиной проблемы. Нужно проверить настройки в базе данных.</div>';
        } else {
            echo '<div class="success">✓ URL админки правильный</div>';
        }
        
        // Проверка 2: Пользователи в базе данных
        echo '<h2>Проверка 2: Пользователи в базе данных</h2>';
        global $wpdb;
        
        $users = $wpdb->get_results("SELECT ID, user_login, user_email, user_registered FROM {$wpdb->users} ORDER BY ID LIMIT 10");
        
        if (empty($users)) {
            echo '<div class="error">✗ Пользователи не найдены в базе данных!</div>';
        } else {
            echo '<div class="success">✓ Найдено пользователей: ' . count($users) . '</div>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Логин</th><th>Email</th><th>Дата регистрации</th><th>Роли</th></tr>';
            
            foreach ($users as $user) {
                $user_meta = get_user_meta($user->ID);
                $roles = get_user_meta($user->ID, $wpdb->get_blog_prefix() . 'capabilities', true);
                $roles_display = is_array($roles) ? implode(', ', array_keys($roles)) : 'не определены';
                
                $is_admin = user_can($user->ID, 'administrator');
                $row_style = $is_admin ? 'style="background: #fff3cd;"' : '';
                
                echo '<tr ' . $row_style . '>';
                echo '<td>' . $user->ID . '</td>';
                echo '<td><code>' . htmlspecialchars($user->user_login) . '</code></td>';
                echo '<td>' . htmlspecialchars($user->user_email) . '</td>';
                echo '<td>' . htmlspecialchars($user->user_registered) . '</td>';
                echo '<td>' . htmlspecialchars($roles_display) . ($is_admin ? ' <strong>(Администратор)</strong>' : '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // Проверка 3: Права доступа администратора
        echo '<h2>Проверка 3: Права доступа администратора</h2>';
        $admin_users = $wpdb->get_results("
            SELECT u.ID, u.user_login, u.user_email 
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = '{$wpdb->get_blog_prefix()}capabilities'
            AND um.meta_value LIKE '%administrator%'
            LIMIT 10
        ");
        
        if (empty($admin_users)) {
            echo '<div class="error">✗ Администраторы не найдены!</div>';
            echo '<div class="warning">⚠ Это критическая проблема. Нужно создать администратора или восстановить права.</div>';
        } else {
            echo '<div class="success">✓ Найдено администраторов: ' . count($admin_users) . '</div>';
            echo '<ul>';
            foreach ($admin_users as $admin) {
                echo '<li><code>' . htmlspecialchars($admin->user_login) . '</code> (' . htmlspecialchars($admin->user_email) . ')</li>';
            }
            echo '</ul>';
        }
        
        // Проверка 4: Текущий пользователь
        echo '<h2>Проверка 4: Текущий пользователь</h2>';
        $current_user = wp_get_current_user();
        
        if ($current_user->ID > 0) {
            echo '<div class="success">✓ Пользователь авторизован</div>';
            echo '<div class="info">';
            echo 'ID: ' . $current_user->ID . '<br>';
            echo 'Логин: <code>' . htmlspecialchars($current_user->user_login) . '</code><br>';
            echo 'Email: ' . htmlspecialchars($current_user->user_email) . '<br>';
            echo 'Роли: ' . implode(', ', $current_user->roles) . '<br>';
            echo 'Может управлять опциями: ' . (current_user_can('manage_options') ? 'Да' : 'Нет');
            echo '</div>';
        } else {
            echo '<div class="info">Пользователь не авторизован (это нормально для этого скрипта)</div>';
        }
        
        // Проверка 5: .htaccess и редиректы
        echo '<h2>Проверка 5: Возможные проблемы с доступом</h2>';
        
        // Проверяем, нет ли редиректов
        $admin_path = str_replace(home_url(), '', admin_url());
        echo '<div class="info">';
        echo 'Путь к админке: <code>' . htmlspecialchars($admin_path) . '</code><br>';
        echo 'Полный URL: <code>' . htmlspecialchars($admin_url) . '</code>';
        echo '</div>';
        
        // Проверяем настройки в базе данных
        $siteurl = get_option('siteurl');
        $home = get_option('home');
        
        if ($siteurl !== 'https://staging.gustolocal.es' || $home !== 'https://staging.gustolocal.es') {
            echo '<div class="warning">⚠ URL в базе данных не совпадают с ожидаемыми:</div>';
            echo '<div class="info">';
            echo 'siteurl: <code>' . htmlspecialchars($siteurl) . '</code> (ожидается: https://staging.gustolocal.es)<br>';
            echo 'home: <code>' . htmlspecialchars($home) . '</code> (ожидается: https://staging.gustolocal.es)';
            echo '</div>';
        } else {
            echo '<div class="success">✓ URL в базе данных правильные</div>';
        }
        ?>
        
        <div class="warning">
            <h3>Рекомендации:</h3>
            <ul>
                <li>Попробуй открыть админку напрямую: <a href="<?php echo admin_url(); ?>" target="_blank" class="btn">Открыть админку</a></li>
                <li>Попробуй войти через: <a href="<?php echo wp_login_url(); ?>" target="_blank" class="btn">Страница входа</a></li>
                <li>Если не получается войти, попробуй сбросить пароль через базу данных или создать нового администратора</li>
            </ul>
        </div>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После проверки удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

