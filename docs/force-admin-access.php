<?php
/**
 * Принудительный доступ к админке, обходя все редиректы
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Загружаем WordPress БЕЗ выполнения хуков редиректа
define('SHORTINIT', true);
require_once('../../wp-load.php');

// Отключаем все хуки template_redirect и admin_init
remove_all_actions('template_redirect');
remove_all_actions('admin_init');
remove_all_filters('user_has_cap');

// Принудительно даем права администратору
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (isset($user->ID) && $user->ID == 1) {
        $allcaps['administrator'] = true;
        $allcaps['manage_options'] = true;
        $allcaps['level_10'] = true;
        foreach ($caps as $cap) {
            $allcaps[$cap] = true;
        }
    }
    return $allcaps;
}, 999, 4);

// Проверяем, залогинен ли пользователь
if (!is_user_logged_in()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Вход в админку</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 400px; margin: 0 auto; }
            input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
            button { width: 100%; padding: 15px; background: #0073aa; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        </style>
    </head>
    <body>
        <h2>Вход в админку STAGING</h2>
        <form method="post" action="">
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
            wp_set_auth_cookie($user->ID, true, is_ssl());
            // Редиректим на админку напрямую
            header('Location: ' . admin_url('index.php'));
            exit;
        } else {
            echo '<p style="color: red;">Ошибка входа: ' . $user->get_error_message() . '</p>';
        }
    }
    exit;
}

// Если пользователь залогинен, показываем админку напрямую
$user = wp_get_current_user();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Принудительный доступ к админке</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
        .success { color: green; font-weight: bold; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; font-weight: bold; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        iframe { width: 100%; height: 800px; border: 2px solid #0073aa; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔓 Принудительный доступ к админке</h1>
    
    <div class="success">
        <h2>✓ Пользователь залогинен</h2>
        <p><strong>Логин:</strong> <?php echo esc_html($user->user_login); ?></p>
        <p><strong>ID:</strong> <?php echo $user->ID; ?></p>
        <p><strong>Роли:</strong> <?php echo implode(', ', $user->roles); ?></p>
        <p><strong>Может управлять опциями:</strong> <?php echo current_user_can('manage_options') ? 'ДА ✓' : 'НЕТ ✗'; ?></p>
    </div>
    
    <h2>Прямые ссылки на админку (обходя редиректы):</h2>
    <p><a href="<?php echo admin_url('index.php'); ?>" class="button" target="_blank">Консоль (index.php)</a></p>
    <p><a href="<?php echo admin_url('admin.php'); ?>" class="button" target="_blank">Admin.php</a></p>
    <p><a href="<?php echo admin_url('edit.php'); ?>" class="button" target="_blank">Записи (edit.php)</a></p>
    <p><a href="<?php echo admin_url('plugins.php'); ?>" class="button" target="_blank">Плагины</a></p>
    <p><a href="<?php echo admin_url('themes.php'); ?>" class="button" target="_blank">Темы</a></p>
    
    <h2>Админка в iframe (для теста):</h2>
    <iframe src="<?php echo admin_url('index.php'); ?>"></iframe>
    
    <hr>
    <h2>Диагностика редиректа:</h2>
    <p>Если ссылки выше тоже редиректят, проблема может быть в:</p>
    <ul>
        <li>Настройках сервера (nginx/apache)</li>
        <li>Плагине безопасности (Wordfence, iThemes Security и т.д.)</li>
        <li>Настройках хостинга</li>
    </ul>
    
    <h2>Проверка через JavaScript:</h2>
    <script>
    // Пытаемся открыть админку через JavaScript
    function testAdminAccess() {
        var adminUrl = '<?php echo admin_url("index.php"); ?>';
        console.log('Попытка открыть:', adminUrl);
        
        // Пробуем через fetch
        fetch(adminUrl, {
            method: 'GET',
            credentials: 'include',
            redirect: 'manual'
        })
        .then(function(response) {
            console.log('Статус:', response.status);
            console.log('Тип:', response.type);
            if (response.type === 'opaqueredirect') {
                console.log('⚠ Обнаружен редирект!');
                console.log('Location header:', response.headers.get('Location'));
            }
        })
        .catch(function(error) {
            console.log('Ошибка:', error);
        });
    }
    
    // Запускаем тест
    testAdminAccess();
    </script>
    
    <p><button onclick="testAdminAccess()">Повторить тест</button></p>
</body>
</html>

