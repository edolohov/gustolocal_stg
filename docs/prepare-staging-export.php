<?php
/**
 * Скрипт для подготовки SQL экспорта для staging
 * Заменяет префиксы и очищает ненужные данные
 */

$input_file = $_GET['file'] ?? '';
$action = $_GET['action'] ?? '';

if ($action === 'process' && !empty($input_file)) {
    // Читаем файл
    $content = file_get_contents($input_file);
    
    if ($content === false) {
        die('Не удалось прочитать файл');
    }
    
    // 1. Заменяем префикс wp_ на staging_
    $content = str_replace('`wp_', '`staging_', $content);
    $content = str_replace("'wp_", "'staging_", $content);
    $content = str_replace(' wp_', ' staging_', $content);
    $content = str_replace('(wp_', '(staging_', $content);
    
    // 2. Удаляем данные из таблиц обратной связи (оставляем только структуру)
    $tables_to_clear = array(
        'staging_dish_feedback',
        'staging_custom_feedback_requests',
        'staging_custom_feedback_entries'
    );
    
    foreach ($tables_to_clear as $table) {
        // Удаляем все INSERT для этих таблиц
        $pattern = '/INSERT INTO `?' . preg_quote($table, '/') . '`?[^;]*;/i';
        $content = preg_replace($pattern, '', $content);
    }
    
    // 3. Удаляем INSERT для страниц (оставляем только блюда meal builder)
    // Это сложнее, нужно оставить только посты типа wmb_dish
    // Пока просто удалим все INSERT в wp_posts, потом можно будет добавить только нужные
    
    // 4. Удаляем INSERT для комментариев
    $content = preg_replace('/INSERT INTO `?staging_comments`?[^;]*;/i', '', $content);
    $content = preg_replace('/INSERT INTO `?staging_commentmeta`?[^;]*;/i', '', $content);
    
    // 5. Удаляем INSERT для actionscheduler
    $content = preg_replace('/INSERT INTO `?staging_actionscheduler[^;]*;/i', '', $content);
    
    // 6. Удаляем INSERT для Wordfence
    $content = preg_replace('/INSERT INTO `?staging_wf[^;]*;/i', '', $content);
    
    // Сохраняем обработанный файл
    $output_file = 'gustolocal-staging-ready.sql';
    file_put_contents($output_file, $content);
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $output_file . '"');
    echo $content;
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Подготовка SQL для staging</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .button { display: inline-block; padding: 15px 30px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 5px; }
        input[type="file"] { padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Подготовка SQL экспорта для staging</h1>
    
    <div class="info">
        <h2>Что делает этот скрипт:</h2>
        <ul>
            <li>Заменяет префикс <code>wp_</code> на <code>staging_</code></li>
            <li>Удаляет данные из таблиц обратной связи (оставляет структуру)</li>
            <li>Удаляет комментарии</li>
            <li>Удаляет данные actionscheduler</li>
            <li>Удаляет данные Wordfence</li>
        </ul>
    </div>
    
    <form method="get" action="">
        <input type="hidden" name="action" value="process">
        <p>
            <label>Выберите SQL файл с продакшна:</label><br>
            <input type="file" name="file" accept=".sql" required>
        </p>
        <p>
            <button type="submit" class="button">Обработать и скачать</button>
        </p>
    </form>
    
    <p><strong>Примечание:</strong> Этот скрипт нужно запускать локально, так как он обрабатывает большой файл.</p>
</body>
</html>

