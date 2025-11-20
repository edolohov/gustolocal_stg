<?php
/**
 * GustoLocal Child Theme Functions
 * 
 * Professional architecture with modular code and configuration flags
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define theme constants
define('GUSTOLOCAL_VERSION', '1.0.0');
define('GUSTOLOCAL_PATH', get_stylesheet_directory());
define('GUSTOLOCAL_URL', get_stylesheet_directory_uri());

// Initialize theme
add_action('after_setup_theme', 'gustolocal_init');
function gustolocal_init() {
    // Load text domain for translations
    load_child_theme_textdomain('gustolocal', GUSTOLOCAL_PATH . '/languages');
    
    // Add theme support
    add_theme_support('menus');
    add_theme_support('post-thumbnails');
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'gustolocal'),
    ));
}

// Load configuration first
if (file_exists(GUSTOLOCAL_PATH . '/inc/config.php')) {
    require_once GUSTOLOCAL_PATH . '/inc/config.php';
} else {
    // Fallback if config doesn't exist
    function gustolocal_is_enabled($feature) {
        return true; // Enable all features by default
    }
}

// Load modules conditionally
add_action('init', 'gustolocal_load_modules', 1);
function gustolocal_load_modules() {
    // Load multilang module
    if (file_exists(GUSTOLOCAL_PATH . '/inc/multilang.php')) {
        require_once GUSTOLOCAL_PATH . '/inc/multilang.php';
        if (class_exists('GustoLocal_Multilang')) {
            new GustoLocal_Multilang();
        }
    }
    
    // Load WooCommerce module
    if (file_exists(GUSTOLOCAL_PATH . '/inc/woocommerce.php')) {
        require_once GUSTOLOCAL_PATH . '/inc/woocommerce.php';
        if (class_exists('GustoLocal_WooCommerce')) {
            new GustoLocal_WooCommerce();
        }
    }
    
    // Load Meal Builder module
    if (file_exists(GUSTOLOCAL_PATH . '/inc/meal-builder.php')) {
        require_once GUSTOLOCAL_PATH . '/inc/meal-builder.php';
        if (class_exists('GustoLocal_MealBuilder')) {
            new GustoLocal_MealBuilder();
        }
    }
    
    // Load admin module
    if (file_exists(GUSTOLOCAL_PATH . '/inc/admin.php')) {
        require_once GUSTOLOCAL_PATH . '/inc/admin.php';
        if (class_exists('GustoLocal_Admin')) {
            new GustoLocal_Admin();
        }
    }
}

// Add admin notice for successful loading
add_action('admin_notices', 'gustolocal_admin_notice');
function gustolocal_admin_notice() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>GustoLocal Child Theme</strong> ✅</p>';
        echo '<p>Child theme loaded successfully with modular architecture!</p>';
        echo '</div>';
    }
}