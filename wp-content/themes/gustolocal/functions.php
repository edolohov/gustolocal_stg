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
    wp_enqueue_script( 'gustolocal-tooltip', $theme_dir . '/assets/js/tooltip.js', [], GUSTOLOCAL_VERSION, true );
    
    // Load gallery script only on rico page
    if ( is_page( 'rico' ) || ( is_page() && get_post_field( 'post_name' ) === 'rico' ) ) {
        wp_enqueue_script( 'gustolocal-rico-gallery', $theme_dir . '/assets/js/rico-gallery.js', [], GUSTOLOCAL_VERSION, true );
    }
} );

// Inline стили для формы Contact Form 7 - принудительное применение
add_action( 'wp_head', function () {
    ?>
    <style id="gustolocal-form-fix">
    /* Принудительные стили для формы Contact Form 7 */
    .gl-form-grid,
    .wpcf7-form .gl-form-grid {
        display: grid !important;
        gap: 0.5rem !important;
        margin-bottom: 0.75rem !important;
    }
    .gl-form-grid-single {
        grid-template-columns: 1fr !important;
    }
    .gl-form-group,
    .wpcf7-form .gl-form-group {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.2rem !important;
        margin-bottom: 0 !important;
    }
    .gl-form-grid-single .gl-form-group + .gl-form-group {
        margin-top: 0.3rem !important;
    }
    .gl-form-group p,
    .gl-form-actions p,
    .wpcf7-form p {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.2 !important;
    }
    .gl-form-group br,
    .wpcf7-form br {
        display: none !important;
        line-height: 0 !important;
        height: 0 !important;
    }
    .gl-form-group .wpcf7-form-control-wrap {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .gl-form-group label {
        margin-bottom: 0.2rem !important;
        display: block !important;
    }
    .gl-form-group .wpcf7-form-control,
    .gl-form-group input,
    .gl-form-group textarea {
        width: 100% !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    .gl-form-actions {
        display: flex !important;
        justify-content: center !important;
        text-align: center !important;
        margin-top: 0.75rem !important;
    }
    .gl-form-actions p {
        margin: 0 !important;
        padding: 0 !important;
    }
    .gl-form-actions .gl-button--primary,
    .gl-form-actions input[type="submit"],
    .gl-form-actions .wpcf7-submit {
        background: rgb(216, 228, 160) !important;
        color: #1a1a1a !important;
    }
    .gl-form-actions .gl-button--primary:hover,
    .gl-form-actions input[type="submit"]:hover,
    .gl-form-actions .wpcf7-submit:hover {
        background: rgb(23, 129, 94) !important;
        color: #fff !important;
    }
    </style>
    <?php
}, 999 );

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
        array(
            'slug'        => 'corporate-meals',
            'pattern'     => 'gustolocal/corporate-meals',
            'option_name' => 'gustolocal_seed_corporate_meals',
        ),
        array(
            'slug'        => 'catering',
            'pattern'     => 'gustolocal/catering',
            'option_name' => 'gustolocal_seed_catering',
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
/* ============ Настройки минимального заказа ============ */
// Добавляем страницу настроек в админке
add_action('admin_menu', 'gustolocal_add_minimum_order_settings_page');
function gustolocal_add_minimum_order_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Минимальный заказ',
        'Минимальный заказ',
        'manage_options',
        'gustolocal-minimum-order',
        'gustolocal_minimum_order_settings_page'
    );
}

// Страница настроек минимального заказа
function gustolocal_minimum_order_settings_page() {
    // Сохранение настроек
    if (isset($_POST['gustolocal_save_minimum_order']) && check_admin_referer('gustolocal_minimum_order_settings')) {
        $enabled = isset($_POST['minimum_order_enabled']) ? 1 : 0;
        $amount = floatval($_POST['minimum_order_amount']);
        $message = sanitize_text_field($_POST['minimum_order_message']);
        
        update_option('gustolocal_minimum_order_enabled', $enabled);
        update_option('gustolocal_minimum_order_amount', $amount);
        update_option('gustolocal_minimum_order_message', $message);
        
        echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
    }
    
    // Получаем текущие настройки
    $enabled = get_option('gustolocal_minimum_order_enabled', 0);
    $amount = get_option('gustolocal_minimum_order_amount', 60.00);
    $message = get_option('gustolocal_minimum_order_message', 'Минимальная сумма заказа: {amount} €. Добавьте товаров на {remaining} €.');
    
    ?>
    <div class="wrap">
        <h1>Настройки минимального заказа</h1>
        <form method="post" action="">
            <?php wp_nonce_field('gustolocal_minimum_order_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="minimum_order_enabled">Включить минимальный заказ</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="minimum_order_enabled" value="1" <?php checked($enabled, 1); ?>>
                            Включить проверку минимальной суммы заказа
                        </label>
                        <p class="description">Когда включено, пользователи не смогут оформить заказ, если сумма меньше указанной.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="minimum_order_amount">Минимальная сумма заказа (€)</label>
                    </th>
                    <td>
                        <input type="number" 
                               name="minimum_order_amount" 
                               id="minimum_order_amount" 
                               value="<?php echo esc_attr($amount); ?>" 
                               step="0.01" 
                               min="0" 
                               class="regular-text">
                        <p class="description">Минимальная сумма заказа в евро (без учета доставки).</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="minimum_order_message">Сообщение об ошибке</label>
                    </th>
                    <td>
                        <textarea name="minimum_order_message" 
                                  id="minimum_order_message" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($message); ?></textarea>
                        <p class="description">
                            Сообщение, которое увидит пользователь, если сумма заказа меньше минимальной.<br>
                            Используйте <code>{amount}</code> для суммы минимального заказа и <code>{remaining}</code> для недостающей суммы.
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Сохранить настройки', 'primary', 'gustolocal_save_minimum_order'); ?>
        </form>
        
        <hr>
        
        <h2>Текущие настройки</h2>
        <table class="form-table">
            <tr>
                <th>Статус:</th>
                <td><strong><?php echo $enabled ? 'Включено' : 'Выключено'; ?></strong></td>
            </tr>
            <tr>
                <th>Минимальная сумма:</th>
                <td><strong><?php echo number_format($amount, 2, ',', ' '); ?> €</strong></td>
            </tr>
            <tr>
                <th>Сообщение:</th>
                <td><?php echo esc_html($message); ?></td>
            </tr>
        </table>
    </div>
    <?php
}

// Валидация минимального заказа при оформлении
add_action('woocommerce_checkout_process', 'gustolocal_validate_minimum_order');
function gustolocal_validate_minimum_order() {
    // Проверяем, включена ли функция
    $enabled = get_option('gustolocal_minimum_order_enabled', 0);
    if (!$enabled) {
        return; // Функция выключена, не проверяем
    }
    
    // Проверяем, что WooCommerce активен
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    
    $minimum_amount = floatval(get_option('gustolocal_minimum_order_amount', 60.00));
    $message_template = get_option('gustolocal_minimum_order_message', 'Минимальная сумма заказа: {amount} €. Добавьте товаров на {remaining} €.');
    
    // Получаем сумму корзины БЕЗ доставки (subtotal)
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal < $minimum_amount) {
        $remaining = $minimum_amount - $cart_subtotal;
        
        $message = str_replace(
            array('{amount}', '{remaining}'),
            array(number_format($minimum_amount, 2, ',', ' ') . ' €', number_format($remaining, 2, ',', ' ') . ' €'),
            $message_template
        );
        
        wc_add_notice($message, 'error');
    }
}

// Показываем уведомление в корзине
add_action('woocommerce_before_cart', 'gustolocal_show_minimum_order_notice_cart');
function gustolocal_show_minimum_order_notice_cart() {
    // Проверяем, включена ли функция
    $enabled = get_option('gustolocal_minimum_order_enabled', 0);
    if (!$enabled) {
        return; // Функция выключена, не показываем
    }
    
    // Проверяем, что WooCommerce активен
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    
    $minimum_amount = floatval(get_option('gustolocal_minimum_order_amount', 60.00));
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal < $minimum_amount) {
        $remaining = $minimum_amount - $cart_subtotal;
        
        $message = sprintf(
            'Минимальная сумма заказа: <strong>%s €</strong>. Добавьте товаров на <strong>%s €</strong>.',
            number_format($minimum_amount, 2, ',', ' '),
            number_format($remaining, 2, ',', ' ')
        );
        
        echo '<div class="gl-minimum-order-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px; border-radius: 4px;">';
        echo '<strong>⚠️ Минимальный заказ:</strong> ' . $message;
        echo '</div>';
    }
}

// Показываем уведомление на странице чекаута
add_action('woocommerce_before_checkout_form', 'gustolocal_show_minimum_order_notice_checkout');
function gustolocal_show_minimum_order_notice_checkout() {
    // Проверяем, включена ли функция
    $enabled = get_option('gustolocal_minimum_order_enabled', 0);
    if (!$enabled) {
        return; // Функция выключена, не показываем
    }
    
    // Проверяем, что WooCommerce активен
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    
    $minimum_amount = floatval(get_option('gustolocal_minimum_order_amount', 60.00));
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal < $minimum_amount) {
        $remaining = $minimum_amount - $cart_subtotal;
        
        $message = sprintf(
            'Минимальная сумма заказа: <strong>%s €</strong>. Добавьте товаров на <strong>%s €</strong>.',
            number_format($minimum_amount, 2, ',', ' '),
            number_format($remaining, 2, ',', ' ')
        );
        
        echo '<div class="gl-minimum-order-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px; border-radius: 4px;">';
        echo '<strong>⚠️ Минимальный заказ:</strong> ' . $message;
        echo '</div>';
    }
}

/* ========================================
   УПРАВЛЕНИЕ КАТЕГОРИЯМИ МЕНЮ
   ======================================== */

// Регистрация страницы настроек категорий
add_action('admin_menu', 'gustolocal_add_category_settings_page');
function gustolocal_add_category_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Категории меню',
        'Категории меню',
        'manage_options',
        'gustolocal-categories',
        'gustolocal_category_settings_page'
    );
}

