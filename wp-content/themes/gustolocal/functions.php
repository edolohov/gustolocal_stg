<?php
/**
 * GustoLocal theme functions.
 */

if ( ! defined( 'GUSTOLOCAL_VERSION' ) ) {
    define( 'GUSTOLOCAL_VERSION', '0.5.3' );
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'custom-logo', [
        'height'      => 120,
        'width'       => 120,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_theme_support( 'custom-spacing' );
    add_theme_support( 'custom-units', [ 'px', 'em', 'rem', '%' ] );
    add_theme_support( 'align-wide' );
} );

add_action( 'wp_enqueue_scripts', function () {
    $theme_dir = get_template_directory_uri();
    wp_enqueue_style( 'gustolocal-main', $theme_dir . '/style.css', [], GUSTOLOCAL_VERSION );
    wp_enqueue_script( 'gustolocal-navigation', $theme_dir . '/assets/js/navigation.js', [], GUSTOLOCAL_VERSION, true );
    
    // Load gallery script only on rico page
    if ( is_page( 'rico' ) || ( is_page() && get_post_field( 'post_name' ) === 'rico' ) ) {
        wp_enqueue_script( 'gustolocal-rico-gallery', $theme_dir . '/assets/js/rico-gallery.js', [], GUSTOLOCAL_VERSION, true );
    }
} );

add_action( 'enqueue_block_editor_assets', function () {
    $theme_dir = get_template_directory_uri();
    wp_enqueue_style( 'gustolocal-editor', $theme_dir . '/style.css', [], GUSTOLOCAL_VERSION );
} );

/* ============ Автоматическое создание таблиц WooCommerce ============ */
// Проверяем и создаем недостающие таблицы WooCommerce
add_action('woocommerce_loaded', 'gustolocal_check_wc_tables', 20);
function gustolocal_check_wc_tables() {
    // Проверяем только если WooCommerce активен
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Загружаем класс установки WooCommerce, если он еще не загружен
    if (!class_exists('WC_Install')) {
        $wc_install_file = WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-install.php';
        if (file_exists($wc_install_file)) {
            require_once($wc_install_file);
        } else {
            return; // Не можем создать таблицы без класса установки
        }
    }
    
    global $wpdb;
    $table_prefix = $wpdb->prefix;
    
    // Проверяем наличие критических таблиц
    $critical_tables = array(
        'wc_orders_meta',
        'wc_order_addresses',
    );
    
    $missing_tables = array();
    foreach ($critical_tables as $table) {
        $full_table_name = $table_prefix . $table;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
        if (!$exists) {
            $missing_tables[] = $table;
        }
    }
    
    // Если есть недостающие таблицы, создаем их
    if (!empty($missing_tables) && class_exists('WC_Install') && method_exists('WC_Install', 'create_tables')) {
        try {
            // Запускаем создание таблиц WooCommerce
            WC_Install::create_tables();
            
            // Логируем для отладки
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GustoLocal: Созданы недостающие таблицы WooCommerce: ' . implode(', ', $missing_tables));
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GustoLocal: Ошибка при создании таблиц WooCommerce: ' . $e->getMessage());
            }
        }
    }
}

/* ============ Контент главной страницы ============ */
// Если страница "Главная" пустая, заполняем ее шаблоном-паттерном
add_action('init', 'gustolocal_seed_front_page_content', 30);
function gustolocal_seed_front_page_content() {
    if (is_admin() && !wp_doing_ajax()) {
        // В админке не вмешиваемся, чтобы не мешать редактированию
        return;
    }
    
    $already_seeded = get_option('gustolocal_front_page_seeded', false);
    $front_page_id = (int) get_option('page_on_front');
    if (!$front_page_id) {
        return;
    }
    
    $front_page = get_post($front_page_id);
    if (!$front_page || 'page' !== $front_page->post_type) {
        return;
    }
    
    // Если на странице уже есть контент, фиксируем флаг и выходим
    if (!empty(trim($front_page->post_content))) {
        if (!$already_seeded) {
            update_option('gustolocal_front_page_seeded', 1);
        }
        return;
    }
    
    if ($already_seeded) {
        // Контент пустой, но мы уже заполняли раньше — не перезаписываем
        return;
    }
    
    if (!class_exists('WP_Block_Patterns_Registry')) {
        return;
    }
    
    $registry = WP_Block_Patterns_Registry::get_instance();
    if (!$registry->is_registered('gustolocal/homepage')) {
        return;
    }
    
    $pattern = $registry->get_registered('gustolocal/homepage');
    if (empty($pattern['content'])) {
        return;
    }
    
    wp_update_post(array(
        'ID' => $front_page_id,
        'post_content' => $pattern['content'],
    ));
    
    update_option('gustolocal_front_page_seeded', 1);
}

