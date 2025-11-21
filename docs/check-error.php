<?php
/**
 * Скрипт для детальной диагностики критической ошибки
 */

// Включаем максимальный уровень ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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
    <title>Диагностика критической ошибки</title>
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
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Диагностика критической ошибки</h1>
        
        <?php
        $base_path = dirname(__FILE__);
        $wp_load_path = $base_path . '/wp-load.php';
        $debug_log_path = $base_path . '/wp-content/debug.log';
        $theme_path = $base_path . '/wp-content/themes/gustolocal';
        $functions_path = $theme_path . '/functions.php';
        
        // Проверка 1: Попытка загрузить WordPress с перехватом ошибок
        echo '<h2>Проверка 1: Загрузка WordPress</h2>';
        
        // Перехватываем фатальные ошибки
        register_shutdown_function(function() use ($base_path) {
            $error = error_get_last();
            if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                echo '<div class="error">';
                echo '<strong>Фатальная ошибка PHP:</strong><br>';
                echo 'Тип: ' . $error['type'] . '<br>';
                echo 'Сообщение: <pre>' . htmlspecialchars($error['message']) . '</pre>';
                echo 'Файл: <code>' . htmlspecialchars($error['file']) . '</code><br>';
                echo 'Строка: ' . $error['line'];
                echo '</div>';
            }
        });
        
        // Пытаемся загрузить WordPress
        try {
            if (file_exists($wp_load_path)) {
                // Включаем обработку ошибок
                set_error_handler(function($errno, $errstr, $errfile, $errline) {
                    echo '<div class="error">';
                    echo '<strong>Ошибка PHP:</strong> ' . htmlspecialchars($errstr) . '<br>';
                    echo 'Файл: <code>' . htmlspecialchars($errfile) . '</code><br>';
                    echo 'Строка: ' . $errline;
                    echo '</div>';
                    return true;
                }, E_ALL);
                
                require_once($wp_load_path);
                
                restore_error_handler();
                
                if (function_exists('get_option')) {
                    echo '<div class="success">✓ WordPress загружен успешно</div>';
                } else {
                    echo '<div class="error">✗ WordPress загрузился, но функции не доступны</div>';
                }
            } else {
                echo '<div class="error">✗ Файл wp-load.php не найден</div>';
            }
        } catch (Throwable $e) {
            echo '<div class="error">';
            echo '<strong>Исключение при загрузке WordPress:</strong><br>';
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo 'Файл: <code>' . htmlspecialchars($e->getFile()) . '</code><br>';
            echo 'Строка: ' . $e->getLine();
            echo '</div>';
        }
        
        // Проверка 2: Синтаксис functions.php
        echo '<h2>Проверка 2: Синтаксис functions.php темы</h2>';
        if (file_exists($functions_path)) {
            // Проверяем синтаксис PHP через tokenizer
            $code = file_get_contents($functions_path);
            $tokens = @token_get_all($code);
            $syntax_ok = true;
            $syntax_error = '';
            
            // Простая проверка на незакрытые скобки
            $open_braces = 0;
            $open_parens = 0;
            $open_brackets = 0;
            
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    $token_type = $token[0];
                    $token_value = $token[1];
                    
                    if ($token_type === T_OPEN_TAG || $token_type === T_OPEN_TAG_WITH_ECHO) {
                        // Начало PHP кода
                    }
                } else {
                    if ($token === '{') $open_braces++;
                    if ($token === '}') $open_braces--;
                    if ($token === '(') $open_parens++;
                    if ($token === ')') $open_parens--;
                    if ($token === '[') $open_brackets++;
                    if ($token === ']') $open_brackets--;
                }
            }
            
            if ($open_braces === 0 && $open_parens === 0 && $open_brackets === 0) {
                echo '<div class="success">✓ Базовая проверка синтаксиса functions.php пройдена (скобки сбалансированы)</div>';
            } else {
                echo '<div class="warning">⚠ Возможная проблема с балансом скобок в functions.php</div>';
                echo '<div class="info">Открытых фигурных скобок: ' . $open_braces . '<br>';
                echo 'Открытых круглых скобок: ' . $open_parens . '<br>';
                echo 'Открытых квадратных скобок: ' . $open_brackets . '</div>';
            }
        } else {
            echo '<div class="error">✗ Файл functions.php не найден</div>';
        }
        
        // Проверка 3: Попытка загрузить functions.php
        echo '<h2>Проверка 3: Загрузка functions.php темы</h2>';
        if (file_exists($functions_path)) {
            try {
                // Подавляем вывод и перехватываем ошибки
                ob_start();
                set_error_handler(function($errno, $errstr, $errfile, $errline) {
                    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
                }, E_ALL);
                
                // Пытаемся включить файл
                include_once($functions_path);
                
                restore_error_handler();
                ob_end_clean();
                
                echo '<div class="success">✓ Файл functions.php загружен без ошибок</div>';
            } catch (Throwable $e) {
                restore_error_handler();
                ob_end_clean();
                
                echo '<div class="error">';
                echo '<strong>Ошибка при загрузке functions.php:</strong><br>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo 'Файл: <code>' . htmlspecialchars($e->getFile()) . '</code><br>';
                echo 'Строка: ' . $e->getLine();
                echo '</div>';
            }
        }
        
        // Проверка 4: debug.log
        echo '<h2>Проверка 4: Лог ошибок (debug.log)</h2>';
        if (file_exists($debug_log_path)) {
            $log_size = filesize($debug_log_path);
            if ($log_size > 0) {
                echo '<div class="warning">⚠ Файл debug.log содержит данные (' . number_format($log_size) . ' байт)</div>';
                
                // Показываем последние 100 строк
                $lines = file($debug_log_path);
                $last_lines = array_slice($lines, -100);
                
                echo '<details><summary>Показать последние 100 строк лога</summary>';
                echo '<pre>' . htmlspecialchars(implode('', $last_lines)) . '</pre>';
                echo '</details>';
            } else {
                echo '<div class="info">Файл debug.log существует, но пуст</div>';
            }
        } else {
            echo '<div class="info">Файл debug.log не найден</div>';
        }
        
        // Проверка 5: Зависимости темы
        echo '<h2>Проверка 5: Зависимости темы</h2>';
        if (file_exists($functions_path)) {
            $functions_content = file_get_contents($functions_path);
            
            // Проверяем наличие критических функций WordPress
            $required_functions = array('add_action', 'add_filter', 'wp_enqueue_script', 'wp_enqueue_style');
            foreach ($required_functions as $func) {
                if (function_exists($func)) {
                    echo '<div class="success">✓ Функция WordPress <code>' . htmlspecialchars($func) . '</code> доступна</div>';
                } else {
                    echo '<div class="error">✗ Функция WordPress <code>' . htmlspecialchars($func) . '</code> не доступна</div>';
                }
            }
            
            // Проверяем наличие плагинов, которые могут быть нужны
            if (strpos($functions_content, 'woocommerce') !== false) {
                echo '<div class="info">⚠ В коде темы найдены упоминания WooCommerce</div>';
            }
        }
        
        // Проверка 6: Child тема
        echo '<h2>Проверка 6: Child тема</h2>';
        $child_theme_path = $base_path . '/wp-content/themes/gustolocal-child';
        if (is_dir($child_theme_path)) {
            echo '<div class="warning">⚠ Найдена child тема gustolocal-child</div>';
            echo '<div class="info">';
            echo 'Если активна основная тема gustolocal, child тема не должна вызывать проблем.<br>';
            echo 'Но если child тема активна, убедись, что она правильно настроена.';
            echo '</div>';
        } else {
            echo '<div class="info">Child тема не найдена</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После диагностики удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

