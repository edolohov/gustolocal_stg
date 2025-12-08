jQuery(document).ready(function($) {
    // Handle delivery type change
    $('input[name="delivery_type"]').on('change', function() {
        var deliveryType = $(this).val();
        
        // Show loading state
        $('.delivery-options').addClass('loading');
        
        // Make AJAX request
        $.ajax({
            url: wc_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'update_delivery_type',
                delivery_type: deliveryType,
                nonce: wc_cart_params.delivery_nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload the page to update totals
                    window.location.reload();
                } else {
                    alert('Ошибка при обновлении типа доставки');
                }
            },
            error: function() {
                alert('Ошибка при обновлении типа доставки');
            },
            complete: function() {
                $('.delivery-options').removeClass('loading');
            }
        });
    });
});