// Фолбэк: если по какой-то причине контент пустой, показываем паттерн на фронте
add_filter('render_block', 'gustolocal_front_page_fallback_pattern', 20, 2);
function gustolocal_front_page_fallback_pattern($block_content, $block) {
    if (!is_front_page()) {
        return $block_content;
    }
    
    if ($block['blockName'] !== 'core/post-content') {
        return $block_content;
    }
    
    if (trim($block_content) !== '') {
        return $block_content;
    }
    
    $pattern_content = gustolocal_get_pattern_content('gustolocal/homepage');

    return $pattern_content ? $pattern_content : $block_content;
}

// Утилита для заполнения страниц контентом из паттернов (одноразово)
add_action('init', 'gustolocal_register_theme_patterns', 9);
function gustolocal_register_theme_patterns() {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    $patterns_dir = get_theme_file_path('patterns');
    if (!is_dir($patterns_dir)) {
        return;
    }

    $files = glob($patterns_dir . '/*.php');
    if (!$files) {
        return;
    }

    foreach ($files as $file_path) {
        $data = get_file_data($file_path, array(
            'title'      => 'Title',
            'slug'       => 'Slug',
            'categories' => 'Categories',
        ));

        $slug = !empty($data['slug']) ? trim($data['slug']) : 'gustolocal/' . basename($file_path, '.php');
        $title = !empty($data['title']) ? $data['title'] : ucwords(str_replace('-', ' ', basename($file_path, '.php')));
        $categories = array();

        if (!empty($data['categories'])) {
            $categories = array_map('trim', explode(',', $data['categories']));
        }

        if (empty($categories)) {
            $categories = array('featured');
        }

        $content = gustolocal_load_pattern_file($file_path);
        if (empty($content)) {
            continue;
        }

        register_block_pattern($slug, array(
            'title'      => $title,
            'categories' => $categories,
            'content'    => $content,
        ));
    }
}

add_action('wp_loaded', 'gustolocal_seed_static_pages');
function gustolocal_seed_static_pages() {
    $pages = array(
        array(
            'slug'        => 'como-preparamos',
            'pattern'     => 'gustolocal/como-preparamos',
            'option_name' => 'gustolocal_seed_como_preparamos',
        ),
        array(
            'slug'        => 'test',
            'pattern'     => 'gustolocal/test-page',
            'option_name' => 'gustolocal_seed_test',
        ),
        array(
            'slug'        => 'custom',
            'pattern'     => 'gustolocal/custom-page',
            'option_name' => 'gustolocal_seed_custom',
        ),
        array(
            'slug'        => 'pan-sandwiches-valencia',
            'pattern'     => 'gustolocal/pan-sandwiches',
            'option_name' => 'gustolocal_seed_pan_sandwiches',
        ),
    );
    
    $registry = WP_Block_Patterns_Registry::get_instance();
    
    foreach ($pages as $item) {
        $option_name  = $item['option_name'];
        $option_value = get_option($option_name);
        
        $page = get_page_by_path($item['slug']);
        if (!$page) {
            continue;
        }
        
        $has_blocks = strpos($page->post_content, '<!-- wp:') !== false;

        if ($option_value === 'done' && $has_blocks) {
            continue;
        }

        if (!empty(trim($page->post_content))) {
            // Если контент уже заполнен блоками, пропускаем
            if ($has_blocks) {
                update_option($option_name, 'done');
                continue;
            }
        }
        
        $pattern_content = gustolocal_get_pattern_content($item['pattern']);
        if (!$pattern_content) {
            continue;
        }
        
        wp_update_post(array(
            'ID'           => $page->ID,
            'post_content' => $pattern_content,
        ));
        
        update_option($option_name, 'done');
    }
}

