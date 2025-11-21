<?php
/**
 * Принудительное восстановление прав администратора
 * Очищает кеш и принудительно устанавливает права
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
    <title>Принудительное восстановление прав</title>
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
        <h1>Принудительное восстановление прав администратора</h1>
        
        <?php
        global $wpdb;
        
        $user_id = 1;
        $prefix = $wpdb->get_blog_prefix();
        
        echo '<div class="info">';
        echo '<strong>Параметры:</strong><br>';
        echo 'ID пользователя: <code>' . $user_id . '</code><br>';
        echo 'Префикс: <code>' . htmlspecialchars($prefix) . '</code>';
        echo '</div>';
        
        // Шаг 1: Очистка кеша пользователя
        echo '<h2>Шаг 1: Очистка кеша</h2>';
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'users');
        wp_cache_delete('user_meta', $user_id);
        echo '<div class="success">✓ Кеш пользователя очищен</div>';
        
        // Шаг 2: Прямое обновление в базе данных
        echo '<h2>Шаг 2: Прямое обновление в базе данных</h2>';
        
        // Удаляем старые записи
        $wpdb->delete(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => $prefix . 'capabilities'
            )
        );
        
        $wpdb->delete(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => $prefix . 'user_level'
            )
        );
        
        // Вставляем новые записи
        $capabilities = array('administrator' => true);
        $result1 = $wpdb->insert(
            $wpdb->usermeta,
            array(
                'user_id' => $user_id,
                'meta_key' => $prefix . 'capabilities',
                'meta_value' => serialize($capabilities)
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
            array('%d', '%s', '%s')
        );
        
        if ($result1 && $result2) {
            echo '<div class="success">✓ Права установлены напрямую в базе данных</div>';
        } else {
            echo '<div class="warning">⚠ Возможно, права уже были установлены</div>';
        }
        
        // Шаг 3: Очистка кеша снова
        echo '<h2>Шаг 3: Повторная очистка кеша</h2>';
        clean_user_cache($user_id);
        wp_cache_flush();
        echo '<div class="success">✓ Весь кеш WordPress очищен</div>';
        
        // Шаг 4: Проверка через WordPress API
        echo '<h2>Шаг 4: Установка прав через WordPress API</h2>';
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        
        // Принудительно обновляем метаданные
        update_user_meta($user_id, $prefix . 'capabilities', $capabilities);
        update_user_meta($user_id, $prefix . 'user_level', 10);
        
        echo '<div class="success">✓ Права установлены через WordPress API</div>';
        
        // Шаг 5: Финальная проверка
        echo '<h2>Шаг 5: Финальная проверка</h2>';
        
        // Очищаем кеш еще раз
        clean_user_cache($user_id);
        wp_cache_delete($user_id, 'users');
        
        // Получаем пользователя заново
        $user = new WP_User($user_id);
        
        $has_caps = get_user_meta($user_id, $prefix . 'capabilities', true);
        $has_level = get_user_meta($user_id, $prefix . 'user_level', true);
        $can_manage = user_can($user_id, 'manage_options');
        $is_admin = in_array('administrator', $user->roles);
        
        echo '<div class="info">';
        echo '<strong>Результат проверки:</strong><br>';
        echo 'capabilities установлены: ' . ($has_caps ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'user_level установлен: ' . ($has_level == 10 ? '<span style="color: green;">Да (10) ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'Роль administrator: ' . ($is_admin ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'Может управлять опциями: ' . ($can_manage ? '<span style="color: green;">Да ✓</span>' : '<span style="color: red;">Нет ✗</span>') . '<br>';
        echo 'Все роли: ' . implode(', ', $user->roles);
        echo '</div>';
        
        if ($can_manage && $is_admin) {
            echo '<div class="success">';
            echo '<strong>✓ Права администратора успешно восстановлены!</strong><br><br>';
            echo '<strong>Что делать дальше:</strong><br>';
            echo '1. Выйди из WordPress (если залогинен)<br>';
            echo '2. Очисти cookies браузера для этого сайта (или используй режим инкогнито)<br>';
            echo '3. Войди заново через <a href="' . wp_login_url() . '" target="_blank">страницу входа</a><br>';
            echo '4. После входа открой <a href="' . admin_url() . '" target="_blank">админку</a>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ Права все еще не восстановлены.</strong><br><br>';
            echo '<strong>Попробуй следующее:</strong><br>';
            echo '1. Выполни SQL запрос вручную (см. fix-admin-rights.sql)<br>';
            echo '2. Убедись, что префикс таблиц правильный (должен быть <code>' . htmlspecialchars($prefix) . '</code>)<br>';
            echo '3. Проверь, что user_id правильный (должен быть <code>1</code>)<br>';
            echo '4. Очисти все кеши (браузер, WordPress, сервер)';
            echo '</div>';
        }
        
        // Дополнительная информация
        echo '<h2>Дополнительная информация</h2>';
        echo '<div class="info">';
        echo '<strong>Текущие значения в базе данных:</strong><br>';
        $db_caps = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id,
            $prefix . 'capabilities'
        ));
        $db_level = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
            $user_id,
            $prefix . 'user_level'
        ));
        
        echo 'capabilities (из БД): <code>' . htmlspecialchars($db_caps) . '</code><br>';
        echo 'user_level (из БД): <code>' . htmlspecialchars($db_level) . '</code>';
        echo '</div>';
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После восстановления прав удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