// Функция для получения всех существующих категорий из таксономии
function gustolocal_get_all_categories() {
    $terms = get_terms(array(
        'taxonomy' => 'wmb_section',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($terms)) {
        return array();
    }
    
    $categories = array();
    foreach ($terms as $term) {
        $categories[] = $term->name;
    }
    
    return $categories;
}

// Функция для получения настроек категорий
function gustolocal_get_category_settings() {
    $settings = get_option('gustolocal_category_settings', array());
    
    // Если настройки пустые, инициализируем из существующих категорий
    if (empty($settings)) {
        $default_order = array(
            'Завтраки и сладкое',
            'Авторские сэндвичи и перекусы',
            'Паста ручной работы',
            'Основные блюда',
            'Гарниры и зелень',
            'Супы и крем-супы',
            'Для запаса / в морозильник',
        );
        
        $all_categories = gustolocal_get_all_categories();
        $order = 1;
        
        foreach ($default_order as $cat_name) {
            if (in_array($cat_name, $all_categories)) {
                $settings[$cat_name] = array(
                    'original' => $cat_name,
                    'display' => $cat_name,
                    'order' => $order++,
                    'aliases' => array(),
                );
            }
        }
        
        // Добавляем остальные категории, которых нет в дефолтном списке
        foreach ($all_categories as $cat_name) {
            if (!isset($settings[$cat_name])) {
                $settings[$cat_name] = array(
                    'original' => $cat_name,
                    'display' => $cat_name,
                    'order' => $order++,
                    'aliases' => array(),
                );
            }
        }
        
        update_option('gustolocal_category_settings', $settings);
    }
    
    return $settings;
}

// Функция для получения категории по синониму
function gustolocal_map_category_by_alias($category_name) {
    $settings = gustolocal_get_category_settings();
    $category_name_lower = mb_strtolower(trim($category_name));
    
    // Сначала проверяем точное совпадение
    foreach ($settings as $original => $config) {
        if (mb_strtolower($original) === $category_name_lower || 
            mb_strtolower($config['display']) === $category_name_lower) {
            return $original;
        }
    }
    
    // Затем проверяем синонимы
    foreach ($settings as $original => $config) {
        foreach ($config['aliases'] as $alias) {
            if (mb_strtolower(trim($alias)) === $category_name_lower) {
                return $original;
            }
        }
    }
    
    // Проверяем частичное совпадение (для обратной совместимости)
    foreach ($settings as $original => $config) {
        $original_lower = mb_strtolower($original);
        if (strpos($original_lower, $category_name_lower) !== false || 
            strpos($category_name_lower, $original_lower) !== false) {
            return $original;
        }
    }
    
    return $category_name; // Возвращаем как есть, если не найдено
}

// Функция для получения отображаемого названия категории
function gustolocal_get_category_display_name($category_name) {
    $settings = gustolocal_get_category_settings();
    
    if (isset($settings[$category_name]) && !empty($settings[$category_name]['display'])) {
        return $settings[$category_name]['display'];
    }
    
    return $category_name;
}

// Функция для получения отсортированного списка категорий
function gustolocal_get_ordered_categories() {
    $settings = gustolocal_get_category_settings();
    
    // Сортируем по порядку
    uasort($settings, function($a, $b) {
        $order_a = isset($a['order']) ? (int)$a['order'] : 999;
        $order_b = isset($b['order']) ? (int)$b['order'] : 999;
        
        if ($order_a === $order_b) {
            return strcmp($a['original'], $b['original']);
        }
        
        return $order_a - $order_b;
    });
    
    return $settings;
}

// Страница настроек категорий
function gustolocal_category_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Обработка сохранения
    if (isset($_POST['gustolocal_save_categories']) && check_admin_referer('gustolocal_categories_nonce')) {
        $settings = array();
        
        if (isset($_POST['categories']) && is_array($_POST['categories'])) {
            foreach ($_POST['categories'] as $original => $data) {
                $settings[$original] = array(
                    'original' => sanitize_text_field($original),
                    'display' => sanitize_text_field($data['display'] ?? $original),
                    'order' => isset($data['order']) ? (int)$data['order'] : 999,
                    'aliases' => !empty($data['aliases']) 
                        ? array_filter(array_map('trim', explode(',', sanitize_text_field($data['aliases']))))
                        : array(),
                );
            }
        }
        
        // Добавляем новые категории из формы
        if (isset($_POST['new_categories']) && is_array($_POST['new_categories'])) {
            foreach ($_POST['new_categories'] as $new_cat) {
                $new_cat = trim($new_cat);
                if (!empty($new_cat) && !isset($settings[$new_cat])) {
                    $max_order = 0;
                    foreach ($settings as $cat) {
                        if (isset($cat['order']) && $cat['order'] > $max_order) {
                            $max_order = $cat['order'];
                        }
                    }
                    
                    $settings[$new_cat] = array(
                        'original' => $new_cat,
                        'display' => $new_cat,
                        'order' => $max_order + 1,
                        'aliases' => array(),
                    );
                }
            }
        }
        
        update_option('gustolocal_category_settings', $settings);
        echo '<div class="notice notice-success"><p>Настройки категорий сохранены!</p></div>';
    }
    
    // Обработка удаления
    if (isset($_POST['gustolocal_delete_category']) && check_admin_referer('gustolocal_categories_nonce')) {
        $category_to_delete = sanitize_text_field($_POST['category_to_delete'] ?? '');
        if (!empty($category_to_delete)) {
            $settings = gustolocal_get_category_settings();
            unset($settings[$category_to_delete]);
            update_option('gustolocal_category_settings', $settings);
            echo '<div class="notice notice-success"><p>Категория удалена из настроек!</p></div>';
        }
    }
    
    $settings = gustolocal_get_category_settings();
    $all_categories = gustolocal_get_all_categories();
    $ordered_categories = gustolocal_get_ordered_categories();
    
    ?>
    <div class="wrap">
        <h1>Управление категориями меню</h1>
        <p>Здесь вы можете настроить порядок отображения категорий, переименовать их для отображения на сайте и добавить синонимы для автоматического маппинга при импорте CSV.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('gustolocal_categories_nonce'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Порядок</th>
                        <th>Оригинальное название</th>
                        <th>Отображаемое название</th>
                        <th>Синонимы (через запятую)</th>
                        <th style="width: 100px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordered_categories as $original => $config): ?>
                        <tr>
                            <td>
                                <input type="number" 
                                       name="categories[<?php echo esc_attr($original); ?>][order]" 
                                       value="<?php echo esc_attr($config['order'] ?? 999); ?>" 
                                       min="1" 
                                       style="width: 60px;">
                            </td>
                            <td>
                                <strong><?php echo esc_html($original); ?></strong>
                                <?php if (!in_array($original, $all_categories)): ?>
                                    <span style="color: #d63638;">(не найдена в таксономии)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" 
                                       name="categories[<?php echo esc_attr($original); ?>][display]" 
                                       value="<?php echo esc_attr($config['display'] ?? $original); ?>" 
                                       class="regular-text">
                            </td>
                            <td>
                                <input type="text" 
                                       name="categories[<?php echo esc_attr($original); ?>][aliases]" 
                                       value="<?php echo esc_attr(implode(', ', $config['aliases'] ?? array())); ?>" 
                                       class="large-text" 
                                       placeholder="Авторская паста, Паста, Макароны">
                                <p class="description">Синонимы используются для автоматического маппинга при импорте CSV</p>
                            </td>
                            <td>
                                <button type="submit" 
                                        name="gustolocal_delete_category" 
                                        value="1" 
                                        onclick="return confirm('Удалить категорию из настроек?');"
                                        class="button button-small">
                                    Удалить
                                </button>
                                <input type="hidden" name="category_to_delete" value="<?php echo esc_attr($original); ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>Добавить новую категорию</h2>
            <p>Добавьте категорию, которая будет использоваться при импорте CSV (например, если в CSV указана категория, которой еще нет в настройках):</p>
            <div id="new-categories-container">
                <p>
                    <input type="text" 
                           name="new_categories[]" 
                           class="regular-text" 
                           placeholder="Название категории">
                    <button type="button" class="button" onclick="addNewCategoryField()">+ Добавить еще</button>
                </p>
            </div>
            
            <p class="submit">
                <input type="submit" 
                       name="gustolocal_save_categories" 
                       class="button button-primary" 
                       value="Сохранить настройки">
            </p>
        </form>
        
        <hr>
        
        <h2>Справка</h2>
        <ul>
            <li><strong>Порядок</strong> — определяет последовательность отображения категорий на странице меню (меньше = выше)</li>
            <li><strong>Отображаемое название</strong> — название, которое будет показано пользователям на сайте (может отличаться от оригинального)</li>
            <li><strong>Синонимы</strong> — варианты названий категории, которые будут автоматически маппиться к основной категории при импорте CSV</li>
            <li>Если категория не найдена в таксономии, она будет создана автоматически при импорте CSV</li>
        </ul>
        
        <h2>Все существующие категории в таксономии</h2>
        <ul>
            <?php foreach ($all_categories as $cat): ?>
                <li><?php echo esc_html($cat); ?></li>
            <?php endforeach; ?>
            <?php if (empty($all_categories)): ?>
                <li><em>Категории не найдены</em></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <script>
    function addNewCategoryField() {
        var container = document.getElementById('new-categories-container');
        var p = document.createElement('p');
        p.innerHTML = '<input type="text" name="new_categories[]" class="regular-text" placeholder="Название категории"> ' +
                      '<button type="button" class="button" onclick="this.parentElement.remove()">Удалить</button>';
        container.appendChild(p);
    }
    </script>
    <?php
}

/* ========================================
   РАЗБОР ЗАКАЗОВ ПО ПОЗИЦИЯМ
   ======================================== */

// Регистрация страницы разбора заказов
add_action('admin_menu', 'gustolocal_add_order_breakdown_page');
function gustolocal_add_order_breakdown_page() {
    add_submenu_page(
        'woocommerce',
        'Разбор заказов',
        'Разбор заказов',
        'manage_options',
        'gustolocal-order-breakdown',
        'gustolocal_order_breakdown_page'
    );
}

// Функция для получения категории блюда по названию
function gustolocal_get_dish_category($dish_name) {
    // Ищем блюдо в таксономии wmb_section
    $dishes = get_posts(array(
        'post_type' => 'wmb_dish',
        'title' => $dish_name,
        'posts_per_page' => 1,
        'post_status' => 'any',
    ));
    
    if (!empty($dishes)) {
        $dish_id = $dishes[0]->ID;
        $terms = wp_get_post_terms($dish_id, 'wmb_section', array('fields' => 'names'));
        if (!empty($terms) && !is_wp_error($terms)) {
            $category = $terms[0];
            // Используем отображаемое название категории, если доступно
            if (function_exists('gustolocal_get_category_display_name')) {
                return gustolocal_get_category_display_name($category);
            }
            return $category;
        }
    }
    
    return 'Прочее';
}

// Функция для получения порядка категории
function gustolocal_get_category_order($category_name) {
    if (function_exists('gustolocal_get_ordered_categories')) {
        $ordered = gustolocal_get_ordered_categories();
        foreach ($ordered as $original => $config) {
            $display = !empty($config['display']) ? $config['display'] : $original;
            if (mb_strtolower($display) === mb_strtolower($category_name) || 
                mb_strtolower($original) === mb_strtolower($category_name)) {
                return isset($config['order']) ? (int)$config['order'] : 999;
            }
        }
    }
    return 999;
}

// Функция для извлечения блюд из заказа
function gustolocal_extract_dishes_from_order($order) {
    $dishes = array();
    
    foreach ($order->get_items() as $item_id => $item) {
        // Проверяем, есть ли payload от meal-builder
        $payload_meta = $item->get_meta('_wmb_payload', true);
        if (!$payload_meta) {
            $payload_meta = $item->get_meta('Meal plan payload', true);
        }
        
        if ($payload_meta) {
            $payload = json_decode($payload_meta, true);
            if ($payload && isset($payload['items_list']) && is_array($payload['items_list'])) {
                foreach ($payload['items_list'] as $dish_item) {
                    $name = isset($dish_item['name']) ? trim($dish_item['name']) : '';
                    $qty = isset($dish_item['qty']) ? intval($dish_item['qty']) : 0;
                    $unit = isset($dish_item['unit']) ? trim($dish_item['unit']) : '';
                    $price = isset($dish_item['price']) ? floatval($dish_item['price']) : 0;
                    
                    if (empty($name) || $qty <= 0) continue;
                    
                    // Формируем ключ: название + единица
                    $key = $name . ($unit ? ' (' . $unit . ')' : '');
                    
                    if (!isset($dishes[$key])) {
                        $category = gustolocal_get_dish_category($name);
                        $dishes[$key] = array(
                            'name' => $name,
                            'unit' => $unit,
                            'category' => $category,
                            'category_order' => gustolocal_get_category_order($category),
                            'total_qty' => 0,
                            'total_price' => 0,
                        );
                    }
                    
                    $dishes[$key]['total_qty'] += $qty;
                    $dishes[$key]['total_price'] += $price * $qty;
                }
            }
        } else {
            // Обычный товар (не из meal-builder)
            $product_name = $item->get_name();
            $qty = $item->get_quantity();
            $price = $item->get_total();
            
            $key = $product_name;
            if (!isset($dishes[$key])) {
                $dishes[$key] = array(
                    'name' => $product_name,
                    'unit' => '',
                    'category' => 'Прочее',
                    'category_order' => 999,
                    'total_qty' => 0,
                    'total_price' => 0,
                );
            }
            
            $dishes[$key]['total_qty'] += $qty;
            $dishes[$key]['total_price'] += $price;
        }
    }
    
    return $dishes;
}

// Страница разбора заказов
function gustolocal_order_breakdown_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Проверяем, что WooCommerce активен
    if (!function_exists('wc_get_orders')) {
        echo '<div class="wrap"><h1>Разбор заказов</h1><div class="error"><p>WooCommerce не активирован!</p></div></div>';
        return;
    }
    
    $selected_orders = isset($_POST['order_ids']) && is_array($_POST['order_ids']) 
        ? array_map('intval', $_POST['order_ids']) 
        : array();
    
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : date('Y-m-d', strtotime('-7 days'));
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : date('Y-m-d');
    $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    // Получаем заказы для выбора
    $orders_query = array(
        'limit' => 500,
        'orderby' => 'date',
        'order' => 'DESC',
        'date_created' => $date_from . '...' . $date_to,
    );
    
    if ($status_filter) {
        $orders_query['status'] = $status_filter;
    }
    
    $all_orders = wc_get_orders($orders_query);
    
    // Если выбраны заказы, формируем сводку
    $breakdown_data = null;
    if (!empty($selected_orders)) {
        $breakdown_data = gustolocal_generate_breakdown($selected_orders);
    }
    
    ?>
    <div class="wrap">
        <h1>Разбор заказов по позициям</h1>
        
        <form method="post" action="" id="breakdown-form">
            <div class="postbox" style="margin-top: 20px; padding: 20px;">
                <h2>Фильтры</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="date_from">Дата от:</label></th>
                        <td><input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="date_to">Дата до:</label></th>
                        <td><input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="status">Статус:</label></th>
                        <td>
                            <select id="status" name="status" class="regular-text">
                                <option value="">Все статусы</option>
                                <?php
                                $statuses = wc_get_order_statuses();
                                foreach ($statuses as $status_key => $status_label) {
                                    $selected = ($status_filter === $status_key) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="filter_orders" class="button button-primary" value="Применить фильтры">
                </p>
            </div>
            
            <div class="postbox" style="margin-top: 20px; padding: 20px;">
                <h2>Выберите заказы</h2>
                <p>
                    <button type="button" class="button" onclick="selectAllOrders()">Выбрать все</button>
                    <button type="button" class="button" onclick="deselectAllOrders()">Снять выбор</button>
                </p>
                
                <?php if (empty($all_orders)): ?>
                    <p>Заказы не найдены.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="select-all-checkbox" onclick="toggleAllOrders(this)"></th>
                                <th>№ заказа</th>
                                <th>Дата</th>
                                <th>Клиент</th>
                                <th>Статус</th>
                                <th>Способ получения</th>
                                <th>Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_orders as $order): 
                                $is_selected = in_array($order->get_id(), $selected_orders);
                                $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                                if (trim($customer_name) === '') {
                                    $customer_name = $order->get_billing_company() ?: 'Гость';
                                }
                                $shipping_method = $order->get_shipping_method();
                                $is_pickup = (stripos($shipping_method, 'самовывоз') !== false || stripos($shipping_method, 'pickup') !== false);
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="order_ids[]" 
                                               value="<?php echo esc_attr($order->get_id()); ?>"
                                               <?php echo $is_selected ? 'checked' : ''; ?>>
                                    </td>
                                    <td><strong>#<?php echo esc_html($order->get_id()); ?></strong></td>
                                    <td><?php echo esc_html($order->get_date_created()->date_i18n('d.m.Y H:i')); ?></td>
                                    <td><?php echo esc_html($customer_name); ?></td>
                                    <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                    <td><?php echo $is_pickup ? '<strong>Самовывоз</strong>' : 'Доставка'; ?></td>
                                    <td><?php echo $order->get_formatted_order_total(); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit" style="margin-top: 20px;">
                        <input type="submit" name="generate_breakdown" class="button button-primary button-large" value="Сформировать сводку">
                    </p>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($breakdown_data): ?>
            <div class="postbox" style="margin-top: 20px; padding: 20px;">
                <h2>Сводная таблица</h2>
                <?php gustolocal_display_breakdown_table($breakdown_data); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function toggleAllOrders(checkbox) {
        var checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = checkbox.checked;
        });
    }
    
    function selectAllOrders() {
        var checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
        document.getElementById('select-all-checkbox').checked = true;
    }
    
    function deselectAllOrders() {
        var checkboxes = document.querySelectorAll('input[name="order_ids[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = false;
        });
        document.getElementById('select-all-checkbox').checked = false;
    }
    </script>
    <?php
}