function gustolocal_get_pattern_content($pattern_slug) {
    $content = '';

    if (class_exists('WP_Block_Patterns_Registry')) {
        $registry = WP_Block_Patterns_Registry::get_instance();
        if ($registry->is_registered($pattern_slug)) {
            $pattern = $registry->get_registered($pattern_slug);
            if (!empty($pattern['content'])) {
                $content = $pattern['content'];
            }
        }
    }

    if ($content) {
        return $content;
    }

    $parts = explode('/', $pattern_slug);
    $file  = end($parts);
    $path  = get_theme_file_path('patterns/' . $file . '.php');

    return gustolocal_load_pattern_file($path);
}

function gustolocal_load_pattern_file($path) {
    if (!file_exists($path)) {
        return '';
    }
    ob_start();
    include $path;
    return trim(ob_get_clean());
}

/* ============ WooCommerce упрощенная форма оформления ============ */
// Упрощаем форму чекаута - оставляем только необходимые поля
add_filter('woocommerce_checkout_fields', 'gustolocal_simplify_checkout_fields');
function gustolocal_simplify_checkout_fields($fields) {
    // Полностью скрываем shipping поля (доставка не используется)
    unset($fields['shipping']);
    
    // Удаляем ненужные поля
    unset($fields['billing']['billing_company']);
    
    // Скрываем поля, которые заполняются автоматически (делаем необязательными и скрываем через CSS)
    if (isset($fields['billing']['billing_country'])) {
        $fields['billing']['billing_country']['required'] = false;
        $fields['billing']['billing_country']['class'][] = 'hidden-field';
        $fields['billing']['billing_country']['validate'] = array(); // Убираем валидацию
    }
    
    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['required'] = false;
        $fields['billing']['billing_state']['class'][] = 'hidden-field';
        $fields['billing']['billing_state']['validate'] = array(); // Убираем валидацию
    }
    
    if (isset($fields['billing']['billing_city'])) {
        $fields['billing']['billing_city']['required'] = false;
        $fields['billing']['billing_city']['class'][] = 'hidden-field';
        $fields['billing']['billing_city']['validate'] = array(); // Убираем валидацию
    }
    
    if (isset($fields['billing']['billing_postcode'])) {
        $fields['billing']['billing_postcode']['required'] = false;
        $fields['billing']['billing_postcode']['class'][] = 'hidden-field';
        $fields['billing']['billing_postcode']['validate'] = array(); // Убираем валидацию
    }
    
    // Настраиваем видимые поля согласно дизайну
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['label'] = 'Ваше имя';
        $fields['billing']['billing_first_name']['required'] = true;
        $fields['billing']['billing_first_name']['placeholder'] = '';
        $fields['billing']['billing_first_name']['priority'] = 10;
        $fields['billing']['billing_first_name']['class'] = array('form-row-first');
    }
    
    if (isset($fields['billing']['billing_last_name'])) {
        $fields['billing']['billing_last_name']['label'] = 'и фамилия';
        $fields['billing']['billing_last_name']['required'] = true;
        $fields['billing']['billing_last_name']['placeholder'] = '';
        $fields['billing']['billing_last_name']['priority'] = 20;
        $fields['billing']['billing_last_name']['class'] = array('form-row-last');
    }
    
    if (isset($fields['billing']['billing_address_1'])) {
        $fields['billing']['billing_address_1']['label'] = 'Адрес';
        $fields['billing']['billing_address_1']['required'] = false;
        $fields['billing']['billing_address_1']['placeholder'] = 'Номер дома и название улицы';
        $fields['billing']['billing_address_1']['priority'] = 30;
    }
    
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['label'] = 'Ваш e-mail';
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['placeholder'] = '';
        $fields['billing']['billing_email']['priority'] = 40;
    }
    
    if (isset($fields['billing']['billing_address_2'])) {
        $fields['billing']['billing_address_2']['required'] = false;
        $fields['billing']['billing_address_2']['label'] = 'Как к вам попасть';
        $fields['billing']['billing_address_2']['placeholder'] = 'укажите домофон, этаж и квартиру';
        $fields['billing']['billing_address_2']['priority'] = 50;
    }
    
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['required'] = false;
        $fields['billing']['billing_phone']['label'] = 'Как с вами связаться';
        $fields['billing']['billing_phone']['placeholder'] = 'телеграм, whatsApp, телефон или факс';
        $fields['billing']['billing_phone']['priority'] = 60;
    }
    
    return $fields;
}

