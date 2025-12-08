<?php
/**
 * Тест подключения к базе данных staging
 * Загрузите этот файл на сервер и откройте в браузере
 */

// Настройки БД
$db_name = 'u850527203_stg';
$db_user = 'u850527203_stg';
$db_pass = 'hiLKov15!';
$db_host = 'localhost';

echo "<h1>Тест подключения к БД</h1>";

// Тест 1: Подключение к MySQL
echo "<h2>1. Подключение к MySQL:</h2>";
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    echo "<p style='color: red;'>❌ Ошибка подключения: " . $mysqli->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Подключение успешно!</p>";
}

// Тест 2: Проверка таблиц
echo "<h2>2. Проверка таблиц с префиксом staging_:</h2>";
$result = $mysqli->query("SHOW TABLES LIKE 'staging_%'");
if ($result) {
    $count = $result->num_rows;
    echo "<p style='color: green;'>✅ Найдено таблиц: $count</p>";
    if ($count > 0) {
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>❌ Ошибка: " . $mysqli->error . "</p>";
}

// Тест 3: Проверка опций WordPress
echo "<h2>3. Проверка опций WordPress:</h2>";
$result = $mysqli->query("SELECT option_name, option_value FROM staging_options WHERE option_name IN ('siteurl', 'home')");
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><strong>" . $row['option_name'] . ":</strong> " . $row['option_value'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ Ошибка: " . $mysqli->error . "</p>";
}

// Тест 4: Проверка пользователей
echo "<h2>4. Проверка пользователей:</h2>";
$result = $mysqli->query("SELECT COUNT(*) as count FROM staging_users");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p>Пользователей в БД: " . $row['count'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Ошибка: " . $mysqli->error . "</p>";
}

$mysqli->close();
?>

