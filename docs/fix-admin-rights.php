<?php
/**
 * Скрипт для автоматического восстановления прав администратора
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
    <title>Восстановление прав администратора</title>
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Восстановление прав администратора</h1>
        
        <?php
        global $wpdb;
        
        // Получаем префикс таблиц
        $prefix = $wpdb->get_blog_prefix();
        
        // ID пользователя для восстановления прав
        $user_id = 1; // admin
        
        echo '<div class="info">';
        echo '<strong>Параметры:</strong><br>';
        echo 'Префикс таблиц: <code>' . htmlspecialchars($prefix) . '</code><br>';
        echo 'ID пользователя: <code>' . $user_id . '</code>';
        echo '</div>';
        
        // Получаем информацию о пользователе
        $user = get_userdata($user_id);
        if ($user) {
            echo '<div class="info">';
            echo '<strong>Пользователь:</strong><br>';
            echo 'Логин: <code>' . htmlspecialchars($user->user_login) . '</code><br>';
            echo 'Email: ' . htmlspecialchars($user->user_email);
            echo '</div>';
        } else {
            echo '<div class="error">✗ Пользователь с ID ' . $user_id . ' не найден!</div>';
            exit;
        }
        
        // Проверяем текущие права
        echo '<h2>Текущие права (до исправления)</h2>';
        $current_caps = get_user_meta($user_id, $prefix . 'capabilities', true);
        $current_level = get_user_meta($user_id, $prefix . 'user_level', true);
        
        echo '<div class="info">';
        echo 'capabilities: <code>' . (is_array($current_caps) ? print_r($current_caps, true) : (empty($current_caps) ? 'не установлены' : htmlspecialchars($current_caps))) . '</code><br>';
        echo 'user_level: <code>' . ($current_level ? $current_level : 'не установлен') . '</code>';
        echo '</div>';
        
        // Восстанавливаем права
        echo '<h2>Восстановление прав</h2>';
        
        // Устанавливаем capabilities
        $capabilities = array('administrator' => true);
        $result_caps = update_user_meta($user_id, $prefix . 'capabilities', $capabilities);
        
        // Устанавливаем user_level (10 = администратор)
        $result_level = update_user_meta($user_id, $prefix . 'user_level', 10);
        
        if ($result_caps || $result_level) {
            echo '<div class="success">✓ Права администратора установлены</div>';
        } else {
            echo '<div class="warning">⚠ Права уже были установлены или произошла ошибка</div>';
        }
        
        // Проверяем результат
        echo '<h2>Проверка результата</h2>';
        
        // Очищаем кеш пользователя
        clean_user_cache($user_id);
        
        // Получаем обновленные данные
        $user = new WP_User($user_id);
        $new_caps = get_user_meta($user_id, $prefix . 'capabilities', true);
        $new_level = get_user_meta($user_id, $prefix . 'user_level', true);
        
        echo '<div class="info">';
        echo 'capabilities: <code>' . (is_array($new_caps) ? print_r($new_caps, true) : htmlspecialchars($new_caps)) . '</code><br>';
        echo 'user_level: <code>' . ($new_level ? $new_level : 'не установлен') . '</code><br>';
        echo 'Роли: ' . implode(', ', $user->roles) . '<br>';
        echo 'Может управлять опциями: ' . (user_can($user_id, 'manage_options') ? '<strong style="color: green;">Да ✓</strong>' : '<strong style="color: red;">Нет ✗</strong>');
        echo '</div>';
        
        if (user_can($user_id, 'manage_options')) {
            echo '<div class="success">';
            echo '<strong>✓ Права администратора успешно восстановлены!</strong><br>';
            echo 'Теперь ты можешь войти в админку.';
            echo '</div>';
            
            echo '<div class="info">';
            echo '<a href="' . admin_url() . '" class="btn">Открыть админку</a>';
            echo '<a href="' . wp_logout_url() . '" class="btn">Выйти и войти заново</a>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ Права не восстановлены. Попробуй выполнить SQL запрос вручную.</strong><br>';
            echo 'Смотри файл <code>fix-admin-rights.sql</code> для SQL запросов.';
            echo '</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После восстановления прав удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

