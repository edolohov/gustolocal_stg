jQuery(document).ready(function($) {
    // Handle delivery type change
    $('input[name="delivery_type"]').on('change', function() {
        var deliveryType = $(this).val();
        
        // Show loading state
        $('.delivery-options').addClass('loading');
        
        // Check if gustolocal_ajax is available
        if (typeof gustolocal_ajax === 'undefined') {
            console.error('gustolocal_ajax is not defined');
            $('.delivery-options').removeClass('loading');
            alert('Ошибка: скрипт не загружен правильно');
            return;
        }
        
        // Make AJAX request
        $.ajax({
            url: gustolocal_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_delivery_type',
                delivery_type: deliveryType,
                nonce: gustolocal_ajax.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    // Reload the page to update totals
                    window.location.reload();
                } else {
                    alert('Ошибка при обновлении типа доставки');
                    $('.delivery-options').removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('Ошибка при обновлении типа доставки');
                $('.delivery-options').removeClass('loading');
            }
        });
    });
});

