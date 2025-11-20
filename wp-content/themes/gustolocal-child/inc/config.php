<?php
/**
 * GustoLocal Configuration System
 * 
 * Centralized configuration with flags and settings
 * Easy to modify without touching code
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Configuration Class
 */
class GustoLocal_Config {
    
    private static $config = null;
    
    /**
     * Get configuration array
     */
    public static function get_config() {
        if (self::$config === null) {
            self::$config = self::load_config();
        }
        return self::$config;
    }
    
    /**
     * Get specific configuration value
     */
    public static function get($key, $default = null) {
        $config = self::get_config();
        return isset($config[$key]) ? $config[$key] : $default;
    }
    
    /**
     * Load configuration from database or defaults
     */
    private static function load_config() {
        // Try to load from database first
        $saved_config = get_option('gustolocal_config', null);
        
        if ($saved_config !== null) {
            return array_merge(self::get_default_config(), $saved_config);
        }
        
        // Return default configuration
        return self::get_default_config();
    }
    
    /**
     * Default configuration
     */
    private static function get_default_config() {
        return array(
            // === MULTILANGUAGE SETTINGS ===
            'multilang' => array(
                'enabled' => true,
                'default_language' => 'ru',
                'supported_languages' => array('ru', 'es', 'en'),
                'auto_detect_browser' => true,
                'show_language_switcher' => true,
                'persist_language_choice' => true,
            ),
            
            // === WOOCOMMERCE SETTINGS ===
            'woocommerce' => array(
                'enabled' => true,
                'minimum_order_amount' => array(
                    'enabled' => false,  // ← ФЛАГ: включить минимальный заказ
                    'amount' => 60.00,
                    'currency' => 'EUR',
                    'message' => 'Минимальная сумма заказа: {amount} {currency}',
                ),
                'delivery_fee' => array(
                    'enabled' => true,   // ← ФЛАГ: включить плату за доставку
                    'amount' => 3.00,
                    'currency' => 'EUR',
                    'free_delivery_threshold' => 0, // 0 = всегда платная доставка
                ),
                'cart_management' => array(
                    'clear_cart_after_order' => true,    // ← ФЛАГ: очищать корзину после заказа
                    'clear_cart_on_login' => true,       // ← ФЛАГ: очищать корзину при входе
                    'persistent_cart' => false,          // ← ФЛАГ: сохранять корзину между сессиями
                ),
                'styling' => array(
                    'custom_cart_styles' => true,        // ← ФЛАГ: кастомные стили корзины
                    'custom_checkout_styles' => true,    // ← ФЛАГ: кастомные стили чекаута
                    'mobile_optimization' => true,       // ← ФЛАГ: оптимизация для мобильных
                ),
            ),
            
            // === MEAL BUILDER SETTINGS ===
            'meal_builder' => array(
                'enabled' => true,
                'multilang_support' => true,             // ← ФЛАГ: поддержка многоязычности
                'separate_pages_per_language' => true,   // ← ФЛАГ: отдельные страницы для каждого языка
                'auto_translate_interface' => true,      // ← ФЛАГ: автоматический перевод интерфейса
            ),
            
            // === DEBUG SETTINGS ===
            'debug' => array(
                'enabled' => false,                      // ← ФЛАГ: включить отладку
                'show_debug_info' => false,              // ← ФЛАГ: показывать отладочную информацию
                'log_errors' => true,                    // ← ФЛАГ: логировать ошибки
            ),
            
            // === PERFORMANCE SETTINGS ===
            'performance' => array(
                'minify_css' => false,                   // ← ФЛАГ: минифицировать CSS
                'minify_js' => false,                    // ← ФЛАГ: минифицировать JavaScript
                'lazy_load_images' => false,             // ← ФЛАГ: ленивая загрузка изображений
                'cache_translations' => true,            // ← ФЛАГ: кэшировать переводы
            ),
        );
    }
    
    /**
     * Save configuration to database
     */
    public static function save_config($config) {
        update_option('gustolocal_config', $config);
        self::$config = null; // Reset cache
    }
    
    /**
     * Reset to default configuration
     */
    public static function reset_to_defaults() {
        delete_option('gustolocal_config');
        self::$config = null; // Reset cache
    }
    
    /**
     * Update specific configuration section
     */
    public static function update_section($section, $values) {
        $config = self::get_config();
        $config[$section] = array_merge($config[$section], $values);
        self::save_config($config);
    }
}

/**
 * Helper functions for easy access
 */

// Get configuration value
function gustolocal_config($key, $default = null) {
    return GustoLocal_Config::get($key, $default);
}

// Check if feature is enabled
function gustolocal_is_enabled($feature) {
    return gustolocal_config($feature . '.enabled', false);
}

// Get WooCommerce setting
function gustolocal_wc_setting($key, $default = null) {
    return gustolocal_config('woocommerce.' . $key, $default);
}

// Get multilang setting
function gustolocal_ml_setting($key, $default = null) {
    return gustolocal_config('multilang.' . $key, $default);
}

// Get meal builder setting
function gustolocal_mb_setting($key, $default = null) {
    return gustolocal_config('meal_builder.' . $key, $default);
}

// Check if debug is enabled
function gustolocal_debug_enabled() {
    return gustolocal_config('debug.enabled', false);
}

// Log debug message
function gustolocal_debug_log($message, $context = array()) {
    if (gustolocal_debug_enabled()) {
        error_log('[GustoLocal Debug] ' . $message . ' ' . json_encode($context));
    }
}