// Отключаем обязательную валидацию email, так как поле необязательное
add_filter('woocommerce_checkout_fields', 'gustolocal_make_email_optional', 20);
function gustolocal_make_email_optional($fields) {
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['validate'] = array('email'); // Валидация формата, но не обязательность
    }
    return $fields;
}

// Отключаем валидацию скрытых полей (провинция, страна, город, почтовый индекс)
add_action('woocommerce_checkout_process', 'gustolocal_disable_hidden_fields_validation', 1);
function gustolocal_disable_hidden_fields_validation() {
    // Устанавливаем значения по умолчанию для скрытых полей перед валидацией
    if (empty($_POST['billing_country']) || !isset($_POST['billing_country'])) {
        $_POST['billing_country'] = 'ES';
    }
    if (empty($_POST['billing_state']) || !isset($_POST['billing_state'])) {
        $_POST['billing_state'] = 'VC';
    }
    if (empty($_POST['billing_city']) || !isset($_POST['billing_city'])) {
        $_POST['billing_city'] = 'Валенсия';
    }
    if (empty($_POST['billing_postcode']) || !isset($_POST['billing_postcode'])) {
        $_POST['billing_postcode'] = '46000';
    }
}

// Удаляем ошибки валидации для скрытых полей
add_filter('woocommerce_checkout_fields', 'gustolocal_remove_hidden_fields_errors', 999);
function gustolocal_remove_hidden_fields_errors($fields) {
    // Удаляем ошибки валидации для скрытых полей
    $hidden_fields = array('billing_country', 'billing_state', 'billing_city', 'billing_postcode');
    foreach ($hidden_fields as $field_key) {
        if (isset($fields['billing'][$field_key])) {
            // Убираем все валидации
            $fields['billing'][$field_key]['validate'] = array();
            $fields['billing'][$field_key]['required'] = false;
        }
    }
    return $fields;
}

// Удаляем уведомления об ошибках для скрытых полей
add_action('woocommerce_after_checkout_validation', 'gustolocal_remove_hidden_fields_notices', 10, 2);
function gustolocal_remove_hidden_fields_notices($data, $errors) {
    $hidden_fields = array('billing_country', 'billing_state', 'billing_city', 'billing_postcode');
    foreach ($hidden_fields as $field_key) {
        if ($errors->get_error_message($field_key)) {
            $errors->remove($field_key);
        }
    }
}

// Устанавливаем значения по умолчанию только если они пустые (резервное заполнение)
// Плагин Checkout Field Editor управляет полями, но если значения не установлены,
// заполняем их для корректной работы платежных систем
add_filter('woocommerce_checkout_get_value', 'gustolocal_set_default_checkout_values', 10, 2);
function gustolocal_set_default_checkout_values($value, $input) {
    if (empty($value)) {
        switch ($input) {
            case 'billing_country':
                return 'ES';
            case 'billing_state':
                return 'VC';
            case 'billing_city':
                return 'Валенсия';
            case 'billing_postcode':
                return '46000';
        }
    }
    return $value;
}

// Устанавливаем значения по умолчанию ПЕРЕД обработкой заказа (критично для платежных систем)
// Используем более ранний хук, чтобы значения были установлены до валидации
// ВАЖНО: Устанавливаем значения только в $_POST, не трогая процесс создания заказа
add_action('woocommerce_before_checkout_process', 'gustolocal_set_checkout_defaults_before_process', 1);
function gustolocal_set_checkout_defaults_before_process() {
    // Проверяем, что это действительно запрос чекаута
    if (!isset($_POST['woocommerce-process-checkout-nonce'])) {
        return;
    }
    
    // Устанавливаем значения только если они действительно пустые
    // Это нужно для корректной работы платежных систем
    if (empty($_POST['billing_country']) || !isset($_POST['billing_country'])) {
        $_POST['billing_country'] = 'ES';
    }
    if (empty($_POST['billing_state']) || !isset($_POST['billing_state'])) {
        $_POST['billing_state'] = 'VC';
    }
    if (empty($_POST['billing_city']) || !isset($_POST['billing_city'])) {
        $_POST['billing_city'] = 'Валенсия';
    }
    if (empty($_POST['billing_postcode']) || !isset($_POST['billing_postcode'])) {
        $_POST['billing_postcode'] = '46000';
    }
    
    // Если email пустой, устанавливаем дефолтный для создания заказа
    // WooCommerce требует email для создания заказа, но мы делаем поле необязательным для пользователя
    if (empty($_POST['billing_email']) || !isset($_POST['billing_email'])) {
        // Используем email текущего пользователя, если он залогинен, иначе дефолтный
        $user_email = '';
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_email = $current_user->user_email;
        }
        if (empty($user_email)) {
            // Дефолтный email для анонимных пользователей
            $user_email = 'noreply@gustolocal.es';
        }
        $_POST['billing_email'] = $user_email;
    }
}

