<?php
/**
 * Временное отключение всех плагинов для диагностики
 */
require_once('../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Доступ запрещен. Войдите как администратор.');
}

global $wpdb;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отключение плагинов</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        .button-success { background: #28a745; }
        .button-secondary { background: #6c757d; }
    </style>
</head>
<body>
    <h1>Отключение плагинов для диагностики</h1>
    
    <?php
    $action = $_GET['action'] ?? '';
    
    if ($action === 'disable') {
        // Сохраняем текущий список плагинов
        $active_plugins = get_option('active_plugins', array());
        update_option('gustolocal_backup_active_plugins', $active_plugins);
        
        // Отключаем все плагины
        update_option('active_plugins', array());
        
        echo '<div class="success">';
        echo '<h2>✓ Все плагины отключены!</h2>';
        echo '<p>Сохранено плагинов: ' . count($active_plugins) . '</p>';
        echo '<p><strong>Теперь попробуйте зайти в админку:</strong></p>';
        echo '<p><a href="' . admin_url() . '" class="button button-success" target="_blank">Открыть админку</a></p>';
        echo '<p><a href="?action=restore" class="button button-secondary">Восстановить плагины</a></p>';
        echo '</div>';
        
    } elseif ($action === 'restore') {
        // Восстанавливаем плагины
        $backup = get_option('gustolocal_backup_active_plugins', array());
        if (!empty($backup)) {
            update_option('active_plugins', $backup);
            delete_option('gustolocal_backup_active_plugins');
            echo '<div class="success">';
            echo '<h2>✓ Плагины восстановлены!</h2>';
            echo '<p>Восстановлено плагинов: ' . count($backup) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<h2>✗ Резервная копия не найдена</h2>';
            echo '</div>';
        }
        
    } else {
        $active_plugins = get_option('active_plugins', array());
        $backup = get_option('gustolocal_backup_active_plugins', false);
        
        if ($backup) {
            echo '<div class="warning">';
            echo '<h2>⚠ Внимание!</h2>';
            echo '<p>Есть сохраненная резервная копия плагинов. Вы можете восстановить их.</p>';
            echo '<p><a href="?action=restore" class="button button-secondary">Восстановить плагины</a></p>';
            echo '</div>';
        }
        
        echo '<div class="info">';
        echo '<h2>Текущие активные плагины:</h2>';
        if (empty($active_plugins)) {
            echo '<p>Нет активных плагинов</p>';
        } else {
            echo '<ul>';
            foreach ($active_plugins as $plugin) {
                $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
                if (file_exists($plugin_path)) {
                    $plugin_data = get_plugin_data($plugin_path);
                    echo '<li>' . esc_html($plugin_data['Name'] ?: $plugin) . '</li>';
                } else {
                    echo '<li>' . esc_html($plugin) . ' (файл не найден)</li>';
                }
            }
            echo '</ul>';
        }
        echo '</div>';
        
        echo '<div class="warning">';
        echo '<h2>⚠ ВНИМАНИЕ!</h2>';
        echo '<p>Это действие отключит <strong>ВСЕ</strong> плагины, включая WooCommerce и Weekly Meal Builder.</p>';
        echo '<p>Это нужно для диагностики проблемы с админкой.</p>';
        echo '<p>После проверки вы сможете восстановить все плагины одной кнопкой.</p>';
        echo '<p><a href="?action=disable" class="button" onclick="return confirm(\'Вы уверены? Это отключит все плагины.\')">Отключить все плагины</a></p>';
        echo '</div>';
    }
    ?>
    
    <hr style="margin: 30px 0;">
    <p style="color: #666; font-size: 12px;">
        <strong>Безопасность:</strong> После использования удалите этот файл через FTP.
    </p>
</body>
</html>

