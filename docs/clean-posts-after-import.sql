-- Очистка лишних постов после импорта
-- Оставляем только блюда meal builder и служебные типы

-- Удаляем все посты, кроме блюд meal builder и служебных
DELETE FROM staging_posts 
WHERE post_type NOT IN (
    'wmb_dish',           -- Блюда meal builder
    'attachment',         -- Медиафайлы
    'nav_menu_item',      -- Пункты меню
    'wp_block',           -- Блоки Gutenberg
    'revision'            -- Ревизии (можно оставить)
);

-- Удаляем метаданные удаленных постов
DELETE FROM staging_postmeta 
WHERE post_id NOT IN (SELECT ID FROM staging_posts);

-- Удаляем связи терминов с удаленными постами
DELETE FROM staging_term_relationships 
WHERE object_id NOT IN (SELECT ID FROM staging_posts);

-- Проверка: сколько блюд осталось
SELECT post_type, COUNT(*) as count 
FROM staging_posts 
GROUP BY post_type;

