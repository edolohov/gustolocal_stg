<?php
/**
 * Скрипт для настройки staging из продакшна
 * Создает все необходимые таблицы для обратной связи
 */
require_once('../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Доступ запрещен. Войдите как администратор.');
}

global $wpdb;

echo '<h1>Настройка Staging из Production</h1>';
echo '<style>body { font-family: Arial, sans-serif; padding: 20px; } .success { color: green; } .error { color: red; } pre { background: #f5f5f5; padding: 10px; }</style>';

$charset_collate = $wpdb->get_charset_collate();
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// 1. Таблица для обычных отзывов
$table_name = $wpdb->prefix . 'dish_feedback';
$sql = "CREATE TABLE IF NOT EXISTS $table_name (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    token varchar(64) NOT NULL,
    order_id bigint(20) UNSIGNED NOT NULL,
    customer_name varchar(255) DEFAULT '',
    dish_name varchar(255) NOT NULL,
    dish_unit varchar(100) DEFAULT '',
    rating int(1) NOT NULL COMMENT '1=😞, 2=😐, 3=😊, 4=😍',
    comment text DEFAULT '',
    general_comment text DEFAULT '',
    shared_instagram tinyint(1) DEFAULT 0,
    shared_google tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY token (token),
    KEY order_id (order_id),
    KEY dish_name (dish_name)
) $charset_collate;";

dbDelta($sql);
echo '<h2>1. Таблица dish_feedback</h2>';
if ($wpdb->last_error) {
    echo '<p class="error">Ошибка: ' . esc_html($wpdb->last_error) . '</p>';
} else {
    echo '<p class="success">✓ Создана или уже существует</p>';
}

// 2. Таблица для кастомных запросов
$custom_requests_table = $wpdb->prefix . 'custom_feedback_requests';
$sql_requests = "CREATE TABLE IF NOT EXISTS $custom_requests_table (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    token varchar(100) NOT NULL,
    client_name varchar(255) NOT NULL,
    client_contact varchar(255) DEFAULT '',
    dishes longtext NOT NULL,
    status varchar(20) DEFAULT 'pending',
    general_comment text DEFAULT '',
    shared_instagram tinyint(1) DEFAULT 0,
    shared_google tinyint(1) DEFAULT 0,
    submitted_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY token (token),
    KEY status (status)
) $charset_collate;";

dbDelta($sql_requests);
echo '<h2>2. Таблица custom_feedback_requests</h2>';
if ($wpdb->last_error) {
    echo '<p class="error">Ошибка: ' . esc_html($wpdb->last_error) . '</p>';
} else {
    echo '<p class="success">✓ Создана или уже существует</p>';
}

// 3. Таблица для кастомных записей
$custom_entries_table = $wpdb->prefix . 'custom_feedback_entries';
$sql_entries = "CREATE TABLE IF NOT EXISTS $custom_entries_table (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id bigint(20) UNSIGNED NOT NULL,
    dish_name varchar(255) NOT NULL,
    dish_unit varchar(100) DEFAULT '',
    rating int(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY request_id (request_id),
    KEY dish_name (dish_name)
) $charset_collate;";

dbDelta($sql_entries);
echo '<h2>3. Таблица custom_feedback_entries</h2>';
if ($wpdb->last_error) {
    echo '<p class="error">Ошибка: ' . esc_html($wpdb->last_error) . '</p>';
} else {
    echo '<p class="success">✓ Создана или уже существует</p>';
}

// Проверяем колонки
echo '<h2>4. Проверка колонок</h2>';
$columns_to_check = array(
    array('table' => $table_name, 'column' => 'shared_instagram'),
    array('table' => $table_name, 'column' => 'shared_google'),
    array('table' => $custom_entries_table, 'column' => 'dish_unit'),
);

foreach ($columns_to_check as $check) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM {$check['table']} LIKE %s",
        $check['column']
    ));
    if (!$exists) {
        if ($check['column'] === 'dish_unit') {
            $wpdb->query("ALTER TABLE {$check['table']} ADD COLUMN {$check['column']} varchar(100) DEFAULT '' AFTER dish_name");
        } else {
            $wpdb->query("ALTER TABLE {$check['table']} ADD COLUMN {$check['column']} tinyint(1) DEFAULT 0");
        }
        if ($wpdb->last_error) {
            echo '<p class="error">Ошибка добавления колонки ' . $check['column'] . ': ' . esc_html($wpdb->last_error) . '</p>';
        } else {
            echo '<p class="success">✓ Колонка ' . $check['column'] . ' добавлена</p>';
        }
    } else {
        echo '<p class="success">✓ Колонка ' . $check['column'] . ' уже существует</p>';
    }
}

// Проверяем таблицы
echo '<h2>5. Проверка всех таблиц</h2>';
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%feedback%'", ARRAY_N);
if (!empty($tables)) {
    echo '<p class="success">✓ Найдены таблицы:</p><ul>';
    foreach ($tables as $table) {
        echo '<li>' . esc_html($table[0]) . '</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="error">✗ Таблицы не найдены</p>';
}

echo '<hr>';
echo '<h2>Готово!</h2>';
echo '<p>Теперь проверьте:</p>';
echo '<ul>';
echo '<li><a href="' . admin_url() . '">Войти в админку</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=gustolocal-feedback') . '">Обратная связь</a></li>';
echo '<li><a href="' . admin_url('admin.php?page=gustolocal-custom-feedback') . '">Кастомные опросы</a></li>';
echo '</ul>';

