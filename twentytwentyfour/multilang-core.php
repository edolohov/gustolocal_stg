<?php
/**
 * Основные функции многоязычности
 * Вынесено из functions.php для оптимизации
 */

// Подключаем файл переводов
require_once get_template_directory() . '/translations.php';

/**
 * Чистая система языковой маршрутизации
 * Только самое необходимое
 */

// Инициализация системы маршрутизации
add_action('init', 'setup_language_routing');

// Обработка языковых URL на раннем этапе
add_action('parse_request', 'handle_language_urls_early', 1);
function handle_language_urls_early($wp) {
    $request = $wp->request;
    
    // Отладка
    if (current_user_can('manage_options')) {
        add_action('wp_footer', function() use ($request) {
            echo "<!-- DEBUG: handle_language_urls_early called with request: $request -->";
        });
    }
    
    // Проверяем, есть ли языковой префикс
    if (preg_match('/^(es|en)\/(.*)/', $request, $matches)) {
        $lang = $matches[1];
        $path = $matches[2];
        
        // Отладка
        if (current_user_can('manage_options')) {
            add_action('wp_footer', function() use ($lang, $path) {
                echo "<!-- DEBUG: Found language prefix: $lang, path: $path -->";
            });
        }
        
        // Специальная обработка для WooCommerce страниц
        if (strpos($path, 'checkout/order-received') !== false) {
            // Устанавливаем правильные query vars для WooCommerce
            $wp->query_vars['lang'] = $lang;
            $wp->query_vars['pagename'] = 'checkout';
            
            // Извлекаем номер заказа
            if (preg_match('/order-received\/(\d+)/', $path, $order_matches)) {
                $wp->query_vars['order-received'] = $order_matches[1];
            }
            
            // Устанавливаем флаги для WooCommerce
            $wp->query_vars['woocommerce'] = true;
            $wp->query_vars['checkout'] = true;
            
            // Отладка
            if (current_user_can('manage_options')) {
                add_action('wp_footer', function() use ($wp) {
                    echo "<!-- DEBUG: Set query vars: " . print_r($wp->query_vars, true) . " -->";
                });
            }
        }
    }
}

// Принудительное обновление rewrite rules (выполнить один раз)
add_action('init', 'force_flush_rewrite_rules', 999);
function force_flush_rewrite_rules() {
    if (get_option('multilang_rewrite_flushed') !== '1') {
        flush_rewrite_rules();
        update_option('multilang_rewrite_flushed', '1');
    }
}

// Отладка rewrite rules
add_action('wp_footer', 'debug_rewrite_rules');
function debug_rewrite_rules() {
    if (current_user_can('manage_options')) {
        global $wp_rewrite;
        echo "<!-- DEBUG: Current rewrite rules -->";
        echo "<!-- DEBUG: " . print_r($wp_rewrite->rules, true) . " -->";
        echo "<!-- DEBUG: Current URL: " . $_SERVER['REQUEST_URI'] . " -->";
        echo "<!-- DEBUG: Query vars: " . print_r($GLOBALS['wp_query']->query_vars, true) . " -->";
    }
}

function setup_language_routing() {
    // Добавляем rewrite rules для испанской версии
    add_rewrite_rule('^es/(.*)/?', 'index.php?lang=es&pagename=$matches[1]', 'top');
    add_rewrite_rule('^es/?$', 'index.php?lang=es', 'top');
    
    // Добавляем rewrite rules для английской версии
    add_rewrite_rule('^en/(.*)/?', 'index.php?lang=en&pagename=$matches[1]', 'top');
    add_rewrite_rule('^en/?$', 'index.php?lang=en', 'top');
    
    // Специальные правила для WooCommerce страниц
    add_rewrite_rule('^es/checkout/(.*)/?', 'index.php?lang=es&pagename=checkout/$matches[1]', 'top');
    add_rewrite_rule('^en/checkout/(.*)/?', 'index.php?lang=en&pagename=checkout/$matches[1]', 'top');
    add_rewrite_rule('^es/cart/?', 'index.php?lang=es&pagename=cart', 'top');
    add_rewrite_rule('^en/cart/?', 'index.php?lang=en&pagename=cart', 'top');
    add_rewrite_rule('^es/my-account/(.*)/?', 'index.php?lang=es&pagename=my-account/$matches[1]', 'top');
    add_rewrite_rule('^en/my-account/(.*)/?', 'index.php?lang=en&pagename=my-account/$matches[1]', 'top');
    
    // Добавляем query vars для языка
    add_filter('query_vars', 'add_language_query_vars');
}

