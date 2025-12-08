<?php
/**
 * Twenty Twenty-Four functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Twenty Twenty-Four
 * @since Twenty Twenty-Four 1.0
 */

// Подключаем файл переводов
require_once get_template_directory() . '/translations.php';

/**
 * Register block styles.
 */

if ( ! function_exists( 'twentytwentyfour_block_styles' ) ) :
	/**
	 * Register custom block styles
	 *
	 * @since Twenty Twenty-Four 1.0
	 * @return void
	 */
	function twentytwentyfour_block_styles() {

		register_block_style(
			'core/details',
			array(
				'name'         => 'arrow-icon-details',
				'label'        => __( 'Arrow icon', 'twentytwentyfour' ),
				/*
				 * Styles for the custom Arrow icon style of the Details block
				 */
				'inline_style' => '
				.is-style-arrow-icon-details {
					padding-top: var(--wp--preset--spacing--10);
					padding-bottom: var(--wp--preset--spacing--10);
				}

				.is-style-arrow-icon-details summary {
					list-style-type: "\2193\00a0\00a0\00a0";
				}

				.is-style-arrow-icon-details[open]>summary {
					list-style-type: "\2192\00a0\00a0\00a0";
				}',
			)
		);
		register_block_style(
			'core/post-terms',
			array(
				'name'         => 'pill',
				'label'        => __( 'Pill', 'twentytwentyfour' ),
				'inline_style' => '
				.is-style-pill a,
				.is-style-pill span:not([class*="color"]),
				.is-style-pill span:not([class*="background-color"]) {
					display: inline-block;
					background-color: var(--wp--preset--color--contrast-2, currentColor);
					color: var(--wp--preset--color--base, #fff);
					padding: 0.375rem 0.875rem;
					border-radius: 9999px;
					font-size: 0.875rem;
					font-weight: 600;
				}

				.is-style-pill a:hover {
					background-color: var(--wp--preset--color--contrast-3, currentColor);
					color: var(--wp--preset--color--base, #fff);
				}',
			)
		);
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark list', 'twentytwentyfour' ),
				'inline_style' => '
				.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
		register_block_style(
			'core/navigation-link',
			array(
				'name'         => 'arrow-link',
				'label'        => __( 'Arrow', 'twentytwentyfour' ),
				'inline_style' => '
				.is-style-arrow-link .wp-block-navigation-item__content:after {
					content: "\2197";
					padding-inline-start: 0.25rem;
					vertical-align: middle;
					text-decoration: none;
					display: inline-block;
				}',
			)
		);
		register_block_style(
			'core/heading',
			array(
				'name'         => 'asterisk',
				'label'        => __( 'With asterisk', 'twentytwentyfour' ),
				'inline_style' => "
				.is-style-asterisk:before {
					content: '';
					width: 1.5rem;
					height: 3rem;
					background: var(--wp--preset--color--contrast-2, currentColor);
					clip-path: path('M11.93.684v8.039l5.633-5.633 1.216 1.23-5.66 5.66h8.04v1.737H13.2l5.701 5.701-1.23 1.23-5.742-5.742V21h-1.737v-8.094l-5.77 5.77-1.23-1.217 5.743-5.742H.842V9.98h8.162l-5.701-5.7 1.23-1.231 5.66 5.66V.684h1.737Z');
					display: block;
				}

				/* Hide the asterisk if the heading has no content, to avoid using empty headings to display the asterisk only, which is an A11Y issue */
				.is-style-asterisk:empty:before {
					content: none;
				}

				.is-style-asterisk:-moz-only-whitespace:before {
					content: none;
				}

				.is-style-asterisk.has-text-align-center:before {
					margin: 0 auto;
				}

				.is-style-asterisk.has-text-align-right:before {
					margin-left: auto;
				}

				.rtl .is-style-asterisk.has-text-align-left:before {
					margin-right: auto;
				}",
			)
		);
	}
endif;

add_action( 'init', 'twentytwentyfour_block_styles' );

/**
 * Enqueue block stylesheets.
 */

if ( ! function_exists( 'twentytwentyfour_block_stylesheets' ) ) :
	/**
	 * Enqueue custom block stylesheets
	 *
	 * @since Twenty Twenty-Four 1.0
	 * @return void
	 */
	function twentytwentyfour_block_stylesheets() {
		/**
		 * The wp_enqueue_block_style() function allows us to enqueue a stylesheet
		 * for a specific block. These will only get loaded when the block is rendered
		 * (both in the editor and on the front end), improving performance
		 * and reducing the amount of data requested by visitors.
		 *
		 * See https://make.wordpress.org/core/2021/12/15/using-multiple-stylesheets-per-block/ for more info.
		 */
		wp_enqueue_block_style(
			'core/button',
			array(
				'handle' => 'twentytwentyfour-button-style-outline',
				'src'    => get_parent_theme_file_uri( 'assets/css/button-outline.css' ),
				'ver'    => wp_get_theme( get_template() )->get( 'Version' ),
				'path'   => get_parent_theme_file_path( 'assets/css/button-outline.css' ),
			)
		);
	}
endif;

add_action( 'init', 'twentytwentyfour_block_stylesheets' );

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
    
    // Проверяем, есть ли языковой префикс
    if (preg_match('/^(es|en)\/(.*)/', $request, $matches)) {
        $lang = $matches[1];
        $path = $matches[2];
        
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

// Отладка rewrite rules - удалено для производительности

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
    
    $pagename = get_query_var('pagename');
    
    // Если это главная страница с языковым префиксом
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

// Диагностика пользователя и прав доступа - удалено для производительности

// Принудительное включение REST API
add_filter('rest_authentication_errors', 'allow_rest_api_access');
function allow_rest_api_access($result) {
    // Если уже есть ошибка, не перезаписываем её
    if (!empty($result)) {
        return $result;
    }
    
    // Разрешаем доступ к REST API для всех аутентифицированных пользователей
    if (is_user_logged_in()) {
        return true;
    }
    
    // Разрешаем доступ к публичным эндпоинтам
    return true;
}

// Убираем ограничения на REST API
add_filter('rest_pre_serve_request', 'remove_rest_api_restrictions', 10, 4);
function remove_rest_api_restrictions($served, $result, $request, $server) {
    // Убираем заголовки, которые могут блокировать доступ
    header_remove('X-Robots-Tag');
    return $served;
}

// Экстренное восстановление прав администратора
add_action('init', 'emergency_admin_restore');
function emergency_admin_restore() {
    // Только для авторизованных пользователей
    if (!is_user_logged_in()) {
        return;
    }
    
    $user = wp_get_current_user();
    
    // Если пользователь не имеет прав администратора, но должен их иметь
    if (!current_user_can('manage_options') && in_array('administrator', $user->roles)) {
        // Принудительно добавляем права администратора
        $user->set_role('administrator');
        
        // Очищаем кэш прав
        wp_cache_delete($user->ID, 'user_meta');
        clean_user_cache($user->ID);
        
        // Логируем восстановление
        error_log("Emergency admin restore for user ID: " . $user->ID);
    }
}

// Принудительное разрешение редактирования для администраторов
add_filter('user_has_cap', 'force_admin_capabilities', 10, 4);
function force_admin_capabilities($allcaps, $caps, $args, $user) {
    // Только для администраторов
    if (!in_array('administrator', $user->roles)) {
        return $allcaps;
    }
    
    // Принудительно добавляем все необходимые права
    $admin_caps = array(
        'edit_posts' => true,
        'edit_pages' => true,
        'edit_others_posts' => true,
        'edit_others_pages' => true,
        'edit_published_posts' => true,
        'edit_published_pages' => true,
        'publish_posts' => true,
        'publish_pages' => true,
        'delete_posts' => true,
        'delete_pages' => true,
        'delete_others_posts' => true,
        'delete_others_pages' => true,
        'delete_published_posts' => true,
        'delete_published_pages' => true,
        'manage_options' => true,
        'manage_categories' => true,
        'manage_links' => true,
        'moderate_comments' => true,
        'unfiltered_html' => true,
        'edit_theme_options' => true,
        'install_plugins' => true,
        'activate_plugins' => true,
        'edit_plugins' => true,
        'delete_plugins' => true,
        'install_themes' => true,
        'edit_themes' => true,
        'delete_themes' => true,
        'switch_themes' => true,
        'edit_users' => true,
        'delete_users' => true,
        'create_users' => true,
        'list_users' => true,
        'promote_users' => true,
        'remove_users' => true,
        'add_users' => true
    );
    
    return array_merge($allcaps, $admin_caps);
}

// Простой переключатель языков
add_action('wp_head', 'add_simple_language_switcher');

function add_simple_language_switcher() {
    $current_lang = get_current_language();
    $current_url = home_url($_SERVER['REQUEST_URI']);
    
    // Убираем текущий префикс языка из URL
    if ($current_lang === 'es') {
        $base_url = str_replace('/es/', '/', $current_url);
        $base_url = str_replace(home_url('/es'), home_url(), $base_url);
    } elseif ($current_lang === 'en') {
        $base_url = str_replace('/en/', '/', $current_url);
        $base_url = str_replace(home_url('/en'), home_url(), $base_url);
    } else {
        $base_url = $current_url;
    }
    
    // Создаем URL для каждого языка
    $ru_url = $base_url;
    $es_url = str_replace(home_url(), home_url('/es'), $base_url);
    $en_url = str_replace(home_url(), home_url('/en'), $base_url);
    
    ?>
    <div id="language-switcher" style="
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
        font-size: 14px;
    ">
        <div style="margin-bottom: 8px; font-weight: bold; color: #333;">Язык:</div>
        <div style="display: flex; gap: 8px;">
            <a href="<?php echo esc_url($ru_url); ?>" style="
                padding: 4px 8px;
                text-decoration: none;
                border-radius: 4px;
                color: <?php echo $current_lang === 'ru' ? 'white' : '#333'; ?>;
                background: <?php echo $current_lang === 'ru' ? '#007cba' : '#f0f0f0'; ?>;
                font-size: 12px;
            ">RU</a>
            <a href="<?php echo esc_url($es_url); ?>" style="
                padding: 4px 8px;
                text-decoration: none;
                border-radius: 4px;
                color: <?php echo $current_lang === 'es' ? 'white' : '#333'; ?>;
                background: <?php echo $current_lang === 'es' ? '#007cba' : '#f0f0f0'; ?>;
                font-size: 12px;
            ">ES</a>
            <a href="<?php echo esc_url($en_url); ?>" style="
                padding: 4px 8px;
                text-decoration: none;
                border-radius: 4px;
                color: <?php echo $current_lang === 'en' ? 'white' : '#333'; ?>;
                background: <?php echo $current_lang === 'en' ? '#007cba' : '#f0f0f0'; ?>;
                font-size: 12px;
            ">EN</a>
        </div>
    </div>
	<?php
}

// JavaScript для сохранения языка (оптимизировано)
add_action('wp_footer', 'add_language_persistence_script');

function add_language_persistence_script() {
    ?>
    <script defer>
    // Функция для установки cookie
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }
    
    // Показываем индикатор загрузки при переключении языка
    function showLanguageLoader() {
        var loader = document.createElement('div');
        loader.id = 'language-loader';
        loader.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.9);z-index:99999;display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif';
        loader.innerHTML = '<div style="text-align:center"><div style="width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #007cba;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 20px"></div><div style="color:#333;font-size:16px">Переключение языка...</div></div>';
        var style = document.createElement('style');
        style.textContent = '@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}';
        document.head.appendChild(style);
        document.body.appendChild(loader);
    }
    
    // Проверяем язык сразу, не дожидаясь DOMContentLoaded (убраны console.log для производительности)
    (function() {
        var savedLang = localStorage.getItem('user_language');
        var currentUrl = window.location.href;
        var currentLang = getCurrentLangFromUrl(currentUrl);
        
        // Если нет сохраненного языка, определяем язык браузера
        if (!savedLang) {
            var browserLang = getBrowserLanguage();
            if (browserLang && browserLang !== 'ru') {
                localStorage.setItem('user_language', browserLang);
                setCookie('user_language', browserLang, 365);
                var newUrl = switchLanguageInUrl(currentUrl, browserLang);
                if (newUrl !== currentUrl) {
                    showLanguageLoader();
                    window.location.href = newUrl;
                    return;
                }
            } else {
                localStorage.setItem('user_language', 'ru');
                setCookie('user_language', 'ru', 365);
            }
        }
        
        // Если есть сохраненный язык, но текущий URL не соответствует ему
        if (savedLang && savedLang !== currentLang) {
            var newUrl = switchLanguageInUrl(currentUrl, savedLang);
            if (newUrl !== currentUrl) {
                showLanguageLoader();
                window.location.href = newUrl;
                return;
            }
        }
    })();
    
    document.addEventListener('DOMContentLoaded', function() {
        
        // Добавляем обработчики кликов на переключатель
        var switcher = document.getElementById('language-switcher');
        if (switcher) {
            var links = switcher.querySelectorAll('a');
            links.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var targetLang = getLangFromLink(this);
                    if (targetLang) {
                        localStorage.setItem('user_language', targetLang);
                        setCookie('user_language', targetLang, 365);
                        var newUrl = switchLanguageInUrl(window.location.href, targetLang);
                        if (newUrl !== window.location.href) {
                            showLanguageLoader();
                            window.location.href = newUrl;
                        }
                    }
                });
            });
        }
    });
    
    function getBrowserLanguage() {
        var lang = navigator.language || navigator.userLanguage;
        if (lang.startsWith('es')) return 'es';
        if (lang.startsWith('en')) return 'en';
        return 'ru';
    }
    
    function getCurrentLangFromUrl(url) {
        if (url.includes('/es/')) return 'es';
        if (url.includes('/en/')) return 'en';
        return 'ru';
    }
    
    function getLangFromLink(link) {
        var text = link.textContent.trim();
        if (text === 'RU') return 'ru';
        if (text === 'ES') return 'es';
        if (text === 'EN') return 'en';
        return null;
    }
    
    function switchLanguageInUrl(url, targetLang) {
        var baseUrl = url;
        
        // Убираем текущие префиксы языков
        baseUrl = baseUrl.replace(/\/es\//g, '/');
        baseUrl = baseUrl.replace(/\/en\//g, '/');
        baseUrl = baseUrl.replace(/\/es$/, '');
        baseUrl = baseUrl.replace(/\/en$/, '');
        
        // Добавляем новый префикс
        if (targetLang === 'ru') {
            return baseUrl;
        } else {
            var domain = baseUrl.split('/')[0] + '//' + baseUrl.split('/')[2];
            var path = baseUrl.replace(domain, '');
            return domain + '/' + targetLang + path;
        }
    }
    </script>
	<?php
}

// Простое перенаправление на основе сохраненного языка
add_action('template_redirect', 'simple_language_redirect');
function simple_language_redirect() {
    // Проверяем, есть ли сохраненный язык в cookie
    if (isset($_COOKIE['user_language'])) {
        $saved_lang = sanitize_text_field($_COOKIE['user_language']);
        $current_lang = get_current_language();
        
        // Если сохраненный язык не соответствует текущему URL
        if ($saved_lang !== $current_lang && in_array($saved_lang, ['ru', 'es', 'en'])) {
            $current_url = home_url($_SERVER['REQUEST_URI']);
            
            // Создаем правильный URL для сохраненного языка
            if ($saved_lang === 'ru') {
                $new_url = str_replace(['/es/', '/en/'], '/', $current_url);
                $new_url = str_replace([home_url('/es'), home_url('/en')], home_url(), $new_url);
            } else {
                $base_url = str_replace(['/es/', '/en/'], '/', $current_url);
                $base_url = str_replace([home_url('/es'), home_url('/en')], home_url(), $base_url);
                $new_url = str_replace(home_url(), home_url('/' . $saved_lang), $base_url);
            }
            
            if ($new_url !== $current_url) {
                wp_redirect($new_url, 302);
                exit;
            }
        }
    }
}

// Система переводов WooCommerce
add_filter('gettext', 'translate_woocommerce_strings', 20, 3);
function translate_woocommerce_strings($translated_text, $text, $domain) {
    // Только для фронтенда и только для WooCommerce
    if (is_admin() || $domain !== 'woocommerce') {
        return $translated_text;
    }
    
    $current_lang = get_current_language();
    return get_translation($text, $current_lang);
}

// Перевод текста на странице "Спасибо за заказ"
add_filter('woocommerce_thankyou_order_received_text', 'translate_thankyou_order_received_text', 10, 2);
function translate_thankyou_order_received_text($text, $order) {
    if (is_admin()) {
        return $text;
    }
    $current_lang = get_current_language();
    return get_translation($text, $current_lang);
}

// Переводы полей формы чекаута
add_filter('woocommerce_checkout_fields', 'translate_checkout_fields');
function translate_checkout_fields($fields) {
    $current_lang = get_current_language();
    
    // Список полей для перевода
    $field_keys = array(
        'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 
        'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 
        'billing_country', 'billing_phone', 'billing_email',
        'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 
        'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 
        'shipping_country'
    );
    
    // Переводим каждое поле
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

// Переводы сообщений WooCommerce
add_filter('woocommerce_add_to_cart_message', 'translate_add_to_cart_message');
function translate_add_to_cart_message($message) {
    $current_lang = get_current_language();
    
    // Переводим основные фразы в сообщениях
    $message = str_replace('has been added to your cart.', get_translation('has been added to your cart.', $current_lang), $message);
    $message = str_replace('have been added to your cart.', get_translation('have been added to your cart.', $current_lang), $message);
    $message = str_replace('View cart', get_translation('View cart', $current_lang), $message);
    
    return $message;
}

// Переводы кнопок товаров
add_filter('woocommerce_product_add_to_cart_text', 'translate_add_to_cart_button');
function translate_add_to_cart_button($text) {
    $current_lang = get_current_language();
    return get_translation('Add to cart', $current_lang);
}

// Переводы заголовков страниц WooCommerce
add_filter('woocommerce_page_title', 'translate_woocommerce_page_title');
function translate_woocommerce_page_title($title) {
    $current_lang = get_current_language();
    return get_translation($title, $current_lang);
}

// Переводы кастомных полей и инструкций
add_filter('woocommerce_form_field', 'translate_custom_form_fields', 10, 4);
function translate_custom_form_fields($field, $key, $args, $value) {
    $current_lang = get_current_language();
    $translations = get_translations($current_lang);
    
    // Переводим все строки из словаря
    foreach ($translations as $original => $translated) {
        $field = str_replace($original, $translated, $field);
    }
    
    return $field;
}

// Переводы способов доставки
add_filter('woocommerce_shipping_method_title', 'translate_shipping_methods');
function translate_shipping_methods($title) {
    $current_lang = get_current_language();
    return get_translation($title, $current_lang);
}

// Переводы заголовков секций и других элементов
add_filter('woocommerce_cart_totals_order_total_html', 'translate_cart_totals');
function translate_cart_totals($html) {
    $current_lang = get_current_language();
    $translations = get_translations($current_lang);
    
    // Переводим все строки из словаря
    foreach ($translations as $original => $translated) {
        $html = str_replace($original, $translated, $html);
    }
    
    return $html;
}

// Переводы для всех остальных строк
add_filter('gettext', 'translate_remaining_strings', 25, 3);
function translate_remaining_strings($translated_text, $text, $domain) {
    // Только для фронтенда
    if (is_admin()) {
        return $translated_text;
    }
    
    $current_lang = get_current_language();
    return get_translation($text, $current_lang);
}

// Специальные переводы для страниц WooCommerce
add_filter('the_content', 'translate_woocommerce_page_content');
function translate_woocommerce_page_content($content) {
    // Только для страниц WooCommerce
    if (!is_wc_endpoint_url() && !is_checkout() && !is_cart() && !is_account_page()) {
        return $content;
    }
    
    $current_lang = get_current_language();
    $translations = get_translations($current_lang);
    
    // Переводим все строки из словаря
    foreach ($translations as $original => $translated) {
        $content = str_replace($original, $translated, $content);
    }
    
    return $content;
}

// Переводы для страницы 404
add_filter('the_content', 'translate_404_page_content');
function translate_404_page_content($content) {
    // Только для страницы 404
    if (!is_404()) {
        return $content;
    }
    
    $current_lang = get_current_language();
    $translations = get_translations($current_lang);
    
    // Переводим все строки из словаря
    foreach ($translations as $original => $translated) {
        $content = str_replace($original, $translated, $content);
    }
    
    return $content;
}

// Переводы для заголовков страниц
add_filter('wp_title', 'translate_page_title');
function translate_page_title($title) {
    if (is_404()) {
        $current_lang = get_current_language();
        return get_translation('Страница не найдена', $current_lang);
    }
    return $title;
}

// JavaScript для перевода страницы 404 (оптимизировано с defer)
add_action('wp_footer', 'translate_404_with_javascript');
function translate_404_with_javascript() {
    if (!is_404()) {
        return;
    }
    
    $current_lang = get_current_language();
    if ($current_lang === 'ru') {
        return; // Русский уже правильный
    }
    
    $translations = get_translations($current_lang);
    ?>
    <script defer>
    document.addEventListener('DOMContentLoaded', function() {
        var translations = <?php echo json_encode($translations); ?>;
        
        // Переводим заголовок
        var title = document.querySelector('h1');
        if (title) {
            var titleText = title.textContent.trim();
            if (translations[titleText]) {
                title.textContent = translations[titleText];
            }
        }
        
        // Переводим описание
        var description = document.querySelector('p');
        if (description) {
            var descText = description.textContent.trim();
            if (translations[descText]) {
                description.textContent = translations[descText];
            }
        }
        
        // Переводим кнопку поиска
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

// Конец языковой системы

/**
 * Simple WooCommerce customizations
 */

// Simple cart page styling
function simple_cart_styling() {
    if ( is_cart() ) {
        ?>
        <style>
        /* Basic cart improvements */
        .woocommerce-cart-form__contents .product-thumbnail {
            display: none !important;
        }
        
        .woocommerce-cart-form__contents .product-name {
            width: 50% !important;
        }
        
        /* Hide page title */
        .woocommerce-cart .entry-title,
        .woocommerce-cart h1.entry-title,
        .woocommerce-cart .page-title,
        .woocommerce-cart h1.page-title,
        .woocommerce-cart h1 {
            display: none !important;
        }
        
        /* Simple button styling */
        .woocommerce-cart .wc-proceed-to-checkout a {
            background-color: #6a5eb7 !important;
            color: white !important;
            padding: 12px 24px !important;
            border-radius: 4px !important;
            text-decoration: none !important;
            display: inline-block !important;
            width: 100% !important;
            text-align: center !important;
            box-sizing: border-box !important;
        }
        
        /* Remove borders and improve layout */
        .woocommerce-cart .woocommerce-cart-form__contents {
            border: none !important;
        }
        
        .woocommerce-cart .woocommerce-cart-form__contents tr {
            border-bottom: 1px solid #eee !important;
        }
        
        .woocommerce-cart .woocommerce-cart-form__contents td {
            border: none !important;
            vertical-align: top !important;
        }
        
        /* Remove gray borders around cart sections */
        .woocommerce-cart .woocommerce-cart-form {
            border: none !important;
            background: transparent !important;
        }
        
        .woocommerce-cart .coupon {
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .woocommerce-cart .cart-actions {
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Mobile responsive fixes */
        @media (max-width: 768px) {
            .woocommerce-cart-form__contents {
                display: block !important;
            }
            
            .woocommerce-cart-form__contents thead {
                display: none !important;
            }
            
            .woocommerce-cart-form__contents tbody {
                display: block !important;
            }
            
            .woocommerce-cart-form__contents tr {
                display: block !important;
                border: 1px solid #ddd !important;
                margin-bottom: 15px !important;
                padding: 15px !important;
                border-radius: 8px !important;
                background: #f9f9f9 !important;
            }
            
            .woocommerce-cart-form__contents td {
                display: block !important;
                width: 100% !important;
                padding: 8px 0 !important;
                border: none !important;
                text-align: left !important;
            }
            
            .woocommerce-cart-form__contents .product-remove {
                position: absolute !important;
                top: 10px !important;
                right: 10px !important;
                width: auto !important;
            }
            
            .woocommerce-cart-form__contents .product-name {
                width: 100% !important;
                padding-right: 40px !important;
                margin-bottom: 15px !important;
                display: block !important;
            }
            
            .woocommerce-cart-form__contents .product-price,
            .woocommerce-cart-form__contents .product-quantity,
            .woocommerce-cart-form__contents .product-subtotal {
                width: 100% !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                margin-bottom: 5px !important;
            }
            
            .woocommerce-cart-form__contents .product-price:before {
                content: "Цена: " !important;
                font-weight: bold !important;
            }
            
            .woocommerce-cart-form__contents .product-quantity:before {
                content: "Количество: " !important;
                font-weight: bold !important;
            }
            
            .woocommerce-cart-form__contents .product-subtotal:before {
                content: "Подытог: " !important;
                font-weight: bold !important;
            }
            
            /* Fix buttons on mobile */
            .woocommerce-cart .coupon,
            .woocommerce-cart .cart-actions {
                display: block !important;
                width: 100% !important;
                margin-bottom: 15px !important;
            }
            
            .woocommerce-cart .coupon input[type="text"] {
                width: 100% !important;
                margin-bottom: 10px !important;
            }
            
            .woocommerce-cart .coupon button,
            .woocommerce-cart .cart-actions button {
                width: 100% !important;
                margin-bottom: 10px !important;
            }
            
            .woocommerce-cart .wc-proceed-to-checkout {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                text-align: center !important;
                display: block !important;
            }
            
            .woocommerce-cart .wc-proceed-to-checkout a {
                width: 100% !important;
                text-align: center !important;
                display: block !important;
                margin: 0 !important;
                padding: 12px 24px !important;
                box-sizing: border-box !important;
            }
            
            /* Remove borders on mobile too */
            .woocommerce-cart .woocommerce-cart-form {
                border: none !important;
                background: transparent !important;
            }
            
            .woocommerce-cart .coupon {
                border: none !important;
                background: transparent !important;
            }
            
            .woocommerce-cart .cart-actions {
                border: none !important;
                background: transparent !important;
            }
        }
        </style>
        <?php
    }
}
add_action( 'wp_head', 'simple_cart_styling' );

// Simple checkout page styling
function simple_checkout_styling() {
    if ( is_checkout() ) {
        ?>
        <style>
        /* Hide page title */
        .woocommerce-checkout .entry-title {
            display: none !important;
        }
        
        /* Hide duplicate site title */
        .woocommerce-checkout .wp-block-site-title,
        .woocommerce-checkout .site-title,
        .woocommerce-checkout h1.wp-block-site-title {
            display: none !important;
        }
        </style>
        <?php
    }
}
add_action( 'wp_head', 'simple_checkout_styling' );

// Order received page styling
function order_received_styling() {
    if ( is_wc_endpoint_url( 'order-received' ) ) {
        ?>
        <style>
        /* Center content on mobile */
        @media (max-width: 768px) {
            .woocommerce-order {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 15px !important;
                box-sizing: border-box !important;
            }
            
            .woocommerce-order .woocommerce-order-details {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 15px !important;
                box-sizing: border-box !important;
            }
            
            .woocommerce-order .woocommerce-customer-details {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 15px !important;
                box-sizing: border-box !important;
            }
            
            /* Ensure text doesn't overflow */
            .woocommerce-order * {
                max-width: 100% !important;
                word-wrap: break-word !important;
                box-sizing: border-box !important;
            }
            
            /* Center the main content area */
            .entry-content {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 15px !important;
            }
            
            /* Center any tables or lists */
            .woocommerce-order table,
            .woocommerce-order ul,
            .woocommerce-order ol {
                max-width: 100% !important;
                margin: 0 auto !important;
            }
        }
        
        /* Desktop centering */
        @media (min-width: 769px) {
            .woocommerce-order {
                max-width: 800px !important;
                margin: 0 auto !important;
            }
        }
        </style>
        <?php
    }
}
add_action( 'wp_head', 'order_received_styling' );

// Remove delivery information from cart item data
function remove_delivery_from_cart_item_data( $item_data, $cart_item ) {
    // Remove all delivery-related information
    if ( isset( $item_data['delivery'] ) ) {
        unset( $item_data['delivery'] );
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'remove_delivery_from_cart_item_data', 10, 2 );

// Add delivery fee based on user selection
add_action('woocommerce_cart_calculate_fees', 'add_delivery_fee');
function add_delivery_fee($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    // Get delivery type from session or default to delivery
    $delivery_type = WC()->session->get('delivery_type', 'delivery');
    
    if ($delivery_type === 'delivery') {
        // Add 10 euro delivery fee
        $delivery_fee = 10.00;
        $cart->add_fee(__('Доставка', 'woocommerce'), $delivery_fee);
    } else {
        // Add pickup option (free)
        $cart->add_fee(__('Самовывоз', 'woocommerce'), 0);
    }
}

// Set minimum order amount (60 euros without delivery fee) - TEMPORARILY DISABLED
/*
add_action('woocommerce_checkout_process', 'enforce_minimum_order_amount');
add_action('woocommerce_before_cart', 'show_minimum_order_notice');
add_action('woocommerce_before_checkout_form', 'show_minimum_order_notice');

function enforce_minimum_order_amount() {
    $minimum_amount = 60.00; // 60 euros without delivery fee
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal < $minimum_amount) {
        wc_add_notice(
            sprintf(
                'Минимальная сумма заказа: %s. Текущая сумма: %s. Добавьте товаров на %s.',
                wc_price($minimum_amount),
                wc_price($cart_subtotal),
                wc_price($minimum_amount - $cart_subtotal)
            ),
            'error'
        );
    }
}

function show_minimum_order_notice() {
    $minimum_amount = 60.00;
    $cart_subtotal = WC()->cart->get_subtotal();
    
    if ($cart_subtotal < $minimum_amount && $cart_subtotal > 0) {
        $remaining = $minimum_amount - $cart_subtotal;
        ?>
        <div class="woocommerce-minimum-order-notice" style="
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
            color: #856404;
            font-weight: 500;
        ">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">⚠️</span>
                <div>
                    <strong>Минимальная сумма заказа: <?php echo wc_price($minimum_amount); ?></strong><br>
                    <span style="font-size: 14px; opacity: 0.8;">
                        Текущая сумма: <?php echo wc_price($cart_subtotal); ?> 
                        (без учета доставки)<br>
                        Добавьте товаров на: <strong><?php echo wc_price($remaining); ?></strong>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
}
*/

// Simplify checkout form - максимально упрощенная форма как на оригинале
add_filter('woocommerce_checkout_fields', 'simplify_checkout_fields');
function simplify_checkout_fields($fields) {
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
add_filter('woocommerce_checkout_get_value', 'set_default_checkout_values', 10, 2);
function set_default_checkout_values($value, $input) {
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

// Set default shipping address to Valencia
add_filter('woocommerce_checkout_get_value', 'set_default_shipping_address', 10, 2);
function set_default_shipping_address($value, $input) {
    if (strpos($input, 'shipping_') === 0) {
        switch ($input) {
            case 'shipping_city':
                return 'Валенсия';
            case 'shipping_country':
                return 'ES';
            case 'shipping_state':
                return 'VC'; // Valencia province code
            case 'shipping_postcode':
                return '46000';
        }
    }
    return $value;
}

// Force clear cart after successful order
add_action('woocommerce_thankyou', 'clear_cart_after_order', 10, 1);
function clear_cart_after_order($order_id) {
    if ($order_id) {
        WC()->cart->empty_cart();
    }
}

// Clear cart when user logs in (remove persistent cart items)
add_action('wp_login', 'clear_cart_on_login', 10, 2);
function clear_cart_on_login($user_login, $user) {
    // Clear cart for ALL users to prevent persistent items
    WC()->cart->empty_cart();
    
    // Also clear from database
    global $wpdb;
    $wpdb->delete(
        $wpdb->usermeta,
        array(
            'user_id' => $user->ID,
            'meta_key' => '_woocommerce_persistent_cart'
        )
    );
    
    // Clear user meta for this user
    delete_user_meta($user->ID, '_woocommerce_persistent_cart');
    delete_user_meta($user->ID, '_woocommerce_persistent_cart_hash');
}

// Force clear cart on every page load for logged-in users
add_action('wp_loaded', 'force_clear_cart_on_load');
function force_clear_cart_on_load() {
    if (is_user_logged_in() && is_cart()) {
        // Check if cart has invalid items
        $cart_items = WC()->cart->get_cart();
        $has_invalid_items = false;
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            if (!empty($cart_item['wmb_payload']['items_list'])) {
                foreach ($cart_item['wmb_payload']['items_list'] as $row) {
                    $name = isset($row['name']) ? trim($row['name']) : '';
                    if (empty($name)) {
                        $has_invalid_items = true;
                        break 2;
                    }
                }
            }
        }
        
        if ($has_invalid_items) {
            WC()->cart->empty_cart();
        }
    }
}

// Clear cart on order completion
add_action('woocommerce_order_status_completed', 'clear_cart_on_order_completion');
add_action('woocommerce_order_status_processing', 'clear_cart_on_order_completion');
function clear_cart_on_order_completion($order_id) {
    WC()->cart->empty_cart();
}

// Disable persistent cart completely
add_filter('woocommerce_persistent_cart_enabled', '__return_false');

// Remove clickable links from Weekly Meal Plan product
function remove_weekly_meal_plan_links() {
    ?>
    <style>
    /* Remove links from Weekly Meal Plan product everywhere */
    a[href*="weekly-meal-plan"],
    a[href*="product/weekly-meal-plan"] {
        pointer-events: none !important;
        cursor: default !important;
        text-decoration: none !important;
        color: inherit !important;
    }
    
    /* Remove hover effects */
    a[href*="weekly-meal-plan"]:hover,
    a[href*="product/weekly-meal-plan"]:hover {
        text-decoration: none !important;
        color: inherit !important;
    }
    
    /* In cart - make product name not clickable */
    .woocommerce-cart .product-name a[href*="weekly-meal-plan"] {
        pointer-events: none !important;
        cursor: default !important;
    }
    
    /* In meal builder - make product name not clickable */
    .wmb-item-name a[href*="weekly-meal-plan"] {
        pointer-events: none !important;
        cursor: default !important;
    }
    </style>
    <?php
}
add_action('wp_head', 'remove_weekly_meal_plan_links');

// JavaScript to prevent clicks on Weekly Meal Plan links (оптимизировано)
function prevent_weekly_meal_plan_clicks() {
    ?>
    <script defer>
    document.addEventListener('DOMContentLoaded', function() {
        // Find all links to Weekly Meal Plan
        var links = document.querySelectorAll('a[href*="weekly-meal-plan"], a[href*="product/weekly-meal-plan"]');
        
        links.forEach(function(link) {
            // Remove href attribute
            link.removeAttribute('href');
            
            // Add click prevention
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            
            // Remove link styling
            link.style.cursor = 'default';
            link.style.textDecoration = 'none';
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'prevent_weekly_meal_plan_clicks');

// Contact Form 7 styling
function contact_form_7_styling() {
    ?>
    <style>
    /* Contact Form 7 styling to match site design */
    .wpcf7-form {
        max-width: 600px;
        margin: 0 auto;
        padding: 10px 20px;
    }
    
    .wpcf7-form .form-group {
        margin-bottom: 12px;
    }
    
    /* Remove default paragraph margins from Contact Form 7 */
    .wpcf7-form p {
        margin: 0 0 12px 0 !important;
        padding: 0 !important;
    }
    
    /* Remove all default spacing from form elements */
    .wpcf7-form br {
        display: none !important;
    }
    
    .wpcf7-form .wpcf7-form-control-wrap {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Ensure consistent spacing between form groups */
    .wpcf7-form p:last-child {
        margin-bottom: 0 !important;
    }
    
    .wpcf7-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 4px;
        color: #333;
        font-size: 16px;
    }
    
    .wpcf7-form input[type="text"],
    .wpcf7-form input[type="email"],
    .wpcf7-form input[type="tel"],
    .wpcf7-form textarea,
    .wpcf7-form select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }
    
    .wpcf7-form input[type="text"]:focus,
    .wpcf7-form input[type="email"]:focus,
    .wpcf7-form input[type="tel"]:focus,
    .wpcf7-form textarea:focus,
    .wpcf7-form select:focus {
        outline: none;
        border-color: #6a5eb7;
        box-shadow: 0 0 0 3px rgba(106, 94, 183, 0.1);
    }
    
    .wpcf7-form textarea {
        min-height: 120px;
        resize: vertical;
    }
    
    .wpcf7-form input[type="submit"] {
        background-color: #6a5eb7;
        color: white;
        padding: 14px 32px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s ease;
        width: 100%;
        margin-top: 10px;
    }
    
    .wpcf7-form input[type="submit"]:hover {
        background-color: #5a4ea6;
    }
    
    .wpcf7-form input[type="submit"]:active {
        transform: translateY(1px);
    }
    
    /* Error and success messages */
    .wpcf7-response-output {
        margin-top: 20px;
        padding: 12px 16px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .wpcf7-mail-sent-ok {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .wpcf7-validation-errors,
    .wpcf7-spam-blocked {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* Checkbox and radio styling */
    .wpcf7-form input[type="checkbox"],
    .wpcf7-form input[type="radio"] {
        margin-right: 8px;
        transform: scale(1.2);
    }
    
    .wpcf7-form .wpcf7-list-item {
        margin-bottom: 10px;
    }
    
    .wpcf7-form .wpcf7-list-item label {
        font-weight: normal;
        margin-bottom: 0;
        cursor: pointer;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .wpcf7-form {
            padding: 8px 15px;
        }
        
        .wpcf7-form .form-group {
            margin-bottom: 10px;
        }
        
        .wpcf7-form input[type="text"],
        .wpcf7-form input[type="email"],
        .wpcf7-form input[type="tel"],
        .wpcf7-form textarea,
        .wpcf7-form select {
            font-size: 16px; /* Prevents zoom on iOS */
        }
    }
    </style>
    <?php
}
add_action('wp_head', 'contact_form_7_styling');

// SMTP Configuration for Contact Form 7
function configure_smtp_for_contact_form() {
    // Настройки SMTP для отправки почты через Contact Form 7
    add_action('phpmailer_init', 'configure_phpmailer_smtp');
}
add_action('init', 'configure_smtp_for_contact_form');

function configure_phpmailer_smtp($phpmailer) {
    // Получаем настройки SMTP из опций WordPress
    $smtp_host = get_option('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_option('smtp_port', 587);
    $smtp_username = get_option('smtp_username', '');
    $smtp_password = get_option('smtp_password', '');
    $smtp_encryption = get_option('smtp_encryption', 'tls');
    $smtp_from_email = get_option('smtp_from_email', get_option('admin_email'));
    $smtp_from_name = get_option('smtp_from_name', get_option('blogname'));
    
    // Если настройки SMTP не заданы, используем альтернативный метод
    if (empty($smtp_username) || empty($smtp_password)) {
        // Используем wp_mail с базовыми настройками
        $phpmailer->isMail();
        $phpmailer->setFrom($smtp_from_email, $smtp_from_name);
        return;
    }
    
    // Настройка SMTP
    $phpmailer->isSMTP();
    $phpmailer->Host = $smtp_host;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = $smtp_port;
    $phpmailer->Username = $smtp_username;
    $phpmailer->Password = $smtp_password;
    $phpmailer->SMTPSecure = $smtp_encryption;
    $phpmailer->setFrom($smtp_from_email, $smtp_from_name);
    
    // Дополнительные настройки для надежности
    $phpmailer->SMTPDebug = 0; // Отключить отладку в продакшене
    $phpmailer->SMTPKeepAlive = true;
    $phpmailer->Timeout = 30;
}

// Добавляем страницу настроек SMTP в админку
function add_smtp_settings_page() {
    add_options_page(
        'SMTP Settings',
        'SMTP Settings',
        'manage_options',
        'smtp-settings',
        'smtp_settings_page'
    );
}
add_action('admin_menu', 'add_smtp_settings_page');

function smtp_settings_page() {
    if (isset($_POST['submit'])) {
        update_option('smtp_host', sanitize_text_field($_POST['smtp_host']));
        update_option('smtp_port', intval($_POST['smtp_port']));
        update_option('smtp_username', sanitize_text_field($_POST['smtp_username']));
        update_option('smtp_password', sanitize_text_field($_POST['smtp_password']));
        update_option('smtp_encryption', sanitize_text_field($_POST['smtp_encryption']));
        update_option('smtp_from_email', sanitize_email($_POST['smtp_from_email']));
        update_option('smtp_from_name', sanitize_text_field($_POST['smtp_from_name']));
        echo '<div class="notice notice-success"><p>Настройки SMTP сохранены!</p></div>';
    }
    
    // Обработка тестового письма
    if (isset($_POST['test_email']) && isset($_POST['test_email_address'])) {
        $test_email = sanitize_email($_POST['test_email_address']);
        $subject = 'Тест отправки почты - ' . get_option('blogname');
        $message = 'Это тестовое письмо для проверки настроек SMTP. Если вы получили это письмо, значит настройки работают корректно.';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($test_email, $subject, $message, $headers);
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Тестовое письмо успешно отправлено на ' . esc_html($test_email) . '!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Ошибка отправки тестового письма. Проверьте настройки SMTP и логи ошибок.</p></div>';
        }
    }
    
    $smtp_host = get_option('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_option('smtp_port', 587);
    $smtp_username = get_option('smtp_username', '');
    $smtp_password = get_option('smtp_password', '');
    $smtp_encryption = get_option('smtp_encryption', 'tls');
    $smtp_from_email = get_option('smtp_from_email', get_option('admin_email'));
    $smtp_from_name = get_option('smtp_from_name', get_option('blogname'));
    ?>
    <div class="wrap">
        <h1>Настройки SMTP</h1>
        <p>Настройте SMTP для отправки почты через Contact Form 7 и другие формы.</p>
        
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">SMTP Host</th>
                    <td><input type="text" name="smtp_host" value="<?php echo esc_attr($smtp_host); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">SMTP Port</th>
                    <td><input type="number" name="smtp_port" value="<?php echo esc_attr($smtp_port); ?>" class="small-text" /></td>
                </tr>
                <tr>
                    <th scope="row">SMTP Username</th>
                    <td><input type="text" name="smtp_username" value="<?php echo esc_attr($smtp_username); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">SMTP Password</th>
                    <td><input type="password" name="smtp_password" value="<?php echo esc_attr($smtp_password); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Encryption</th>
                    <td>
                        <select name="smtp_encryption">
                            <option value="none" <?php selected($smtp_encryption, 'none'); ?>>None</option>
                            <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>>SSL</option>
                            <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>>TLS</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">From Email</th>
                    <td><input type="email" name="smtp_from_email" value="<?php echo esc_attr($smtp_from_email); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">From Name</th>
                    <td><input type="text" name="smtp_from_name" value="<?php echo esc_attr($smtp_from_name); ?>" class="regular-text" /></td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Сохранить настройки" />
            </p>
        </form>
        
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h3>Популярные SMTP провайдеры:</h3>
            <ul>
                <li><strong>Gmail:</strong> smtp.gmail.com, порт 587, TLS</li>
                <li><strong>Yahoo:</strong> smtp.mail.yahoo.com, порт 587, TLS</li>
                <li><strong>Outlook:</strong> smtp-mail.outlook.com, порт 587, TLS</li>
                <li><strong>Mailgun:</strong> smtp.mailgun.org, порт 587, TLS</li>
                <li><strong>SendGrid:</strong> smtp.sendgrid.net, порт 587, TLS</li>
            </ul>
            <p><strong>Важно:</strong> Для Gmail нужно включить "Менее безопасные приложения" или использовать пароль приложения.</p>
        </div>
        
        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h3>Тест отправки почты</h3>
            <p>Отправьте тестовое письмо, чтобы проверить настройки SMTP:</p>
            <form method="post" action="">
                <input type="hidden" name="test_email" value="1" />
                <p>
                    <label>Email для теста:</label><br>
                    <input type="email" name="test_email_address" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text" required />
                </p>
                <p class="submit">
                    <input type="submit" name="submit_test" class="button-secondary" value="Отправить тестовое письмо" />
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Альтернативный способ отправки через webhook (если SMTP не работает)
function contact_form_7_webhook_fallback($contact_form, &$abort, $submission) {
    // Проверяем, есть ли настройки webhook
    $webhook_url = get_option('cf7_webhook_url', '');
    
    if (empty($webhook_url)) {
        return;
    }
    
    // Получаем данные формы
    $posted_data = $submission->get_posted_data();
    $form_data = array(
        'form_id' => $contact_form->id(),
        'form_title' => $contact_form->title(),
        'submission_time' => current_time('mysql'),
        'data' => $posted_data
    );
    
    // Отправляем данные на webhook
    $response = wp_remote_post($webhook_url, array(
        'body' => json_encode($form_data),
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('CF7 Webhook Error: ' . $response->get_error_message());
    }
}
add_action('wpcf7_before_send_mail', 'contact_form_7_webhook_fallback', 10, 3);

// Add delivery options styling
add_action('wp_head', 'delivery_options_styling');
function delivery_options_styling() {
    if (is_cart() || is_checkout()) {
    ?>
    <style>
    .delivery-options,
    .delivery-options-checkout {
        margin: 20px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .delivery-options h4,
    .delivery-options-checkout h3 {
        margin: 0 0 15px 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .delivery-radio-group {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .delivery-option {
        display: flex;
        align-items: center;
        padding: 15px;
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .delivery-option:hover {
        border-color: #007cba;
        box-shadow: 0 2px 8px rgba(0, 124, 186, 0.1);
    }
    
    .delivery-option input[type="radio"] {
        margin: 0;
        transform: scale(1.2);
        accent-color: #007cba;
    }
    
    .delivery-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        font-size: 14px;
        line-height: 1.4;
        margin-left: 20px;
    }
    
    .delivery-option input[type="radio"]:checked + .delivery-label {
        color: #007cba;
    }
    
    .delivery-option:has(input[type="radio"]:checked) {
        border-color: #007cba;
        background: #f0f8ff;
        box-shadow: 0 2px 8px rgba(0, 124, 186, 0.15);
    }
    
    .delivery-label strong {
        font-weight: 600;
    }
    
    .delivery-price {
        font-weight: 600;
        color: #28a745;
    }
    
    .delivery-options.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .delivery-options.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #007cba;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .delivery-options {
            margin: 15px 0;
            padding: 15px;
        }
        
        .delivery-option {
            padding: 12px;
        }
        
        .delivery-label {
            font-size: 13px;
        }
        
        .delivery-label strong {
            margin-right: 10px;
        }
    }
    </style>
    <?php
    }
}

// AJAX handler for delivery type change
add_action('wp_ajax_update_delivery_type', 'update_delivery_type');
add_action('wp_ajax_nopriv_update_delivery_type', 'update_delivery_type');
function update_delivery_type() {
    if (!wp_verify_nonce($_POST['nonce'], 'delivery_type_nonce')) {
        wp_die('Security check failed');
    }
    
    $delivery_type = sanitize_text_field($_POST['delivery_type']);
    
    if (in_array($delivery_type, ['delivery', 'pickup'])) {
        WC()->session->set('delivery_type', $delivery_type);
        WC()->cart->calculate_totals();
        
        wp_send_json_success([
            'delivery_type' => $delivery_type,
            'cart_total' => WC()->cart->get_total()
        ]);
    } else {
        wp_send_json_error('Invalid delivery type');
    }
}

// Enqueue delivery options script (оптимизировано)
add_action('wp_enqueue_scripts', 'enqueue_delivery_options_script');
function enqueue_delivery_options_script() {
    if (is_cart() || is_checkout()) {
        wp_enqueue_script(
            'delivery-options',
            get_template_directory_uri() . '/assets/js/delivery-options.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('delivery-options', 'wc_cart_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'delivery_nonce' => wp_create_nonce('delivery_type_nonce')
        ));
        
        // Добавляем defer для неблокирующей загрузки
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'delivery-options') {
                return str_replace(' src', ' defer src', $tag);
            }
            return $tag;
        }, 10, 2);
    }
}

// Оптимизация производительности: Lazy loading для изображений
add_filter('wp_get_attachment_image_attributes', 'add_lazy_loading_to_images', 10, 3);
function add_lazy_loading_to_images($attr, $attachment, $size) {
    // Добавляем loading="lazy" для всех изображений кроме первого на странице
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
}

// Оптимизация: добавляем defer для всех неблокирующих скриптов
add_filter('script_loader_tag', 'optimize_script_loading', 10, 3);
function optimize_script_loading($tag, $handle, $src) {
    // Список скриптов, которые можно загружать с defer
    $defer_scripts = array('jquery-migrate', 'wmb-app');
    
    // Не добавляем defer для критичных скриптов (jquery, woocommerce)
    $critical_scripts = array('jquery', 'woocommerce', 'wc-cart', 'wc-checkout');
    
    if (in_array($handle, $defer_scripts) && !in_array($handle, $critical_scripts)) {
        // Проверяем, не добавлен ли уже defer
        if (strpos($tag, ' defer') === false && strpos($tag, 'defer=') === false) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
    }
    
    return $tag;
}

// Включение Application Passwords для мобильного приложения WooCommerce
// Это необходимо для работы мобильного приложения WooCommerce
add_filter('wp_is_application_passwords_available', '__return_true', 10);

// Дополнительно: разрешаем Application Passwords даже без HTTPS (если нужно)
// ВНИМАНИЕ: Это менее безопасно, рекомендуется использовать HTTPS
// Раскомментируйте следующую строку только если сайт не использует HTTPS:
// add_filter('wp_is_application_passwords_available', function($available) {
//     return true;
// }, 10);
