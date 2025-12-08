<?php
/**
 * Скрипт для проверки и установки темы
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
    <title>Проверка темы</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Проверка темы WordPress</h1>
        
        <?php
        // Получаем активную тему
        $active_theme = get_option('template');
        $active_stylesheet = get_option('stylesheet');
        
        echo '<div class="info">';
        echo '<strong>Активная тема в базе данных:</strong><br>';
        echo 'Template: <code>' . htmlspecialchars($active_theme) . '</code><br>';
        echo 'Stylesheet: <code>' . htmlspecialchars($active_stylesheet) . '</code>';
        echo '</div>';
        
        // Проверяем, существует ли активная тема
        $theme_path = get_theme_root() . '/' . $active_theme;
        $style_path = get_theme_root() . '/' . $active_stylesheet;
        
        if (is_dir($theme_path)) {
            echo '<div class="success">✓ Папка темы <code>' . htmlspecialchars($active_theme) . '</code> найдена</div>';
        } else {
            echo '<div class="error">✗ Папка темы <code>' . htmlspecialchars($active_theme) . '</code> не найдена по пути: <code>' . htmlspecialchars($theme_path) . '</code></div>';
        }
        
        if (is_dir($style_path)) {
            echo '<div class="success">✓ Папка стилей <code>' . htmlspecialchars($active_stylesheet) . '</code> найдена</div>';
        } else {
            echo '<div class="error">✗ Папка стилей <code>' . htmlspecialchars($active_stylesheet) . '</code> не найдена</div>';
        }
        
        // Список всех доступных тем
        echo '<h2>Доступные темы</h2>';
        $themes = wp_get_themes();
        
        if (empty($themes)) {
            echo '<div class="error">✗ Не найдено ни одной темы</div>';
        } else {
            echo '<table>';
            echo '<tr><th>Название</th><th>Папка</th><th>Версия</th><th>Статус</th></tr>';
            foreach ($themes as $theme_slug => $theme) {
                $is_active = ($theme_slug === $active_theme || $theme_slug === $active_stylesheet);
                $status = $is_active ? '<span style="color: green;">✓ Активна</span>' : '';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($theme->get('Name')) . '</td>';
                echo '<td><code>' . htmlspecialchars($theme_slug) . '</code></td>';
                echo '<td>' . htmlspecialchars($theme->get('Version')) . '</td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        
        // Проверяем тему gustolocal
        echo '<h2>Проверка темы gustolocal</h2>';
        $gustolocal_path = get_theme_root() . '/gustolocal';
        if (is_dir($gustolocal_path)) {
            echo '<div class="success">✓ Папка темы gustolocal найдена</div>';
            
            $functions_file = $gustolocal_path . '/functions.php';
            if (file_exists($functions_file)) {
                echo '<div class="success">✓ Файл functions.php найден</div>';
            } else {
                echo '<div class="warning">⚠ Файл functions.php не найден</div>';
            }
            
            $style_file = $gustolocal_path . '/style.css';
            if (file_exists($style_file)) {
                echo '<div class="success">✓ Файл style.css найден</div>';
            } else {
                echo '<div class="warning">⚠ Файл style.css не найден</div>';
            }
            
            // Если тема существует, но не активна, предлагаем активировать
            if ($active_theme !== 'gustolocal' && $active_stylesheet !== 'gustolocal') {
                echo '<div class="warning">';
                echo '⚠ Тема gustolocal найдена, но не активна. ';
                echo 'Нужно активировать её в админке WordPress или установить через базу данных.';
                echo '</div>';
            }
        } else {
            echo '<div class="error">✗ Папка темы gustolocal не найдена</div>';
            echo '<div class="info">';
            echo 'Путь, где должна быть тема: <code>' . htmlspecialchars($gustolocal_path) . '</code><br>';
            echo 'Нужно загрузить тему в эту папку.';
            echo '</div>';
        }
        ?>
        
        <div class="warning">
            <strong>⚠️ ВАЖНО:</strong> После проверки удали этот файл с сервера для безопасности!
        </div>
    </div>
</body>
</html>

