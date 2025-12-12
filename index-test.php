<?php
// Простой тест без WordPress
echo "PHP работает!<br>";
echo "Версия PHP: " . phpversion() . "<br>";
echo "Текущая директория: " . __DIR__ . "<br>";
echo "Файл wp-config.php существует: " . (file_exists(__DIR__ . '/wp-config.php') ? 'ДА' : 'НЕТ') . "<br>";
?>