// Проверяем успешность создания заказа
add_filter('woocommerce_checkout_create_order', 'gustolocal_ensure_order_creation', 10, 2);
function gustolocal_ensure_order_creation($order, $data) {
    if (!$order || is_wp_error($order)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Ошибка создания заказа: ' . (is_wp_error($order) ? $order->get_error_message() : 'Order is false'));
            error_log('GustoLocal: Данные заказа: ' . print_r($data, true));
        }
    }
    return $order;
}

// Также устанавливаем значения в заказ, если они не были установлены
// Используем хук ПОСЛЕ создания заказа, чтобы не конфликтовать с плагином Checkout Field Editor
add_action('woocommerce_new_order', 'gustolocal_set_order_defaults_after_creation', 20, 1);
add_action('woocommerce_checkout_order_processed', 'gustolocal_set_order_defaults_after_creation', 20, 1);
function gustolocal_set_order_defaults_after_creation($order_id) {
    // Проверяем, что это действительно ID заказа
    if (!$order_id || !is_numeric($order_id)) {
        return;
    }
    
    // Используем небольшую задержку, чтобы плагин успел обработать заказ
    // Но делаем это синхронно, чтобы не было проблем с AJAX
    gustolocal_apply_order_defaults($order_id);
}

function gustolocal_apply_order_defaults($order_id) {
    try {
        $order = wc_get_order($order_id);
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        
        $needs_save = false;
        
        if (!$order->get_billing_country()) {
            $order->set_billing_country('ES');
            $needs_save = true;
        }
        if (!$order->get_billing_state()) {
            $order->set_billing_state('VC');
            $needs_save = true;
        }
        if (!$order->get_billing_city()) {
            $order->set_billing_city('Валенсия');
            $needs_save = true;
        }
        if (!$order->get_billing_postcode()) {
            $order->set_billing_postcode('46000');
            $needs_save = true;
        }
        
        if ($needs_save) {
            $order->save();
        }
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем процесс
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Ошибка при установке значений по умолчанию для заказа ' . $order_id . ': ' . $e->getMessage());
        }
    }
}

// Логирование ошибок чекаута для отладки
add_action('woocommerce_checkout_process', 'gustolocal_log_checkout_errors', 999);
function gustolocal_log_checkout_errors() {
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $errors = wc_get_notices('error');
        if (!empty($errors)) {
            error_log('GustoLocal: Ошибки валидации чекаута: ' . print_r($errors, true));
        }
    }
}

// ФИКС: Исправляем проблему с плагином Checkout Field Editor
// Плагин получает false вместо объекта заказа и пытается вызвать save() на false
// Перехватываем хук с максимальным приоритетом и исправляем проблему ДО плагина
add_action('woocommerce_checkout_update_order_meta', 'gustolocal_fix_checkout_field_editor_order', 0, 2);
function gustolocal_fix_checkout_field_editor_order($order_id, $data) {
    // Проверяем, что order_id валидный
    if (!$order_id || !is_numeric($order_id)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Невалидный order_id в woocommerce_checkout_update_order_meta: ' . var_export($order_id, true));
        }
        // Прерываем выполнение, чтобы плагин не получил false
        return;
    }
    
    // Получаем заказ и проверяем, что он существует
    $order = wc_get_order($order_id);
    if (!$order || !is_a($order, 'WC_Order')) {
        // Если заказ не найден, логируем ошибку и прерываем выполнение
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Заказ ' . $order_id . ' не найден при вызове woocommerce_checkout_update_order_meta. Данные: ' . print_r($data, true));
        }
        // Прерываем выполнение, чтобы плагин не получил false
        return;
    }
    
    // Убеждаемся, что заказ сохранен и имеет все необходимые данные
    $needs_save = false;
    if (!$order->get_billing_country()) {
        $order->set_billing_country('ES');
        $needs_save = true;
    }
    if (!$order->get_billing_state()) {
        $order->set_billing_state('VC');
        $needs_save = true;
    }
    if (!$order->get_billing_city()) {
        $order->set_billing_city('Валенсия');
        $needs_save = true;
    }
    if (!$order->get_billing_postcode()) {
        $order->set_billing_postcode('46000');
        $needs_save = true;
    }
    
    // Сохраняем заказ, чтобы плагин получил валидный объект
    if ($needs_save) {
        $order->save();
    }
    
    // КРИТИЧНО: Убеждаемся, что заказ доступен в глобальном контексте для плагина
    // Плагин может пытаться получить заказ из глобальной переменной
    global $thwcfd_order;
    if (!isset($thwcfd_order) || !$thwcfd_order) {
        $thwcfd_order = $order;
    }
}

