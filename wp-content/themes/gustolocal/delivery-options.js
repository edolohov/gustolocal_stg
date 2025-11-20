jQuery(function($) {
    function handleDeliveryChange(event) {
        var $input = $(event.target);
        if (!$input.is('input[name="delivery_type"]')) {
            return;
        }

        var deliveryType = $input.val();
        var $cartForm = $input.closest('form.woocommerce-cart-form');
        var $checkoutForm = $input.closest('form.checkout');
        var $container = $input.closest('.delivery-options, .delivery-options-checkout');

        if (typeof gustolocal_ajax === 'undefined') {
            console.error('gustolocal_ajax is not defined');
            if ($container.length) {
                $container.removeClass('loading');
            }
            alert('Ошибка: скрипт не загружен правильно');
            return;
        }

        if ($container.length) {
            $container.addClass('loading');
        }

        var request = {
            url: gustolocal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_delivery_type',
                delivery_type: deliveryType,
                nonce: gustolocal_ajax.nonce
            }
        };

        if ($cartForm.length) {
            $.ajax(request).done(function(response) {
                if (response && response.success) {
                    window.location.reload();
                } else {
                    alert('Ошибка при обновлении типа доставки');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('Ошибка при обновлении типа доставки');
            }).always(function() {
                if ($container.length) {
                    $container.removeClass('loading');
                }
            });
        } else if ($checkoutForm.length) {
            $.ajax(request).always(function() {
                $(document.body).trigger('update_checkout');
                if ($container.length) {
                    $container.removeClass('loading');
                }
            });
        }
    }

    $(document.body).on('change', 'input[name="delivery_type"]', handleDeliveryChange);
});

