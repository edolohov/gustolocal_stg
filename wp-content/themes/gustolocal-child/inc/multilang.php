<?php
/**
 * GustoLocal Multilanguage Module
 * 
 * Handles all multilanguage functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load translations from parent theme (avoid conflicts)
if (file_exists(get_template_directory() . '/translations.php')) {
    require_once get_template_directory() . '/translations.php';
}

class GustoLocal_Multilang {
    
    public function __construct() {
        if (!gustolocal_is_enabled('multilang')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'setup_routing'));
        add_action('parse_request', array($this, 'handle_language_urls'), 1);
        add_action('pre_get_posts', array($this, 'page_lookup'));
        add_action('wp_head', array($this, 'add_language_switcher'));
        add_action('wp_footer', array($this, 'add_language_script'));
        add_action('template_redirect', array($this, 'language_redirect'));
        
        // Translation hooks
        add_filter('gettext', array($this, 'translate_woocommerce_strings'), 20, 3);
        add_filter('woocommerce_checkout_fields', array($this, 'translate_checkout_fields'));
        add_filter('woocommerce_add_to_cart_message', array($this, 'translate_add_to_cart_message'));
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'translate_add_to_cart_button'));
        add_filter('woocommerce_page_title', array($this, 'translate_woocommerce_page_title'));
        add_filter('woocommerce_form_field', array($this, 'translate_custom_form_fields'), 10, 4);
        add_filter('woocommerce_shipping_method_title', array($this, 'translate_shipping_methods'));
        add_filter('woocommerce_cart_totals_order_total_html', array($this, 'translate_cart_totals'));
        add_filter('gettext', array($this, 'translate_remaining_strings'), 25, 3);
        add_filter('the_content', array($this, 'translate_woocommerce_page_content'));
        add_filter('the_content', array($this, 'translate_404_page_content'));
        add_filter('wp_title', array($this, 'translate_page_title'));
        add_filter('woocommerce_thankyou_order_received_text', array($this, 'translate_thankyou_order_received_text'), 10, 2);
        add_action('wp_footer', array($this, 'translate_404_with_javascript'));
    }
    
    public function setup_routing() {
        // Add rewrite rules
        $languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
        
        foreach ($languages as $lang) {
            if ($lang === 'ru') continue; // Skip default language
            
            add_rewrite_rule('^' . $lang . '/(.*)/?', 'index.php?lang=' . $lang . '&pagename=$matches[1]', 'top');
            add_rewrite_rule('^' . $lang . '/?$', 'index.php?lang=' . $lang, 'top');
            
            // WooCommerce specific rules
            add_rewrite_rule('^' . $lang . '/checkout/(.*)/?', 'index.php?lang=' . $lang . '&pagename=checkout/$matches[1]', 'top');
            add_rewrite_rule('^' . $lang . '/cart/?', 'index.php?lang=' . $lang . '&pagename=cart', 'top');
            add_rewrite_rule('^' . $lang . '/my-account/(.*)/?', 'index.php?lang=' . $lang . '&pagename=my-account/$matches[1]', 'top');
        }
        
        add_filter('query_vars', array($this, 'add_language_query_vars'));
        
        // Force flush rewrite rules once
        if (get_option('gustolocal_rewrite_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('gustolocal_rewrite_flushed', '1');
        }
    }
    
    public function add_language_query_vars($vars) {
        $vars[] = 'lang';
        $vars[] = 'order-received';
        return $vars;
    }
    
    public function handle_language_urls($wp) {
        if (!gustolocal_debug_enabled()) {
            return;
        }
        
        $request = $wp->request;
        gustolocal_debug_log('Handling language URL', array('request' => $request));
        
        if (preg_match('/^(es|en)\/(.*)/', $request, $matches)) {
            $lang = $matches[1];
            $path = $matches[2];
            
            if (strpos($path, 'checkout/order-received') !== false) {
                $wp->query_vars['lang'] = $lang;
                $wp->query_vars['pagename'] = 'checkout';
                
                if (preg_match('/order-received\/(\d+)/', $path, $order_matches)) {
                    $wp->query_vars['order-received'] = $order_matches[1];
                }
                
                $wp->query_vars['woocommerce'] = true;
                $wp->query_vars['checkout'] = true;
            }
        }
    }
    
    public function page_lookup($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        $lang = get_query_var('lang');
        $supported_languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
        
        if (!in_array($lang, $supported_languages)) {
            return;
        }
        
        $pagename = get_query_var('pagename');
        
        // Handle WooCommerce pages
        if (strpos($pagename, 'checkout') !== false ||
            strpos($pagename, 'cart') !== false ||
            strpos($pagename, 'my-account') !== false) {
            
            if (strpos($pagename, 'checkout/order-received') !== false) {
                $clean_path = str_replace('checkout/', '', $pagename);
                $query->set('pagename', 'checkout');
                $query->set('lang', $lang);
                
                if (preg_match('/order-received\/(\d+)/', $clean_path, $matches)) {
                    $query->set('order-received', $matches[1]);
                }
            }
            return;
        }
        
        // Handle homepage
        if (empty($pagename)) {
            $homepage_id = get_option('page_on_front');
            if ($homepage_id) {
                $homepage = get_post($homepage_id);
                if ($homepage) {
                    $lang_slug = $homepage->post_name . '-' . $lang;
                    $lang_page = get_page_by_path($lang_slug);
                    
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
        
        // Handle regular pages
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
    
    public function get_current_language() {
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
    
    public function add_language_switcher() {
        if (!gustolocal_ml_setting('show_language_switcher', true)) {
            return;
        }
        
        $current_lang = $this->get_current_language();
        $current_url = home_url($_SERVER['REQUEST_URI']);
        
        // Remove current language prefix
        $base_url = $current_url;
        $supported_languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
        
        foreach ($supported_languages as $lang) {
            if ($lang === 'ru') continue;
            $base_url = str_replace('/' . $lang . '/', '/', $base_url);
            $base_url = str_replace('/' . $lang, '', $base_url);
        }
        
        // Create URLs for each language
        $urls = array();
        $urls['ru'] = $base_url;
        
        foreach ($supported_languages as $lang) {
            if ($lang === 'ru') continue;
            $urls[$lang] = str_replace(home_url(), home_url() . '/' . $lang, $base_url);
            
            if (rtrim($base_url, '/') === rtrim(home_url(), '/')) {
                $urls[$lang] = home_url('/' . $lang . '/');
            }
        }
        
        ?>
        <div id="language-switcher" style="position: fixed; top: 20px; right: 20px; background: white; border: 2px solid #007cba; border-radius: 8px; padding: 12px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-family: Arial, sans-serif; font-size: 14px;">
            <div style="margin-bottom: 8px; font-weight: bold; color: #333;">Язык:</div>
            <div style="display: flex; gap: 8px;">
                <?php foreach ($urls as $lang => $url): ?>
                    <a href="<?php echo esc_url($url); ?>" style="display: inline-block; padding: 6px 12px; background: <?php echo $current_lang === $lang ? '#007cba' : '#f0f0f0'; ?>; color: <?php echo $current_lang === $lang ? 'white' : '#333'; ?>; text-decoration: none; border-radius: 4px; font-weight: bold; transition: all 0.2s;"><?php echo strtoupper($lang); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    public function add_language_script() {
        if (!gustolocal_ml_setting('persist_language_choice', true)) {
            return;
        }
        
        $auto_detect = gustolocal_ml_setting('auto_detect_browser', true);
        $supported_languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
        ?>
        <script>
        (function() {
            var supportedLangs = <?php echo json_encode($supported_languages); ?>;
            var autoDetect = <?php echo $auto_detect ? 'true' : 'false'; ?>;
            
            function setCookie(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }
            
            function getBrowserLanguage() {
                var lang = navigator.language || navigator.userLanguage;
                if (lang.startsWith('es')) return 'es';
                if (lang.startsWith('en')) return 'en';
                return 'ru';
            }
            
            function getCurrentLangFromUrl(url) {
                for (var i = 0; i < supportedLangs.length; i++) {
                    var lang = supportedLangs[i];
                    if (lang !== 'ru' && url.includes('/' + lang + '/')) {
                        return lang;
                    }
                }
                return 'ru';
            }
            
            function switchLanguageInUrl(url, targetLang) {
                var baseUrl = url;
                
                for (var i = 0; i < supportedLangs.length; i++) {
                    var lang = supportedLangs[i];
                    if (lang !== 'ru') {
                        baseUrl = baseUrl.replace(new RegExp('/' + lang + '/', 'g'), '/');
                        baseUrl = baseUrl.replace(new RegExp('/' + lang + '$'), '');
                    }
                }
                
                if (targetLang === 'ru') {
                    return baseUrl;
                } else {
                    var domain = baseUrl.split('/')[0] + '//' + baseUrl.split('/')[2];
                    var path = baseUrl.replace(domain, '');
                    return domain + '/' + targetLang + path;
                }
            }
            
            var savedLang = localStorage.getItem('user_language');
            var currentUrl = window.location.href;
            var currentLang = getCurrentLangFromUrl(currentUrl);
            
            if (!savedLang && autoDetect) {
                var browserLang = getBrowserLanguage();
                if (browserLang && browserLang !== 'ru') {
                    localStorage.setItem('user_language', browserLang);
                    setCookie('user_language', browserLang, 365);
                    var newUrl = switchLanguageInUrl(currentUrl, browserLang);
                    if (newUrl !== currentUrl) {
                        window.location.href = newUrl;
                        return;
                    }
                } else {
                    localStorage.setItem('user_language', 'ru');
                    setCookie('user_language', 'ru', 365);
                }
            }
            
            if (savedLang && savedLang !== currentLang) {
                var newUrl = switchLanguageInUrl(currentUrl, savedLang);
                if (newUrl !== currentUrl) {
                    window.location.href = newUrl;
                    return;
                }
            }
        })();
        </script>
        <?php
    }
    
    public function language_redirect() {
        if (!gustolocal_ml_setting('persist_language_choice', true)) {
            return;
        }
        
        if (isset($_COOKIE['user_language'])) {
            $saved_lang = sanitize_text_field($_COOKIE['user_language']);
            $current_lang = $this->get_current_language();
            $supported_languages = gustolocal_ml_setting('supported_languages', array('ru', 'es', 'en'));
            
            if ($saved_lang !== $current_lang && in_array($saved_lang, $supported_languages)) {
                $current_url = home_url($_SERVER['REQUEST_URI']);
                
                $base_url = $current_url;
                foreach ($supported_languages as $lang) {
                    if ($lang === 'ru') continue;
                    $base_url = str_replace('/' . $lang . '/', '/', $base_url);
                    $base_url = str_replace('/' . $lang, '', $base_url);
                }
                
                $new_url = $base_url;
                if ($saved_lang !== 'ru') {
                    $new_url = str_replace(home_url(), home_url() . '/' . $saved_lang, $base_url);
                    if (rtrim($base_url, '/') === rtrim(home_url(), '/')) {
                        $new_url = home_url('/' . $saved_lang . '/');
                    }
                }
                
                if ($new_url !== $current_url) {
                    wp_redirect($new_url, 302);
                    exit;
                }
            }
        }
    }
    
    // Translation methods
    public function translate_woocommerce_strings($translated_text, $text, $domain) {
        if (is_admin() || $domain !== 'woocommerce') {
            return $translated_text;
        }
        
        $current_lang = $this->get_current_language();
        return get_translation($text, $current_lang);
    }
    
    public function translate_checkout_fields($fields) {
        $current_lang = $this->get_current_language();
        
        $field_keys = array(
            'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 
            'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 
            'billing_country', 'billing_phone', 'billing_email',
            'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 
            'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 
            'shipping_country'
        );
        
        foreach ($field_keys as $field_key) {
            $section = strpos($field_key, 'billing_') === 0 ? 'billing' : 'shipping';
            $original_label = $fields[$section][$field_key]['label'];
            $translated_label = get_translation($original_label, $current_lang);
            
            if ($translated_label !== $original_label) {
                $fields[$section][$field_key]['label'] = $translated_label;
            }
        }
        
        return $fields;
    }
    
    public function translate_add_to_cart_message($message) {
        $current_lang = $this->get_current_language();
        
        $message = str_replace('has been added to your cart.', get_translation('has been added to your cart.', $current_lang), $message);
        $message = str_replace('have been added to your cart.', get_translation('have been added to your cart.', $current_lang), $message);
        $message = str_replace('View cart', get_translation('View cart', $current_lang), $message);
        
        return $message;
    }
    
    public function translate_add_to_cart_button($text) {
        $current_lang = $this->get_current_language();
        return get_translation('Add to cart', $current_lang);
    }
    
    public function translate_woocommerce_page_title($title) {
        $current_lang = $this->get_current_language();
        return get_translation($title, $current_lang);
    }
    
    public function translate_custom_form_fields($field, $key, $args, $value) {
        $current_lang = $this->get_current_language();
        $translations = get_translations($current_lang);
        
        foreach ($translations as $original => $translated) {
            $field = str_replace($original, $translated, $field);
        }
        
        return $field;
    }
    
    public function translate_shipping_methods($title) {
        $current_lang = $this->get_current_language();
        return get_translation($title, $current_lang);
    }
    
    public function translate_cart_totals($html) {
        $current_lang = $this->get_current_language();
        $translations = get_translations($current_lang);
        
        foreach ($translations as $original => $translated) {
            $html = str_replace($original, $translated, $html);
        }
        
        return $html;
    }
    
    public function translate_remaining_strings($translated_text, $text, $domain) {
        if (is_admin()) {
            return $translated_text;
        }
        
        $current_lang = $this->get_current_language();
        return get_translation($text, $current_lang);
    }
    
    public function translate_woocommerce_page_content($content) {
        if (!is_wc_endpoint_url() && !is_checkout() && !is_cart() && !is_account_page()) {
            return $content;
        }
        
        $current_lang = $this->get_current_language();
        $translations = get_translations($current_lang);
        
        foreach ($translations as $original => $translated) {
            $content = str_replace($original, $translated, $content);
        }
        
        return $content;
    }
    
    public function translate_404_page_content($content) {
        if (!is_404()) {
            return $content;
        }
        
        $current_lang = $this->get_current_language();
        $translations = get_translations($current_lang);
        
        foreach ($translations as $original => $translated) {
            $content = str_replace($original, $translated, $content);
        }
        
        return $content;
    }
    
    public function translate_page_title($title) {
        if (is_404()) {
            $current_lang = $this->get_current_language();
            return get_translation('Страница не найдена', $current_lang);
        }
        return $title;
    }
    
    public function translate_thankyou_order_received_text($text, $order) {
        if (is_admin()) {
            return $text;
        }
        $current_lang = $this->get_current_language();
        return get_translation($text, $current_lang);
    }
    
    public function translate_404_with_javascript() {
        if (!is_404()) {
            return;
        }
        
        $current_lang = $this->get_current_language();
        if ($current_lang === 'ru') {
            return;
        }
        
        $translations = get_translations($current_lang);
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var translations = <?php echo json_encode($translations); ?>;
            
            var title = document.querySelector('h1');
            if (title) {
                var titleText = title.textContent.trim();
                if (translations[titleText]) {
                    title.textContent = translations[titleText];
                }
            }
            
            var description = document.querySelector('p');
            if (description) {
                var descText = description.textContent.trim();
                if (translations[descText]) {
                    description.textContent = translations[descText];
                }
            }
            
            var searchButton = document.querySelector('input[type="submit"]');
            if (searchButton) {
                var buttonText = searchButton.value;
                if (translations[buttonText]) {
                    searchButton.value = translations[buttonText];
                }
            }
        });
        </script>
        <?php
    }
}

// Initialize multilanguage module (called from main functions.php)
// new GustoLocal_Multilang();
