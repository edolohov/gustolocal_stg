# Настройка Staging окружения

## Шаг 1: Настройка wp-config.php

На сервере в папке `/home/u850527203/domains/gustolocal.es/public_html/staging/` должен быть файл `wp-config.php`.

**Открой `wp-config.php` и измени следующие строки:**

```php
// База данных для staging
define( 'DB_NAME', 'u850527203_stg' );
define( 'DB_USER', 'u850527203' );
define( 'DB_PASSWORD', 'hiLKov15!' );
define( 'DB_HOST', 'localhost' );

// Префикс таблиц (ВАЖНО: staging_, а не stg_!)
$table_prefix = 'staging_';

// URL для staging
define('WP_HOME', 'https://staging.gustolocal.es');
define('WP_SITEURL', 'https://staging.gustolocal.es');

// Включить отладку для staging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

## Шаг 2: Замена URL в базе данных

После импорта базы данных нужно заменить все URL с production на staging.

**Используй скрипт `search-replace-urls.php`** (см. ниже) или выполни SQL запросы вручную в phpMyAdmin:

```sql
-- Замена в таблице options
UPDATE staging_options SET option_value = REPLACE(option_value, 'https://gustolocal.es', 'https://staging.gustolocal.es') WHERE option_name IN ('siteurl', 'home');
UPDATE staging_options SET option_value = REPLACE(option_value, 'https://gustolocal.es', 'https://staging.gustolocal.es');

-- Замена в таблице posts
UPDATE staging_posts SET post_content = REPLACE(post_content, 'https://gustolocal.es', 'https://staging.gustolocal.es');
UPDATE staging_posts SET guid = REPLACE(guid, 'https://gustolocal.es', 'https://staging.gustolocal.es');

-- Замена в таблице postmeta
UPDATE staging_postmeta SET meta_value = REPLACE(meta_value, 'https://gustolocal.es', 'https://staging.gustolocal.es') WHERE meta_value LIKE '%gustolocal.es%';

-- Замена в таблице usermeta
UPDATE stg_usermeta SET meta_value = REPLACE(meta_value, 'https://gustolocal.es', 'https://staging.gustolocal.es') WHERE meta_value LIKE '%gustolocal.es%';
```

## Шаг 3: Проверка работы сайта

1. Открой `https://staging.gustolocal.es` в браузере
2. Проверь, что сайт открывается
3. Попробуй войти в админку: `https://staging.gustolocal.es/wp-admin/`
   - Логин и пароль те же, что и на production

## Шаг 4: Отключение production интеграций

**Важно!** На staging нужно отключить:
- Платежные системы (Stripe, PayPal и т.д.)
- Email уведомления (или настроить тестовый email)
- Интеграции с внешними сервисами

Это можно сделать через настройки плагинов в админке WordPress.

## Шаг 5: Настройка .htaccess

Убедись, что файл `.htaccess` существует и содержит правильные правила для WordPress.

Если файла нет, создай его с содержимым:

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /staging/
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /staging/index.php [L]
</IfModule>
# END WordPress
```

**Примечание:** Если staging находится в корне поддомена (не в папке `/staging/`), используй:

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

