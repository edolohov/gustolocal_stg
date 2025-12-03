<?php
/**
 * Полная диагностика проблемы с админкой
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Загружаем WordPress
$wp_load_paths = array(
    dirname(__FILE__) . '/wp-load.php',
    dirname(__FILE__) . '/../wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php',
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
    die('Не удалось загрузить WordPress');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Полная диагностика админки</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .test-link { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .test-link:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>🔍 Полная диагностика проблемы с админкой</h1>
    
    <?php
    // Проверка 1: Пользователь
    echo '<h2>1. Проверка пользователя</h2>';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        echo '<p class="success">✓ Пользователь залогинен: ' . esc_html($user->user_login) . ' (ID: ' . $user->ID . ')</p>';
        echo '<p>Роли: ' . implode(', ', $user->roles) . '</p>';
        echo '<p>Может управлять опциями: ' . (current_user_can('manage_options') ? '<span class="success">ДА</span>' : '<span class="error">НЕТ</span>') . '</p>';
    } else {
        echo '<p class="error">✗ Пользователь не залогинен</p>';
        echo '<p><a href="' . wp_login_url() . '" class="test-link">Войти в систему</a></p>';
    }
    
    // Проверка 2: URL админки
    echo '<h2>2. Проверка URL админки</h2>';
    $admin_url = admin_url();
    $admin_index = admin_url('index.php');
    echo '<p><strong>admin_url():</strong> <a href="' . esc_url($admin_url) . '" target="_blank">' . esc_html($admin_url) . '</a></p>';
    echo '<p><strong>admin_url(\'index.php\'):</strong> <a href="' . esc_url($admin_index) . '" target="_blank">' . esc_html($admin_index) . '</a></p>';
    
    // Проверка 3: Активные плагины
    echo '<h2>3. Активные плагины</h2>';
    $active_plugins = get_option('active_plugins', array());
    if (empty($active_plugins)) {
        echo '<p class="warning">⚠ Нет активных плагинов</p>';
    } else {
        echo '<table><tr><th>Плагин</th><th>Действие</th></tr>';
        foreach ($active_plugins as $plugin) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
            $plugin_data = get_plugin_data($plugin_path);
            echo '<tr>';
            echo '<td>' . esc_html($plugin_data['Name'] ?: $plugin) . '</td>';
            echo '<td><a href="?deactivate=' . urlencode($plugin) . '" onclick="return confirm(\'Отключить этот плагин?\')">Отключить</a></td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // Обработка отключения плагина
    if (isset($_GET['deactivate']) && current_user_can('manage_options')) {
        $plugin_to_deactivate = sanitize_text_field($_GET['deactivate']);
        deactivate_plugins($plugin_to_deactivate);
        echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<p class="success">✓ Плагин ' . esc_html($plugin_to_deactivate) . ' отключен. <a href="' . remove_query_arg('deactivate') . '">Обновить страницу</a></p>';
        echo '</div>';
    }
    
    // Проверка 4: Хуки template_redirect
    echo '<h2>4. Хуки template_redirect (могут делать редирект)</h2>';
    global $wp_filter;
    if (isset($wp_filter['template_redirect'])) {
        echo '<pre>';
        foreach ($wp_filter['template_redirect']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function_name = 'unknown';
                if (is_string($callback['function'])) {
                    $function_name = $callback['function'];
                } elseif (is_array($callback['function'])) {
                    if (is_object($callback['function'][0])) {
                        $function_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                    } else {
                        $function_name = $callback['function'][0] . '::' . $callback['function'][1];
                    }
                }
                echo "Priority: $priority | Function: $function_name\n";
            }
        }
        echo '</pre>';
    } else {
        echo '<p class="success">✓ Нет активных хуков template_redirect</p>';
    }
    
    // Проверка 5: Хуки admin_init
    echo '<h2>5. Хуки admin_init (могут блокировать доступ)</h2>';
    if (isset($wp_filter['admin_init'])) {
        echo '<pre>';
        foreach ($wp_filter['admin_init']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function_name = 'unknown';
                if (is_string($callback['function'])) {
                    $function_name = $callback['function'];
                } elseif (is_array($callback['function'])) {
                    if (is_object($callback['function'][0])) {
                        $function_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                    } else {
                        $function_name = $callback['function'][0] . '::' . $callback['function'][1];
                    }
                }
                echo "Priority: $priority | Function: $function_name\n";
            }
        }
        echo '</pre>';
    } else {
        echo '<p class="success">✓ Нет активных хуков admin_init</p>';
    }
    
    // Проверка 6: Фильтры user_has_cap
    echo '<h2>6. Фильтры user_has_cap (проверка прав)</h2>';
    if (isset($wp_filter['user_has_cap'])) {
        echo '<pre>';
        foreach ($wp_filter['user_has_cap']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function_name = 'unknown';
                if (is_string($callback['function'])) {
                    $function_name = $callback['function'];
                } elseif (is_array($callback['function'])) {
                    if (is_object($callback['function'][0])) {
                        $function_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                    } else {
                        $function_name = $callback['function'][0] . '::' . $callback['function'][1];
                    }
                } elseif (is_object($callback['function'])) {
                    $function_name = 'Closure/Anonymous';
                }
                echo "Priority: $priority | Function: $function_name\n";
            }
        }
        echo '</pre>';
    } else {
        echo '<p class="success">✓ Нет активных фильтров user_has_cap</p>';
    }
    
    // Проверка 7: Функции в functions.php, которые могут делать редирект
    echo '<h2>7. Поиск функций редиректа в functions.php</h2>';
    $functions_file = get_template_directory() . '/functions.php';
    if (file_exists($functions_file)) {
        $content = file_get_contents($functions_file);
        $redirect_patterns = array(
            'wp_redirect',
            'wp_safe_redirect',
            'header.*Location',
            'exit.*admin',
            'is_admin.*redirect'
        );
        $found = false;
        foreach ($redirect_patterns as $pattern) {
            if (preg_match_all('/' . $pattern . '/i', $content, $matches)) {
                $found = true;
                echo '<p class="warning">⚠ Найдено совпадение: ' . esc_html($pattern) . '</p>';
                // Показываем контекст
                $lines = explode("\n", $content);
                foreach ($lines as $num => $line) {
                    if (preg_match('/' . $pattern . '/i', $line)) {
                        $start = max(0, $num - 3);
                        $end = min(count($lines), $num + 4);
                        echo '<pre>Строки ' . ($start + 1) . '-' . ($end) . ":\n";
                        for ($i = $start; $i < $end; $i++) {
                            $marker = ($i == $num) ? '>>> ' : '    ';
                            echo $marker . ($i + 1) . ': ' . esc_html($lines[$i]) . "\n";
                        }
                        echo '</pre>';
                    }
                }
            }
        }
        if (!$found) {
            echo '<p class="success">✓ Не найдено явных редиректов в functions.php</p>';
        }
    }
    
    // Проверка 8: Тест прямого доступа к админке
    echo '<h2>8. Тесты доступа</h2>';
    echo '<p><a href="' . admin_url('index.php') . '" class="test-link" target="_blank">Попробовать открыть админку</a></p>';
    echo '<p><a href="' . admin_url('admin.php') . '" class="test-link" target="_blank">Попробовать admin.php</a></p>';
    echo '<p><a href="' . admin_url('edit.php') . '" class="test-link" target="_blank">Попробовать edit.php (записи)</a></p>';
    
    // Проверка 9: .htaccess
    echo '<h2>9. Проверка .htaccess</h2>';
    $htaccess = ABSPATH . '.htaccess';
    if (file_exists($htaccess)) {
        $content = file_get_contents($htaccess);
        echo '<pre>' . esc_html($content) . '</pre>';
        if (preg_match('/RewriteRule.*admin/i', $content)) {
            echo '<p class="error">⚠ Найдены правила редиректа для админки в .htaccess</p>';
        } else {
            echo '<p class="success">✓ Нет явных редиректов админки в .htaccess</p>';
        }
    } else {
        echo '<p class="warning">⚠ Файл .htaccess не найден</p>';
    }
    
    // Проверка 10: wp-config.php
    echo '<h2>10. Проверка wp-config.php (важные настройки)</h2>';
    $wp_config = ABSPATH . 'wp-config.php';
    if (file_exists($wp_config)) {
        $content = file_get_contents($wp_config);
        $important_settings = array(
            'FORCE_SSL_ADMIN',
            'DISALLOW_FILE_EDIT',
            'WP_DEBUG',
            'WP_DEBUG_LOG',
            'COOKIE_DOMAIN',
            'ADMIN_COOKIE_PATH'
        );
        echo '<table><tr><th>Настройка</th><th>Значение</th></tr>';
        foreach ($important_settings as $setting) {
            if (preg_match("/define\s*\(\s*['\"]" . $setting . "['\"]\s*,\s*(.+?)\s*\)/i", $content, $matches)) {
                echo '<tr><td>' . esc_html($setting) . '</td><td>' . esc_html(trim($matches[1])) . '</td></tr>';
            }
        }
        echo '</table>';
    }
    
    // Проверка 11: База данных - права пользователя
    echo '<h2>11. Проверка прав пользователя в БД</h2>';
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        global $wpdb;
        $user_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE '%%capabilities%%'",
            $user->ID
        ));
        if (!empty($user_meta)) {
            echo '<pre>';
            foreach ($user_meta as $meta) {
                echo esc_html($meta->meta_key) . ': ' . esc_html($meta->meta_value) . "\n";
            }
            echo '</pre>';
        } else {
            echo '<p class="error">✗ Не найдены метаданные о правах пользователя</p>';
        }
    }
    
    ?>
    
    <hr style="margin: 30px 0;">
    <h2>💡 Рекомендации</h2>
    <ol>
        <li>Попробуйте отключить все плагины (кроме WooCommerce) через таблицу выше</li>
        <li>Проверьте, нет ли в functions.php функций, которые делают редирект при заходе в админку</li>
        <li>Попробуйте открыть админку в режиме инкогнито</li>
        <li>Очистите кеш браузера</li>
        <li>Проверьте, нет ли редиректов на уровне сервера (в настройках хостинга)</li>
    </ol>
</body>
</html>

