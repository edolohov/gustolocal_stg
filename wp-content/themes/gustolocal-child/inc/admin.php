<?php
/**
 * GustoLocal Admin Module
 * 
 * Admin interface for configuration management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if required constants are defined
if (!defined('GUSTOLOCAL_PATH')) {
    return;
}

class GustoLocal_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'GustoLocal Settings',
            'GustoLocal',
            'manage_options',
            'gustolocal-settings',
            array($this, 'admin_page'),
            'dashicons-admin-site',
            30
        );
        
        add_submenu_page(
            'gustolocal-settings',
            'General Settings',
            'General',
            'manage_options',
            'gustolocal-settings',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'gustolocal-settings',
            'WooCommerce Settings',
            'WooCommerce',
            'manage_options',
            'gustolocal-woocommerce',
            array($this, 'woocommerce_admin_page')
        );
        
        add_submenu_page(
            'gustolocal-settings',
            'Multilanguage Settings',
            'Multilanguage',
            'manage_options',
            'gustolocal-multilang',
            array($this, 'multilang_admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('gustolocal_settings', 'gustolocal_config');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gustolocal') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_general_settings();
        }
        
        $config = GustoLocal_Config::get_config();
        ?>
        <div class="wrap">
            <h1>GustoLocal - General Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gustolocal_general_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug[enabled]" value="1" <?php checked($config['debug']['enabled'], true); ?>>
                                Show debug information and log errors
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show Debug Info</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug[show_debug_info]" value="1" <?php checked($config['debug']['show_debug_info'], true); ?>>
                                Display debug information on frontend
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Log Errors</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug[log_errors]" value="1" <?php checked($config['debug']['log_errors'], true); ?>>
                                Log errors to WordPress debug log
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <hr>
            
            <h2>Quick Actions</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=gustolocal-woocommerce'); ?>" class="button">WooCommerce Settings</a>
                <a href="<?php echo admin_url('admin.php?page=gustolocal-multilang'); ?>" class="button">Multilanguage Settings</a>
                <button type="button" class="button" onclick="if(confirm('Reset all settings to defaults?')) { document.getElementById('reset-form').submit(); }">Reset to Defaults</button>
            </p>
            
            <form id="reset-form" method="post" action="" style="display: none;">
                <input type="hidden" name="reset_to_defaults" value="1">
                <?php wp_nonce_field('gustolocal_reset_defaults'); ?>
            </form>
        </div>
        <?php
    }
    
    public function woocommerce_admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_woocommerce_settings();
        }
        
        $config = GustoLocal_Config::get_config();
        ?>
        <div class="wrap">
            <h1>GustoLocal - WooCommerce Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gustolocal_woocommerce_settings'); ?>
                
                <h2>Minimum Order Amount</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Minimum Order</th>
                        <td>
                            <label>
                                <input type="checkbox" name="woocommerce[minimum_order_amount][enabled]" value="1" <?php checked($config['woocommerce']['minimum_order_amount']['enabled'], true); ?>>
                                Require minimum order amount
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Minimum Amount</th>
                        <td>
                            <input type="number" step="0.01" name="woocommerce[minimum_order_amount][amount]" value="<?php echo esc_attr($config['woocommerce']['minimum_order_amount']['amount']); ?>" class="regular-text">
                            <select name="woocommerce[minimum_order_amount][currency]">
                                <option value="EUR" <?php selected($config['woocommerce']['minimum_order_amount']['currency'], 'EUR'); ?>>EUR</option>
                                <option value="USD" <?php selected($config['woocommerce']['minimum_order_amount']['currency'], 'USD'); ?>>USD</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message</th>
                        <td>
                            <input type="text" name="woocommerce[minimum_order_amount][message]" value="<?php echo esc_attr($config['woocommerce']['minimum_order_amount']['message']); ?>" class="large-text">
                            <p class="description">Use {amount} and {currency} as placeholders</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Delivery Fee</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Delivery Fee</th>
                        <td>
                            <label>
                                <input type="checkbox" name="woocommerce[delivery_fee][enabled]" value="1" <?php checked($config['woocommerce']['delivery_fee']['enabled'], true); ?>>
                                Add delivery fee to orders
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Delivery Amount</th>
                        <td>
                            <input type="number" step="0.01" name="woocommerce[delivery_fee][amount]" value="<?php echo esc_attr($config['woocommerce']['delivery_fee']['amount']); ?>" class="regular-text">
                            <select name="woocommerce[delivery_fee][currency]">
                                <option value="EUR" <?php selected($config['woocommerce']['delivery_fee']['currency'], 'EUR'); ?>>EUR</option>
                                <option value="USD" <?php selected($config['woocommerce']['delivery_fee']['currency'], 'USD'); ?>>USD</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Free Delivery Threshold</th>
                        <td>
                            <input type="number" step="0.01" name="woocommerce[delivery_fee][free_delivery_threshold]" value="<?php echo esc_attr($config['woocommerce']['delivery_fee']['free_delivery_threshold']); ?>" class="regular-text">
                            <p class="description">Set to 0 for always paid delivery</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Cart Management</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Clear Cart After Order</th>
                        <td>
                            <label>
                                <input type="checkbox" name="woocommerce[cart_management][clear_cart_after_order]" value="1" <?php checked($config['woocommerce']['cart_management']['clear_cart_after_order'], true); ?>>
                                Empty cart after successful order
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clear Cart On Login</th>
                        <td>
                            <label>
                                <input type="checkbox" name="woocommerce[cart_management][clear_cart_on_login]" value="1" <?php checked($config['woocommerce']['cart_management']['clear_cart_on_login'], true); ?>>
                                Empty cart when user logs in
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Persistent Cart</th>
                        <td>
                            <label>
                                <input type="checkbox" name="woocommerce[cart_management][persistent_cart]" value="1" <?php checked($config['woocommerce']['cart_management']['persistent_cart'], true); ?>>
                                Save cart between sessions
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save WooCommerce Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    public function multilang_admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_multilang_settings();
        }
        
        $config = GustoLocal_Config::get_config();
        ?>
        <div class="wrap">
            <h1>GustoLocal - Multilanguage Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('gustolocal_multilang_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Multilanguage</th>
                        <td>
                            <label>
                                <input type="checkbox" name="multilang[enabled]" value="1" <?php checked($config['multilang']['enabled'], true); ?>>
                                Enable multilanguage support
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Language</th>
                        <td>
                            <select name="multilang[default_language]">
                                <option value="ru" <?php selected($config['multilang']['default_language'], 'ru'); ?>>Russian</option>
                                <option value="es" <?php selected($config['multilang']['default_language'], 'es'); ?>>Spanish</option>
                                <option value="en" <?php selected($config['multilang']['default_language'], 'en'); ?>>English</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Supported Languages</th>
                        <td>
                            <label><input type="checkbox" name="multilang[supported_languages][]" value="ru" <?php checked(in_array('ru', $config['multilang']['supported_languages'])); ?>> Russian</label><br>
                            <label><input type="checkbox" name="multilang[supported_languages][]" value="es" <?php checked(in_array('es', $config['multilang']['supported_languages'])); ?>> Spanish</label><br>
                            <label><input type="checkbox" name="multilang[supported_languages][]" value="en" <?php checked(in_array('en', $config['multilang']['supported_languages'])); ?>> English</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-detect Browser Language</th>
                        <td>
                            <label>
                                <input type="checkbox" name="multilang[auto_detect_browser]" value="1" <?php checked($config['multilang']['auto_detect_browser'], true); ?>>
                                Automatically redirect to browser language on first visit
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Show Language Switcher</th>
                        <td>
                            <label>
                                <input type="checkbox" name="multilang[show_language_switcher]" value="1" <?php checked($config['multilang']['show_language_switcher'], true); ?>>
                                Display language switcher on frontend
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Persist Language Choice</th>
                        <td>
                            <label>
                                <input type="checkbox" name="multilang[persist_language_choice]" value="1" <?php checked($config['multilang']['persist_language_choice'], true); ?>>
                                Remember user's language choice
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Multilanguage Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    private function save_general_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gustolocal_general_settings')) {
            return;
        }
        
        $config = GustoLocal_Config::get_config();
        
        $config['debug']['enabled'] = isset($_POST['debug']['enabled']);
        $config['debug']['show_debug_info'] = isset($_POST['debug']['show_debug_info']);
        $config['debug']['log_errors'] = isset($_POST['debug']['log_errors']);
        
        GustoLocal_Config::save_config($config);
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    private function save_woocommerce_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gustolocal_woocommerce_settings')) {
            return;
        }
        
        $config = GustoLocal_Config::get_config();
        
        // Minimum order amount
        $config['woocommerce']['minimum_order_amount']['enabled'] = isset($_POST['woocommerce']['minimum_order_amount']['enabled']);
        $config['woocommerce']['minimum_order_amount']['amount'] = floatval($_POST['woocommerce']['minimum_order_amount']['amount']);
        $config['woocommerce']['minimum_order_amount']['currency'] = sanitize_text_field($_POST['woocommerce']['minimum_order_amount']['currency']);
        $config['woocommerce']['minimum_order_amount']['message'] = sanitize_text_field($_POST['woocommerce']['minimum_order_amount']['message']);
        
        // Delivery fee
        $config['woocommerce']['delivery_fee']['enabled'] = isset($_POST['woocommerce']['delivery_fee']['enabled']);
        $config['woocommerce']['delivery_fee']['amount'] = floatval($_POST['woocommerce']['delivery_fee']['amount']);
        $config['woocommerce']['delivery_fee']['currency'] = sanitize_text_field($_POST['woocommerce']['delivery_fee']['currency']);
        $config['woocommerce']['delivery_fee']['free_delivery_threshold'] = floatval($_POST['woocommerce']['delivery_fee']['free_delivery_threshold']);
        
        // Cart management
        $config['woocommerce']['cart_management']['clear_cart_after_order'] = isset($_POST['woocommerce']['cart_management']['clear_cart_after_order']);
        $config['woocommerce']['cart_management']['clear_cart_on_login'] = isset($_POST['woocommerce']['cart_management']['clear_cart_on_login']);
        $config['woocommerce']['cart_management']['persistent_cart'] = isset($_POST['woocommerce']['cart_management']['persistent_cart']);
        
        GustoLocal_Config::save_config($config);
        
        echo '<div class="notice notice-success"><p>WooCommerce settings saved successfully!</p></div>';
    }
    
    private function save_multilang_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gustolocal_multilang_settings')) {
            return;
        }
        
        $config = GustoLocal_Config::get_config();
        
        $config['multilang']['enabled'] = isset($_POST['multilang']['enabled']);
        $config['multilang']['default_language'] = sanitize_text_field($_POST['multilang']['default_language']);
        $config['multilang']['supported_languages'] = isset($_POST['multilang']['supported_languages']) ? $_POST['multilang']['supported_languages'] : array('ru');
        $config['multilang']['auto_detect_browser'] = isset($_POST['multilang']['auto_detect_browser']);
        $config['multilang']['show_language_switcher'] = isset($_POST['multilang']['show_language_switcher']);
        $config['multilang']['persist_language_choice'] = isset($_POST['multilang']['persist_language_choice']);
        
        GustoLocal_Config::save_config($config);
        
        echo '<div class="notice notice-success"><p>Multilanguage settings saved successfully!</p></div>';
    }
}

// Initialize admin module (called from main functions.php)
// new GustoLocal_Admin();
