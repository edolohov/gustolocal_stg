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

/* ============ WooCommerce упрощенная форма оформления ============ */
// Упрощаем форму оформления заказа - максимально упрощенная форма как на оригинале
add_filter('woocommerce_checkout_fields', 'gustolocal_simplify_checkout_fields');
function gustolocal_simplify_checkout_fields($fields) {
    // Полностью скрываем shipping поля
    unset($fields['shipping']);
    
    // Упрощаем billing поля - оставляем только самое необходимое
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_country']); // По умолчанию Испания
    unset($fields['billing']['billing_state']); // По умолчанию Валенсия
    unset($fields['billing']['billing_postcode']); // Не критично для доставки
    
    // Переименовываем поля для простоты
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['label'] = 'Ваше имя';
        $fields['billing']['billing_first_name']['placeholder'] = '';
    }
    
    if (isset($fields['billing']['billing_last_name'])) {
        $fields['billing']['billing_last_name']['label'] = 'и фамилия';
        $fields['billing']['billing_last_name']['placeholder'] = '';
    }
    
    if (isset($fields['billing']['billing_address_1'])) {
        $fields['billing']['billing_address_1']['label'] = 'Ваш адрес';
        $fields['billing']['billing_address_1']['placeholder'] = '';
    }
    
    if (isset($fields['billing']['billing_address_2'])) {
        $fields['billing']['billing_address_2']['required'] = false;
        $fields['billing']['billing_address_2']['label'] = 'Как к вам попасть (необязательно)';
        $fields['billing']['billing_address_2']['placeholder'] = 'Укажите домофон, этаж и квартиру';
    }
    
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['label'] = 'Email (необязательно)';
    }
    
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['label'] = 'Как с вами связаться';
        $fields['billing']['billing_phone']['placeholder'] = 'телеграм, whatsApp, телефон или факс';
    }
    
    // Скрываем город - не нужен для доставки в Валенсии
    unset($fields['billing']['billing_city']);
    
    return $fields;
}

// Устанавливаем значения по умолчанию для скрытых полей
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

/* ============ WooCommerce опции доставки ============ */
// Обработка изменения типа доставки через AJAX
add_action('wp_ajax_update_delivery_type', 'gustolocal_update_delivery_type');
add_action('wp_ajax_nopriv_update_delivery_type', 'gustolocal_update_delivery_type');
function gustolocal_update_delivery_type() {
    check_ajax_referer('gustolocal_delivery', 'nonce');
    
    $delivery_type = sanitize_text_field($_POST['delivery_type']);
    
    if (in_array($delivery_type, array('delivery', 'pickup'))) {
        WC()->session->set('delivery_type', $delivery_type);
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
    
    if ($delivery_type === 'delivery') {
        WC()->cart->add_fee(__('Доставка', 'woocommerce'), 10.00);
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
        'Delivery' => 'Доставка',
    );
    
    if (isset($translations[$text])) {
        return $translations[$text];
    }
    
    return $translated_text;
}
