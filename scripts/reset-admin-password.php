<?php
/**
 * Сброс пароля администратора
 * ВАЖНО: Удалите этот файл после использования!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Простая защита - измените этот ключ
$secret_key = 'CHANGE_THIS_TO_SOMETHING_SECRET_12345';
$key = isset($_GET['key']) ? $_GET['key'] : '';

if ($key !== $secret_key) {
    die('Неверный ключ доступа. Откройте файл и измените $secret_key.');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Сброс пароля администратора</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.ok { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
input[type='text'], input[type='password'] { width: 100%; padding: 8px; margin: 5px 0; }
button { background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer; }
button:hover { background: #005177; }
</style></head><body><div class='container'>";

echo "<h1>Сброс пароля администратора</h1>";

// Загружаем WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-load.php');

$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$username = isset($_POST['username']) ? $_POST['username'] : 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($new_password)) {
    $user = get_user_by('login', $username);
    
    if (!$user) {
        echo "<p class='error'>❌ Пользователь '$username' не найден!</p>";
    } else {
        // Устанавливаем новый пароль
        wp_set_password($new_password, $user->ID);
        
        echo "<p class='ok'>✅ Пароль для пользователя '$username' успешно изменен!</p>";
        echo "<p><strong>Новый пароль:</strong> " . htmlspecialchars($new_password) . "</p>";
        echo "<p><a href='/wp-admin'>Перейти в админку</a></p>";
        echo "<hr>";
        echo "<p style='color: red;'><strong>ВАЖНО: Удалите этот файл (reset-admin-password.php) с сервера!</strong></p>";
    }
}

// Форма для сброса пароля
echo "<h2>Установить новый пароль</h2>";
echo "<form method='POST'>";
echo "<p><label>Логин пользователя:</label><br>";
echo "<input type='text' name='username' value='$username' required></p>";
echo "<p><label>Новый пароль:</label><br>";
echo "<input type='password' name='new_password' placeholder='Введите новый пароль' required></p>";
echo "<button type='submit'>Изменить пароль</button>";
echo "</form>";

// Список всех пользователей
echo "<h2>Все пользователи:</h2>";
$users = get_users();
echo "<ul>";
foreach ($users as $user) {
    $is_admin = user_can($user->ID, 'administrator');
    $badge = $is_admin ? ' <span style="color: green;">[Админ]</span>' : '';
    echo "<li>{$user->user_login} ({$user->user_email})$badge</li>";
}
echo "</ul>";

echo "</div></body></html>";
?>