// Функция для генерации сводки
function gustolocal_generate_breakdown($order_ids) {
    $dishes_by_category = array(); // [category][dish_key] = dish_data
    $customers = array(); // [order_id] = customer_data
    $total_sum = 0;
    $total_portions = 0;
    
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;
        
        // Информация о клиенте
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($customer_name === '') {
            $customer_name = $order->get_billing_company() ?: 'Гость';
        }
        
        $shipping_method = $order->get_shipping_method();
        $is_pickup = (stripos($shipping_method, 'самовывоз') !== false || stripos($shipping_method, 'pickup') !== false);
        
        $customers[$order_id] = array(
            'name' => $customer_name,
            'order_id' => $order_id,
            'is_pickup' => $is_pickup,
            'total' => $order->get_total(),
        );
        
        $total_sum += $order->get_total();
        
        // Извлекаем блюда из заказа
        $order_dishes = gustolocal_extract_dishes_from_order($order);
        
        foreach ($order_dishes as $dish_key => $dish_data) {
            $category = $dish_data['category'];
            
            if (!isset($dishes_by_category[$category])) {
                $dishes_by_category[$category] = array();
            }
            
            if (!isset($dishes_by_category[$category][$dish_key])) {
                $dishes_by_category[$category][$dish_key] = array(
                    'name' => $dish_data['name'],
                    'unit' => $dish_data['unit'],
                    'category' => $category,
                    'category_order' => $dish_data['category_order'],
                    'quantities' => array(), // [order_id] => qty
                );
            }
            
            $dishes_by_category[$category][$dish_key]['quantities'][$order_id] = $dish_data['total_qty'];
            $total_portions += $dish_data['total_qty'];
        }
    }
    
    // Сортируем категории по порядку
    uasort($dishes_by_category, function($a, $b) {
        $order_a = !empty($a) ? reset($a)['category_order'] : 999;
        $order_b = !empty($b) ? reset($b)['category_order'] : 999;
        return $order_a - $order_b;
    });
    
    return array(
        'dishes_by_category' => $dishes_by_category,
        'customers' => $customers,
        'total_sum' => $total_sum,
        'total_portions' => $total_portions,
        'order_ids' => $order_ids,
    );
}

// Функция для умножения всех чисел в строке на множитель
function gustolocal_multiply_numbers_in_string($unit, $multiplier) {
    if (empty($unit) || $multiplier <= 0) {
        return $unit;
    }
    
    // Заменяем все числа на умноженные значения
    $result = preg_replace_callback(
        '/\d+(?:[.,]\d+)?/',
        function($matches) use ($multiplier) {
            $number = floatval(str_replace(',', '.', $matches[0]));
            $multiplied = $number * $multiplier;
            // Если было целое число, возвращаем целое, иначе с десятичными
            if (strpos($matches[0], '.') === false && strpos($matches[0], ',') === false) {
                return (string)intval($multiplied);
            }
            return number_format($multiplied, 2, '.', '');
        },
        $unit
    );
    
    return $result;
}

// Функция для вычисления итогового веса блюда
function gustolocal_calculate_dish_weight($dish_data, $quantities) {
    if (empty($dish_data['unit'])) {
        return array('total' => null, 'display' => '');
    }
    
    $total_qty = array_sum($quantities);
    
    if ($total_qty <= 0) {
        return array('total' => null, 'display' => '');
    }
    
    // Проверяем, является ли формат сложным (содержит "/" или скобки с числами)
    $has_slashes = (strpos($dish_data['unit'], '/') !== false);
    $has_brackets_with_numbers = preg_match('/\([^)]*\d+[^)]*\)/', $dish_data['unit']);
    
    // Если это сложный формат - умножаем все числа
    if ($has_slashes || $has_brackets_with_numbers) {
        // Сложные случаи: умножаем все числа в строке
        // "250/ 400/ 60 (2 пор)" -> "750/ 1200/ 180 (6 пор)"
        // "200 г (8 шт)" -> "400 г (16 шт)"
        $multiplied_unit = gustolocal_multiply_numbers_in_string($dish_data['unit'], $total_qty);
        
        // Для расчета общего веса в сложных случаях берем первое число
        preg_match('/^(\d+(?:[.,]\d+)?)/', $dish_data['unit'], $first_num_match);
        $total_weight = null;
        if (!empty($first_num_match)) {
            $first_value = floatval(str_replace(',', '.', $first_num_match[1]));
            $total_weight = $first_value * $total_qty;
        }
        
        return array(
            'total' => $total_weight,
            'display' => $multiplied_unit
        );
    }
    
    // Простые случаи: "200 г", "1200 мл" - просто умножаем число
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(г|мл|кг|л|шт|пор)/ui', $dish_data['unit'], $matches)) {
        $value = floatval(str_replace(',', '.', $matches[1]));
        $unit_type = $matches[2];
        $total_weight = $value * $total_qty;
        return array(
            'total' => $total_weight,
            'display' => number_format($total_weight, 0, ',', ' ') . ' ' . $unit_type
        );
    }
    
    // Если ничего не подошло
    return array('total' => null, 'display' => '');
}

// Функция для отображения сводной таблицы
function gustolocal_display_breakdown_table($data) {
    $dishes_by_category = $data['dishes_by_category'];
    $customers = $data['customers'];
    $total_sum = $data['total_sum'];
    $total_portions = $data['total_portions'];
    $order_ids = $data['order_ids'];
    
    // Собираем все уникальные блюда
    $all_dishes = array();
    foreach ($dishes_by_category as $category => $dishes) {
        foreach ($dishes as $dish_key => $dish_data) {
            $all_dishes[$dish_key] = $dish_data;
        }
    }
    
    // Пересчитываем суммы из заказов для проверки
    $recalculated_sum = 0;
    $recalculated_portions = 0;
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $recalculated_sum += $order->get_total();
            $order_dishes = gustolocal_extract_dishes_from_order($order);
            foreach ($order_dishes as $dish_data) {
                $recalculated_portions += $dish_data['total_qty'];
            }
        }
    }
    
    ?>
    <style>
    .breakdown-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 13px;
    }
    .breakdown-table th,
    .breakdown-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }
    .breakdown-table th {
        background-color: #f5f5f5;
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .breakdown-table .category-header {
        background-color: #e8f4f8;
        font-weight: bold;
        font-size: 14px;
    }
    .breakdown-table .dish-row {
        background-color: #fff;
    }
    .breakdown-table .total-row {
        background-color: #fff3cd;
        font-weight: bold;
    }
    .breakdown-table .customer-col {
        min-width: 150px;
        text-align: center;
    }
    .breakdown-table .dish-col {
        min-width: 200px;
    }
    .breakdown-table .qty-cell {
        text-align: center;
        font-weight: bold;
    }
    .breakdown-table .pickup-badge {
        display: inline-block;
        background-color: #d1ecf1;
        color: #0c5460;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        margin-left: 5px;
    }
    .breakdown-verification {
        margin-top: 20px;
        padding: 15px;
        background-color: #f0f0f0;
        border-left: 4px solid #0073aa;
    }
    .breakdown-verification.ok {
        border-left-color: #46b450;
    }
    .breakdown-verification.error {
        border-left-color: #dc3232;
    }
    </style>
    
    <div class="breakdown-verification <?php echo ($recalculated_sum == $total_sum && $recalculated_portions == $total_portions) ? 'ok' : 'error'; ?>">
        <h3>Проверка данных</h3>
        <p><strong>Сумма заказов:</strong> <?php echo wc_price($total_sum); ?> 
        <?php if ($recalculated_sum != $total_sum): ?>
            <span style="color: #dc3232;">(Ожидалось: <?php echo wc_price($recalculated_sum); ?>)</span>
        <?php else: ?>
            <span style="color: #46b450;">✓</span>
        <?php endif; ?>
        </p>
        <p><strong>Общее количество порций:</strong> <?php echo number_format($total_portions, 0, ',', ' '); ?> 
        <?php if ($recalculated_portions != $total_portions): ?>
            <span style="color: #dc3232;">(Ожидалось: <?php echo number_format($recalculated_portions, 0, ',', ' '); ?>)</span>
        <?php else: ?>
            <span style="color: #46b450;">✓</span>
        <?php endif; ?>
        </p>
    </div>
    
    <div style="overflow-x: auto; max-width: 100%;">
        <table class="breakdown-table">
            <thead>
                <tr>
                    <th class="dish-col">Блюдо</th>
                    <th class="total-row">ИТОГО</th>
                    <th class="total-row">Итоговый вес</th>
                    <?php foreach ($customers as $order_id => $customer): ?>
                        <th class="customer-col">
                            <?php echo esc_html($customer['name']); ?><br>
                            <small>#<?php echo esc_html($order_id); ?></small>
                            <?php if ($customer['is_pickup']): ?>
                                <span class="pickup-badge">Самовывоз</span>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #666;">Доставка</span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_category = '';
                foreach ($dishes_by_category as $category => $dishes): 
                    if ($current_category !== $category):
                        $current_category = $category;
                ?>
                    <tr class="category-header">
                        <td colspan="<?php echo count($customers) + 3; ?>">
                            <strong><?php echo esc_html($category); ?></strong>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <?php foreach ($dishes as $dish_key => $dish_data): 
                    $dish_total = array_sum($dish_data['quantities']);
                    $weight_info = gustolocal_calculate_dish_weight($dish_data, $dish_data['quantities']);
                ?>
                    <tr class="dish-row">
                        <td class="dish-col">
                            <?php echo esc_html($dish_data['name']); ?>
                            <?php if ($dish_data['unit']): ?>
                                <small style="color: #666;">(<?php echo esc_html($dish_data['unit']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="qty-cell total-row"><?php echo $dish_total; ?></td>
                        <td class="qty-cell total-row" style="text-align: left;">
                            <?php if ($weight_info['display']): ?>
                                <?php echo esc_html($weight_info['display']); ?>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($customers as $order_id => $customer): 
                            $qty = isset($dish_data['quantities'][$order_id]) ? $dish_data['quantities'][$order_id] : 0;
                        ?>
                            <td class="qty-cell"><?php echo $qty > 0 ? $qty : ''; ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td><strong>ИТОГО</strong></td>
                    <td class="qty-cell"><strong><?php echo number_format($total_portions, 0, ',', ' '); ?></strong></td>
                    <td></td>
                    <?php 
                    // Подсчитываем общее количество порций для каждого клиента
                    $customer_totals = array();
                    foreach ($dishes_by_category as $category => $dishes) {
                        foreach ($dishes as $dish_data) {
                            foreach ($dish_data['quantities'] as $order_id => $qty) {
                                if (!isset($customer_totals[$order_id])) {
                                    $customer_totals[$order_id] = 0;
                                }
                                $customer_totals[$order_id] += $qty;
                            }
                        }
                    }
                    foreach ($customers as $order_id => $customer): 
                        $customer_total = isset($customer_totals[$order_id]) ? $customer_totals[$order_id] : 0;
                    ?>
                        <td class="qty-cell"><strong><?php echo $customer_total > 0 ? number_format($customer_total, 0, ',', ' ') : ''; ?></strong></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/* ========================================
   СИСТЕМА ОБРАТНОЙ СВЯЗИ О БЛЮДАХ
   ======================================== */

// Создание таблицы для хранения отзывов при активации темы
add_action('after_switch_theme', 'gustolocal_create_feedback_table');
add_action('admin_init', 'gustolocal_create_feedback_table'); // Также при загрузке админки
function gustolocal_create_feedback_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        token varchar(64) NOT NULL,
        order_id bigint(20) UNSIGNED NOT NULL,
        customer_name varchar(255) DEFAULT '',
        dish_name varchar(255) NOT NULL,
        dish_unit varchar(100) DEFAULT '',
        rating int(1) NOT NULL COMMENT '1=😞, 2=😐, 3=😊, 4=😍',
        comment text DEFAULT '',
        general_comment text DEFAULT '',
        shared_instagram tinyint(1) DEFAULT 0,
        shared_google tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY token (token),
        KEY order_id (order_id),
        KEY dish_name (dish_name)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Создание таблиц для кастомных опросов
    $custom_requests_table = $wpdb->prefix . 'custom_feedback_requests';
    $custom_entries_table = $wpdb->prefix . 'custom_feedback_entries';
    
    $sql_requests = "CREATE TABLE IF NOT EXISTS $custom_requests_table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        token varchar(100) NOT NULL,
        client_name varchar(255) NOT NULL,
        client_contact varchar(255) DEFAULT '',
        dishes longtext NOT NULL,
        status varchar(20) DEFAULT 'pending',
        general_comment text DEFAULT '',
        shared_instagram tinyint(1) DEFAULT 0,
        shared_google tinyint(1) DEFAULT 0,
        submitted_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY token (token),
        KEY status (status)
    ) $charset_collate;";
    
    $sql_entries = "CREATE TABLE IF NOT EXISTS $custom_entries_table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id bigint(20) UNSIGNED NOT NULL,
        dish_name varchar(255) NOT NULL,
        dish_unit varchar(100) DEFAULT '',
        rating int(1) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY request_id (request_id),
        KEY dish_name (dish_name)
    ) $charset_collate;";
    
    dbDelta($sql_requests);
    dbDelta($sql_entries);
}

