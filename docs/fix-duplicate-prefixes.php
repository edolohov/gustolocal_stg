<?php
/**
 * Исправление дублирующихся префиксов и прав
 * Запустите через браузер: https://staging.gustolocal.es/docs/fix-duplicate-prefixes.php?key=hello
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

// Подключаем WordPress
$wp_load_paths = array(
    dirname(__FILE__) . '/../../staging/wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    dirname(__FILE__) . '/../wp-load.php',
    dirname(__FILE__) . '/wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Ошибка: не удалось найти wp-load.php.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Исправление дублирующихся префиксов</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3232; border-bottom: 3px solid #dc3232; padding-bottom: 10px; }
        .success { color: #46b450; font-weight: bold; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3232; font-weight: bold; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f9f9f9; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #ddd; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Исправление дублирующихся префиксов</h1>

        <?php
        global $wpdb;
        
        $user_id = 1;
        
        echo "<h2>1. Проблема найдена!</h2>";
        echo "<p class='error'>✗ В базе данных есть записи с РАЗНЫМИ префиксами:</p>";
        echo "<ul>";
        echo "<li><code>staging_capabilities</code> - правильный префикс</li>";
        echo "<li><code>stg_capabilities</code> - ЛИШНИЙ префикс (нужно удалить)</li>";
        echo "<li><code>staging_user_level = 0</code> - НЕПРАВИЛЬНО (должно быть 10)</li>";
        echo "<li><code>stg_user_level = 10</code> - правильный, но с неправильным префиксом</li>";
        echo "</ul>";
        
        echo "<h2>2. Удаление лишних записей с префиксом stg_</h2>";
        
        // Удаляем все записи с префиксом stg_ для этого пользователя
        $deleted_stg_caps = $wpdb->delete(
            $wpdb->prefix . 'usermeta',
            array(
                'user_id' => $user_id,
                'meta_key' => 'stg_capabilities'
            )
        );
        
        $deleted_stg_level = $wpdb->delete(
            $wpdb->prefix . 'usermeta',
            array(
                'user_id' => $user_id,
                'meta_key' => 'stg_user_level'
            )
        );
        
        echo "<p>Удалено записей с префиксом stg_: capabilities=$deleted_stg_caps, user_level=$deleted_stg_level</p>";
        
        echo "<h2>3. Исправление staging_user_level</h2>";
        
        // Обновляем staging_user_level на 10
        $updated = $wpdb->update(
            $wpdb->prefix . 'usermeta',
            array('meta_value' => '10'),
            array(
                'user_id' => $user_id,
                'meta_key' => $wpdb->prefix . 'user_level'
            )
        );
        
        if ($updated !== false) {
            echo "<p class='success'>✓ staging_user_level обновлен на 10</p>";
        } else {
            // Если записи нет, создаем
            $wpdb->insert(
                $wpdb->prefix . 'usermeta',
                array(
                    'user_id' => $user_id,
                    'meta_key' => $wpdb->prefix . 'user_level',
                    'meta_value' => '10'
                ),
                array('%d', '%s', '%s')
            );
            echo "<p class='success'>✓ staging_user_level создан со значением 10</p>";
        }
        
        echo "<h2>4. Проверка результата</h2>";
        
        // Проверяем все записи
        $all_meta = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->prefix}usermeta 
            WHERE user_id = %d
            AND (meta_key LIKE '%capabilities%' OR meta_key LIKE '%user_level%')
            ORDER BY meta_key
        ", $user_id));
        
        echo "<pre>";
        foreach ($all_meta as $meta) {
            echo htmlspecialchars($meta->meta_key) . " = " . htmlspecialchars($meta->meta_value) . "\n";
        }
        echo "</pre>";
        
        // Проверяем правильные записи
        $caps = $wpdb->get_var($wpdb->prepare("
            SELECT meta_value 
            FROM {$wpdb->prefix}usermeta 
            WHERE user_id = %d 
            AND meta_key = %s
        ", $user_id, $wpdb->prefix . 'capabilities'));
        
        $level = $wpdb->get_var($wpdb->prepare("
            SELECT meta_value 
            FROM {$wpdb->prefix}usermeta 
            WHERE user_id = %d 
            AND meta_key = %s
        ", $user_id, $wpdb->prefix . 'user_level'));
        
        if ($caps && stripos($caps, 'administrator') !== false && $level == '10') {
            echo "<p class='success'>✓ Все правильно! capabilities и user_level установлены</p>";
        } else {
            echo "<p class='error'>✗ Что-то не так. Проверьте записи выше.</p>";
        }
        
        echo "<h2>5. Очистка кеша и проверка прав</h2>";
        
        // Очищаем весь кеш
        wp_cache_flush();
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');
        clean_user_cache($user_id);
        
        echo "<p class='success'>✓ Кеш полностью очищен</p>";
        
        // Перезагружаем пользователя
        $user_obj = get_user_by('ID', $user_id);
        
        if ($user_obj) {
            // Принудительно устанавливаем роль
            $user_obj->set_role('administrator');
            
            // Очищаем кеш снова
            wp_cache_delete($user_id, 'users');
            clean_user_cache($user_id);
            
            // Проверяем права
            $can_manage = user_can($user_id, 'manage_options');
            $is_admin = user_can($user_id, 'administrator');
            
            echo "<p>can('manage_options'): " . ($can_manage ? 'ДА ✓' : 'НЕТ ✗') . "</p>";
            echo "<p>can('administrator'): " . ($is_admin ? 'ДА ✓' : 'НЕТ ✗') . "</p>";
            
            if ($can_manage) {
                echo "<p class='success'>✓ Пользователь имеет права manage_options! Теперь можно войти в админку.</p>";
                echo "<p><a href='" . admin_url('index.php') . "' style='display:inline-block;padding:15px 30px;background:#46b450;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>Перейти в админку</a></p>";
            } else {
                echo "<p class='error'>✗ Все еще нет прав manage_options.</p>";
                echo "<p>Возможно, есть фильтр, который блокирует права. Проверьте плагины или functions.php.</p>";
            }
        }
        ?>

    </div>
</body>
</html>

