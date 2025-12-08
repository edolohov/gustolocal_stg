<?php
/**
 * Файл переводов для многоязычного сайта
 * Содержит все переводы для WooCommerce и других элементов
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Получить все переводы для указанного языка (с кэшированием)
 */
function get_translations($lang) {
    // Кэширование переводов для производительности
    static $cache = array();
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    
    $translations = array(
        'ru' => array(
            // WooCommerce основные строки
            'Add to cart' => 'Добавить в корзину',
            'View cart' => 'Посмотреть корзину',
            'Checkout' => 'Оформить заказ',
            'Cart' => 'Корзина',
            'Your cart is currently empty.' => 'Ваша корзина пуста.',
            'Update cart' => 'Обновить корзину',
            'Proceed to checkout' => 'Перейти к оформлению',
            'Remove this item' => 'Удалить товар',
            'Quantity' => 'Количество',
            'Price' => 'Цена',
            'Total' => 'Итого',
            'Subtotal' => 'Промежуточный итог',
            'Shipping' => 'Доставка',
            'Tax' => 'Налог',
            'Order total' => 'Итого к оплате',
            'Billing details' => 'Детали оплаты',
            'Shipping details' => 'Детали доставки',
            'Place order' => 'Оформить заказ',
            'Order received' => 'Заказ получен',
            'Thank you. Your order has been received.' => 'Спасибо. Ваш заказ получен.',
            'Order details' => 'Детали заказа',
            'Order number' => 'Номер заказа',
            'Date' => 'Дата',
            'Email' => 'Email',
            'Phone' => 'Телефон',
            'Address' => 'Адрес',
            'Payment method' => 'Способ оплаты',
            'Order notes' => 'Примечания к заказу',
            
            // Дополнительные переводы для сообщений
            'has been added to your cart.' => 'добавлен в корзину.',
            'have been added to your cart.' => 'добавлены в корзину.',
            
            // Поля формы
            'First name' => 'Имя',
            'Last name' => 'Фамилия',
            'Company' => 'Компания',
            'Apartment, suite, etc.' => 'Квартира, дом и т.д.',
            'City' => 'Город',
            'State' => 'Область',
            'Postcode' => 'Почтовый индекс',
            'Country' => 'Страна',
            
            // Кастомные поля
            'Детали' => 'Детали',
            'Способ получения заказа' => 'Способ получения заказа',
            'Код купона' => 'Код купона',
            'Применить купон' => 'Применить купон',
            'Сумма заказов' => 'Сумма заказов',
            'Доставка' => 'Доставка',
            'Товар' => 'Товар',
            'Actualizar carrito' => 'Обновить корзину',
            'Мессенджер' => 'Мессенджер',
            'Укажите домофон, этаж и квартиру' => 'Укажите домофон, этаж и квартиру',
            'Что удобней: telegram или whatsApp' => 'Что удобней: telegram или whatsApp',
            'Примечания к заказу, например, указания по доставке.' => 'Примечания к заказу, например, указания по доставке.',
            'Доставка до двери в Валенсии' => 'Доставка до двери в Валенсии',
            'Заберу самостоятельно' => 'Заберу самостоятельно',
            'Бесплатно' => 'Бесплатно',
            '(необязательно)' => '(необязательно)',
            
            // Переводы для страницы подтверждения заказа
            'Спасибо, что выбрали нас! ???? Заказ уже у нас, чуть позже мы свяжемся и подтвердим всё.' => 'Спасибо, что выбрали нас! ???? Заказ уже у нас, чуть позже мы свяжемся и подтвердим всё.',
            'Ваши данные' => 'Ваши данные',
            'Итого' => 'Итого',
            'Доставка:' => 'Доставка:',
            'Total:' => 'Итого:',
            
            // Переводы для страницы 404
            'Страница не найдена' => 'Страница не найдена',
            'Страница, которую вы ищете, не существует или была перемещена. Пожалуйста, попробуйте выполнить поиск, используя форму ниже.' => 'Страница, которую вы ищете, не существует или была перемещена. Пожалуйста, попробуйте выполнить поиск, используя форму ниже.',
            'Поиск' => 'Поиск'
        ),
        
        'es' => array(
            // WooCommerce основные строки
            'Add to cart' => 'Añadir al carrito',
            'View cart' => 'Ver carrito',
            'Checkout' => 'Finalizar compra',
            'Cart' => 'Carrito',
            'Your cart is currently empty.' => 'Tu carrito está vacío.',
            'Update cart' => 'Actualizar carrito',
            'Proceed to checkout' => 'Proceder al pago',
            'Remove this item' => 'Eliminar este artículo',
            'Quantity' => 'Cantidad',
            'Price' => 'Precio',
            'Total' => 'Total',
            'Subtotal' => 'Subtotal',
            'Shipping' => 'Envío',
            'Tax' => 'Impuesto',
            'Order total' => 'Total del pedido',
            'Billing details' => 'Detalles de facturación',
            'Shipping details' => 'Detalles de envío',
            'Place order' => 'Realizar pedido',
            'Order received' => 'Pedido recibido',
            'Thank you. Your order has been received.' => 'Gracias. Tu pedido ha sido recibido.',
            'Order details' => 'Detalles del pedido',
            'Order number' => 'Número de pedido',
            'Date' => 'Fecha',
            'Email' => 'Email',
            'Phone' => 'Teléfono',
            'Address' => 'Dirección',
            'Payment method' => 'Método de pago',
            'Order notes' => 'Notas del pedido',
            
            // Дополнительные переводы для сообщений
            'has been added to your cart.' => 'ha sido añadido a tu carrito.',
            'have been added to your cart.' => 'han sido añadidos a tu carrito.',
            
            // Поля формы
            'First name' => 'Nombre',
            'Last name' => 'Apellidos',
            'Company' => 'Empresa',
            'Apartment, suite, etc.' => 'Apartamento, suite, etc.',
            'City' => 'Ciudad',
            'State' => 'Provincia',
            'Postcode' => 'Código postal',
            'Country' => 'País',
            
            // Кастомные поля
            'Детали' => 'Detalles',
            'Способ получения заказа' => 'Método de recepción del pedido',
            'Код купона' => 'Código de cupón',
            'Применить купон' => 'Aplicar cupón',
            'Сумма заказов' => 'Total del pedido',
            'Доставка' => 'Envío',
            'Товар' => 'Producto',
            'Actualizar carrito' => 'Actualizar carrito',
            'Мессенджер' => 'Mensajería',
            'Укажите домофон, этаж и квартиру' => 'Especifica portero, piso y apartamento',
            'Что удобней: telegram или whatsApp' => '¿Qué es más conveniente: telegram o whatsApp?',
            'Примечания к заказу, например, указания по доставке.' => 'Notas del pedido, por ejemplo, instrucciones de entrega.',
            'Доставка до двери в Валенсии' => 'Entrega a domicilio en Valencia',
            'Заберу самостоятельно' => 'Recogeré yo mismo',
            'Бесплатно' => 'Gratis',
            '(необязательно)' => '(opcional)',
            
            // Переводы для страницы подтверждения заказа
            'Спасибо, что выбрали нас! ???? Заказ уже у нас, чуть позже мы свяжемся и подтвердим всё.' => '¡Gracias por elegirnos! ???? El pedido ya está con nosotros, nos pondremos en contacto un poco más tarde y confirmaremos todo.',
            'Ваши данные' => 'Tus datos',
            'Итого' => 'Total',
            'Доставка:' => 'Envío:',
            'Total:' => 'Total:',
            // Возможные заголовки таблиц
            'Product' => 'Producto',
            'Total' => 'Total',
            
            // Переводы для страницы 404
            'Страница не найдена' => 'Página no encontrada',
            'Страница, которую вы ищете, не существует или была перемещена. Пожалуйста, попробуйте выполнить поиск, используя форму ниже.' => 'La página que buscas no existe o ha sido movida. Por favor, intenta buscar usando el formulario de abajo.',
            'Поиск' => 'Buscar'
        ),
        
        'en' => array(
            // WooCommerce основные строки (остаются как есть)
            'Add to cart' => 'Add to cart',
            'View cart' => 'View cart',
            'Checkout' => 'Checkout',
            'Cart' => 'Cart',
            'Your cart is currently empty.' => 'Your cart is currently empty.',
            'Update cart' => 'Update cart',
            'Proceed to checkout' => 'Proceed to checkout',
            'Remove this item' => 'Remove this item',
            'Quantity' => 'Quantity',
            'Price' => 'Price',
            'Total' => 'Total',
            'Subtotal' => 'Subtotal',
            'Shipping' => 'Shipping',
            'Tax' => 'Tax',
            'Order total' => 'Order total',
            'Billing details' => 'Billing details',
            'Shipping details' => 'Shipping details',
            'Place order' => 'Place order',
            'Order received' => 'Order received',
            'Thank you. Your order has been received.' => 'Thank you. Your order has been received.',
            'Order details' => 'Order details',
            'Order number' => 'Order number',
            'Date' => 'Date',
            'Email' => 'Email',
            'Phone' => 'Phone',
            'Address' => 'Address',
            'Payment method' => 'Payment method',
            'Order notes' => 'Order notes',
            
            // Дополнительные переводы для сообщений (остаются как есть)
            'has been added to your cart.' => 'has been added to your cart.',
            'have been added to your cart.' => 'have been added to your cart.',
            
            // Поля формы (остаются как есть)
            'First name' => 'First name',
            'Last name' => 'Last name',
            'Company' => 'Company',
            'Apartment, suite, etc.' => 'Apartment, suite, etc.',
            'City' => 'City',
            'State' => 'State',
            'Postcode' => 'Postcode',
            'Country' => 'Country',
            
            // Кастомные поля
            'Детали' => 'Details',
            'Способ получения заказа' => 'Order delivery method',
            'Код купона' => 'Coupon code',
            'Применить купон' => 'Apply coupon',
            'Сумма заказов' => 'Order total',
            'Доставка' => 'Shipping',
            'Товар' => 'Product',
            'Actualizar carrito' => 'Update cart',
            'Мессенджер' => 'Messenger',
            'Укажите домофон, этаж и квартиру' => 'Specify intercom, floor and apartment',
            'Что удобней: telegram или whatsApp' => 'What is more convenient: telegram or whatsApp?',
            'Примечания к заказу, например, указания по доставке.' => 'Order notes, for example, delivery instructions.',
            'Доставка до двери в Валенсии' => 'Door delivery in Valencia',
            'Заберу самостоятельно' => 'I will pick up myself',
            'Бесплатно' => 'Free',
            '(необязательно)' => '(optional)',
            
            // Переводы для страницы подтверждения заказа
            'Спасибо, что выбрали нас! ???? Заказ уже у нас, чуть позже мы свяжемся и подтвердим всё.' => 'Thank you for choosing us! ???? The order is already with us, we will contact you a little later and confirm everything.',
            'Ваши данные' => 'Your data',
            'Итого' => 'Total',
            'Доставка:' => 'Shipping:',
            'Total:' => 'Total:',
            // Возможные заголовки таблиц (на англ оставляем как есть)
            'Product' => 'Product',
            'Total' => 'Total',
            
            // Переводы для страницы 404
            'Страница не найдена' => 'Page Not Found',
            'Страница, которую вы ищете, не существует или была перемещена. Пожалуйста, попробуйте выполнить поиск, используя форму ниже.' => 'The page you are looking for does not exist or has been moved. Please try searching using the form below.',
            'Поиск' => 'Search'
        )
    );
    
    $result = isset($translations[$lang]) ? $translations[$lang] : array();
    $cache[$lang] = $result; // Сохраняем в кэш
    return $result;
}

/**
 * Получить перевод для конкретной строки
 */
function get_translation($text, $lang) {
    $translations = get_translations($lang);
    return isset($translations[$text]) ? $translations[$text] : $text;
}