add_action('init', 'gustolocal_ensure_feedback_table_columns');
function gustolocal_ensure_feedback_table_columns() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    $required_columns = array(
        'shared_instagram' => "ALTER TABLE {$table_name} ADD COLUMN shared_instagram tinyint(1) DEFAULT 0",
        'shared_google'    => "ALTER TABLE {$table_name} ADD COLUMN shared_google tinyint(1) DEFAULT 0",
    );
    
    foreach ($required_columns as $column => $alter_sql) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            $column
        ));
        if (!$exists) {
            $wpdb->query($alter_sql);
        }
    }
    
    // Проверяем колонку dish_unit в таблице кастомных опросов
    $custom_entries_table = $wpdb->prefix . 'custom_feedback_entries';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SHOW COLUMNS FROM {$custom_entries_table} LIKE %s",
        'dish_unit'
    ));
    if (!$exists) {
        $wpdb->query("ALTER TABLE {$custom_entries_table} ADD COLUMN dish_unit varchar(100) DEFAULT '' AFTER dish_name");
    }
}

// Регистрация страницы управления опросами
add_action('admin_menu', 'gustolocal_add_feedback_management_page');
function gustolocal_add_feedback_management_page() {
    add_submenu_page(
        'woocommerce',
        'Обратная связь',
        'Обратная связь',
        'manage_options',
        'gustolocal-feedback',
        'gustolocal_feedback_management_page'
    );
    
    add_submenu_page(
        'woocommerce',
        'Результаты отзывов',
        'Результаты отзывов',
        'manage_options',
        'gustolocal-feedback-results',
        'gustolocal_feedback_results_page'
    );
    
    add_submenu_page(
        'woocommerce',
        'Кастомные опросы',
        'Кастомные опросы',
        'manage_options',
        'gustolocal-custom-feedback',
        'gustolocal_custom_feedback_management_page'
    );
    
    add_submenu_page(
        'woocommerce',
        'Результаты кастомных опросов',
        'Результаты кастомных опросов',
        'manage_options',
        'gustolocal-custom-feedback-results',
        'gustolocal_custom_feedback_results_page'
    );
}

// Функция для определения клиентов для опроса
function gustolocal_get_customers_for_feedback($date_from = null, $date_to = null, $status_filter = '') {
    if (!function_exists('wc_get_orders')) {
        return array();
    }
    
    $orders_query = array(
        'limit' => 500,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    if ($date_from && !$date_to) {
        $date_to = $date_from;
    } elseif ($date_to && !$date_from) {
        $date_from = $date_to;
    }
    
    if ($date_from && $date_to) {
        $orders_query['date_created'] = $date_from . '...' . $date_to;
    }
    
    if ($status_filter) {
        $orders_query['status'] = $status_filter;
    } else {
        $orders_query['status'] = array('processing', 'completed', 'on-hold');
    }
    
    $orders = wc_get_orders($orders_query);
    
    $customers_data = array();
    
    foreach ($orders as $order) {
        $order_id = $order->get_id();
        
        // Проверяем, есть ли уже токен для этого заказа
        $token = $order->get_meta('_feedback_token', true);
        
        if (!$token) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dish_feedback';
            $existing_token = $wpdb->get_var($wpdb->prepare(
                "SELECT token FROM $table_name WHERE order_id = %d LIMIT 1",
                $order_id
            ));
            
            if ($existing_token) {
                $token = $existing_token;
            } else {
                // Генерируем новый токен
                $token = wp_generate_password(32, false);
            }
            
            // Сохраняем токен в мета заказа
            $order->update_meta_data('_feedback_token', $token);
            $order->save();
        }
        
        // Извлекаем блюда из заказа
        $dishes = gustolocal_extract_dishes_from_order($order);
        
        if (empty($dishes)) {
            continue;
        }
        
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($customer_name === '') {
            $customer_name = $order->get_billing_company() ?: 'Гость';
        }
        
        $phone = $order->get_billing_phone();
        $whatsapp_link = $phone ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone) : '';
        
        $customers_data[] = array(
            'order_id' => $order_id,
            'customer_name' => $customer_name,
            'phone' => $phone,
            'whatsapp_link' => $whatsapp_link,
            'token' => $token,
            'dishes_count' => count($dishes),
            'order_date' => $order->get_date_created()->date_i18n('d.m.Y H:i'),
        );
    }
    
    return $customers_data;
}

// Страница управления опросами
function gustolocal_feedback_management_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Получаем параметры фильтрации
    $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
    $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'wc-on-hold';
    
    $customers = gustolocal_get_customers_for_feedback(
        $date_from ? $date_from . ' 00:00:00' : null,
        $date_to ? $date_to . ' 23:59:59' : null,
        $status_filter
    );
    $site_url = home_url();
    
    ?>
    <div class="wrap">
        <h1>Обратная связь о блюдах</h1>
        <p>Выберите заказы и отправьте клиентам ссылку на опросник через WhatsApp или Telegram.</p>
        
        <form method="post" action="" style="margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 5px;">
            <table class="form-table">
                <tr>
                    <th><label for="date_from">Дата от:</label></th>
                    <td><input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="date_to">Дата до:</label></th>
                    <td><input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="status">Статус:</label></th>
                    <td>
                        <select id="status" name="status" class="regular-text">
                            <option value="">Все статусы</option>
                            <?php
                            $statuses = wc_get_order_statuses();
                            foreach ($statuses as $status_key => $status_label) {
                                $selected = ($status_filter === $status_key) ? 'selected' : '';
                                echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="Применить фильтры">
                <button type="button" class="button" onclick="document.getElementById('date_from').value=''; document.getElementById('date_to').value=''; document.getElementById('status').value=''; this.form.submit();">
                    Показать все заказы
                </button>
            </p>
        </form>
        
        <?php if (empty($customers)): ?>
            <div class="notice notice-info">
                <p>Нет заказов для выбранного периода.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">№ заказа</th>
                        <th>Клиент</th>
                        <th>Телефон</th>
                        <th>Дата заказа</th>
                        <th>Блюд</th>
                        <th style="width: 300px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): 
                        $feedback_url = $site_url . '/feedback/' . $customer['token'];
                    ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($customer['order_id']); ?></strong></td>
                            <td><?php echo esc_html($customer['customer_name']); ?></td>
                            <td><?php echo esc_html($customer['phone']); ?></td>
                            <td><?php echo esc_html($customer['order_date']); ?></td>
                            <td><?php echo esc_html($customer['dishes_count']); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <input type="text" 
                                           id="feedback-link-<?php echo esc_attr($customer['order_id']); ?>" 
                                           value="<?php echo esc_attr($feedback_url); ?>" 
                                           readonly 
                                           style="flex: 1; min-width: 200px; font-size: 11px;">
                                    <button type="button" 
                                            class="button button-small copy-link-btn" 
                                            data-target="feedback-link-<?php echo esc_attr($customer['order_id']); ?>">
                                        Копировать
                                    </button>
                                    <?php if ($customer['whatsapp_link']): ?>
                                        <a href="<?php echo esc_url($customer['whatsapp_link']); ?>" 
                                           target="_blank" 
                                           class="button button-small">
                                            WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var input = document.getElementById(targetId);
                input.select();
                input.setSelectionRange(0, 99999); // Для мобильных
                document.execCommand('copy');
                
                var originalText = this.textContent;
                this.textContent = 'Скопировано!';
                this.classList.add('button-primary');
                
                setTimeout(function() {
                    this.textContent = originalText;
                    this.classList.remove('button-primary');
                }.bind(this), 2000);
            });
        });
    });
    </script>
    <?php
}

