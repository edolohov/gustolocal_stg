<?php
/**
 * Финальное исправление прав администратора
 * Принудительно пересоздает объект пользователя
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
    <title>Финальное исправление прав</title>
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
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Финальное исправление прав администратора</h1>
        
        <?php
        global $wpdb;
        
        $user_id = 1;
        $prefix = $wpdb->get_blog_prefix();
        
        echo '<div class="info">';
        echo '<strong>Параметры:</strong><br>';
        echo 'ID пользователя: <code>' . $user_id . '</code><br>';
        echo 'Префикс: <code>' . htmlspecialchars($prefix) . '</code>';
        echo '</div>';
        
        // Шаг 1: Проверяем текущее состояние
        echo '<h2>Шаг 1: Текущее состояние</h2>';
        $current_caps = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id,
            $prefix . 'capabilities'
        ));
        $current_level = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id,
            $prefix . 'user_level'
        ));
        
        echo '<div class="info">';
        echo 'capabilities (из БД): <code>' . htmlspecialchars($current_caps) . '</code><br>';
        echo 'user_level (из БД): <code>' . htmlspecialchars($current_level) . '</code>';
        echo '</div>';
        
        // Шаг 2: Удаляем все метаданные пользователя связанные с правами
        echo '<h2>Шаг 2: Очистка всех метаданных прав</h2>';
        $wpdb->delete($wpdb->usermeta, array('user_id' => $user_id, 'meta_key' => $prefix . 'capabilities'));
        $wpdb->delete($wpdb->usermeta, array('user_id' => $user_id, 'meta_key' => $prefix . 'user_level'));
        echo '<div class="success">✓ Старые метаданные удалены</div>';
        
        // Шаг 3: Вставляем правильные значения
        echo '<h2>Шаг 3: Установка правильных прав</h2>';
        
        // Правильная сериализация для WordPress
        $capabilities = array('administrator' => true);
        $serialized_caps = serialize($capabilities);
        
        $result1 = $wpdb->insert(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => $prefix . 'capabilities',
                'meta_value' => $serialized_caps
            ),
            array('%d', '%s', '%s')
        );
        
        $result2 = $wpdb->insert(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => $prefix . 'user_level',
                'meta_value' => '10'
            ),
            array('%d', '%s', '%d')
        );
        
        if ($result1 && $result2) {
            echo '<div class="success">✓ Права установлены</div>';
        } else {
            echo '<div class="error">✗ Ошибка при установке прав: ' . $wpdb->last_error . '</div>';
        }
        
        // Шаг 4: Полная очистка всех кешей
        echo '<h2>Шаг 4: Полная очистка кешей</h2>';
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'users');
        wp_cache_delete('user_meta', $user_id);
        wp_cache_flush();
        
        // Удаляем из object cache если есть
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('user_' . $user_id, 'users');
            wp_cache_delete($user_id, 'user_meta');
        }
        
        echo '<div class="success">✓ Все кеши очищены</div>';
        
        // Шаг 5: Принудительное пересоздание объекта пользователя
        echo '<h2>Шаг 5: Пересоздание объекта пользователя</h2>';
        
        // Удаляем пользователя из кеша WordPress
        unset($GLOBALS['wp_user_roles']);
        
        // Создаем новый объект пользователя напрямую из БД
        $user = new WP_User();
        $user->init($user_id);
        
        // Принудительно устанавливаем роль
        $user->set_role('administrator');
        
        // Обновляем метаданные через API
        update_user_meta($user_id, $prefix . 'capabilities', $capabilities);
        update_user_meta($user_id, $prefix . 'user_level', 10);
        
        echo '<div class="success">✓ Объект пользователя пересоздан</div>';
        
        // Шаг 6: Финальная проверка
        echo '<h2>Шаг 6: Финальная проверка</h2>';
        
        // Еще раз очищаем кеш
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'users');
        
        // Получаем пользователя заново
        $user = new WP_User($user_id);
        
        // Проверяем через разные методы
        $has_caps_db = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id,
            $prefix . 'capabilities'
        ));
        
        $has_caps_meta = get_user_meta($user_id, $prefix . 'capabilities', true);
        $has_level = get_user_meta($user_id, $prefix . 'user_level', true);
        $can_manage = user_can($user_id, 'manage_options');
        $is_admin = in_array('administrator', $user->roles);
        $user_caps = $user->allcaps;
        $has_admin_cap = isset($user_caps['manage_options']) && $user_caps['manage_options'];
        
        echo '<div class="info">';
        echo '<strong>Результаты проверки:</strong><br>';
        echo 'capabilities (из БД напрямую): <code>' . htmlspecialchars($has_caps_db) . '</code><br>';
        echo 'capabilities (через get_user_meta): ' . (is_array($has_caps_meta) ? '<span style="color: green;">Массив ✓</span>' : '<span style="color: red;">Не массив ✗</span>') . '<br>';
        echo 'user_level: ' . ($has_level == 10 ? '<span style="color: green;">10 ✓</span>' : '<span style="color: red;">' . $has_level . ' ✗</span>') . '<br>';
        echo 'Роли пользователя: ' . (empty($user->roles) ? '<span style="color: red;">Пусто ✗</span>' : '<span style="color: green;">' . implode(', ', $user->roles) . ' ✓</span>') . '<br>';
        echo 'Роль administrator: ' . ($is_admin ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'Может управлять опциями (user_can): ' . ($can_manage ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'Может управлять опциями (allcaps): ' . ($has_admin_cap ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo '</div>';
        
        // Дополнительная диагностика
        if (!$is_admin || !$can_manage) {
            echo '<h2>Диагностика проблемы</h2>';
            echo '<div class="warning">';
            echo '<strong>Проблема все еще существует. Возможные причины:</strong><br><br>';
            
            if (empty($user->roles)) {
                echo '1. <strong>Роли не загружаются</strong> - возможно проблема с десериализацией<br>';
                echo '   Попробуй выполнить SQL запрос вручную:<br>';
                echo '   <pre>UPDATE staging_usermeta SET meta_value = \'a:1:{s:13:"administrator";b:1;}\' WHERE user_id = 1 AND meta_key = \'staging_capabilities\';</pre>';
            }
            
            if ($has_caps_db && !is_array($has_caps_meta)) {
                echo '2. <strong>Проблема с десериализацией</strong> - WordPress не может прочитать данные<br>';
                echo '   Проверь формат данных в базе данных';
            }
            
            echo '3. <strong>Попробуй создать нового администратора</strong> через SQL:<br>';
            echo '   <pre>INSERT INTO staging_users (user_login, user_pass, user_nicename, user_email, user_status) VALUES (\'admin2\', MD5(\'новый_пароль\'), \'admin2\', \'email@example.com\', 0);</pre>';
            echo '   Затем установи права для нового пользователя';
            echo '</div>';
        } else {
            echo '<div class="success">';
            echo '<strong>✓ Права администратора успешно восстановлены!</strong><br><br>';
            echo '<strong>Что делать дальше:</strong><br>';
            echo '1. <strong>Выйди из WordPress</strong> (если залогинен)<br>';
            echo '2. <strong>Очисти cookies браузера</strong> для staging.gustolocal.es<br>';
            echo '   Или используй режим инкогнито<br>';
            echo '3. <strong>Войди заново</strong> через <a href="' . wp_login_url() . '" target="_blank">страницу входа</a><br>';
            echo '4. <strong>Открой админку</strong>: <a href="' . admin_url() . '" target="_blank">' . admin_url() . '</a>';
            echo '</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После восстановления прав удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

