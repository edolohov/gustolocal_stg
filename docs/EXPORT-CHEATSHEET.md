# 📝 Шпаргалка: Что экспортировать для Staging

## ✅ ВКЛЮЧИТЬ (Структура + Данные):

### Пользователи и настройки:
- ✅ `wp_users`
- ✅ `wp_usermeta`
- ✅ `wp_options`

### Категории и таксономии:
- ✅ `wp_terms`
- ✅ `wp_term_taxonomy`
- ✅ `wp_term_relationships`
- ✅ `wp_termmeta`

### Meal Builder (блюда):
- ✅ `wp_posts` (экспортируйте полностью, потом очистим)
- ✅ `wp_postmeta` (экспортируйте полностью, потом очистим)

### WooCommerce:
- ✅ `wp_wc_orders`
- ✅ `wp_wc_orders_meta`
- ✅ `wp_wc_order_addresses`
- ✅ `wp_wc_order_operational_data`
- ✅ `wp_wc_order_stats`
- ✅ `wp_wc_order_product_lookup`
- ✅ `wp_wc_order_tax_lookup`
- ✅ `wp_wc_order_coupon_lookup`
- ✅ `wp_wc_customer_lookup`
- ✅ `wp_wc_product_meta_lookup`
- ✅ `wp_wc_category_lookup`
- ✅ `wp_wc_tax_rate_classes`
- ✅ `wp_wc_reserved_stock`
- ✅ `wp_wc_webhooks` (если есть)

### Дополнительные WooCommerce таблицы (если есть):
- ✅ `wp_wc_download_log`
- ✅ `wp_wc_rate_limits`

## ✅ ТОЛЬКО СТРУКТУРА (БЕЗ данных):

- ✅ `wp_dish_feedback` (только структура)
- ✅ `wp_custom_feedback_requests` (только структура)
- ✅ `wp_custom_feedback_entries` (только структура)

## ❌ НЕ ВКЛЮЧАТЬ:

- ❌ `wp_comments`
- ❌ `wp_commentmeta`
- ❌ `wp_actionscheduler_*` (все таблицы)
- ❌ `wp_wf*` (Wordfence)
- ❌ `wp_post_smtp_*` (логи почты)
- ❌ `wp_links`
- ❌ Все остальные таблицы, не перечисленные выше

---

## После экспорта:

1. Замените `wp_` на `staging_` во всем файле
2. Импортируйте в staging базу
3. Выполните `clean-posts-after-import.sql` для очистки лишних постов
4. Исправьте права пользователя через `fix-user-rights-direct.php`

