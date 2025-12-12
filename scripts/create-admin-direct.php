<?php
/**
 * Создание пользователя admin с паролем hiLKov15! напрямую через WordPress
 * Загрузите на сервер и откройте в браузере
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Создание пользователя admin</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
.ok { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
</style></head><body><div class='container'>";

echo "<h1>Создание пользователя admin</h1>";

// Загружаем WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-load.php');

$username = 'admin';
$password = 'hiLKov15!';
$email = 'admin@gustolocal.es';

// Проверяем, существует ли пользователь
$user = get_user_by('login', $username);

if ($user) {
    echo "<p>Пользователь '$username' уже существует.</p>";
    
    // Обновляем пароль
    wp_set_password($password, $user->ID);
    
    // Устанавливаем права администратора
    $user->set_role('administrator');
    
    echo "<p class='ok'>✅ Пароль обновлен, права администратора установлены!</p>";
} else {
    // Создаем нового пользователя
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        echo "<p class='error'>❌ Ошибка создания пользователя: " . $user_id->get_error_message() . "</p>";
    } else {
        // Устанавливаем права администратора
        $user = new WP_User($user_id);
        $user->set_role('administrator');
        
        echo "<p class='ok'>✅ Пользователь '$username' создан успешно!</p>";
    }
}

// Проверяем результат
$user = get_user_by('login', $username);
if ($user) {
    echo "<h2>Информация о пользователе:</h2>";
    echo "<ul>";
    echo "<li>ID: {$user->ID}</li>";
    echo "<li>Логин: {$user->user_login}</li>";
    echo "<li>Email: {$user->user_email}</li>";
    echo "<li>Права администратора: " . (user_can($user->ID, 'administrator') ? '✅ Да' : '❌ Нет') . "</li>";
    echo "</ul>";
    
    echo "<p class='ok' style='font-size: 18px;'>✅ Готово! Теперь вы можете войти в админку:</p>";
    echo "<p><a href='/wp-admin' style='font-size: 16px; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;'>Войти в админку</a></p>";
    echo "<p><strong>Логин:</strong> admin<br><strong>Пароль:</strong> hiLKov15!</p>";
} else {
    echo "<p class='error'>❌ Не удалось создать/найти пользователя</p>";
}

echo "<hr>";
echo "<p style='color: red;'><strong>ВАЖНО: Удалите этот файл (create-admin-direct.php) с сервера после использования!</strong></p>";

echo "</div></body></html>";
?>