// Страница просмотра результатов отзывов
function gustolocal_feedback_results_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    // Получаем статистику по блюдам
    $dish_stats = $wpdb->get_results("
        SELECT 
            dish_name,
            dish_unit,
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
        FROM $table_name
        GROUP BY dish_name, dish_unit
        ORDER BY avg_rating DESC, total_reviews DESC
    ", ARRAY_A);
    
    // Последние заказы с отзывами
    $recent_feedback = $wpdb->get_results("
        SELECT 
            MIN(f.id) as id,
            f.token,
            f.order_id,
            f.customer_name,
            DATE_FORMAT(MAX(f.created_at), '%d.%m.%Y %H:%i') as last_date,
            MAX(f.general_comment) as general_comment,
            MAX(f.shared_instagram) as shared_instagram,
            MAX(f.shared_google) as shared_google,
            COUNT(*) as dishes_count,
            ROUND(AVG(f.rating), 2) as avg_rating,
            GROUP_CONCAT(
                CONCAT(
                    f.dish_name,
                    IF(f.dish_unit != '', CONCAT(' (', f.dish_unit, ')'), ''),
                    '::',
                    f.rating
                )
                ORDER BY f.created_at DESC
                SEPARATOR '||'
            ) as dishes_list
        FROM $table_name f
        GROUP BY f.token, f.order_id, f.customer_name
        ORDER BY MAX(f.created_at) DESC
        LIMIT 50
    ", ARRAY_A);
    
    $delete_nonce = wp_create_nonce('gustolocal_feedback_delete');
    
    ?>
    <div class="wrap">
        <h1>Результаты отзывов о блюдах</h1>
        
        <h2>Статистика по блюдам</h2>
        <p class="description">Таблица автоматически группирует отзывы по названию блюда и единице измерения. Кликните на строку, чтобы увидеть все отзывы по этому блюду.</p>
        
        <table class="wp-list-table widefat fixed striped" id="feedback-stats-table">
            <thead>
                <tr>
                    <th>Блюдо</th>
                    <th>Отзывов</th>
                    <th>Средняя оценка</th>
                    <th>😍</th>
                    <th>😊</th>
                    <th>😐</th>
                    <th>😞</th>
                    <th style="width: 100px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dish_stats as $stat): 
                    $avg = round($stat['avg_rating'], 2);
                    $dish_full = $stat['dish_name'] . ($stat['dish_unit'] ? ' (' . $stat['dish_unit'] . ')' : '');
                    $dish_key = esc_attr($stat['dish_name'] . '|' . $stat['dish_unit']);
                ?>
                    <tr data-dish-name="<?php echo esc_attr($stat['dish_name']); ?>" data-dish-unit="<?php echo esc_attr($stat['dish_unit']); ?>">
                        <td><strong><?php echo esc_html($dish_full); ?></strong></td>
                        <td><?php echo esc_html($stat['total_reviews']); ?></td>
                        <td>
                            <strong><?php echo number_format($avg, 2); ?></strong>
                            <span style="font-size: 20px;">
                                <?php 
                                if ($avg >= 3.5) echo '😍';
                                elseif ($avg >= 2.5) echo '😊';
                                elseif ($avg >= 1.5) echo '😐';
                                else echo '😞';
                                ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($stat['rating_4']); ?></td>
                        <td><?php echo esc_html($stat['rating_3']); ?></td>
                        <td><?php echo esc_html($stat['rating_2']); ?></td>
                        <td><?php echo esc_html($stat['rating_1']); ?></td>
                        <td>
                            <button type="button" class="button button-small view-details-btn" 
                                    data-dish-name="<?php echo esc_attr($stat['dish_name']); ?>" 
                                    data-dish-unit="<?php echo esc_attr($stat['dish_unit']); ?>">
                                Детали
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
        .feedback-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .feedback-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .feedback-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .feedback-modal-close:hover {
            color: #000;
        }
        .feedback-detail-item {
            padding: 15px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            border-radius: 4px;
        }
        .feedback-detail-item .rating {
            font-size: 24px;
            margin-right: 10px;
        }
        </style>
        
        <div id="feedback-modal" class="feedback-modal">
            <div class="feedback-modal-content">
                <span class="feedback-modal-close">&times;</span>
                <h2 id="modal-dish-name"></h2>
                <div id="modal-feedback-list"></div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('feedback-modal');
            var closeBtn = document.querySelector('.feedback-modal-close');
            var viewDetailsBtns = document.querySelectorAll('.view-details-btn');
            
            viewDetailsBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var dishName = this.getAttribute('data-dish-name');
                    var dishUnit = this.getAttribute('data-dish-unit');
                    showFeedbackDetails(dishName, dishUnit);
                });
            });
            
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            };
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };
            
            function showFeedbackDetails(dishName, dishUnit) {
                document.getElementById('modal-dish-name').textContent = dishName + (dishUnit ? ' (' + dishUnit + ')' : '');
                document.getElementById('modal-feedback-list').innerHTML = '<p>Загрузка...</p>';
                modal.style.display = 'block';
                
                // AJAX запрос для получения детальных отзывов
                var formData = new FormData();
                formData.append('action', 'get_feedback_details');
                formData.append('dish_name', dishName);
                formData.append('dish_unit', dishUnit);
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        var html = '';
                        if (data.data.length === 0) {
                            html = '<p>Нет детальных отзывов для этого блюда.</p>';
                        } else {
                            data.data.forEach(function(feedback) {
                                var ratingEmoji = {'1': '😞', '2': '😐', '3': '😊', '4': '😍'};
                                html += '<div class="feedback-detail-item">';
                                html += '<div style="display: flex; align-items: center; margin-bottom: 10px;">';
                                html += '<span class="rating">' + ratingEmoji[feedback.rating] + '</span>';
                                html += '<strong>' + feedback.customer_name + '</strong>';
                                html += '<span style="margin-left: auto; color: #666; font-size: 12px;">Заказ #' + feedback.order_id + ' • ' + feedback.date + '</span>';
                                html += '</div>';
                                if (feedback.general_comment) {
                                    html += '<p style="margin: 10px 0; padding: 10px; background: white; border-radius: 4px;">' + escapeHtml(feedback.general_comment) + '</p>';
                                }
                                html += '</div>';
                            });
                        }
                        document.getElementById('modal-feedback-list').innerHTML = html;
                    } else {
                        document.getElementById('modal-feedback-list').innerHTML = '<p>Ошибка: ' + (data.data || 'Не удалось загрузить отзывы') + '</p>';
                    }
                })
                .catch(function(error) {
                    document.getElementById('modal-feedback-list').innerHTML = '<p>Ошибка: ' + error + '</p>';
                });
            }
            
            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            document.querySelectorAll('.delete-feedback-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var token = this.getAttribute('data-token');
                    if (!token) {
                        return;
                    }
                    
                    if (!confirm('Удалить отзыв полностью? Это действие нельзя отменить.')) {
                        return;
                    }
                    
                    var formData = new FormData();
                    formData.append('action', 'gustolocal_delete_feedback');
                    formData.append('token', token);
                    formData.append('nonce', '<?php echo esc_js($delete_nonce); ?>');
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.data || 'Не удалось удалить отзыв');
                        }
                    })
                    .catch(function() {
                        alert('Ошибка при удалении отзыва');
                    });
                });
            });
        });
        </script>
        
        <h2>Последние комментарии и активности</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Клиент</th>
                    <th>Заказ</th>
                    <th>Блюд</th>
                    <th>Средняя</th>
                    <th>Отзывы</th>
                    <th>Комментарий</th>
                    <th>Instagram</th>
                    <th>Google</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_feedback)): ?>
                    <?php foreach ($recent_feedback as $feedback): ?>
                        <tr>
                            <td><?php echo esc_html($feedback['last_date']); ?></td>
                            <td><?php echo esc_html($feedback['customer_name']); ?></td>
                            <td>#<?php echo esc_html($feedback['order_id']); ?></td>
                            <td><?php echo esc_html($feedback['dishes_count']); ?></td>
                            <td><?php echo esc_html(number_format((float) $feedback['avg_rating'], 2)); ?></td>
                            <td>
                                <?php
                                if (!empty($feedback['dishes_list'])) {
                                    $items = explode('||', $feedback['dishes_list']);
                                    foreach ($items as $item) {
                                        list($name, $rating) = array_pad(explode('::', $item), 2, '');
                                        $emoji = array('1' => '😞', '2' => '😐', '3' => '😊', '4' => '😍');
                                        echo '<div>' . esc_html($name) . ': ' . ($emoji[$rating] ?? $rating) . '</div>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo $feedback['general_comment'] ? nl2br(esc_html($feedback['general_comment'])) : '—'; ?></td>
                            <td><?php echo !empty($feedback['shared_instagram']) ? '✅' : '—'; ?></td>
                            <td><?php echo !empty($feedback['shared_google']) ? '✅' : '—'; ?></td>
                            <td>
                                <button class="button delete-feedback-btn" data-token="<?php echo esc_attr($feedback['token']); ?>">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Нет комментариев</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2>Экспорт данных</h2>
        <p>
            <a href="<?php echo admin_url('admin-post.php?action=export_feedback'); ?>" class="button button-primary">
                Экспортировать в CSV
            </a>
        </p>
    </div>
    <?php
}

// Экспорт отзывов в CSV
add_action('admin_post_export_feedback', 'gustolocal_export_feedback_csv');
function gustolocal_export_feedback_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('Доступ запрещен');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    $results = $wpdb->get_results("
        SELECT 
            dish_name,
            dish_unit,
            customer_name,
            order_id,
            rating,
            general_comment,
            shared_instagram,
            created_at
        FROM $table_name
        ORDER BY created_at DESC
    ", ARRAY_A);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=feedback_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // BOM для правильного отображения кириллицы в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Заголовки
    fputcsv($output, array('Блюдо', 'Единица', 'Клиент', 'Заказ', 'Оценка', 'Комментарий', 'Поделились Instagram', 'Дата'), ';');
    
    // Данные
    foreach ($results as $row) {
        $rating_emoji = array(1 => '😞', 2 => '😐', 3 => '😊', 4 => '😍');
        fputcsv($output, array(
            $row['dish_name'],
            $row['dish_unit'],
            $row['customer_name'],
            $row['order_id'],
            $rating_emoji[$row['rating']] ?? $row['rating'],
            $row['general_comment'],
            $row['shared_instagram'] ? 'Да' : 'Нет',
            $row['created_at']
        ), ';');
    }
    
    fclose($output);
    exit;
}

// Регистрация кастомного эндпоинта для опросника
add_action('init', 'gustolocal_register_feedback_endpoint');
function gustolocal_register_feedback_endpoint() {
    add_rewrite_rule('^feedback/([^/]+)/?$', 'index.php?feedback_token=$matches[1]', 'top');
    add_rewrite_tag('%feedback_token%', '([^&]+)');
}

function gustolocal_feedback_rewrite_exists() {
    $rules = get_option('rewrite_rules');
    return is_array($rules) && array_key_exists('^feedback/([^/]+)/?$', $rules);
}

add_action('init', 'gustolocal_ensure_feedback_rewrite', 19);
function gustolocal_ensure_feedback_rewrite() {
    if (!gustolocal_feedback_rewrite_exists()) {
        gustolocal_register_feedback_endpoint();
        flush_rewrite_rules(false);
    }
}

// Перезапись правил при активации
add_action('after_switch_theme', 'gustolocal_flush_rewrite_rules');
function gustolocal_flush_rewrite_rules() {
    gustolocal_register_feedback_endpoint();
    flush_rewrite_rules();
}

// Обработка запроса опросника
add_action('template_redirect', 'gustolocal_handle_feedback_page');
function gustolocal_handle_feedback_page() {
    $token = get_query_var('feedback_token');
    
    if (!$token) {
        return;
    }
    
    global $wpdb;
    $custom_requests_table = $wpdb->prefix . 'custom_feedback_requests';
    
    // Сначала проверяем, это кастомный опрос?
    $custom_request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $custom_requests_table WHERE token = %s",
        $token
    ), ARRAY_A);
    
    if ($custom_request) {
        // Это кастомный опрос
        gustolocal_display_custom_feedback_form($token, $custom_request);
        exit;
    }
    
    // Иначе это обычный опрос по заказу
    $order_id = null;
    
    // Сначала проверяем в мета заказа
    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders(array(
            'limit' => 100,
            'meta_key' => '_feedback_token',
            'meta_value' => $token,
        ));
        
        if (!empty($orders)) {
            $order_id = $orders[0]->get_id();
        }
    }
    
    // Если не нашли, проверяем в БД
    if (!$order_id) {
        $table_name = $wpdb->prefix . 'dish_feedback';
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM $table_name WHERE token = %s LIMIT 1",
            $token
        ));
    }
    
    if (!$order_id) {
        wp_die('Неверная ссылка на опросник.', 'Ошибка', array('response' => 404));
    }
    
    // Показываем опросник
    gustolocal_display_feedback_form($token, $order_id);
    exit;
}

// Отображение формы опросника
function gustolocal_display_feedback_form($token, $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Заказ не найден.', 'Ошибка', array('response' => 404));
    }
    
    $dishes = gustolocal_extract_dishes_from_order($order);
    
    if (empty($dishes)) {
        wp_die('Блюда не найдены в заказе.', 'Ошибка', array('response' => 404));
    }
    
    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if ($customer_name === '') {
        $customer_name = $order->get_billing_company() ?: 'Дорогой клиент';
    }
    
    // Проверяем, не заполнен ли уже опрос
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    $already_submitted = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE token = %s",
        $token
    ));
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Оцените наши блюда</title>
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feedback-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 30px;
            margin: 20px auto;
        }
        .feedback-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .feedback-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .feedback-header p {
            color: #666;
            font-size: 16px;
        }
        .dish-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .dish-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .rating-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .rating-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 32px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating-btn:hover {
            transform: scale(1.1);
            border-color: #667eea;
        }
        .rating-btn.selected {
            border-color: #667eea;
            background: #667eea;
            transform: scale(1.1);
        }
        .rating-label {
            text-align: center;
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }
        .general-comment {
            margin-top: 30px;
        }
        .general-comment label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .general-comment textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }
        .share-section {
            margin-top: 30px;
            padding: 20px;
            background: #f0f4ff;
            border-radius: 12px;
            text-align: center;
        }
        .share-section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .share-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            margin: 5px;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .share-button:hover {
            transform: scale(1.05);
        }
        .share-button:active {
            transform: scale(0.98);
        }
        .share-icon {
            font-size: 18px;
        }
        .share-button--google {
            background: linear-gradient(120deg, #4285F4, #34A853, #FBBC05, #EA4335);
            color: #fff;
        }
        .share-button--google .share-icon {
            font-size: 16px;
        }
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .success-message {
            text-align: center;
            padding: 40px;
            color: #46b450;
        }
        .success-message h2 {
            font-size: 32px;
            margin-bottom: 15px;
        }
        @media (max-width: 600px) {
            .feedback-container {
                padding: 20px;
            }
            .rating-btn {
                width: 50px;
                height: 50px;
                font-size: 28px;
            }
        }
        </style>
    </head>
    <body>
        <div class="feedback-container">
            <?php if ($already_submitted > 0): ?>
                <div class="success-message">
                    <h2>✅ Спасибо!</h2>
                    <p>Вы уже оставили отзыв. Мы ценим ваше мнение!</p>
                </div>
            <?php else: ?>
                <div class="feedback-header">
                    <h1>Нам важно ваше мнение! 🙏</h1>
                    <p>Пожалуйста, оцените блюда из последнего заказа (пропускайте, если не успели попробовать):</p>
                </div>
                <form id="feedback-form">
                    <input type="hidden" name="action" value="guest_feedback_submit">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                    <input type="hidden" name="shared_instagram" id="shared-instagram-field" value="0">
                    <input type="hidden" name="shared_google" id="shared-google-field" value="0">
                    
                    <?php foreach ($dishes as $dish_key => $dish_data): ?>
                        <div class="dish-item" data-dish="<?php echo esc_attr($dish_key); ?>">
                            <div class="dish-name">
                                <?php echo esc_html($dish_data['name']); ?>
                                <?php if ($dish_data['unit']): ?>
                                    <small style="color: #666;">(<?php echo esc_html($dish_data['unit']); ?>)</small>
                                <?php endif; ?>
                            </div>
                            <div class="rating-buttons">
                                <button type="button" class="rating-btn" data-rating="1" data-dish="<?php echo esc_attr($dish_key); ?>">
                                    😞
                                </button>
                                <button type="button" class="rating-btn" data-rating="2" data-dish="<?php echo esc_attr($dish_key); ?>">
                                    😐
                                </button>
                                <button type="button" class="rating-btn" data-rating="3" data-dish="<?php echo esc_attr($dish_key); ?>">
                                    😊
                                </button>
                                <button type="button" class="rating-btn" data-rating="4" data-dish="<?php echo esc_attr($dish_key); ?>">
                                    😍
                                </button>
                            </div>
                            <input type="hidden" name="ratings[<?php echo esc_attr($dish_key); ?>]" value="">
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="general-comment">
                        <label for="general-comment">Хотите что-то добавить?</label>
                        <textarea id="general-comment" name="general_comment" placeholder="Ваши пожелания, замечания, предложения..."></textarea>
                    </div>
                    
                    <div class="share-section">
                        <h3>Понравилось? Расскажите друзьям! 👥</h3>
                        <button type="button" class="share-button" id="share-btn" onclick="shareInstagram()">
                            <span class="share-icon">↗️</span>
                            <span>Поделиться нашим Instagram</span>
                        </button>
                        <a class="share-button share-button--google"
                           href="https://maps.app.goo.gl/6rmjMdquG5vcVFry6"
                           target="_blank"
                           rel="noopener noreferrer"
                           onclick="markShareField('shared-google-field')">
                            <span class="share-icon">★</span>
                            <span>Оставить отзыв в Google Maps</span>
                        </a>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submit-btn">
                        Отправить отзыв
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('feedback-form');
            if (!form) return;
            
            // Обработка кликов по смайликам
            document.querySelectorAll('.rating-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var dish = this.getAttribute('data-dish');
                    var rating = this.getAttribute('data-rating');
                    
                    // Убираем выделение с других кнопок этого блюда
                    document.querySelectorAll('.rating-btn[data-dish="' + dish + '"]').forEach(function(b) {
                        b.classList.remove('selected');
                    });
                    
                    // Выделяем выбранную кнопку
                    this.classList.add('selected');
                    
                    // Сохраняем рейтинг в скрытое поле
                    document.querySelector('input[name="ratings[' + dish + ']"]').value = rating;
                    
                    checkFormComplete();
                });
            });
            
            function checkFormComplete() {
                var anyRated = false;
                document.querySelectorAll('.dish-item').forEach(function(item) {
                    var dish = item.getAttribute('data-dish');
                    var rating = document.querySelector('input[name="ratings[' + dish + ']"]').value;
                    if (rating) {
                        anyRated = true;
                    }
                });
                
                document.getElementById('submit-btn').disabled = !anyRated;
            }
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var submitBtn = document.getElementById('submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Отправка...';
                
                var formData = new FormData(form);
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    if (!response.ok) {
                        return response.text().then(function(text) {
                            throw new Error('HTTP ' + response.status + ': ' + text);
                        });
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        var header = document.querySelector('.feedback-header');
                        if (header) {
                            header.remove();
                        }
                        form.innerHTML = '<div class="success-message"><h2>✅ Спасибо!</h2><p>Ваш отзыв сохранен. Мы ценим ваше мнение!</p></div>';
                    } else {
                        var errorMsg = data.data || 'Не удалось сохранить отзыв';
                        console.error('Ошибка сохранения:', data);
                        alert('Ошибка: ' + errorMsg);
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Отправить отзыв';
                    }
                })
                .catch(function(error) {
                    console.error('Ошибка запроса:', error);
                    alert('Ошибка: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Отправить отзыв';
                });
            });
        });
        
        function shareInstagram() {
            var instagramUrl = 'https://www.instagram.com/llevatelo_vlc/';
            var shareText = 'Попробуйте вкусную еду от Llévatelo! 🍽️';
            
            // Проверяем поддержку Web Share API
            if (navigator.share) {
                navigator.share({
                    title: 'Llévatelo - Вкусная еда в Валенсии',
                    text: shareText,
                    url: instagramUrl
                })
                .then(function() {
                    console.log('Успешно поделились');
                    // Отмечаем, что поделились
                    trackShare();
                })
                .catch(function(error) {
                    console.log('Ошибка при попытке поделиться:', error);
                    // Fallback: открываем Instagram в новой вкладке
                    window.open(instagramUrl, '_blank');
                    trackShare();
                });
            } else {
                // Fallback для браузеров без поддержки Web Share API
                // Показываем диалог с ссылкой для копирования
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(instagramUrl).then(function() {
                        alert('Ссылка на Instagram скопирована! Вставьте её в любое приложение.');
                        trackShare();
                    }).catch(function() {
                        // Если не удалось скопировать, просто открываем
                        window.open(instagramUrl, '_blank');
                        trackShare();
                    });
                } else {
                    // Последний fallback: открываем Instagram
                    window.open(instagramUrl, '_blank');
                    trackShare();
                }
            }
        }
        
        function markShareField(fieldId) {
            var field = document.getElementById(fieldId);
            if (field) {
                field.value = '1';
            }
        }
        
        function trackShare() {
            markShareField('shared-instagram-field');
        }
        </script>
    </body>
    </html>
    <?php
}

