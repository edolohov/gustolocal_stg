# Установка WordPress на Staging

## ⚠️ ВАЖНО: Безопасная установка

Мы устанавливаем WordPress в **отдельную папку** `/staging/`, которая не пересекается с production сайтом.

Production сайт находится в: `/home/u850527203/domains/gustolocal.es/public_html/`
Staging сайт будет в: `/home/u850527203/domains/gustolocal.es/public_html/staging/`

**Они полностью изолированы друг от друга!**

---

## Шаг 1: Переименование префикса таблиц (если еще не сделано)

1. Зайди в phpMyAdmin → база данных `u850527203_stg`
2. Открой вкладку **SQL**
3. Скопируй и выполни содержимое файла `docs/rename-tables-prefix.sql`
   - Или выполни SQL запросы вручную для всех таблиц с префиксом `stg_`

**Альтернативный способ (через скрипт):**
- Используй скрипт `docs/rename-tables-prefix-auto.php` (если создан)

---

## Шаг 2: Распаковка WordPress

1. Распакуй скачанный ZIP файл WordPress на своем компьютере
2. У тебя должна получиться папка `wordpress` с файлами внутри

---

## Шаг 3: Загрузка файлов на сервер

**Через FTP/File Manager:**

1. Подключись к серверу через FTP или открой File Manager в панели Hostinger
2. Перейди в папку: `/home/u850527203/domains/gustolocal.es/public_html/staging/`
3. **Убедись, что папка `staging` существует и пуста** (или содержит только нужные файлы)
4. Загрузи **все содержимое** папки `wordpress` в папку `staging/`
   - То есть файлы должны быть в `/staging/`, а не в `/staging/wordpress/`

**Структура должна быть такой:**
```
/home/u850527203/domains/gustolocal.es/public_html/
├── staging/
│   ├── wp-admin/
│   ├── wp-content/
│   ├── wp-includes/
│   ├── index.php
│   ├── wp-config.php (создадим на следующем шаге)
│   └── ... (другие файлы WordPress)
└── ... (файлы production сайта, если есть)
```

---

## Шаг 4: Создание wp-config.php

1. В папке `staging/` найди файл `wp-config-sample.php`
2. Скопируй его и переименуй в `wp-config.php`
3. Открой `wp-config.php` и измени следующие строки:

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

4. Сохрани файл

---

## Шаг 5: Настройка .htaccess

1. В папке `staging/` создай или отредактируй файл `.htaccess`
2. Добавь следующее содержимое:

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

**Важно:** `RewriteBase /staging/` — это путь относительно корня домена

---

## Шаг 6: Замена URL в базе данных

После установки WordPress нужно заменить все URL с production на staging.

**Используй скрипт `docs/search-replace-urls.php`** (обновлен для префикса `staging_`):

1. Загрузи файл `docs/search-replace-urls.php` на сервер в папку `staging/`
2. Открой в браузере: `https://staging.gustolocal.es/search-replace-urls.php?key=YOUR_SECRET_KEY`
3. После выполнения удали файл с сервера!

**Или выполни SQL запросы вручную в phpMyAdmin:**

```sql
-- Замена в таблице options
UPDATE staging_options SET option_value = REPLACE(option_value, 'https://gustolocal.es', 'https://staging.gustolocal.es') WHERE option_name IN ('siteurl', 'home');
UPDATE staging_options SET option_value = REPLACE(option_value, 'https://gustolocal.es', 'https://staging.gustolocal.es');

-- Замена в таблице posts
UPDATE staging_posts SET post_content = REPLACE(post_content, 'https://gustolocal.es', 'https://staging.gustolocal.es');
UPDATE staging_posts SET guid = REPLACE(guid, 'https://gustolocal.es', 'https://staging.gustolocal.es');

-- Замена в таблице postmeta
UPDATE staging_postmeta SET meta_value = REPLACE(meta_value, 'https://gustolocal.es', 'https://staging.gustolocal.es') WHERE meta_value LIKE '%gustolocal.es%';
```

---

## Шаг 7: Проверка работы

1. Открой `https://staging.gustolocal.es` в браузере
2. Должен открыться сайт (может быть с ошибками, это нормально на первом этапе)
3. Открой `https://staging.gustolocal.es/wp-admin/`
4. Войди с теми же логином и паролем, что и на production

---

## Шаг 8: Загрузка темы и плагинов

После входа в админку нужно загрузить:
1. Тему `gustolocal` в `wp-content/themes/gustolocal/`
2. Плагины (weekly-meal-builder и другие)
3. WooCommerce шаблоны (если нужны)

**Или используй Git для синхронизации файлов.**

---

## 🆘 Если что-то пошло не так:

1. **Ошибка подключения к базе данных:**
   - Проверь настройки в wp-config.php
   - Проверь, что база данных `u850527203_stg` существует
   - Проверь, что префикс таблиц `staging_` правильный

2. **404 ошибка или "Страница не найдена":**
   - Проверь файл `.htaccess` в папке `staging/`
   - Проверь настройки `WP_HOME` и `WP_SITEURL` в wp-config.php
   - В админке: Настройки → Постоянные ссылки → Сохранить изменения

3. **Сайт показывает production контент:**
   - Выполни замену URL (шаг 6)
   - Очисти кеш браузера

4. **Ошибки в админке:**
   - Проверь файл `wp-content/debug.log`
   - Убедись, что WP_DEBUG включен в wp-config.php

