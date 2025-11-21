<?php
/**
 * Диагностический скрипт для проверки WordPress
 * Поможет выявить проблему с белым экраном
 */

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка безопасности
$security_key = 'hello';
if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
    die('Доступ запрещен. Добавьте ?key=hello к URL');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Диагностика WordPress</title>
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
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Диагностика WordPress</h1>
        
        <?php
        $base_path = dirname(__FILE__);
        $wp_config_path = $base_path . '/wp-config.php';
        $wp_load_path = $base_path . '/wp-load.php';
        $debug_log_path = $base_path . '/wp-content/debug.log';
        
        // Проверка 1: Существование wp-config.php
        echo '<h2>Проверка 1: Файл wp-config.php</h2>';
        if (file_exists($wp_config_path)) {
            echo '<div class="success">✓ Файл wp-config.php найден</div>';
            
            // Проверяем содержимое
            $wp_config_content = file_get_contents($wp_config_path);
            if (strpos($wp_config_content, 'DB_NAME') !== false) {
                echo '<div class="success">✓ В wp-config.php найдены настройки БД</div>';
            } else {
                echo '<div class="error">✗ В wp-config.php не найдены настройки БД</div>';
            }
            
            if (strpos($wp_config_content, 'WP_DEBUG') !== false) {
                echo '<div class="success">✓ WP_DEBUG настроен</div>';
            } else {
                echo '<div class="warning">⚠ WP_DEBUG не найден (рекомендуется включить для staging)</div>';
            }
        } else {
            echo '<div class="error">✗ Файл wp-config.php не найден по пути: <code>' . htmlspecialchars($wp_config_path) . '</code></div>';
        }
        
        // Проверка 2: Существование wp-load.php
        echo '<h2>Проверка 2: Файл wp-load.php</h2>';
        if (file_exists($wp_load_path)) {
            echo '<div class="success">✓ Файл wp-load.php найден</div>';
        } else {
            echo '<div class="error">✗ Файл wp-load.php не найден. WordPress может быть не установлен правильно.</div>';
        }
        
        // Проверка 3: Попытка загрузить WordPress
        echo '<h2>Проверка 3: Загрузка WordPress</h2>';
        if (file_exists($wp_load_path)) {
            try {
                // Подавляем вывод ошибок, чтобы перехватить их
                ob_start();
                $error_occurred = false;
                $error_message = '';
                
                // Пытаемся загрузить WordPress
                if (file_exists($wp_load_path)) {
                    // Включаем обработку ошибок
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error_occurred, &$error_message) {
                        $error_occurred = true;
                        $error_message = "Ошибка $errno: $errstr в $errfile на строке $errline";
                        return true;
                    });
                    
                    require_once($wp_load_path);
                    
                    restore_error_handler();
                }
                
                $output = ob_get_clean();
                
                if ($error_occurred) {
                    echo '<div class="error">✗ Ошибка при загрузке WordPress:<br><pre>' . htmlspecialchars($error_message) . '</pre></div>';
                } elseif (function_exists('get_option')) {
                    echo '<div class="success">✓ WordPress загружен успешно</div>';
                    
                    // Проверяем настройки
                    $siteurl = get_option('siteurl');
                    $home = get_option('home');
                    
                    echo '<div class="info">';
                    echo '<strong>Настройки из базы данных:</strong><br>';
                    echo 'siteurl: <code>' . htmlspecialchars($siteurl) . '</code><br>';
                    echo 'home: <code>' . htmlspecialchars($home) . '</code><br>';
                    if ($siteurl !== 'https://staging.gustolocal.es' || $home !== 'https://staging.gustolocal.es') {
                        echo '<div class="warning">⚠ URL еще не полностью заменены в базе данных</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="error">✗ WordPress загрузился, но функции не доступны</div>';
                    if (!empty($output)) {
                        echo '<div class="error">Вывод:<pre>' . htmlspecialchars($output) . '</pre></div>';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="error">✗ Исключение при загрузке WordPress:<br><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div>';
            } catch (Error $e) {
                echo '<div class="error">✗ Фатальная ошибка при загрузке WordPress:<br><pre>' . htmlspecialchars($e->getMessage()) . '</pre><br>Файл: ' . htmlspecialchars($e->getFile()) . '<br>Строка: ' . $e->getLine() . '</pre></div>';
            }
        } else {
            echo '<div class="error">✗ Не могу проверить загрузку WordPress - файл wp-load.php не найден</div>';
        }
        
        // Проверка 4: Файл debug.log
        echo '<h2>Проверка 4: Лог ошибок (debug.log)</h2>';
        if (file_exists($debug_log_path)) {
            $log_size = filesize($debug_log_path);
            if ($log_size > 0) {
                echo '<div class="warning">⚠ Файл debug.log существует и содержит данные (' . number_format($log_size) . ' байт)</div>';
                
                // Показываем последние 50 строк
                $lines = file($debug_log_path);
                $last_lines = array_slice($lines, -50);
                
                echo '<details><summary>Показать последние 50 строк лога</summary>';
                echo '<pre style="max-height: 400px; overflow-y: auto;">';
                echo htmlspecialchars(implode('', $last_lines));
                echo '</pre></details>';
            } else {
                echo '<div class="info">Файл debug.log существует, но пуст</div>';
            }
        } else {
            echo '<div class="info">Файл debug.log не найден (это нормально, если нет ошибок)</div>';
        }
        
        // Проверка 5: Права доступа к файлам
        echo '<h2>Проверка 5: Права доступа</h2>';
        $important_files = array(
            'wp-config.php',
            'wp-load.php',
            'index.php',
            'wp-content',
            'wp-content/themes',
            'wp-content/plugins',
        );
        
        foreach ($important_files as $file) {
            $full_path = $base_path . '/' . $file;
            if (file_exists($full_path)) {
                $perms = fileperms($full_path);
                $readable = is_readable($full_path);
                $writable = is_writable($full_path);
                
                $status = '';
                if ($readable) {
                    $status .= '<span style="color: green;">✓ Читаемый</span> ';
                } else {
                    $status .= '<span style="color: red;">✗ Не читаемый</span> ';
                }
                
                if (is_dir($full_path)) {
                    $status .= '<span style="color: blue;">[Папка]</span>';
                }
                
                echo '<div class="info">';
                echo '<code>' . htmlspecialchars($file) . '</code>: ' . $status . ' (права: ' . substr(sprintf('%o', $perms), -4) . ')';
                echo '</div>';
            } else {
                echo '<div class="warning">⚠ Файл/папка <code>' . htmlspecialchars($file) . '</code> не найден</div>';
            }
        }
        
        // Проверка 6: .htaccess
        echo '<h2>Проверка 6: Файл .htaccess</h2>';
        $htaccess_path = $base_path . '/.htaccess';
        if (file_exists($htaccess_path)) {
            echo '<div class="success">✓ Файл .htaccess найден</div>';
            $htaccess_content = file_get_contents($htaccess_path);
            if (strpos($htaccess_content, 'RewriteBase /staging/') !== false || strpos($htaccess_content, 'RewriteBase /') !== false) {
                echo '<div class="success">✓ В .htaccess найдены правила RewriteBase</div>';
            } else {
                echo '<div class="warning">⚠ В .htaccess не найдены правила RewriteBase</div>';
            }
            
            echo '<details><summary>Показать содержимое .htaccess</summary>';
            echo '<pre>' . htmlspecialchars($htaccess_content) . '</pre>';
            echo '</details>';
        } else {
            echo '<div class="warning">⚠ Файл .htaccess не найден. WordPress может не работать правильно с постоянными ссылками.</div>';
        }
        
        // Проверка 7: Тема
        echo '<h2>Проверка 7: Тема gustolocal</h2>';
        $theme_path = $base_path . '/wp-content/themes/gustolocal';
        if (file_exists($theme_path)) {
            echo '<div class="success">✓ Папка темы gustolocal найдена</div>';
            
            $functions_file = $theme_path . '/functions.php';
            if (file_exists($functions_file)) {
                echo '<div class="success">✓ Файл functions.php найден</div>';
            } else {
                echo '<div class="warning">⚠ Файл functions.php не найден в теме</div>';
            }
        } else {
            echo '<div class="warning">⚠ Папка темы gustolocal не найдена. Тема может быть не установлена.</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После диагностики удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