// Дополнительный фикс: перехватываем создание заказа и сохраняем его в глобальной переменной
add_action('woocommerce_new_order', 'gustolocal_store_order_for_plugin', 1, 1);
function gustolocal_store_order_for_plugin($order_id) {
    if ($order_id && is_numeric($order_id)) {
        $order = wc_get_order($order_id);
        if ($order && is_a($order, 'WC_Order')) {
            global $thwcfd_order;
            $thwcfd_order = $order;
        }
    }
}

// КРИТИЧЕСКИЙ ФИКС: Перехватываем вызов метода плагина и исправляем проблему
// Плагин получает false вместо объекта заказа, поэтому перехватываем его метод
// Используем несколько хуков, чтобы перехватить плагин в любом случае
add_action('init', 'gustolocal_fix_checkout_field_editor_plugin', 999);
add_action('wp_loaded', 'gustolocal_fix_checkout_field_editor_plugin', 999);
function gustolocal_fix_checkout_field_editor_plugin() {
    try {
        // Проверяем, что плагин активен
        if (!class_exists('THWCFD_Public_Checkout')) {
            return;
        }
        
        // Получаем экземпляр класса плагина безопасно
        $plugin_instance = null;
        if (method_exists('THWCFD_Public_Checkout', 'instance')) {
            $plugin_instance = THWCFD_Public_Checkout::instance();
        } elseif (method_exists('THWCFD_Public_Checkout', 'get_instance')) {
            $plugin_instance = THWCFD_Public_Checkout::get_instance();
        }
        
        if (!$plugin_instance) {
            return;
        }
        
        // Перехватываем метод checkout_update_order_meta
        // Удаляем оригинальный хук (пробуем разные приоритеты)
        if (method_exists($plugin_instance, 'checkout_update_order_meta')) {
            remove_action('woocommerce_checkout_update_order_meta', array($plugin_instance, 'checkout_update_order_meta'), 10);
            remove_action('woocommerce_checkout_update_order_meta', array($plugin_instance, 'checkout_update_order_meta'));
        }
        
        // Добавляем наш исправленный метод с более высоким приоритетом
        if (!has_action('woocommerce_checkout_update_order_meta', 'gustolocal_safe_checkout_update_order_meta')) {
            add_action('woocommerce_checkout_update_order_meta', 'gustolocal_safe_checkout_update_order_meta', 5, 2);
        }
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем работу сайта
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Ошибка при перехвате Checkout Field Editor: ' . $e->getMessage());
        }
    } catch (Error $e) {
        // Логируем фатальную ошибку
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Фатальная ошибка при перехвате Checkout Field Editor: ' . $e->getMessage());
        }
    }
}

