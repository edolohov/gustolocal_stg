<?php
/**
 * GustoLocal WooCommerce Module
 * 
 * Handles all WooCommerce customizations with configurable flags
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if required constants are defined
if (!defined('GUSTOLOCAL_PATH')) {
    return;
}

class GustoLocal_WooCommerce {
    
    public function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        if (!gustolocal_is_enabled('woocommerce')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Styling hooks
        if (gustolocal_wc_setting('styling.custom_cart_styles', true)) {
            add_action('wp_head', array($this, 'add_cart_styling'));
        }
        
        if (gustolocal_wc_setting('styling.custom_checkout_styles', true)) {
            add_action('wp_head', array($this, 'add_checkout_styling'));
        }
        
        // Minimum order amount
        if (gustolocal_wc_setting('minimum_order_amount.enabled', false)) {
            add_action('woocommerce_checkout_process', array($this, 'enforce_minimum_order_amount'));
            add_action('woocommerce_before_cart', array($this, 'show_minimum_order_notice'));
        }
        
        // Delivery fee
        if (gustolocal_wc_setting('delivery_fee.enabled', true)) {
            add_action('woocommerce_cart_calculate_fees', array($this, 'add_delivery_fee'));
        }
        
        // Cart management
        if (gustolocal_wc_setting('cart_management.clear_cart_after_order', true)) {
            add_action('woocommerce_thankyou', array($this, 'clear_cart_after_order'));
        }
        
        if (gustolocal_wc_setting('cart_management.clear_cart_on_login', true)) {
            add_action('wp_login', array($this, 'clear_cart_on_login'));
        }
        
        if (!gustolocal_wc_setting('cart_management.persistent_cart', false)) {
            add_filter('woocommerce_persistent_cart_enabled', '__return_false');
        }
        
        // Other hooks
        add_action('woocommerce_before_cart', array($this, 'remove_weekly_meal_plan_links'));
        add_action('wp_footer', array($this, 'prevent_weekly_meal_plan_clicks'));
        add_action('wp_footer', array($this, 'woocommerce_js_init'));
        
        // AJAX handlers
        add_action('wp_ajax_update_delivery_type', array($this, 'update_delivery_type'));
        add_action('wp_ajax_nopriv_update_delivery_type', array($this, 'update_delivery_type'));
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_cart_styling() {
        if (!is_cart()) {
            return;
        }
        
        $mobile_optimization = gustolocal_wc_setting('styling.mobile_optimization', true);
        ?>
        <style>
        .woocommerce-cart-form, .woocommerce-checkout-form {
            max-width: 100%;
        }
        .woocommerce table.shop_table {
            border-collapse: collapse;
            width: 100%;
        }
        .woocommerce table.shop_table th,
        .woocommerce table.shop_table td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        <?php if ($mobile_optimization): ?>
        @media (max-width: 768px) {
            .woocommerce table.shop_table {
                font-size: 14px;
            }
            .woocommerce table.shop_table th,
            .woocommerce table.shop_table td {
                padding: 8px;
            }
        }
        <?php endif; ?>
        </style>
        <?php
    }
    
    public function add_checkout_styling() {
        if (!is_checkout()) {
            return;
        }
        
        $mobile_optimization = gustolocal_wc_setting('styling.mobile_optimization', true);
        ?>
        <style>
        .woocommerce-checkout-form {
            max-width: 100%;
        }
        .woocommerce form.checkout {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .woocommerce form.checkout .col2-set {
            flex: 1;
            min-width: 300px;
        }
        .woocommerce form.checkout .woocommerce-checkout-review-order {
            flex: 1;
            min-width: 300px;
        }
        <?php if ($mobile_optimization): ?>
        @media (max-width: 768px) {
            .woocommerce form.checkout {
                flex-direction: column;
            }
            .woocommerce form.checkout .col2-set,
            .woocommerce form.checkout .woocommerce-checkout-review-order {
                min-width: auto;
            }
        }
        <?php endif; ?>
        </style>
        <?php
    }
    
    public function enforce_minimum_order_amount() {
        $minimum_amount = gustolocal_wc_setting('minimum_order_amount.amount', 60.00);
        $currency = gustolocal_wc_setting('minimum_order_amount.currency', 'EUR');
        $message = gustolocal_wc_setting('minimum_order_amount.message', 'Минимальная сумма заказа: {amount} {currency}');
        
        $cart_total = WC()->cart->get_cart_contents_total();
        
        if ($cart_total < $minimum_amount) {
            $formatted_message = str_replace(
                array('{amount}', '{currency}'),
                array($minimum_amount, $currency),
                $message
            );
            
            wc_add_notice($formatted_message, 'error');
        }
    }
    
    public function show_minimum_order_notice() {
        $minimum_amount = gustolocal_wc_setting('minimum_order_amount.amount', 60.00);
        $currency = gustolocal_wc_setting('minimum_order_amount.currency', 'EUR');
        $message = gustolocal_wc_setting('minimum_order_amount.message', 'Минимальная сумма заказа: {amount} {currency}');
        
        $cart_total = WC()->cart->get_cart_contents_total();
        
        if ($cart_total < $minimum_amount) {
            $formatted_message = str_replace(
                array('{amount}', '{currency}'),
                array($minimum_amount, $currency),
                $message
            );
            
            echo '<div class="woocommerce-info">' . esc_html($formatted_message) . '</div>';
        }
    }
    
    public function add_delivery_fee() {
        $delivery_amount = gustolocal_wc_setting('delivery_fee.amount', 3.00);
        $free_threshold = gustolocal_wc_setting('delivery_fee.free_delivery_threshold', 0);
        
        $cart_total = WC()->cart->get_cart_contents_total();
        
        // Check if delivery fee should be applied
        if ($free_threshold > 0 && $cart_total >= $free_threshold) {
            return; // Free delivery
        }
        
        // Check if delivery type is set to delivery (not pickup)
        $delivery_type = WC()->session->get('delivery_type');
        if ($delivery_type === 'pickup') {
            return; // No delivery fee for pickup
        }
        
        WC()->cart->add_fee(__('Доставка', 'gustolocal'), $delivery_amount);
    }
    
    public function clear_cart_after_order($order_id) {
        WC()->cart->empty_cart();
    }
    
    public function clear_cart_on_login($user_login) {
        WC()->cart->empty_cart();
    }
    
    public function remove_weekly_meal_plan_links() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var links = document.querySelectorAll('a[href*="weekly-meal-plan"], a[href*="product/weekly-meal-plan"]');
            
            links.forEach(function(link) {
                link.removeAttribute('href');
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
                link.style.cursor = 'default';
                link.style.textDecoration = 'none';
            });
        });
        </script>
        <?php
    }
    
    public function prevent_weekly_meal_plan_clicks() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var links = document.querySelectorAll('a[href*="weekly-meal-plan"], a[href*="product/weekly-meal-plan"]');
            
            links.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
            });
        });
        </script>
        <?php
    }
    
    public function woocommerce_js_init() {
        ?>
        <script>
        (function () {
            var c = document.body.className;
            c = c.replace(/woocommerce-no-js/, 'woocommerce-js');
            document.body.className = c;
        })();
        </script>
        <?php
    }
    
    public function update_delivery_type() {
        check_ajax_referer('gustolocal_delivery', 'nonce');
        
        $delivery_type = sanitize_text_field($_POST['delivery_type']);
        
        if (in_array($delivery_type, array('delivery', 'pickup'))) {
            WC()->session->set('delivery_type', $delivery_type);
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }
    
    public function enqueue_scripts() {
        if (is_cart() || is_checkout()) {
            wp_enqueue_script(
                'gustolocal-delivery-options',
                GUSTOLOCAL_URL . '/assets/js/delivery-options.js',
                array('jquery'),
                GUSTOLOCAL_VERSION,
                true
            );
            
            wp_localize_script('gustolocal-delivery-options', 'gustolocal_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gustolocal_delivery'),
            ));
        }
    }
}

// Initialize WooCommerce module (called from main functions.php)
// new GustoLocal_WooCommerce();
