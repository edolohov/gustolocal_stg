<?php
/**
 * Скрипт для сброса пароля администратора
 * Запустите через браузер: https://staging.gustolocal.es/docs/reset-admin-password.php
 * ВАЖНО: Удалите этот файл после использования!
 */

// Загружаем WordPress
$wp_load_paths = array(
    dirname(__FILE__) . '/wp-load.php',
    dirname(__FILE__) . '/../wp-load.php',
    dirname(__FILE__) . '/../../wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
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
    die('Не удалось загрузить WordPress. Проверьте путь к wp-load.php');
}

// Новый пароль
$new_password = '12345';

// Находим администратора (обычно ID=1 или пользователь с ролью administrator)
$admin_user = null;

// Сначала пробуем найти по ID=1
$admin_user = get_user_by('ID', 1);

// Если не нашли, ищем первого администратора
if (!$admin_user || !in_array('administrator', $admin_user->roles)) {
    $admins = get_users(array('role' => 'administrator', 'number' => 1));
    if (!empty($admins)) {
        $admin_user = $admins[0];
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля администратора</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Сброс пароля администратора</h1>
    
    <?php if (!$admin_user): ?>
        <p class="error">✗ Администратор не найден!</p>
        <p>Проверьте базу данных вручную.</p>
    <?php else: ?>
        <div class="info">
            <h2>Информация о пользователе:</h2>
            <p><strong>ID:</strong> <?php echo $admin_user->ID; ?></p>
            <p><strong>Логин:</strong> <?php echo esc_html($admin_user->user_login); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($admin_user->user_email); ?></p>
            <p><strong>Роли:</strong> <?php echo implode(', ', $admin_user->roles); ?></p>
        </div>
        
        <?php
        // Обновляем пароль
        if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            $result = wp_set_password($new_password, $admin_user->ID);
            
            if ($result) {
                echo '<div class="success">';
                echo '<h2>✓ Пароль успешно изменен!</h2>';
                echo '<p><strong>Новый пароль:</strong> ' . esc_html($new_password) . '</p>';
                echo '<p><strong>Логин:</strong> ' . esc_html($admin_user->user_login) . '</p>';
                echo '<p><a href="' . admin_url() . '" style="display:inline-block;padding:15px 30px;background:#46b450;color:white;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:20px;">Войти в админку</a></p>';
                echo '</div>';
                
                // Пытаемся удалить файл для безопасности
                echo '<div class="info" style="margin-top: 30px; background: #fff3cd; border: 1px solid #ffc107;">';
                echo '<p><strong>⚠ ВАЖНО:</strong> Удалите этот файл (reset-admin-password.php) для безопасности!</p>';
                echo '</div>';
            } else {
                echo '<p class="error">✗ Ошибка при изменении пароля</p>';
            }
        } else {
            ?>
            <div class="info" style="background: #fff3cd; border: 1px solid #ffc107;">
                <h2>⚠ Подтверждение</h2>
                <p>Вы собираетесь изменить пароль для пользователя <strong><?php echo esc_html($admin_user->user_login); ?></strong></p>
                <p><strong>Новый пароль будет:</strong> <?php echo esc_html($new_password); ?></p>
                <p><a href="?confirm=yes" style="display:inline-block;padding:15px 30px;background:#dc3545;color:white;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:20px;">Подтвердить и изменить пароль</a></p>
            </div>
            <?php
        }
        ?>
    <?php endif; ?>
    
    <hr style="margin: 30px 0;">
    <p style="color: #666; font-size: 12px;">
        <strong>Безопасность:</strong> После использования удалите этот файл через FTP или файловый менеджер.
    </p>
</body>
</html>

