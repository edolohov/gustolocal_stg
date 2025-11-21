<?php
/**
 * Скрипт для проверки плагинов и последних ошибок
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

// Загружаем WordPress
require_once(dirname(__FILE__) . '/wp-load.php');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Проверка плагинов и ошибок</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 500px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Проверка плагинов и ошибок</h1>
        
        <?php
        $base_path = dirname(__FILE__);
        $debug_log_path = $base_path . '/wp-content/debug.log';
        
        // Проверка 1: Активные плагины
        echo '<h2>Проверка 1: Активные плагины</h2>';
        if (function_exists('get_option')) {
            $active_plugins = get_option('active_plugins', array());
            
            if (empty($active_plugins)) {
                echo '<div class="warning">⚠ Нет активных плагинов</div>';
            } else {
                echo '<div class="info">Найдено активных плагинов: <strong>' . count($active_plugins) . '</strong></div>';
                echo '<table>';
                echo '<tr><th>Плагин</th><th>Статус</th></tr>';
                
                $woocommerce_found = false;
                foreach ($active_plugins as $plugin) {
                    $plugin_path = $base_path . '/wp-content/plugins/' . $plugin;
                    $exists = file_exists($plugin_path);
                    $status = $exists ? '<span style="color: green;">✓ Установлен</span>' : '<span style="color: red;">✗ Не найден</span>';
                    
                    if (strpos($plugin, 'woocommerce') !== false) {
                        $woocommerce_found = true;
                        echo '<tr style="background: #fff3cd;">';
                    } else {
                        echo '<tr>';
                    }
                    echo '<td><code>' . htmlspecialchars($plugin) . '</code></td>';
                    echo '<td>' . $status . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                if (!$woocommerce_found) {
                    echo '<div class="error">✗ WooCommerce не найден среди активных плагинов!</div>';
                    echo '<div class="warning">⚠ Тема использует WooCommerce, но плагин не установлен или не активирован.</div>';
                }
            }
        }
        
        // Проверка 2: Установленные плагины
        echo '<h2>Проверка 2: Установленные плагины</h2>';
        $plugins_dir = $base_path . '/wp-content/plugins';
        if (is_dir($plugins_dir)) {
            $plugins = array_filter(glob($plugins_dir . '/*'), 'is_dir');
            
            if (empty($plugins)) {
                echo '<div class="warning">⚠ Папка плагинов пуста</div>';
            } else {
                echo '<div class="info">Найдено плагинов в папке: <strong>' . count($plugins) . '</strong></div>';
                echo '<table>';
                echo '<tr><th>Плагин</th><th>Файл</th></tr>';
                
                $woocommerce_installed = false;
                foreach ($plugins as $plugin_path) {
                    $plugin_name = basename($plugin_path);
                    $main_file = $plugin_path . '/' . $plugin_name . '.php';
                    if (!file_exists($main_file)) {
                        // Ищем первый PHP файл
                        $php_files = glob($plugin_path . '/*.php');
                        $main_file = !empty($php_files) ? $php_files[0] : 'не найден';
                    }
                    
                    if (strpos($plugin_name, 'woocommerce') !== false) {
                        $woocommerce_installed = true;
                        echo '<tr style="background: #fff3cd;">';
                    } else {
                        echo '<tr>';
                    }
                    echo '<td><code>' . htmlspecialchars($plugin_name) . '</code></td>';
                    echo '<td><code>' . htmlspecialchars(basename($main_file)) . '</code></td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                if (!$woocommerce_installed) {
                    echo '<div class="error">✗ WooCommerce не установлен!</div>';
                    echo '<div class="warning">⚠ Нужно установить и активировать WooCommerce для работы темы.</div>';
                }
            }
        }
        
        // Проверка 3: Последние ошибки из debug.log
        echo '<h2>Проверка 3: Последние ошибки из debug.log</h2>';
        if (file_exists($debug_log_path)) {
            $log_size = filesize($debug_log_path);
            echo '<div class="info">Размер лога: ' . number_format($log_size) . ' байт</div>';
            
            if ($log_size > 0) {
                // Читаем последние 200 строк
                $lines = file($debug_log_path);
                $last_lines = array_slice($lines, -200);
                
                // Ищем критические ошибки
                $critical_errors = array();
                foreach ($last_lines as $line) {
                    if (stripos($line, 'fatal') !== false || 
                        stripos($line, 'error') !== false || 
                        stripos($line, 'warning') !== false ||
                        stripos($line, 'undefined') !== false ||
                        stripos($line, 'call to undefined') !== false) {
                        $critical_errors[] = $line;
                    }
                }
                
                if (!empty($critical_errors)) {
                    echo '<div class="error">';
                    echo '<strong>Найдено ошибок в последних строках лога: ' . count($critical_errors) . '</strong>';
                    echo '</div>';
                    echo '<details><summary>Показать найденные ошибки</summary>';
                    echo '<pre>' . htmlspecialchars(implode('', array_slice($critical_errors, -50))) . '</pre>';
                    echo '</details>';
                }
                
                echo '<details><summary>Показать последние 200 строк лога</summary>';
                echo '<pre>' . htmlspecialchars(implode('', $last_lines)) . '</pre>';
                echo '</details>';
            }
        } else {
            echo '<div class="info">Файл debug.log не найден</div>';
        }
        
        // Проверка 4: Проверка функций WooCommerce
        echo '<h2>Проверка 4: Функции WooCommerce</h2>';
        if (function_exists('is_woocommerce')) {
            echo '<div class="success">✓ WooCommerce активен и функции доступны</div>';
        } else {
            echo '<div class="error">✗ WooCommerce не активен - функция is_woocommerce() не найдена</div>';
            echo '<div class="warning">⚠ Это может быть причиной критической ошибки, если тема пытается использовать функции WooCommerce.</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После проверки удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

