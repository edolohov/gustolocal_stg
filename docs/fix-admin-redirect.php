<?php
/**
 * Исправление редиректа админки
 */
require_once('../../wp-load.php');

if (!is_user_logged_in()) {
    // Показываем форму входа
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Вход для исправления</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 400px; margin: 0 auto; }
            input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
            button { width: 100%; padding: 15px; background: #0073aa; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        </style>
    </head>
    <body>
        <h2>Войдите для исправления</h2>
        <form method="post">
            <input type="text" name="log" placeholder="Логин" required>
            <input type="password" name="pwd" placeholder="Пароль" required>
            <input type="hidden" name="action" value="login">
            <button type="submit">Войти</button>
        </form>
    </body>
    </html>
    <?php
    
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $user = wp_authenticate($_POST['log'], $_POST['pwd']);
        if (!is_wp_error($user)) {
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo '<p style="color: red;">Ошибка входа: ' . $user->get_error_message() . '</p>';
        }
    }
    exit;
}

if (!current_user_can('manage_options')) {
    die('Доступ запрещен');
}

global $wpdb;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Исправление редиректа админки</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
        .success { color: green; font-weight: bold; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; font-weight: bold; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        .button-danger { background: #dc3545; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Исправление редиректа админки</h1>
    
    <?php
    $action = $_GET['action'] ?? '';
    
    if ($action === 'disable_checkout_editor') {
        // Отключаем плагин Checkout Field Editor
        $active_plugins = get_option('active_plugins', array());
        $new_plugins = array();
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo-checkout-field-editor-pro') === false) {
                $new_plugins[] = $plugin;
            }
        }
        update_option('active_plugins', $new_plugins);
        
        echo '<div class="success">';
        echo '<h2>✓ Плагин Checkout Field Editor отключен!</h2>';
        echo '<p>Теперь попробуйте зайти в админку:</p>';
        echo '<p><a href="' . admin_url() . '" class="button" target="_blank">Открыть админку</a></p>';
        echo '</div>';
        
    } elseif ($action === 'fix_cookie_domain') {
        // Исправляем COOKIE_DOMAIN в wp-config.php
        $wp_config = ABSPATH . 'wp-config.php';
        if (file_exists($wp_config)) {
            $content = file_get_contents($wp_config);
            
            // Заменяем COOKIE_DOMAIN для staging
            $content = preg_replace(
                "/define\s*\(\s*['\"]COOKIE_DOMAIN['\"]\s*,\s*['\"].*?['\"]\s*\)/i",
                "define('COOKIE_DOMAIN', '.staging.gustolocal.es')",
                $content
            );
            
            // Если COOKIE_DOMAIN не найден, добавляем перед require_once ABSPATH . 'wp-settings.php';
            if (!preg_match("/define\s*\(\s*['\"]COOKIE_DOMAIN['\"]/i", $content)) {
                $content = str_replace(
                    "require_once ABSPATH . 'wp-settings.php';",
                    "define('COOKIE_DOMAIN', '.staging.gustolocal.es');\nrequire_once ABSPATH . 'wp-settings.php';",
                    $content
                );
            }
            
            // Сохраняем резервную копию
            file_put_contents($wp_config . '.backup', file_get_contents($wp_config));
            
            if (file_put_contents($wp_config, $content)) {
                echo '<div class="success">';
                echo '<h2>✓ COOKIE_DOMAIN исправлен!</h2>';
                echo '<p>Создана резервная копия: wp-config.php.backup</p>';
                echo '<p><strong>ВАЖНО:</strong> Выйдите из системы и войдите заново, чтобы куки обновились.</p>';
                echo '<p><a href="' . wp_logout_url(home_url()) . '" class="button">Выйти и войти заново</a></p>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<h2>✗ Не удалось записать wp-config.php</h2>';
                echo '<p>Проверьте права доступа к файлу.</p>';
                echo '</div>';
            }
        } else {
            echo '<div class="error">';
            echo '<h2>✗ Файл wp-config.php не найден</h2>';
            echo '</div>';
        }
        
    } elseif ($action === 'remove_redirect_hook') {
        // Пытаемся удалить хук редиректа через базу данных
        // Это сложнее, но можно попробовать через опции
        echo '<div class="warning">';
        echo '<h2>⚠ Удаление хука через БД</h2>';
        echo '<p>Этот метод может не сработать. Лучше отключить плагин.</p>';
        echo '</div>';
        
    } else {
        // Показываем диагностику и варианты исправления
        $active_plugins = get_option('active_plugins', array());
        $has_checkout_editor = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo-checkout-field-editor-pro') !== false) {
                $has_checkout_editor = true;
                break;
            }
        }
        
        echo '<h2>Найденные проблемы:</h2>';
        
        if ($has_checkout_editor) {
            echo '<div class="warning">';
            echo '<h3>1. Плагин Checkout Field Editor активен</h3>';
            echo '<p>Этот плагин имеет хук <code>THWCFD_Admin::redirect_to_landing_page</code>, который может редиректить админку.</p>';
            echo '<p><a href="?action=disable_checkout_editor" class="button button-danger" onclick="return confirm(\'Отключить плагин Checkout Field Editor?\')">Отключить плагин</a></p>';
            echo '</div>';
        }
        
        // Проверяем COOKIE_DOMAIN
        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN === '.gustolocal.es') {
            echo '<div class="warning">';
            echo '<h3>2. COOKIE_DOMAIN установлен для основного домена</h3>';
            echo '<p>Текущее значение: <code>' . COOKIE_DOMAIN . '</code></p>';
            echo '<p>Для staging поддомена это может вызывать проблемы с куками.</p>';
            echo '<p><a href="?action=fix_cookie_domain" class="button" onclick="return confirm(\'Изменить COOKIE_DOMAIN на .staging.gustolocal.es?\')">Исправить COOKIE_DOMAIN</a></p>';
            echo '</div>';
        }
        
        // Проверяем функцию wp_redirect_admin_locations
        global $wp_filter;
        if (isset($wp_filter['template_redirect'])) {
            $has_redirect = false;
            foreach ($wp_filter['template_redirect']->callbacks as $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_string($callback['function']) && $callback['function'] === 'wp_redirect_admin_locations') {
                        $has_redirect = true;
                        break 2;
                    }
                }
            }
            if ($has_redirect) {
                echo '<div class="warning">';
                echo '<h3>3. Найден хук wp_redirect_admin_locations</h3>';
                echo '<p>Это стандартная функция WordPress, которая может редиректить админку при определенных условиях.</p>';
                echo '<p>Обычно это не проблема, но может быть вызвано неправильными настройками.</p>';
                echo '</div>';
            }
        }
        
        echo '<hr>';
        echo '<h2>Рекомендуемый порядок действий:</h2>';
        echo '<ol>';
        echo '<li>Отключите плагин Checkout Field Editor (если он активен)</li>';
        echo '<li>Исправьте COOKIE_DOMAIN для staging</li>';
        echo '<li>Выйдите из системы и войдите заново</li>';
        echo '<li>Попробуйте зайти в админку</li>';
        echo '</ol>';
    }
    ?>
    
    <hr style="margin: 30px 0;">
    <p><a href="<?php echo admin_url(); ?>" class="button" target="_blank">Попробовать открыть админку</a></p>
</body>
</html>

