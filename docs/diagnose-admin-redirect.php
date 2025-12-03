<?php
/**
 * Диагностика редиректа админки
 */
require_once('../../wp-load.php');

if (!is_user_logged_in()) {
    die('Войдите в систему сначала');
}

$user = wp_get_current_user();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Диагностика редиректа админки</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Диагностика редиректа админки</h1>
    
    <h2>1. Информация о пользователе</h2>
    <table>
        <tr><th>ID</th><td><?php echo $user->ID; ?></td></tr>
        <tr><th>Логин</th><td><?php echo esc_html($user->user_login); ?></td></tr>
        <tr><th>Email</th><td><?php echo esc_html($user->user_email); ?></td></tr>
        <tr><th>Роли</th><td><?php echo implode(', ', $user->roles); ?></td></tr>
        <tr><th>Может управлять опциями?</th>
            <td class="<?php echo current_user_can('manage_options') ? 'success' : 'error'; ?>">
                <?php echo current_user_can('manage_options') ? 'ДА ✓' : 'НЕТ ✗'; ?>
            </td>
        </tr>
        <tr><th>Может войти в админку?</th>
            <td class="<?php echo current_user_can('read') ? 'success' : 'error'; ?>">
                <?php echo current_user_can('read') ? 'ДА ✓' : 'НЕТ ✗'; ?>
            </td>
        </tr>
    </table>
    
    <h2>2. Проверка URL админки</h2>
    <?php
    $admin_url = admin_url();
    $admin_index = admin_url('index.php');
    ?>
    <p><strong>admin_url():</strong> <a href="<?php echo esc_url($admin_url); ?>"><?php echo esc_html($admin_url); ?></a></p>
    <p><strong>admin_url('index.php'):</strong> <a href="<?php echo esc_url($admin_index); ?>"><?php echo esc_html($admin_index); ?></a></p>
    
    <h2>3. Активные фильтры и хуки</h2>
    <h3>Фильтры, связанные с редиректами:</h3>
    <pre><?php
    global $wp_filter;
    $redirect_filters = array();
    foreach ($wp_filter as $hook => $filters) {
        if (stripos($hook, 'redirect') !== false || 
            stripos($hook, 'login') !== false ||
            stripos($hook, 'admin') !== false) {
            $redirect_filters[$hook] = $filters;
        }
    }
    print_r($redirect_filters);
    ?></pre>
    
    <h3>Хуки template_redirect:</h3>
    <pre><?php
    if (isset($wp_filter['template_redirect'])) {
        print_r($wp_filter['template_redirect']);
    } else {
        echo 'Нет активных хуков template_redirect';
    }
    ?></pre>
    
    <h2>4. Проверка .htaccess</h2>
    <?php
    $htaccess = ABSPATH . '.htaccess';
    if (file_exists($htaccess)) {
        $content = file_get_contents($htaccess);
        echo '<pre>' . esc_html($content) . '</pre>';
    } else {
        echo '<p class="warning">Файл .htaccess не найден</p>';
    }
    ?>
    
    <h2>5. Проверка wp-config.php</h2>
    <?php
    $wp_config = ABSPATH . 'wp-config.php';
    if (file_exists($wp_config)) {
        $content = file_get_contents($wp_config);
        // Показываем только важные части
        if (preg_match_all('/(define|FORCE_SSL_ADMIN|DISALLOW_FILE_EDIT|WP_DEBUG).*?;/i', $content, $matches)) {
            echo '<pre>' . esc_html(implode("\n", $matches[0])) . '</pre>';
        }
    }
    ?>
    
    <h2>6. Тест прямого доступа</h2>
    <p><a href="<?php echo admin_url('index.php'); ?>" target="_blank">Попробовать открыть админку</a></p>
    
    <h2>7. Проверка плагинов</h2>
    <?php
    $active_plugins = get_option('active_plugins');
    echo '<ul>';
    foreach ($active_plugins as $plugin) {
        echo '<li>' . esc_html($plugin) . '</li>';
    }
    echo '</ul>';
    ?>
    
    <h2>8. Проверка темы</h2>
    <?php
    $theme = wp_get_theme();
    echo '<p><strong>Активная тема:</strong> ' . esc_html($theme->get('Name')) . '</p>';
    echo '<p><strong>Родительская тема:</strong> ' . esc_html($theme->get('Template')) . '</p>';
    ?>
</body>
</html>