// Безопасная версия метода плагина, которая проверяет заказ перед использованием
function gustolocal_safe_checkout_update_order_meta($order_id, $data) {
    try {
        // Проверяем, что order_id валидный
        if (!$order_id || !is_numeric($order_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GustoLocal: Пропущен Checkout Field Editor - невалидный order_id: ' . var_export($order_id, true));
            }
            return;
        }
        
        // Получаем заказ и проверяем, что он существует
        $order = wc_get_order($order_id);
        if (!$order || !is_a($order, 'WC_Order')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GustoLocal: Пропущен Checkout Field Editor - заказ ' . $order_id . ' не найден');
            }
            return;
        }
        
        // Теперь вызываем оригинальный метод плагина, но с гарантией, что заказ существует
        if (!class_exists('THWCFD_Public_Checkout')) {
            return;
        }
        
        $plugin_instance = null;
        if (method_exists('THWCFD_Public_Checkout', 'instance')) {
            $plugin_instance = THWCFD_Public_Checkout::instance();
        } elseif (method_exists('THWCFD_Public_Checkout', 'get_instance')) {
            $plugin_instance = THWCFD_Public_Checkout::get_instance();
        }
        
        if ($plugin_instance && method_exists($plugin_instance, 'checkout_update_order_meta')) {
            // Устанавливаем заказ в глобальной переменной для плагина
            global $thwcfd_order;
            $thwcfd_order = $order;
            
            // Вызываем метод плагина
            $plugin_instance->checkout_update_order_meta($order_id, $data);
        }
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Ошибка в безопасном методе Checkout Field Editor: ' . $e->getMessage());
        }
    } catch (Error $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GustoLocal: Фатальная ошибка в безопасном методе Checkout Field Editor: ' . $e->getMessage());
        }
    }
}

/* ============ WooCommerce опции доставки ============ */
// Обработка изменения типа доставки через AJAX
add_action('wp_ajax_update_delivery_type', 'gustolocal_update_delivery_type');
add_action('wp_ajax_nopriv_update_delivery_type', 'gustolocal_update_delivery_type');
function gustolocal_update_delivery_type() {
    check_ajax_referer('gustolocal_delivery', 'nonce');
    
    $delivery_type = sanitize_text_field($_POST['delivery_type']);
    
    if (in_array($delivery_type, array('delivery', 'pickup'))) {
        WC()->session->set('delivery_type', $delivery_type);
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->calculate_totals();
        }
        wp_send_json_success();
    }
    
    wp_send_json_error();
}

// Обработка изменения типа доставки при обновлении корзины
add_action('woocommerce_update_cart_action_cart_updated', 'gustolocal_update_delivery_type_on_cart_update');
function gustolocal_update_delivery_type_on_cart_update() {
    if (isset($_POST['delivery_type'])) {
        $delivery_type = sanitize_text_field($_POST['delivery_type']);
        if (in_array($delivery_type, array('delivery', 'pickup'))) {
            WC()->session->set('delivery_type', $delivery_type);
        }
    }
}

// Добавляем плату за доставку если выбрана доставка
add_action('woocommerce_cart_calculate_fees', 'gustolocal_add_delivery_fee');
function gustolocal_add_delivery_fee() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $delivery_type = WC()->session->get('delivery_type', 'delivery');
    
    // Remove previously added delivery fees to avoid duplicates
    $fees_api = WC()->cart->fees_api();
    foreach ( $fees_api->get_fees() as $key => $fee ) {
        if ( in_array( $fee->name, array( 'Доставка', 'Самовывоз' ), true ) ) {
            $fees_api->remove_fee( $fee );
        }
    }
    
    if ($delivery_type === 'delivery') {
        WC()->cart->add_fee(__('Доставка', 'woocommerce'), 10.00, false);
    } else {
        // Отображаем строку «Самовывоз» с нулевой стоимостью
        WC()->cart->add_fee(__('Самовывоз', 'woocommerce'), 0, false);
    }
}

// Подключаем JavaScript для обработки опций доставки
add_action('wp_enqueue_scripts', 'gustolocal_enqueue_delivery_scripts');
function gustolocal_enqueue_delivery_scripts() {
    if (is_cart() || is_checkout()) {
        wp_enqueue_script(
            'gustolocal-delivery',
            get_template_directory_uri() . '/assets/js/delivery-options.js',
            array('jquery'),
            GUSTOLOCAL_VERSION,
            true
        );
        
        wp_localize_script('gustolocal-delivery', 'gustolocal_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gustolocal_delivery'),
        ));
    }
}

/* ============ Переводы WooCommerce на русский ============ */
// Принудительно устанавливаем русский язык для WooCommerce
add_filter('plugin_locale', 'gustolocal_force_woocommerce_locale', 10, 2);
function gustolocal_force_woocommerce_locale($locale, $domain) {
    if ($domain === 'woocommerce') {
        return 'ru_RU';
    }
    return $locale;
}