// Добавляем переменную 'lang' в список query vars
function add_language_query_vars($vars) {
    $vars[] = 'lang';
    $vars[] = 'order-received';
    return $vars;
}

// Определяем текущий язык
function get_current_language() {
    global $wp_query;
    
    // Проверяем query var
    if (isset($wp_query->query_vars['lang']) && !empty($wp_query->query_vars['lang'])) {
        $lang = $wp_query->query_vars['lang'];
        if (in_array($lang, ['ru', 'es', 'en'])) {
            return $lang;
        }
    }
    
    // По умолчанию возвращаем русский
    return 'ru';
}

// Простая система поиска страниц для многоязычных URL
add_action('pre_get_posts', 'simple_page_lookup');
function simple_page_lookup($query) {
    // Только для фронтенда, только для главного запроса
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $lang = get_query_var('lang');
    if (!in_array($lang, ['es', 'en'])) {
        return;
    }
    
    $pagename = get_query_var('pagename');
    
    // Специальная обработка для WooCommerce страниц
    if (strpos($pagename, 'checkout') !== false ||
        strpos($pagename, 'cart') !== false ||
        strpos($pagename, 'my-account') !== false) {
        
        // Для order-received страниц нужно правильно установить endpoint
        if (strpos($pagename, 'checkout/order-received') !== false) {
            // Убираем языковой префикс и восстанавливаем правильный путь
            $clean_path = str_replace('checkout/', '', $pagename);
            $query->set('pagename', 'checkout');
            $query->set('lang', $lang);
            
            // Устанавливаем endpoint для order-received
            if (preg_match('/order-received\/(\d+)/', $clean_path, $matches)) {
                $query->set('order-received', $matches[1]);
            }
        }
        
        return;
    }
    
    // Отладка
    if (current_user_can('manage_options')) {
        add_action('wp_footer', function() use ($lang, $pagename) {
            echo "<!-- DEBUG: simple_page_lookup called with lang=$lang, pagename=$pagename -->";
            echo "<!-- DEBUG: is_checkout=" . (is_checkout() ? 'true' : 'false') . " -->";
            echo "<!-- DEBUG: is_cart=" . (is_cart() ? 'true' : 'false') . " -->";
            echo "<!-- DEBUG: is_404=" . (is_404() ? 'true' : 'false') . " -->";
            echo "<!-- DEBUG: current URL=" . $_SERVER['REQUEST_URI'] . " -->";
            echo "<!-- DEBUG: query vars=" . print_r($GLOBALS['wp_query']->query_vars, true) . " -->";
        });
    }
    
    // Если это главная страница с языковым префиксом
    if (empty($pagename)) {
        $homepage_id = get_option('page_on_front');
        if ($homepage_id) {
            $homepage = get_post($homepage_id);
            if ($homepage) {
                $lang_slug = $homepage->post_name . '-' . $lang;
                $lang_page = get_page_by_path($lang_slug);
                
                // Отладка
                if (current_user_can('manage_options')) {
                    add_action('wp_footer', function() use ($lang_slug, $lang_page) {
                        $found = $lang_page ? "YES (ID: {$lang_page->ID})" : "NO";
                        echo "<!-- DEBUG: Looking for homepage slug: $lang_slug, found: $found -->";
                    });
                }
                
                if ($lang_page) {
                    $query->set('page_id', $lang_page->ID);
                    $query->set('pagename', '');
                    $query->is_page = true;
                    $query->is_singular = true;
                    $query->is_home = false;
                    $query->is_archive = false;
                }
            }
        }
        return;
    }
    
    // Для обычных страниц
    $lang_slug = $pagename . '-' . $lang;
    $page = get_page_by_path($lang_slug);
    
    if ($page) {
        $query->set('page_id', $page->ID);
        $query->set('pagename', '');
        $query->is_page = true;
        $query->is_singular = true;
        $query->is_home = false;
        $query->is_archive = false;
    }
}
