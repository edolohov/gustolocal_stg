<?php
/**
 * Многоязычная поддержка для Weekly Meal Builder
 * Простой подход: отдельные страницы для каждого языка
 */

// Подключаем только если плагин активен
if (!function_exists('wmb_assets_url')) {
    return;
}

// Добавляем поддержку языков в REST API
add_filter('rest_pre_serve_request', 'add_language_to_meal_builder_api', 10, 4);
function add_language_to_meal_builder_api($served, $result, $request, $server) {
    // Добавляем текущий язык в ответ API
    if (strpos($request->get_route(), '/wmb/v1/menu') !== false) {
        $current_lang = get_current_language();
        if (is_array($result)) {
            $result['current_language'] = $current_lang;
        }
    }
    return $served;
}

// Модифицируем REST API для фильтрации по языку
add_action('rest_api_init', 'modify_meal_builder_api', 20);
function modify_meal_builder_api() {
    // Переопределяем существующий маршрут
    register_rest_route('wmb/v1', '/menu', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => 'get_meal_builder_menu_with_language',
    ]);
}

function get_meal_builder_menu_with_language() {
    $current_lang = get_current_language();
    
    // Получаем настройки
    $settings = wmb_get_settings();
    
    // Получаем блюда с учетом языка
    $posts = get_posts([
        'post_type'   => 'wmb_dish',
        'numberposts' => -1,
        'meta_key'    => 'wmb_active',
        'meta_value'  => '1',
        'orderby'     => 'title',
        'order'       => 'ASC',
    ]);
    
    $sections = [];
    foreach ($posts as $p) {
        // Проверяем, есть ли у блюда перевод для текущего языка
        $has_translation = false;
        
        if ($current_lang === 'ru') {
            // Для русского языка показываем все блюда
            $has_translation = true;
        } else {
            // Для других языков проверяем наличие перевода
            $translated_title = get_post_meta($p->ID, 'wmb_title_' . $current_lang, true);
            if (!empty($translated_title)) {
                $has_translation = true;
            }
        }
        
        if (!$has_translation) {
            continue;
        }
        
        $price = (float) get_post_meta($p->ID, 'wmb_price', true);
        $unit  = (string) get_post_meta($p->ID, 'wmb_unit', true);
        $ingredients = (string) get_post_meta($p->ID, 'wmb_ingredients', true);
        $allergens_raw = (string) get_post_meta($p->ID, 'wmb_allergens', true);
        $allergens = array_values(array_filter(array_map('trim', explode(',', $allergens_raw))));
        
        // Получаем название секции
        $sec_terms = wp_get_post_terms($p->ID, 'wmb_section', ['fields' => 'names']);
        $sec = $sec_terms ? $sec_terms[0] : 'Прочее';
        
        // Переводим название секции
        $sec = translate_section_name($sec, $current_lang);
        
        if (!isset($sections[$sec])) $sections[$sec] = [];
        
        // Получаем название блюда
        $dish_name = get_the_title($p);
        if ($current_lang !== 'ru') {
            $translated_title = get_post_meta($p->ID, 'wmb_title_' . $current_lang, true);
            if (!empty($translated_title)) {
                $dish_name = $translated_title;
            }
        }
        
        // Получаем переводы ингредиентов и аллергенов
        if ($current_lang !== 'ru') {
            $translated_ingredients = get_post_meta($p->ID, 'wmb_ingredients_' . $current_lang, true);
            if (!empty($translated_ingredients)) {
                $ingredients = $translated_ingredients;
            }
            
            $translated_allergens = get_post_meta($p->ID, 'wmb_allergens_' . $current_lang, true);
            if (!empty($translated_allergens)) {
                $allergens = array_values(array_filter(array_map('trim', explode(',', $translated_allergens))));
            }
        }
        
        $sections[$sec][] = [
            'id'          => 'dish-' . $p->ID,
            'name'        => $dish_name,
            'price'       => $price,
            'unit'        => $unit,
            'ingredients' => $ingredients,
            'allergens'   => $allergens,
            'tags'        => wp_get_post_terms($p->ID, 'wmb_tag', ['fields' => 'names']),
            'kcal'        => 0,
        ];
    }
    
    $out_sections = [];
    foreach ($sections as $title => $items) {
        $out_sections[] = ['title' => $title, 'items' => $items];
    }
    
    // Жёсткий порядок секций с переводами
    $hard_order = get_section_order($current_lang);
    
    $index = array_map('mb_strtolower', $hard_order);
    usort($out_sections, function($a, $b) use ($index) {
        $ai = array_search(mb_strtolower($a['title']), $index);
        $bi = array_search(mb_strtolower($b['title']), $index);
        $ai = ($ai === false) ? PHP_INT_MAX : $ai;
        $bi = ($bi === false) ? PHP_INT_MAX : $bi;
        if ($ai === $bi) return strcmp($a['title'], $b['title']);
        return $ai - $bi;
    });
    
    return rest_ensure_response([
        'description'     => '',
        'delivery_config' => $settings['delivery'],
        'delivery_slots'  => [],
        'limits'          => ['max_portions' => 0],
        'sections'        => $out_sections,
        'current_language' => $current_lang,
    ]);
}

