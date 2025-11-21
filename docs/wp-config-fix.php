<?php
/**
 * КОД ДЛЯ ДОБАВЛЕНИЯ В wp-config.php
 * 
 * Добавь этот код в файл wp-config.php ПЕРЕД строкой:
 * "/* That's all, stop editing! Happy publishing. */"
 * 
 * ВАЖНО: Это временное решение! После восстановления прав удали этот код!
 */

// Временный обход проверки прав для user_id = 1 (admin)
// Позволяет получить доступ к админке даже если права не загружаются правильно
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    // Даем все права пользователю с ID = 1
    if (isset($user->ID) && $user->ID == 1) {
        foreach ($caps as $cap) {
            $allcaps[$cap] = true;
        }
    }
    return $allcaps;
}, 999, 4);

/* That's all, stop editing! Happy publishing. */

