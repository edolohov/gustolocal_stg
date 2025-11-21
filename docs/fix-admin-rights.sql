-- SQL скрипт для восстановления прав администратора
-- Выполни этот скрипт в phpMyAdmin для базы данных u850527203_stg

-- Восстанавливаем права администратора для пользователя с ID = 1
-- Заменяем staging_ на твой префикс таблиц, если он другой

-- Удаляем старые права (если есть)
DELETE FROM staging_usermeta 
WHERE user_id = 1 
AND meta_key IN ('staging_capabilities', 'staging_user_level');

-- Устанавливаем права администратора
INSERT INTO staging_usermeta (user_id, meta_key, meta_value) 
VALUES 
(1, 'staging_capabilities', 'a:1:{s:13:"administrator";b:1;}'),
(1, 'staging_user_level', '10')
ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value);

-- Проверяем результат
SELECT user_id, meta_key, meta_value 
FROM staging_usermeta 
WHERE user_id = 1 
AND meta_key IN ('staging_capabilities', 'staging_user_level');