// Функция для перевода названий секций
function translate_section_name($section_name, $lang) {
    $translations = [
        'ru' => [
            'Паста ручной работы' => 'Паста ручной работы',
            'Авторские сэндвичи' => 'Авторские сэндвичи',
            'Основные блюда - мясо, птица, рыба (сувид)' => 'Основные блюда - мясо, птица, рыба (сувид)',
            'Гарниры и зелень' => 'Гарниры и зелень',
            'Завтраки и сладкое' => 'Завтраки и сладкое',
            'Супы и крем-супы' => 'Супы и крем-супы',
            'Для запаса / в морозильник' => 'Для запаса / в морозильник',
            'Прочее' => 'Прочее',
        ],
        'es' => [
            'Паста ручной работы' => 'Pasta artesanal',
            'Авторские сэндвичи' => 'Sándwiches únicos',
            'Основные блюда - мясо, птица, рыба (сувид)' => 'Platos principales - carne, aves, pescado (sous vide)',
            'Гарниры и зелень' => 'Guarniciones y verduras',
            'Завтраки и сладкое' => 'Desayunos y dulces',
            'Супы и крем-супы' => 'Sopas y cremas',
            'Для запаса / в морозильник' => 'Para reserva / congelador',
            'Прочее' => 'Otros',
        ],
        'en' => [
            'Паста ручной работы' => 'Handmade Pasta',
            'Авторские сэндвичи' => 'Signature Sandwiches',
            'Основные блюда - мясо, птица, рыба (сувид)' => 'Main Dishes - Meat, Poultry, Fish (Sous Vide)',
            'Гарниры и зелень' => 'Side Dishes & Greens',
            'Завтраки и сладкое' => 'Breakfast & Sweets',
            'Супы и крем-супы' => 'Soups & Cream Soups',
            'Для запаса / в морозильник' => 'For Stock / Freezer',
            'Прочее' => 'Other',
        ],
    ];
    
    return isset($translations[$lang][$section_name]) ? $translations[$lang][$section_name] : $section_name;
}

// Функция для получения порядка секций
function get_section_order($lang) {
    $orders = [
        'ru' => [
            'Паста ручной работы',
            'Авторские сэндвичи',
            'Основные блюда - мясо, птица, рыба (сувид)',
            'Гарниры и зелень',
            'Завтраки и сладкое',
            'Супы и крем-супы',
            'Для запаса / в морозильник',
        ],
        'es' => [
            'Pasta artesanal',
            'Sándwiches únicos',
            'Platos principales - carne, aves, pescado (sous vide)',
            'Guarniciones y verduras',
            'Desayunos y dulces',
            'Sopas y cremas',
            'Para reserva / congelador',
        ],
        'en' => [
            'Handmade Pasta',
            'Signature Sandwiches',
            'Main Dishes - Meat, Poultry, Fish (Sous Vide)',
            'Side Dishes & Greens',
            'Breakfast & Sweets',
            'Soups & Cream Soups',
            'For Stock / Freezer',
        ],
    ];
    
    return isset($orders[$lang]) ? $orders[$lang] : $orders['ru'];
}

// Добавляем поля для переводов в админку
add_action('add_meta_boxes', 'add_meal_builder_translation_fields');
function add_meal_builder_translation_fields() {
    add_meta_box(
        'wmb_translations',
        'Переводы блюда',
        'wmb_translations_callback',
        'wmb_dish',
        'normal',
        'high'
    );
}

function wmb_translations_callback($post) {
    wp_nonce_field('wmb_save_translations', 'wmb_translations_nonce');
    
    $languages = ['es' => 'Испанский', 'en' => 'Английский'];
    
    foreach ($languages as $lang => $lang_name) {
        $title = get_post_meta($post->ID, 'wmb_title_' . $lang, true);
        $ingredients = get_post_meta($post->ID, 'wmb_ingredients_' . $lang, true);
        $allergens = get_post_meta($post->ID, 'wmb_allergens_' . $lang, true);
        
        echo '<h3>' . $lang_name . '</h3>';
        echo '<p><label>Название блюда (' . $lang_name . ')<br>';
        echo '<input name="wmb_title_' . $lang . '" type="text" value="' . esc_attr($title) . '" style="width:100%"></label></p>';
        
        echo '<p><label>Состав (' . $lang_name . ')<br>';
        echo '<textarea name="wmb_ingredients_' . $lang . '" rows="3" style="width:100%">' . esc_textarea($ingredients) . '</textarea></label></p>';
        
        echo '<p><label>Аллергены (' . $lang_name . ')<br>';
        echo '<input name="wmb_allergens_' . $lang . '" type="text" value="' . esc_attr($allergens) . '" placeholder="глютен, молоко, яйца" style="width:100%"></label></p>';
    }
}

