<?php
/**
 * Скрипт для создания недостающих таблиц WooCommerce
 * Запустите через браузер: https://gustolocal.es/wp-content/themes/gustolocal/fix-wc-tables.php
 */

// Подключаем WordPress
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// Проверяем, что WooCommerce установлен
if (!class_exists('WooCommerce')) {
    die('WooCommerce не установлен!');
}

// Проверяем права доступа (только для администраторов)
if (!current_user_can('manage_options')) {
    die('Недостаточно прав для выполнения этого действия!');
}

// Загружаем класс установки WooCommerce
$wc_install_file = WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-install.php';
if (!file_exists($wc_install_file)) {
    die('Файл установки WooCommerce не найден: ' . $wc_install_file);
}

require_once($wc_install_file);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Создание таблиц WooCommerce</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo "pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;}";
echo ".success{color:green;}.error{color:red;}</style></head><body>";
echo "<h1>Создание таблиц WooCommerce</h1>";
echo "<pre>";

// Получаем префикс таблиц
global $wpdb;
$table_prefix = $wpdb->prefix;

echo "Префикс таблиц: {$table_prefix}\n\n";

// Проверяем наличие критических таблиц
$critical_tables = array(
    'wc_orders_meta',
    'wc_order_addresses',
);

echo "Проверка существующих таблиц:\n";
$missing_tables = array();

foreach ($critical_tables as $table) {
    $full_table_name = $table_prefix . $table;
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
    
    if ($exists) {
        echo "<span class='success'>✓ {$full_table_name} - существует</span>\n";
    } else {
        echo "<span class='error'>✗ {$full_table_name} - отсутствует</span>\n";
        $missing_tables[] = $table;
    }
}

echo "\n";

// Если есть недостающие таблицы, создаем их
if (!empty($missing_tables)) {
    echo "Создание недостающих таблиц...\n\n";
    
    try {
        // Запускаем создание всех таблиц WooCommerce
        if (class_exists('WC_Install') && method_exists('WC_Install', 'create_tables')) {
            WC_Install::create_tables();
            echo "<span class='success'>Запущено создание таблиц через WC_Install::create_tables()</span>\n\n";
        } else {
            echo "<span class='error'>Класс WC_Install или метод create_tables() не найден</span>\n";
        }
        
        // Проверяем снова после создания
        echo "\nПовторная проверка таблиц:\n";
        foreach ($missing_tables as $table) {
            $full_table_name = $table_prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
            
            if ($exists) {
                echo "<span class='success'>✓ {$full_table_name} - создана успешно</span>\n";
            } else {
                echo "<span class='error'>✗ {$full_table_name} - не удалось создать</span>\n";
            }
        }
    } catch (Exception $e) {
        echo "<span class='error'>Ошибка: " . $e->getMessage() . "</span>\n";
    }
} else {
    echo "<span class='success'>Все необходимые таблицы существуют!</span>\n";
}

echo "\n";
echo "Готово!\n";
echo "</pre>";
echo "<p><a href='" . admin_url() . "'>Вернуться в админку</a></p>";
echo "</body></html>";

