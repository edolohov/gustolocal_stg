<?php
/**
 * GustoLocal Meal Builder Module
 * 
 * Handles multilanguage support for Weekly Meal Builder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if required constants are defined
if (!defined('GUSTOLOCAL_PATH')) {
    return;
}

class GustoLocal_MealBuilder {
    
    public function __construct() {
        // Check if Meal Builder plugin is active
        if (!function_exists('wmb_assets_url')) {
            return;
        }
        
        if (!gustolocal_is_enabled('meal_builder') || !gustolocal_mb_setting('multilang_support', true)) {
            return;
        }
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Modify REST API
        add_filter('rest_pre_serve_request', array($this, 'add_language_to_api'), 10, 4);
        add_action('rest_api_init', array($this, 'modify_meal_builder_api'), 20);
        
        // Add translation fields to admin
        add_action('add_meta_boxes', array($this, 'add_translation_fields'));
        add_action('save_post_wmb_dish', array($this, 'save_translations'));
        
        // Add JavaScript for interface translation
        if (gustolocal_mb_setting('auto_translate_interface', true)) {
            add_action('wp_footer', array($this, 'add_interface_translation_script'));
        }
    }
    
    public function add_language_to_api($served, $result, $request, $server) {
        if (strpos($request->get_route(), '/wmb/v1/menu') !== false) {
            $current_lang = $this->get_current_language();
            if (is_array($result)) {
                $result['current_language'] = $current_lang;
            }
        }
        return $served;
    }
    
    public function modify_meal_builder_api() {
        register_rest_route('wmb/v1', '/menu', array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => array($this, 'get_menu_with_language'),
        ));
    }
    
    public function get_menu_with_language() {
        $current_lang = $this->get_current_language();
        $settings = wmb_get_settings();
        
        $posts = get_posts(array(
            'post_type'   => 'wmb_dish',
            'numberposts' => -1,
            'meta_key'    => 'wmb_active',
            'meta_value'  => '1',
            'orderby'     => 'title',
            'order'       => 'ASC',
        ));
        
        $sections = array();
        foreach ($posts as $p) {
            // Check if dish has translation for current language
            $has_translation = false;
            
            if ($current_lang === 'ru') {
                $has_translation = true;
            } else {
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
            
            // Get section name
            $sec_terms = wp_get_post_terms($p->ID, 'wmb_section', array('fields' => 'names'));
            $sec = $sec_terms ? $sec_terms[0] : 'Прочее';
            
            // Translate section name
            $sec = $this->translate_section_name($sec, $current_lang);
            
            if (!isset($sections[$sec])) $sections[$sec] = array();
            
            // Get dish name
            $dish_name = get_the_title($p);
            if ($current_lang !== 'ru') {
                $translated_title = get_post_meta($p->ID, 'wmb_title_' . $current_lang, true);
                if (!empty($translated_title)) {
                    $dish_name = $translated_title;
                }
            }
            
            // Get translations for ingredients and allergens
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
            
            $sections[$sec][] = array(
                'id'          => 'dish-' . $p->ID,
                'name'        => $dish_name,
                'price'       => $price,
                'unit'        => $unit,
                'ingredients' => $ingredients,
                'allergens'   => $allergens,
                'tags'        => wp_get_post_terms($p->ID, 'wmb_tag', array('fields' => 'names')),
                'kcal'        => 0,
            );
        }
        
        $out_sections = array();
        foreach ($sections as $title => $items) {
            $out_sections[] = array('title' => $title, 'items' => $items);
        }
        
        // Hard order sections with translations
        $hard_order = $this->get_section_order($current_lang);
        
        $index = array_map('mb_strtolower', $hard_order);
        usort($out_sections, function($a, $b) use ($index) {
            $ai = array_search(mb_strtolower($a['title']), $index);
            $bi = array_search(mb_strtolower($b['title']), $index);
            $ai = ($ai === false) ? PHP_INT_MAX : $ai;
            $bi = ($bi === false) ? PHP_INT_MAX : $bi;
            if ($ai === $bi) return strcmp($a['title'], $b['title']);
            return $ai - $bi;
        });
        
        return rest_ensure_response(array(
            'description'     => '',
            'delivery_config' => $settings['delivery'],
            'delivery_slots'  => array(),
            'limits'          => array('max_portions' => 0),
            'sections'        => $out_sections,
            'current_language' => $current_lang,
        ));
    }
    
    private function translate_section_name($section_name, $lang) {
        $translations = array(
            'ru' => array(
                'Паста ручной работы' => 'Паста ручной работы',
                'Авторские сэндвичи' => 'Авторские сэндвичи',
                'Основные блюда - мясо, птица, рыба (сувид)' => 'Основные блюда - мясо, птица, рыба (сувид)',
                'Гарниры и зелень' => 'Гарниры и зелень',
                'Завтраки и сладкое' => 'Завтраки и сладкое',
                'Супы и крем-супы' => 'Супы и крем-супы',
                'Для запаса / в морозильник' => 'Для запаса / в морозильник',
                'Прочее' => 'Прочее',
            ),
            'es' => array(
                'Паста ручной работы' => 'Pasta artesanal',
                'Авторские сэндвичи' => 'Sándwiches únicos',
                'Основные блюда - мясо, птица, рыба (сувид)' => 'Platos principales - carne, aves, pescado (sous vide)',
                'Гарниры и зелень' => 'Guarniciones y verduras',
                'Завтраки и сладкое' => 'Desayunos y dulces',
                'Супы и крем-супы' => 'Sopas y cremas',
                'Для запаса / в морозильник' => 'Para reserva / congelador',
                'Прочее' => 'Otros',
            ),
            'en' => array(
                'Паста ручной работы' => 'Handmade Pasta',
                'Авторские сэндвичи' => 'Signature Sandwiches',
                'Основные блюда - мясо, птица, рыба (сувид)' => 'Main Dishes - Meat, Poultry, Fish (Sous Vide)',
                'Гарниры и зелень' => 'Side Dishes & Greens',
                'Завтраки и сладкое' => 'Breakfast & Sweets',
                'Супы и крем-супы' => 'Soups & Cream Soups',
                'Для запаса / в морозильник' => 'For Stock / Freezer',
                'Прочее' => 'Other',
            ),
        );
        
        return isset($translations[$lang][$section_name]) ? $translations[$lang][$section_name] : $section_name;
    }
    
    private function get_section_order($lang) {
        $orders = array(
            'ru' => array(
                'Паста ручной работы',
                'Авторские сэндвичи',
                'Основные блюда - мясо, птица, рыба (сувид)',
                'Гарниры и зелень',
                'Завтраки и сладкое',
                'Супы и крем-супы',
                'Для запаса / в морозильник',
            ),
            'es' => array(
                'Pasta artesanal',
                'Sándwiches únicos',
                'Platos principales - carne, aves, pescado (sous vide)',
                'Guarniciones y verduras',
                'Desayunos y dulces',
                'Sopas y cremas',
                'Para reserva / congelador',
            ),
            'en' => array(
                'Handmade Pasta',
                'Signature Sandwiches',
                'Main Dishes - Meat, Poultry, Fish (Sous Vide)',
                'Side Dishes & Greens',
                'Breakfast & Sweets',
                'Soups & Cream Soups',
                'For Stock / Freezer',
            ),
        );
        
        return isset($orders[$lang]) ? $orders[$lang] : $orders['ru'];
    }
    
    public function add_translation_fields() {
        add_meta_box(
            'wmb_translations',
            'Переводы блюда',
            array($this, 'translation_fields_callback'),
            'wmb_dish',
            'normal',
            'high'
        );
    }
    
    public function translation_fields_callback($post) {
        wp_nonce_field('wmb_save_translations', 'wmb_translations_nonce');
        
        $languages = array('es' => 'Испанский', 'en' => 'Английский');
        
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
    
    public function save_translations($post_id) {
        if (!isset($_POST['wmb_translations_nonce']) || !wp_verify_nonce($_POST['wmb_translations_nonce'], 'wmb_save_translations')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $languages = array('es', 'en');
        
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
    
    public function add_interface_translation_script() {
        if (!is_page() || !has_shortcode(get_post()->post_content, 'meal_builder')) {
            return;
        }
        
        $current_lang = $this->get_current_language();
        if ($current_lang === 'ru') {
            return;
        }
        
        $translations = array(
            'es' => array(
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
            ),
            'en' => array(
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
            ),
        );
        
        if (!isset($translations[$current_lang])) {
            return;
        }
        
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var translations = <?php echo json_encode($translations[$current_lang]); ?>;
            
            function translateText(text) {
                for (var original in translations) {
                    if (text.includes(original)) {
                        text = text.replace(new RegExp(original, 'g'), translations[original]);
                    }
                }
                return text;
            }
            
            function translateNode(node) {
                if (node.nodeType === 3) {
                    var translated = translateText(node.textContent);
                    if (translated !== node.textContent) {
                        node.textContent = translated;
                    }
                } else if (node.nodeType === 1) {
                    for (var i = 0; i < node.childNodes.length; i++) {
                        translateNode(node.childNodes[i]);
                    }
                }
            }
            
            var mealBuilder = document.getElementById('meal-builder-root');
            if (mealBuilder) {
                translateNode(mealBuilder);
            }
            
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
    
    private function get_current_language() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['lang']) && !empty($wp_query->query_vars['lang'])) {
            $lang = $wp_query->query_vars['lang'];
            $supported_languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
            if (in_array($lang, $supported_languages)) {
                return $lang;
            }
        }
        
        return gustolocal_ml_setting('default_language', 'ru');
    }
}

// Initialize Meal Builder module (called from main functions.php)
// new GustoLocal_MealBuilder();