// AJAX обработчик для сохранения отзывов
add_action('wp_ajax_guest_feedback_submit', 'gustolocal_handle_feedback_submit');
add_action('wp_ajax_nopriv_guest_feedback_submit', 'gustolocal_handle_feedback_submit');
function gustolocal_handle_feedback_submit() {
    // Проверяем action
    $action = sanitize_text_field($_POST['action'] ?? '');
    if (empty($action) || $action !== 'guest_feedback_submit') {
        wp_send_json_error('Неверный запрос');
    }
    
    $token = sanitize_text_field($_POST['token'] ?? '');
    $order_id = intval($_POST['order_id'] ?? 0);
    
    // Правильно обрабатываем массив ratings из FormData
    $ratings = array();
    if (isset($_POST['ratings']) && is_array($_POST['ratings'])) {
        $ratings = $_POST['ratings'];
    } else {
        // Пробуем получить из строки (если пришло как строка)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'ratings[') === 0) {
                $dish_key = str_replace(array('ratings[', ']'), '', $key);
                $ratings[$dish_key] = intval($value);
            }
        }
    }
    
    $general_comment = sanitize_textarea_field($_POST['general_comment'] ?? '');
    $shared_instagram = !empty($_POST['shared_instagram']) ? 1 : 0;
    $shared_google = !empty($_POST['shared_google']) ? 1 : 0;
    
    if (empty($token) || empty($order_id) || empty($ratings)) {
        wp_send_json_error('Неверные данные: токен, заказ или оценки отсутствуют');
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Заказ не найден');
    }
    
    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if ($customer_name === '') {
        $customer_name = $order->get_billing_company() ?: 'Гость';
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    // Проверяем существование таблицы
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        // Создаем таблицу, если её нет
        gustolocal_create_feedback_table();
    }
    
    $saved_count = 0;
    $errors = array();
    
    // Сохраняем отзывы по каждому блюду
    foreach ($ratings as $dish_key => $rating) {
        $rating = intval($rating);
        if ($rating < 1 || $rating > 4) continue;
        
        // Извлекаем название блюда и единицу из ключа
        $dish_parts = explode(' (', $dish_key);
        $dish_name = $dish_parts[0];
        $dish_unit = isset($dish_parts[1]) ? rtrim($dish_parts[1], ')') : '';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'token' => $token,
                'order_id' => $order_id,
                'customer_name' => $customer_name,
                'dish_name' => $dish_name,
                'dish_unit' => $dish_unit,
                'rating' => $rating,
                'general_comment' => '', // Общий комментарий сохраним отдельно
                'shared_instagram' => 0,
                'shared_google' => 0,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            $errors[] = 'Ошибка при сохранении отзыва для ' . $dish_name . ': ' . $wpdb->last_error;
        } else {
            $saved_count++;
        }
    }
    
    if ($saved_count === 0) {
        wp_send_json_error('Не удалось сохранить ни одного отзыва. ' . implode(' ', $errors));
    }
    
    // Сохраняем общий комментарий и флаг поделились в Instagram в первой записи
    if (!empty($general_comment) || $shared_instagram) {
        $first_feedback_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE token = %s ORDER BY id ASC LIMIT 1",
            $token
        ));
        
        if ($first_feedback_id) {
            $wpdb->update(
                $table_name,
                array(
                    'general_comment' => $general_comment,
                    'shared_instagram' => $shared_instagram,
                    'shared_google' => $shared_google,
                ),
                array('id' => $first_feedback_id),
                array('%s', '%d', '%d'),
                array('%d')
            );
        }
    }
    
    wp_send_json_success('Отзыв сохранен');
}

// AJAX обработчик для получения детальных отзывов по блюду
add_action('wp_ajax_get_feedback_details', 'gustolocal_get_feedback_details');
function gustolocal_get_feedback_details() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Доступ запрещен');
    }
    
    $dish_name = sanitize_text_field($_POST['dish_name'] ?? '');
    $dish_unit = sanitize_text_field($_POST['dish_unit'] ?? '');
    
    if (empty($dish_name)) {
        wp_send_json_error('Название блюда не указано');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    $query = $wpdb->prepare(
        "SELECT 
            f.*,
            DATE_FORMAT(f.created_at, '%%d.%%m.%%Y %%H:%%i') as date
        FROM $table_name f
        WHERE f.dish_name = %s",
        $dish_name
    );
    
    if (!empty($dish_unit)) {
        $query .= $wpdb->prepare(" AND f.dish_unit = %s", $dish_unit);
    }
    
    $query .= " ORDER BY f.created_at DESC LIMIT 100";
    
    $feedbacks = $wpdb->get_results($query, ARRAY_A);
    
    // Группируем по заказам, чтобы показать общий комментарий один раз
    $grouped_feedbacks = array();
    foreach ($feedbacks as $feedback) {
        $order_id = $feedback['order_id'];
        if (!isset($grouped_feedbacks[$order_id])) {
            $grouped_feedbacks[$order_id] = array(
                'order_id' => $order_id,
                'customer_name' => $feedback['customer_name'],
                'date' => $feedback['date'],
                'general_comment' => $feedback['general_comment'],
                'shared_instagram' => $feedback['shared_instagram'],
                'dishes' => array(),
            );
        }
        $grouped_feedbacks[$order_id]['dishes'][] = array(
            'dish_name' => $feedback['dish_name'],
            'dish_unit' => $feedback['dish_unit'],
            'rating' => $feedback['rating'],
        );
    }
    
    // Преобразуем в простой массив для отображения
    $result = array();
    foreach ($grouped_feedbacks as $order_feedback) {
        // Находим рейтинг для нужного блюда
        $rating = null;
        foreach ($order_feedback['dishes'] as $dish) {
            if ($dish['dish_name'] === $dish_name && 
                (empty($dish_unit) || $dish['dish_unit'] === $dish_unit)) {
                $rating = $dish['rating'];
                break;
            }
        }
        
        if ($rating) {
            $result[] = array(
                'order_id' => $order_feedback['order_id'],
                'customer_name' => $order_feedback['customer_name'],
                'date' => $order_feedback['date'],
                'rating' => $rating,
                'general_comment' => $order_feedback['general_comment'],
                'shared_instagram' => $order_feedback['shared_instagram'],
            );
        }
    }
    
    wp_send_json_success($result);
}

add_action('wp_ajax_gustolocal_delete_feedback', 'gustolocal_delete_feedback');
function gustolocal_delete_feedback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Доступ запрещен');
    }
    
    check_ajax_referer('gustolocal_feedback_delete', 'nonce');
    
    $token = sanitize_text_field($_POST['token'] ?? '');
    if (empty($token)) {
        wp_send_json_error('Токен не указан');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'dish_feedback';
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE token = %s",
        $token
    ));
    
    if ($deleted === false) {
        wp_send_json_error('Ошибка удаления: ' . $wpdb->last_error);
    }
    
    wp_send_json_success(array('deleted' => $deleted));
}

/* ========================================
   КАСТОМНЫЕ ОПРОСЫ (БЕЗ ЗАКАЗОВ)
   ======================================== */

// Отображение формы кастомного опроса
function gustolocal_display_custom_feedback_form($token, $custom_request) {
    // Проверяем, не заполнен ли уже опрос (только если статус submitted)
    $already_submitted = $custom_request['status'] === 'submitted';
    
    // Парсим блюда из текста
    $dishes_lines = explode("\n", $custom_request['dishes']);
    $dishes = array();
    foreach ($dishes_lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Пытаемся извлечь название и единицу измерения
        if (preg_match('/^(.+?)\s*\((.+?)\)$/', $line, $matches)) {
            $dishes[] = array(
                'name' => trim($matches[1]),
                'unit' => trim($matches[2])
            );
        } else {
            $dishes[] = array(
                'name' => $line,
                'unit' => ''
            );
        }
    }
    
    if (empty($dishes)) {
        wp_die('Блюда не найдены.', 'Ошибка', array('response' => 404));
    }
    
    $customer_name = $custom_request['client_name'] ?: 'Дорогой клиент';
    
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Оцените наши блюда</title>
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feedback-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 30px;
            margin: 20px auto;
        }
        .feedback-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .feedback-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .feedback-header p {
            color: #666;
            font-size: 16px;
        }
        .dish-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .dish-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .rating-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .rating-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 32px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating-btn:hover {
            transform: scale(1.1);
            border-color: #667eea;
        }
        .rating-btn.selected {
            border-color: #667eea;
            background: #667eea;
            transform: scale(1.1);
        }
        .general-comment {
            margin-top: 30px;
        }
        .general-comment label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .general-comment textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }
        .share-section {
            margin-top: 30px;
            padding: 20px;
            background: #f0f4ff;
            border-radius: 12px;
            text-align: center;
        }
        .share-section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .share-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            margin: 5px;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .share-button:hover {
            transform: scale(1.05);
        }
        .share-button--google {
            background: linear-gradient(120deg, #4285F4, #34A853, #FBBC05, #EA4335);
            color: #fff;
        }
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 30px;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: scale(1.02);
        }
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            margin-top: 20px;
        }
        </style>
    </head>
    <body>
        <div class="feedback-container">
            <?php if ($already_submitted): ?>
                <div class="success-message">
                    Спасибо! Ваш отзыв уже был отправлен. 🙏
                </div>
            <?php else: ?>
                <div class="feedback-header">
                    <h1>Нам важно ваше мнение! 🙏</h1>
                    <p>Пожалуйста, оцените блюда из последнего заказа (пропускайте, если не успели попробовать):</p>
                </div>
                
                <form id="custom-feedback-form">
                    <input type="hidden" name="action" value="guest_custom_feedback_submit">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    <input type="hidden" name="request_id" value="<?php echo esc_attr($custom_request['id']); ?>">
                    
                    <?php foreach ($dishes as $index => $dish): 
                        $dish_full = $dish['name'] . ($dish['unit'] ? ' (' . $dish['unit'] . ')' : '');
                    ?>
                        <div class="dish-item">
                            <div class="dish-name"><?php echo esc_html($dish_full); ?></div>
                            <div class="rating-buttons">
                                <button type="button" class="rating-btn" data-rating="1" data-dish-index="<?php echo $index; ?>">
                                    😞
                                </button>
                                <button type="button" class="rating-btn" data-rating="2" data-dish-index="<?php echo $index; ?>">
                                    😐
                                </button>
                                <button type="button" class="rating-btn" data-rating="3" data-dish-index="<?php echo $index; ?>">
                                    😊
                                </button>
                                <button type="button" class="rating-btn" data-rating="4" data-dish-index="<?php echo $index; ?>">
                                    😍
                                </button>
                            </div>
                            <input type="hidden" name="ratings[<?php echo $index; ?>]" value="0">
                            <input type="hidden" name="dish_name_<?php echo $index; ?>" value="<?php echo esc_attr($dish['name']); ?>">
                            <input type="hidden" name="dish_unit_<?php echo $index; ?>" value="<?php echo esc_attr($dish['unit']); ?>">
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="general-comment">
                        <label for="general_comment">Общий комментарий (необязательно)</label>
                        <textarea id="general_comment" name="general_comment" placeholder="Поделитесь своими впечатлениями..."></textarea>
                    </div>
                    
                    <div class="share-section">
                        <h3>Поделитесь с друзьями</h3>
                        <button type="button" class="share-button" onclick="shareInstagram()">
                            <span class="share-icon">📷</span>
                            Поделиться нашим Instagram
                        </button>
                        <button type="button" class="share-button share-button--google" onclick="shareGoogle()">
                            <span class="share-icon">⭐</span>
                            Оставить отзыв в Google Maps
                        </button>
                        <input type="hidden" name="shared_instagram" value="0" id="shared-instagram-field">
                        <input type="hidden" name="shared_google" value="0" id="shared-google-field">
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submit-btn">Отправить отзыв</button>
                </form>
            <?php endif; ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('custom-feedback-form');
            if (!form) return;
            
            var ratings = {};
            var ratingButtons = document.querySelectorAll('.rating-btn');
            
            ratingButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var dishIndex = this.getAttribute('data-dish-index');
                    var rating = parseInt(this.getAttribute('data-rating'));
                    
                    // Убираем выделение с других кнопок этого блюда
                    var dishItem = this.closest('.dish-item');
                    dishItem.querySelectorAll('.rating-btn').forEach(function(b) {
                        b.classList.remove('selected');
                    });
                    
                    // Выделяем текущую кнопку
                    this.classList.add('selected');
                    
                    // Сохраняем рейтинг
                    ratings[dishIndex] = rating;
                    var hiddenInput = dishItem.querySelector('input[type="hidden"][name^="ratings"]');
                    if (hiddenInput) {
                        hiddenInput.value = rating;
                    }
                    
                    updateSubmitButton();
                });
            });
            
            function updateSubmitButton() {
                var hasRating = Object.keys(ratings).some(function(key) {
                    return ratings[key] > 0;
                });
                var submitBtn = document.getElementById('submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = !hasRating;
                }
            }
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var submitBtn = document.getElementById('submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Отправка...';
                }
                
                var formData = new FormData(form);
                
                // Убеждаемся, что все скрытые поля с названиями блюд передаются
                document.querySelectorAll('input[type="hidden"][name^="dish_name_"]').forEach(function(input) {
                    formData.append(input.name, input.value);
                });
                document.querySelectorAll('input[type="hidden"][name^="dish_unit_"]').forEach(function(input) {
                    formData.append(input.name, input.value);
                });
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        // Заменяем весь контент контейнера, чтобы убрать заголовок
                        var container = document.querySelector('.feedback-container');
                        if (container) {
                            container.innerHTML = '<div class="success-message">Спасибо! Ваш отзыв сохранен. Мы ценим ваше мнение!</div>';
                        }
                    } else {
                        alert('Ошибка: ' + (data.data || 'Не удалось сохранить отзыв'));
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Отправить отзыв';
                        }
                    }
                })
                .catch(function(error) {
                    alert('Ошибка: ' + error);
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Отправить отзыв';
                    }
                });
            });
            
            updateSubmitButton();
        });
        
        function shareInstagram() {
            var sharedInput = document.getElementById('shared-instagram-field');
            if (sharedInput) {
                sharedInput.value = '1';
            }
            
            if (navigator.share) {
                navigator.share({
                    title: 'Llévatelo - Готовая еда в Валенсии',
                    text: 'Попробуйте готовую еду от Llévatelo!',
                    url: 'https://www.instagram.com/llevatelo_vlc/'
                }).catch(function(err) {
                    console.log('Error sharing:', err);
                });
            } else {
                window.open('https://www.instagram.com/llevatelo_vlc/', '_blank');
            }
        }
        
        function shareGoogle() {
            var sharedInput = document.getElementById('shared-google-field');
            if (sharedInput) {
                sharedInput.value = '1';
            }
            
            var link = document.createElement('a');
            link.href = 'https://maps.app.goo.gl/6rmjMdquG5vcVFry6';
            link.target = '_blank';
            link.click();
        }
        </script>
    </body>
    </html>
    <?php
}