// Сохраняем переводы
add_action('save_post_wmb_dish', 'save_meal_builder_translations');
function save_meal_builder_translations($post_id) {
    if (!isset($_POST['wmb_translations_nonce']) || !wp_verify_nonce($_POST['wmb_translations_nonce'], 'wmb_save_translations')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $languages = ['es', 'en'];
    
    foreach ($languages as $lang) {
        if (isset($_POST['wmb_title_' . $lang])) {
            update_post_meta($post_id, 'wmb_title_' . $lang, sanitize_text_field($_POST['wmb_title_' . $lang]));
        }
        
        if (isset($_POST['wmb_ingredients_' . $lang])) {
            update_post_meta($post_id, 'wmb_ingredients_' . $lang, sanitize_textarea_field($_POST['wmb_ingredients_' . $lang]));
        }
        
        if (isset($_POST['wmb_allergens_' . $lang])) {
            update_post_meta($post_id, 'wmb_allergens_' . $lang, sanitize_text_field($_POST['wmb_allergens_' . $lang]));
        }
    }
}

// Добавляем JavaScript для перевода интерфейса Meal Builder
add_action('wp_footer', 'add_meal_builder_language_script');
function add_meal_builder_language_script() {
    if (!is_page() || !has_shortcode(get_post()->post_content, 'meal_builder')) {
        return;
    }
    
    $current_lang = get_current_language();
    if ($current_lang === 'ru') {
        return; // Русский уже правильный
    }
    
    $translations = [
        'es' => [
            'Соберите заказ до' => 'Haga su pedido antes de',
            'и мы привезём его' => 'y lo traeremos',
            'Осталось' => 'Queda',
            'Вск' => 'Dom',
            'Пн' => 'Lun',
            'Вт' => 'Mar',
            'Ср' => 'Mié',
            'Чт' => 'Jue',
            'Пт' => 'Vie',
            'Сб' => 'Sáb',
            'января' => 'enero',
            'февраля' => 'febrero',
            'марта' => 'marzo',
            'апреля' => 'abril',
            'мая' => 'mayo',
            'июня' => 'junio',
            'июля' => 'julio',
            'августа' => 'agosto',
            'сентября' => 'septiembre',
            'октября' => 'octubre',
            'ноября' => 'noviembre',
            'декабря' => 'diciembre',
        ],
        'en' => [
            'Соберите заказ до' => 'Place your order before',
            'и мы привезём его' => 'and we will deliver it',
            'Осталось' => 'Time left',
            'Вск' => 'Sun',
            'Пн' => 'Mon',
            'Вт' => 'Tue',
            'Ср' => 'Wed',
            'Чт' => 'Thu',
            'Пт' => 'Fri',
            'Сб' => 'Sat',
            'января' => 'January',
            'февраля' => 'February',
            'марта' => 'March',
            'апреля' => 'April',
            'мая' => 'May',
            'июня' => 'June',
            'июля' => 'July',
            'августа' => 'August',
            'сентября' => 'September',
            'октября' => 'October',
            'ноября' => 'November',
            'декабря' => 'December',
        ],
    ];
    
    if (!isset($translations[$current_lang])) {
        return;
    }
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var translations = <?php echo json_encode($translations[$current_lang]); ?>;
        
        // Функция для перевода текста
        function translateText(text) {
            for (var original in translations) {
                if (text.includes(original)) {
                    text = text.replace(new RegExp(original, 'g'), translations[original]);
                }
            }
            return text;
        }
        
        // Переводим все текстовые узлы
        function translateNode(node) {
            if (node.nodeType === 3) { // Text node
                var translated = translateText(node.textContent);
                if (translated !== node.textContent) {
                    node.textContent = translated;
                }
            } else if (node.nodeType === 1) { // Element node
                for (var i = 0; i < node.childNodes.length; i++) {
                    translateNode(node.childNodes[i]);
                }
            }
        }
        
        // Переводим весь контент Meal Builder
        var mealBuilder = document.getElementById('meal-builder-root');
        if (mealBuilder) {
            translateNode(mealBuilder);
        }
        
        // Также переводим динамически добавляемый контент
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        translateNode(node);
                    }
                });
            });
        });
        
        if (mealBuilder) {
            observer.observe(mealBuilder, {
                childList: true,
                subtree: true
            });
        }
    });
    </script>
    <?php
}
