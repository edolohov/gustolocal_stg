# Пошаговая настройка Staging базы данных

## Что нужно для разработки на staging:

1. **WooCommerce** - для тестирования корзины и чекаута
2. **Custom Checkout Fields** - для тестирования полей
3. **Weekly Meal Builder** - для доработки (фото, категории)
4. **Разбор заказов** - для доработки
5. **Обратная связь** - для доработки
6. **Пользователи и настройки WordPress** - для доступа
7. **Категории и теги блюд** - для meal builder

## ШАГ 1: Экспорт с продакшна

### В phpMyAdmin на продакшне (u850527203_5vYEq):

1. Выберите базу данных `u850527203_5vYEq`
2. Перейдите в "Exportar" (Экспорт)
3. Выберите "Personalizado" (Персонализированный)
4. Формат: **SQL**

### Выберите ТОЛЬКО эти таблицы:

#### Обязательные (структура + данные):
- `wp_users` - пользователи
- `wp_usermeta` - метаданные пользователей
- `wp_options` - настройки WordPress и плагинов
- `wp_terms` - категории и теги
- `wp_term_taxonomy` - таксономии
- `wp_term_relationships` - связи терминов
- `wp_termmeta` - метаданные терминов

#### WooCommerce (структура + данные):
- `wp_wc_orders` - заказы
- `wp_wc_orders_meta` - метаданные заказов
- `wp_wc_order_addresses` - адреса заказов
- `wp_wc_order_operational_data` - операционные данные
- `wp_wc_order_stats` - статистика заказов
- `wp_wc_order_product_lookup` - продукты в заказах
- `wp_wc_order_tax_lookup` - налоги
- `wp_wc_order_coupon_lookup` - купоны
- `wp_wc_customer_lookup` - клиенты
- `wp_wc_product_meta_lookup` - метаданные продуктов
- `wp_wc_category_lookup` - категории WooCommerce
- `wp_wc_tax_rate_classes` - классы налогов
- `wp_wc_reserved_stock` - зарезервированный склад

#### Meal Builder (структура + данные):
- `wp_posts` - посты (блюда meal builder)
- `wp_postmeta` - метаданные постов
- Все таблицы, начинающиеся с `wp_wmb_*` (если есть)

#### Кастомные функции (только структура, БЕЗ данных):
- `wp_dish_feedback` - обратная связь (структура)
- `wp_custom_feedback_requests` - кастомные опросы (структура)
- `wp_custom_feedback_entries` - записи кастомных опросов (структура)

#### НЕ выбирайте:
- ❌ `wp_posts` с контентом страниц (только блюда meal builder)
- ❌ `wp_comments` - комментарии не нужны
- ❌ `wp_actionscheduler_*` - планировщик задач
- ❌ Wordfence таблицы (`wp_wf*`)
- ❌ Логи и другие временные данные

### Настройки экспорта:

1. **Estructura (Структура)**: ✅ Включено для всех выбранных таблиц
2. **Datos (Данные)**: 
   - ✅ Включено для: users, usermeta, options, terms, WooCommerce, meal builder
   - ❌ Отключено для: dish_feedback, custom_feedback_* (только структура)

3. **Opciones específicas al formato**:
   - ✅ "Mostrar comentarios" - включено
   - ✅ "Incluye marca temporal" - включено

4. **Salida (Вывод)**:
   - ✅ "Guardar salida a un archivo" - сохранить в файл
   - Формат имени: `gustolocal-prod-export.sql`

5. Нажмите "Continuar" (Продолжить) и скачайте файл

## ШАГ 2: Подготовка SQL файла

После экспорта нужно:
1. Заменить префикс `wp_` на `staging_` во всем файле
2. Удалить данные из таблиц обратной связи (если они там есть)

## ШАГ 3: Импорт в staging

1. В phpMyAdmin выберите базу `u850527203_stg`
2. Перейдите в "Importar" (Импорт)
3. Выберите подготовленный SQL файл
4. Нажмите "Continuar"

## ШАГ 4: Проверка

После импорта проверьте:
- ✅ Пользователь ID=1 имеет права администратора
- ✅ WooCommerce активирован
- ✅ Weekly Meal Builder работает
- ✅ Таблицы обратной связи созданы (пустые)