// Переводим основные строки WooCommerce
add_filter('gettext', 'gustolocal_translate_woocommerce_strings', 20, 3);
function gustolocal_translate_woocommerce_strings($translated_text, $text, $domain) {
    if ($domain !== 'woocommerce' || is_admin()) {
        return $translated_text;
    }
    
    $translations = array(
        'Product' => 'Товар',
        'Price' => 'Цена',
        'Quantity' => 'Количество',
        'Subtotal' => 'Подытог',
        'Total' => 'Итого',
        'Update cart' => 'Обновить корзину',
        'Coupon code' => 'Код купона',
        'Apply coupon' => 'Применить купон',
        'Your order' => 'Ваш заказ',
        'Place order' => 'Оформить заказ',
        'Checkout' => 'Оформление заказа',
        'Cart' => 'Корзина',
        'Remove item' => 'Удалить товар',
        'Order total' => 'Сумма заказов',
        'Cart totals' => 'Сумма заказа',
        'Proceed to checkout' => 'Перейти к оформлению',
        'Update cart' => 'Обновить корзину',
        'Delivery' => 'Доставка',
        'Coupon code applied successfully.' => 'Купон успешно применён.',
        'Coupon removed.' => 'Купон удалён.',
        'Coupon code removed successfully.' => 'Купон удалён.',
        'Coupon:' => 'Купон:',
        'Your cart is currently empty.' => 'Ваша корзина пуста.',
        'Return to shop' => 'Вернуться в магазин',
        'Remove' => 'Удалить',
    );
    
    if (isset($translations[$text])) {
        return $translations[$text];
    }
    
    return $translated_text;
}

add_filter('woocommerce_return_to_shop_redirect', function() {
    return home_url('/');
});

// Принудительно использовать правильный header для checkout
add_filter('render_block_core/template-part', function($block_content, $block) {
    if (is_checkout() && isset($block['attrs']['slug']) && $block['attrs']['slug'] === 'header') {
        // Загружаем правильный header из файла
        $header_file = get_template_directory() . '/parts/header.html';
        if (file_exists($header_file)) {
            $header_content = file_get_contents($header_file);
            // Рендерим блоки из файла
            $parsed_blocks = parse_blocks($header_content);
            if (!empty($parsed_blocks)) {
                $rendered = '';
                foreach ($parsed_blocks as $parsed_block) {
                    $rendered .= render_block($parsed_block);
                }
                return $rendered;
            }
        }
    }
    return $block_content;
}, 999, 2);

// Альтернативный подход: перехватываем через get_block_template
add_filter('get_block_template', function($template, $id, $template_type) {
    if ($template_type === 'wp_template_part' && is_checkout()) {
        if (($id === 'gustolocal//header' || $id === 'header') && isset($template->slug) && $template->slug === 'header') {
            $header_file = get_template_directory() . '/parts/header.html';
            if (file_exists($header_file)) {
                $template->content = file_get_contents($header_file);
            }
        }
    }
    return $template;
}, 999, 3);

/* ============ Убираем ссылки на товары в корзине, чекауте и заказах ============ */
// Убираем ссылки на товары в корзине
add_filter('woocommerce_cart_item_permalink', '__return_empty_string', 10, 3);

// Убираем ссылки на товары в заказах (чекаут, подтверждение заказа, письма)
add_filter('woocommerce_order_item_permalink', '__return_empty_string', 10, 3);

// Дополнительно: убираем ссылки из названия товара, если они там есть
add_filter('woocommerce_cart_item_name', 'gustolocal_remove_product_links_from_name', 10, 3);
function gustolocal_remove_product_links_from_name($name, $cart_item, $cart_item_key) {
    // Удаляем все ссылки <a> из названия товара
    $name = preg_replace('/<a[^>]*>(.*?)<\/a>/i', '$1', $name);
    return $name;
}

// Убираем ссылки из названия товара в заказах
add_filter('woocommerce_order_item_name', 'gustolocal_remove_product_links_from_order_name', 10, 2);
function gustolocal_remove_product_links_from_order_name($name, $item) {
    // Удаляем все ссылки <a> из названия товара
    $name = preg_replace('/<a[^>]*>(.*?)<\/a>/i', '$1', $name);
    return $name;
}
