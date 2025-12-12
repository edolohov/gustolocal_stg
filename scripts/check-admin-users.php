<?php
/**
 * Проверка пользователей админки
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Проверка пользователей админки</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
.ok { color: #46b450; font-weight: bold; }
.error { color: #dc3232; font-weight: bold; }
.warning { color: #ffb900; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #0073aa; color: white; }
pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
</style></head><body><div class='container'>";

echo "<h1>Проверка пользователей админки</h1>";

// Загружаем WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-load.php');

global $wpdb;

// Проверка подключения к БД
if (!$wpdb) {
    echo "<p class='error'>❌ Не удалось подключиться к базе данных</p>";
    exit;
}

echo "<p class='ok'>✅ Подключение к БД успешно</p>";
echo "<p>Префикс таблиц: <code>" . $wpdb->prefix . "</code></p>";

// Получаем всех пользователей
$users = $wpdb->get_results("
    SELECT u.ID, u.user_login, u.user_email, u.user_registered,
           m.meta_value as capabilities
    FROM {$wpdb->prefix}users u
    LEFT JOIN {$wpdb->prefix}usermeta m ON u.ID = m.user_id AND m.meta_key = '{$wpdb->prefix}capabilities'
    ORDER BY u.ID
");

if (!$users) {
    echo "<p class='error'>❌ Не удалось получить пользователей</p>";
    echo "<p>Ошибка: " . $wpdb->last_error . "</p>";
    exit;
}

echo "<h2>Пользователи в базе данных (" . count($users) . "):</h2>";

echo "<table>";
echo "<tr><th>ID</th><th>Логин</th><th>Email</th><th>Дата регистрации</th><th>Права</th><th>Статус</th></tr>";

foreach ($users as $user) {
    $has_admin = false;
    $capabilities = maybe_unserialize($user->capabilities);
    
    if (is_array($capabilities)) {
        $has_admin = isset($capabilities['administrator']) && $capabilities['administrator'];
    }
    
    $status_class = $has_admin ? 'ok' : 'warning';
    $status_text = $has_admin ? '✅ Администратор' : '⚠️ Не админ';
    
    echo "<tr>";
    echo "<td>{$user->ID}</td>";
    echo "<td><strong>{$user->user_login}</strong></td>";
    echo "<td>{$user->user_email}</td>";
    echo "<td>{$user->user_registered}</td>";
    echo "<td><pre>" . print_r($capabilities, true) . "</pre></td>";
    echo "<td class='$status_class'>$status_text</td>";
    echo "</tr>";
}

echo "</table>";

// Проверка конкретного пользователя admin
echo "<h2>Проверка пользователя 'admin':</h2>";

$admin_user = get_user_by('login', 'admin');
if ($admin_user) {
    echo "<p class='ok'>✅ Пользователь 'admin' найден</p>";
    echo "<ul>";
    echo "<li>ID: {$admin_user->ID}</li>";
    echo "<li>Email: {$admin_user->user_email}</li>";
    echo "<li>Права администратора: " . (user_can($admin_user->ID, 'administrator') ? '✅ Да' : '❌ Нет') . "</li>";
    echo "</ul>";
    
    // Проверка мета-данных
    $user_meta = get_user_meta($admin_user->ID);
    echo "<h3>Мета-данные пользователя:</h3>";
    echo "<pre>";
    foreach ($user_meta as $key => $value) {
        if (strpos($key, 'capabilities') !== false || strpos($key, 'user_level') !== false) {
            echo "$key: " . print_r($value, true) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p class='error'>❌ Пользователь 'admin' НЕ найден!</p>";
    echo "<p>Попробуйте войти с другим логином или создайте нового администратора.</p>";
}

// Проверка URL сайта
echo "<h2>Проверка URL:</h2>";
$site_url = get_option('siteurl');
$home_url = get_option('home');
echo "<p>Site URL: <code>$site_url</code></p>";
echo "<p>Home URL: <code>$home_url</code></p>";

if (strpos($site_url, 'staging') === false && strpos($home_url, 'staging') === false) {
    echo "<p class='warning'>⚠️ URL указывают не на staging! Это может вызывать проблемы с авторизацией.</p>";
}

echo "</div></body></html>";
?>