// AJAX обработчик для сохранения кастомных отзывов
add_action('wp_ajax_guest_custom_feedback_submit', 'gustolocal_handle_custom_feedback_submit');
add_action('wp_ajax_nopriv_guest_custom_feedback_submit', 'gustolocal_handle_custom_feedback_submit');
function gustolocal_handle_custom_feedback_submit() {
    $action = sanitize_text_field($_POST['action'] ?? '');
    if (empty($action) || $action !== 'guest_custom_feedback_submit') {
        wp_send_json_error('Неверный запрос');
    }
    
    $token = sanitize_text_field($_POST['token'] ?? '');
    $request_id = intval($_POST['request_id'] ?? 0);
    
    if (empty($token) || empty($request_id)) {
        wp_send_json_error('Неверные параметры');
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'custom_feedback_requests';
    $entries_table = $wpdb->prefix . 'custom_feedback_entries';
    
    // Проверяем, что запрос существует
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $requests_table WHERE id = %d AND token = %s",
        $request_id,
        $token
    ), ARRAY_A);
    
    if (!$request) {
        wp_send_json_error('Запрос не найден');
    }
    
    // Обрабатываем рейтинги
    $ratings = array();
    
    // Сначала пробуем получить из массива ratings
    if (isset($_POST['ratings']) && is_array($_POST['ratings'])) {
        foreach ($_POST['ratings'] as $index => $rating) {
            $rating = intval($rating);
            if ($rating > 0) {
                // Получаем название и единицу блюда из скрытых полей
                $dish_name = sanitize_text_field($_POST["dish_name_{$index}"] ?? '');
                $dish_unit = sanitize_text_field($_POST["dish_unit_{$index}"] ?? '');
                
                if (empty($dish_name)) {
                    // Если не нашли в POST, получаем из исходного списка блюд
                    $dishes_lines = explode("\n", $request['dishes']);
                    $line = trim($dishes_lines[intval($index)] ?? '');
                    if (preg_match('/^(.+?)\s*\((.+?)\)$/', $line, $matches)) {
                        $dish_name = trim($matches[1]);
                        $dish_unit = trim($matches[2]);
                    } else {
                        $dish_name = $line;
                        $dish_unit = '';
                    }
                }
                $ratings[] = array(
                    'dish_name' => $dish_name,
                    'dish_unit' => $dish_unit,
                    'rating' => $rating
                );
            }
        }
    } else {
        // Альтернативный способ: ищем все поля, начинающиеся с dish_name_
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'dish_name_') === 0) {
                $index = str_replace('dish_name_', '', $key);
                $rating = intval($_POST["ratings[{$index}]"] ?? 0);
                if ($rating > 0) {
                    $dish_name = sanitize_text_field($value);
                    $dish_unit = sanitize_text_field($_POST["dish_unit_{$index}"] ?? '');
                    $ratings[] = array(
                        'dish_name' => $dish_name,
                        'dish_unit' => $dish_unit,
                        'rating' => $rating
                    );
                }
            }
        }
    }
    
    if (empty($ratings)) {
        wp_send_json_error('Необходимо оценить хотя бы одно блюдо');
    }
    
    // Сохраняем рейтинги
    foreach ($ratings as $rating_data) {
        $insert_result = $wpdb->insert(
            $entries_table,
            array(
                'request_id' => $request_id,
                'dish_name' => $rating_data['dish_name'],
                'dish_unit' => $rating_data['dish_unit'],
                'rating' => $rating_data['rating'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($insert_result === false) {
            error_log('Custom feedback insert error: ' . $wpdb->last_error);
            wp_send_json_error('Ошибка сохранения: ' . $wpdb->last_error);
        }
    }
    
    // Обновляем статус запроса и сохраняем общий комментарий
    $general_comment = sanitize_textarea_field($_POST['general_comment'] ?? '');
    $shared_instagram = intval($_POST['shared_instagram'] ?? 0);
    $shared_google = intval($_POST['shared_google'] ?? 0);
    
    $update_result = $wpdb->update(
        $requests_table,
        array(
            'status' => 'submitted',
            'general_comment' => $general_comment,
            'shared_instagram' => $shared_instagram,
            'shared_google' => $shared_google,
            'submitted_at' => current_time('mysql')
        ),
        array('id' => $request_id),
        array('%s', '%s', '%d', '%d', '%s'),
        array('%d')
    );
    
    if ($update_result === false) {
        error_log('Custom feedback update error: ' . $wpdb->last_error);
        wp_send_json_error('Ошибка обновления: ' . $wpdb->last_error);
    }
    
    wp_send_json_success('Отзыв сохранен');
}

// AJAX обработчик для удаления кастомных отзывов
add_action('wp_ajax_gustolocal_delete_custom_feedback', 'gustolocal_delete_custom_feedback');
function gustolocal_delete_custom_feedback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Доступ запрещен');
    }
    
    check_ajax_referer('gustolocal_custom_feedback_delete', 'nonce');
    
    $token = sanitize_text_field($_POST['token'] ?? '');
    if (empty($token)) {
        wp_send_json_error('Токен не указан');
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'custom_feedback_requests';
    $entries_table = $wpdb->prefix . 'custom_feedback_entries';
    
    // Получаем request_id по токену
    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $requests_table WHERE token = %s",
        $token
    ), ARRAY_A);
    
    if (!$request) {
        wp_send_json_error('Запрос не найден');
    }
    
    $request_id = $request['id'];
    
    // Удаляем все записи оценок
    $deleted_entries = $wpdb->query($wpdb->prepare(
        "DELETE FROM $entries_table WHERE request_id = %d",
        $request_id
    ));
    
    // Сбрасываем статус запроса в pending и очищаем данные
    $updated = $wpdb->update(
        $requests_table,
        array(
            'status' => 'pending',
            'general_comment' => '',
            'shared_instagram' => 0,
            'shared_google' => 0,
            'submitted_at' => null
        ),
        array('id' => $request_id),
        array('%s', '%s', '%d', '%d', '%s'),
        array('%d')
    );
    
    if ($updated === false) {
        wp_send_json_error('Ошибка обновления: ' . $wpdb->last_error);
    }
    
    wp_send_json_success(array('deleted_entries' => $deleted_entries, 'updated' => $updated));
}

// AJAX обработчик для получения детальных отзывов по блюду из кастомных опросов
add_action('wp_ajax_get_custom_feedback_details', 'gustolocal_get_custom_feedback_details');
function gustolocal_get_custom_feedback_details() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Доступ запрещен');
    }
    
    $dish_name = sanitize_text_field($_POST['dish_name'] ?? '');
    $dish_unit = sanitize_text_field($_POST['dish_unit'] ?? '');
    
    if (empty($dish_name)) {
        wp_send_json_error('Название блюда не указано');
    }
    
    global $wpdb;
    $entries_table = $wpdb->prefix . 'custom_feedback_entries';
    $requests_table = $wpdb->prefix . 'custom_feedback_requests';
    
    $query = $wpdb->prepare(
        "SELECT 
            e.*,
            r.client_name,
            r.general_comment,
            DATE_FORMAT(e.created_at, '%%d.%%m.%%Y %%H:%%i') as date
        FROM $entries_table e
        INNER JOIN $requests_table r ON r.id = e.request_id
        WHERE e.dish_name = %s",
        $dish_name
    );
    
    if (!empty($dish_unit)) {
        $query .= $wpdb->prepare(" AND e.dish_unit = %s", $dish_unit);
    } else {
        $query .= " AND (e.dish_unit = '' OR e.dish_unit IS NULL)";
    }
    
    $query .= " AND e.rating > 0 ORDER BY e.created_at DESC";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $result = array();
    foreach ($results as $row) {
        $result[] = array(
            'client_name' => $row['client_name'],
            'date' => $row['date'],
            'rating' => $row['rating'],
            'general_comment' => $row['general_comment'],
        );
    }
    
    wp_send_json_success($result);
}

