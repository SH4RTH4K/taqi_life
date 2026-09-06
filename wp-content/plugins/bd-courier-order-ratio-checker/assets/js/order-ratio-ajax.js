jQuery(document).ready(function($) {
    $('#refresh-courier-data').on('click', function() {
        var orderId = $(this).data('order-id');
        var button = $(this);

        button.prop('disabled', true).text('Refreshing...');

        $.ajax({
            url: orderRatioAjax.ajaxurl, // Using localized ajaxurl
            type: 'POST',
            data: {
                action: 'refresh_courier_data',
                order_id: orderId,
                nonce: orderRatioAjax.nonce // Correctly passing nonce as 'nonce'
            },
            success: function(response) {
                if (response.success) {
                    $('#courier-data-table').html(response.data.table); // Update table with new data
                    button.prop('disabled', false).text('Refresh Courier Data');
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Refresh Courier Data');
                }
            },
            error: function(xhr, status, error) {
                alert('Ajax error: ' + error);
                button.prop('disabled', false).text('Refresh Courier Data');
            }
        });
    });
});