// Страница управления кастомными опросами (аналог "Обратная связь")
function gustolocal_custom_feedback_management_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $requests_table = $wpdb->prefix . 'custom_feedback_requests';
    $site_url = home_url();
    
    // Обработка создания нового опроса
    if (isset($_POST['create_custom_feedback']) && check_admin_referer('create_custom_feedback')) {
        $client_name = sanitize_text_field($_POST['client_name']);
        $client_contact = sanitize_text_field($_POST['client_contact']);
        $dishes_text = sanitize_textarea_field($_POST['dishes']);
        
        if (empty($client_name) || empty($dishes_text)) {
            echo '<div class="notice notice-error"><p>Заполните имя клиента и список блюд.</p></div>';
        } else {
            // Генерируем токен
            $token = wp_generate_password(32, false);
            
            // Сохраняем запрос
            $wpdb->insert(
                $requests_table,
                array(
                    'token' => $token,
                    'client_name' => $client_name,
                    'client_contact' => $client_contact,
                    'dishes' => $dishes_text,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($wpdb->last_error) {
                echo '<div class="notice notice-error"><p>Ошибка: ' . esc_html($wpdb->last_error) . '</p></div>';
            } else {
                $feedback_url = $site_url . '/feedback/' . $token;
                echo '<div class="notice notice-success"><p><strong>Опрос создан!</strong> Ссылка: <a href="' . esc_url($feedback_url) . '" target="_blank">' . esc_html($feedback_url) . '</a></p></div>';
            }
        }
    }
    
    // Получаем список созданных опросов
    $requests = $wpdb->get_results(
        "SELECT * FROM $requests_table ORDER BY created_at DESC LIMIT 100",
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1>Кастомные опросы</h1>
        <p>Создайте опрос для клиентов, которым вы отправили кастомное меню (без формального заказа в системе).</p>
        
        <h2>Создать новый опрос</h2>
        <form method="post" action="" style="max-width: 800px; margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 5px;">
            <?php wp_nonce_field('create_custom_feedback'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="client_name">Имя клиента *</label></th>
                    <td>
                        <input type="text" id="client_name" name="client_name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="client_contact">Контакт (телефон/email)</label></th>
                    <td>
                        <input type="text" id="client_contact" name="client_contact" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dishes">Список блюд *</label></th>
                    <td>
                        <textarea id="dishes" name="dishes" rows="10" class="large-text" required placeholder="Введите блюда по одному на строку, например:&#10;Хумус (150 г)&#10;Сэндвич с пастрами (200 г)&#10;Паста с индейкой (250 г)"></textarea>
                        <p class="description">Введите блюда по одному на строку. Можно указать единицу измерения в скобках.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="create_custom_feedback" class="button button-primary" value="Создать опрос и получить ссылку">
            </p>
        </form>
        
        <h2>Созданные опросы</h2>
        <?php if (empty($requests)): ?>
            <div class="notice notice-info">
                <p>Опросы не созданы.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Дата создания</th>
                        <th>Клиент</th>
                        <th>Контакт</th>
                        <th>Блюд</th>
                        <th>Статус</th>
                        <th style="width: 500px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $delete_nonce = wp_create_nonce('gustolocal_custom_feedback_delete');
                    foreach ($requests as $request): 
                        $dishes_list = explode("\n", $request['dishes']);
                        $dishes_count = count(array_filter($dishes_list, 'trim'));
                        $feedback_url = $site_url . '/feedback/' . $request['token'];
                        $status_label = $request['status'] === 'submitted' ? 'Заполнен' : 'Ожидает';
                        $status_class = $request['status'] === 'submitted' ? 'success' : 'warning';
                        
                        // Формируем WhatsApp ссылку
                        $whatsapp_link = '';
                        if (!empty($request['client_contact'])) {
                            $phone = preg_replace('/[^0-9]/', '', $request['client_contact']);
                            if ($phone) {
                                $whatsapp_link = 'https://wa.me/' . $phone;
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('d.m.Y H:i', strtotime($request['created_at']))); ?></td>
                            <td><strong><?php echo esc_html($request['client_name']); ?></strong></td>
                            <td><?php echo esc_html($request['client_contact'] ?: '—'); ?></td>
                            <td><?php echo esc_html($dishes_count); ?></td>
                            <td><span class="status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <input type="text" 
                                           id="custom-feedback-link-<?php echo esc_attr($request['id']); ?>" 
                                           value="<?php echo esc_attr($feedback_url); ?>" 
                                           readonly 
                                           style="flex: 1; min-width: 200px; font-size: 11px;">
                                    <button type="button" 
                                            class="button button-small copy-link-btn" 
                                            data-target="custom-feedback-link-<?php echo esc_attr($request['id']); ?>">
                                        Копировать
                                    </button>
                                    <?php if ($whatsapp_link): ?>
                                        <a href="<?php echo esc_url($whatsapp_link); ?>" 
                                           target="_blank" 
                                           class="button button-small">
                                            WhatsApp
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="button button-small delete-custom-feedback-manage-btn" 
                                            data-token="<?php echo esc_attr($request['token']); ?>"
                                            style="color: #dc3232;">
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <style>
        .status-success { color: #46b450; font-weight: bold; }
        .status-warning { color: #f56e28; font-weight: bold; }
        </style>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var input = document.getElementById(targetId);
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                
                var originalText = this.textContent;
                this.textContent = 'Скопировано!';
                this.classList.add('button-primary');
                
                setTimeout(function() {
                    this.textContent = originalText;
                    this.classList.remove('button-primary');
                }.bind(this), 2000);
            });
        });
        
        // Обработчик удаления опроса
        document.querySelectorAll('.delete-custom-feedback-manage-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var token = this.getAttribute('data-token');
                if (!token) {
                    return;
                }
                
                if (!confirm('Удалить опрос полностью? После удаления ссылка снова станет активной. Это действие нельзя отменить.')) {
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'gustolocal_delete_custom_feedback');
                formData.append('token', token);
                formData.append('nonce', '<?php echo esc_js($delete_nonce); ?>');
                
                var btnElement = this;
                btnElement.disabled = true;
                btnElement.textContent = 'Удаление...';
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        // Удаляем строку из таблицы
                        btnElement.closest('tr').remove();
                    } else {
                        alert(data.data || 'Не удалось удалить опрос');
                        btnElement.disabled = false;
                        btnElement.textContent = 'Удалить';
                    }
                })
                .catch(function() {
                    alert('Ошибка при удалении опроса');
                    btnElement.disabled = false;
                    btnElement.textContent = 'Удалить';
                });
            });
        });
    });
    </script>
    <?php
}

// Страница результатов кастомных опросов (аналог "Результаты отзывов")
function gustolocal_custom_feedback_results_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $custom_entries_table = $wpdb->prefix . 'custom_feedback_entries';
    
    // Получаем статистику по блюдам из кастомных опросов
    $dish_stats = $wpdb->get_results("
        SELECT 
            dish_name,
            dish_unit,
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
        FROM $custom_entries_table
        WHERE rating > 0
        GROUP BY dish_name, dish_unit
        ORDER BY avg_rating DESC, total_reviews DESC
    ", ARRAY_A);
    
    // Последние опросы с отзывами
    $custom_requests_table = $wpdb->prefix . 'custom_feedback_requests';
    $recent_feedback = $wpdb->get_results("
        SELECT 
            r.id,
            r.token,
            r.client_name,
            r.client_contact,
            DATE_FORMAT(MAX(r.submitted_at), '%d.%m.%Y %H:%i') as last_date,
            r.general_comment,
            r.shared_instagram,
            r.shared_google,
            COUNT(e.id) as dishes_count,
            ROUND(AVG(e.rating), 2) as avg_rating,
            GROUP_CONCAT(
                CONCAT(
                    e.dish_name,
                    IF(e.dish_unit != '', CONCAT(' (', e.dish_unit, ')'), ''),
                    '::',
                    e.rating
                )
                ORDER BY e.created_at DESC
                SEPARATOR '||'
            ) as dishes_list
        FROM $custom_requests_table r
        INNER JOIN $custom_entries_table e ON e.request_id = r.id
        WHERE r.status = 'submitted' AND e.rating > 0
        GROUP BY r.id, r.token, r.client_name, r.client_contact
        ORDER BY MAX(r.submitted_at) DESC
        LIMIT 50
    ", ARRAY_A);
    
    $delete_nonce = wp_create_nonce('gustolocal_custom_feedback_delete');
    
    ?>
    <div class="wrap">
        <h1>Результаты кастомных опросов</h1>
        
        <h2>Статистика по блюдам</h2>
        <p class="description">Таблица автоматически группирует отзывы по названию блюда и единице измерения. Кликните на строку, чтобы увидеть все отзывы по этому блюду.</p>
        
        <table class="wp-list-table widefat fixed striped" id="custom-feedback-stats-table">
            <thead>
                <tr>
                    <th>Блюдо</th>
                    <th>Отзывов</th>
                    <th>Средняя оценка</th>
                    <th>😍</th>
                    <th>😊</th>
                    <th>😐</th>
                    <th>😞</th>
                    <th style="width: 100px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($dish_stats)): ?>
                    <?php foreach ($dish_stats as $stat): 
                        $avg = round($stat['avg_rating'], 2);
                        $dish_full = $stat['dish_name'] . ($stat['dish_unit'] ? ' (' . $stat['dish_unit'] . ')' : '');
                    ?>
                        <tr data-dish-name="<?php echo esc_attr($stat['dish_name']); ?>" data-dish-unit="<?php echo esc_attr($stat['dish_unit']); ?>">
                            <td><strong><?php echo esc_html($dish_full); ?></strong></td>
                            <td><?php echo esc_html($stat['total_reviews']); ?></td>
                            <td>
                                <strong><?php echo number_format($avg, 2); ?></strong>
                                <span style="font-size: 20px;">
                                    <?php 
                                    if ($avg >= 3.5) echo '😍';
                                    elseif ($avg >= 2.5) echo '😊';
                                    elseif ($avg >= 1.5) echo '😐';
                                    else echo '😞';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($stat['rating_4']); ?></td>
                            <td><?php echo esc_html($stat['rating_3']); ?></td>
                            <td><?php echo esc_html($stat['rating_2']); ?></td>
                            <td><?php echo esc_html($stat['rating_1']); ?></td>
                            <td>
                                <button type="button" class="button button-small view-custom-details-btn" 
                                        data-dish-name="<?php echo esc_attr($stat['dish_name']); ?>" 
                                        data-dish-unit="<?php echo esc_attr($stat['dish_unit']); ?>">
                                    Детали
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Нет отзывов</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2>Последние комментарии и активности</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Клиент</th>
                    <th>Контакт</th>
                    <th>Блюд</th>
                    <th>Средняя</th>
                    <th>Отзывы</th>
                    <th>Комментарий</th>
                    <th>Instagram</th>
                    <th>Google</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_feedback)): ?>
                    <?php foreach ($recent_feedback as $feedback): ?>
                        <tr>
                            <td><?php echo esc_html($feedback['last_date']); ?></td>
                            <td><?php echo esc_html($feedback['client_name']); ?></td>
                            <td><?php echo esc_html($feedback['client_contact'] ?: '—'); ?></td>
                            <td><?php echo esc_html($feedback['dishes_count']); ?></td>
                            <td><?php echo esc_html(number_format((float) $feedback['avg_rating'], 2)); ?></td>
                            <td>
                                <?php
                                if (!empty($feedback['dishes_list'])) {
                                    $items = explode('||', $feedback['dishes_list']);
                                    foreach ($items as $item) {
                                        list($name, $rating) = array_pad(explode('::', $item), 2, '');
                                        $emoji = array('1' => '😞', '2' => '😐', '3' => '😊', '4' => '😍');
                                        echo '<div>' . esc_html($name) . ': ' . ($emoji[$rating] ?? $rating) . '</div>';
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo $feedback['general_comment'] ? nl2br(esc_html($feedback['general_comment'])) : '—'; ?></td>
                            <td><?php echo !empty($feedback['shared_instagram']) ? '✅' : '—'; ?></td>
                            <td><?php echo !empty($feedback['shared_google']) ? '✅' : '—'; ?></td>
                            <td>
                                <button class="button delete-custom-feedback-btn" data-token="<?php echo esc_attr($feedback['token']); ?>">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">Нет комментариев</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .feedback-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    .feedback-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .feedback-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .feedback-modal-close:hover {
        color: #000;
    }
    .feedback-detail-item {
        padding: 15px;
        margin-bottom: 10px;
        background: #f9f9f9;
        border-left: 4px solid #0073aa;
        border-radius: 4px;
    }
    </style>
    
    <div id="custom-feedback-modal" class="feedback-modal">
        <div class="feedback-modal-content">
            <span class="feedback-modal-close">&times;</span>
            <h2 id="custom-modal-dish-name"></h2>
            <div id="custom-modal-feedback-list"></div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('custom-feedback-modal');
        var closeBtn = modal.querySelector('.feedback-modal-close');
        var viewDetailsBtns = document.querySelectorAll('.view-custom-details-btn');
        
        viewDetailsBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var dishName = this.getAttribute('data-dish-name');
                var dishUnit = this.getAttribute('data-dish-unit');
                showCustomFeedbackDetails(dishName, dishUnit);
            });
        });
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        };
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };
        
        function showCustomFeedbackDetails(dishName, dishUnit) {
            document.getElementById('custom-modal-dish-name').textContent = dishName + (dishUnit ? ' (' + dishUnit + ')' : '');
            document.getElementById('custom-modal-feedback-list').innerHTML = '<p>Загрузка...</p>';
            modal.style.display = 'block';
            
            var formData = new FormData();
            formData.append('action', 'get_custom_feedback_details');
            formData.append('dish_name', dishName);
            formData.append('dish_unit', dishUnit);
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    var html = '';
                    if (data.data.length === 0) {
                        html = '<p>Нет детальных отзывов для этого блюда.</p>';
                    } else {
                        data.data.forEach(function(feedback) {
                            var ratingEmoji = {'1': '😞', '2': '😐', '3': '😊', '4': '😍'};
                            html += '<div class="feedback-detail-item">';
                            html += '<div style="display: flex; align-items: center; margin-bottom: 10px;">';
                            html += '<span class="rating" style="font-size: 24px; margin-right: 10px;">' + ratingEmoji[feedback.rating] + '</span>';
                            html += '<strong>' + feedback.client_name + '</strong>';
                            html += '<span style="margin-left: auto; color: #666; font-size: 12px;">' + feedback.date + '</span>';
                            html += '</div>';
                            if (feedback.general_comment) {
                                html += '<p style="margin: 10px 0; padding: 10px; background: white; border-radius: 4px;">' + escapeHtml(feedback.general_comment) + '</p>';
                            }
                            html += '</div>';
                        });
                    }
                    document.getElementById('custom-modal-feedback-list').innerHTML = html;
                } else {
                    document.getElementById('custom-modal-feedback-list').innerHTML = '<p>Ошибка: ' + (data.data || 'Не удалось загрузить отзывы') + '</p>';
                }
            })
            .catch(function(error) {
                document.getElementById('custom-modal-feedback-list').innerHTML = '<p>Ошибка: ' + error + '</p>';
            });
        }
        
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        document.querySelectorAll('.delete-custom-feedback-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var token = this.getAttribute('data-token');
                if (!token) {
                    return;
                }
                
                if (!confirm('Удалить отзыв полностью? После удаления ссылка снова станет активной. Это действие нельзя отменить.')) {
                    return;
                }
                
                var formData = new FormData();
                formData.append('action', 'gustolocal_delete_custom_feedback');
                formData.append('token', token);
                formData.append('nonce', '<?php echo esc_js($delete_nonce); ?>');
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.data || 'Не удалось удалить отзыв');
                    }
                })
                .catch(function() {
                    alert('Ошибка при удалении отзыва');
                });
            });
        });
    });
    </script>
    <?php
}

