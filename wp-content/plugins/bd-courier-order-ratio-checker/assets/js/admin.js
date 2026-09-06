jQuery(document).ready(function($) {
    // Ensure bdcourierSearchAjax is available
    if (typeof bdcourierSearchAjax === 'undefined') {
        return;
    }

    // View Details button - opens popup (both eye icon and view details button)
    // Also make entire column clickable
    $(document).on('click', '.bdc-view-details-btn, .bdc-view-eye-btn, .bdc-ratio-minimal, .bdc-view-eye-icon, .bdc-minimal-counts, .bdc-minimal-second-line, .bdc-minimal-progress-bar', function(e) {
        // Stop event from bubbling to table row - CRITICAL to prevent navigation
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        // If clicking inside .bdc-ratio-minimal, use the container; otherwise use the clicked element
        const clickedElement = $(this);
        const ratioContainer = clickedElement.hasClass('bdc-ratio-minimal') ? clickedElement : clickedElement.closest('.bdc-ratio-minimal');
        const element = ratioContainer.length ? ratioContainer : clickedElement;
        
        // If we couldn't find the container, don't proceed
        if (!element.length || !element.hasClass('bdc-ratio-minimal')) {
            return false;
        }
        
        // Get data attributes - handle both data attributes and data-* attributes
        let orderId = element.attr('data-order-id') || element.data('order-id');
        let phone = element.attr('data-phone') || element.data('phone');
        let initialData = element.attr('data-initial-data') || element.data('initial-data');
        let relatedOrders = element.attr('data-related-orders') || element.data('related-orders');
        
        // Convert to numbers if needed
        if (orderId) {
            orderId = parseInt(orderId, 10);
        }
        
        // Parse JSON strings if they exist
        if (typeof initialData === 'string' && initialData && initialData !== 'null' && initialData !== 'false') {
            try {
                initialData = JSON.parse(initialData);
            } catch (e) {
                initialData = null;
            }
        }
        if (typeof relatedOrders === 'string' && relatedOrders && relatedOrders !== 'null' && relatedOrders !== 'false') {
            try {
                relatedOrders = JSON.parse(relatedOrders);
            } catch (e) {
                relatedOrders = null;
            }
        }
        
        if (!orderId || !phone) {
            if (window.bdcourierShowNotification) {
                window.bdcourierShowNotification('error', 'Missing order ID or phone number', 'Error');
            }
            return false;
        }
        
        // Remove any existing popup first
        $('.bdc-popup-overlay').remove();
        
        // Create popup overlay
        const overlay = $('<div class="bdc-popup-overlay"></div>');
        const popupContainer = $('<div id="bdcourier-popup-root" class="bdc-popup-container"></div>');
        
        popupContainer.attr('data-order-id', orderId);
        popupContainer.attr('data-phone', phone);
        if (initialData) {
            popupContainer.attr('data-initial-data', JSON.stringify(initialData));
        }
        if (relatedOrders) {
            popupContainer.attr('data-related-orders', JSON.stringify(relatedOrders));
        }
        
        overlay.append(popupContainer);
        $('body').append(overlay);
        
        // Initialize React popup if script is loaded
        if (typeof BDCourierPopup !== 'undefined' && typeof BDCourierPopup.initCourierPopup === 'function') {
            BDCourierPopup.initCourierPopup();
        } else {
            // Fallback: try to initialize after a short delay
            setTimeout(function() {
                if (typeof BDCourierPopup !== 'undefined' && typeof BDCourierPopup.initCourierPopup === 'function') {
                    BDCourierPopup.initCourierPopup();
                }
            }, 100);
        }
        
        return false;
    });

    // Refresh button for both Order Edit and Orders List pages
    $(document).on('click', '.bdcrc-refresh-button, .bdc-refresh-btn, .bdc-check-btn', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent event bubbling
        
        const button = $(this);
        const orderId = button.data('order-id');
        const context = button.data('context'); // "edit" or "list"
        
        // Validate required data
        if (!orderId || !context) {
            if (window.bdcourierShowNotification) {
                window.bdcourierShowNotification('error', 'Missing order ID or context', 'Error');
            } else {
                alert('Error: Missing order ID or context');
            }
            return false;
        }
        
        // Update button label based on context with Dashicon and spin animation
        const originalHtml = button.html();
        if (context === 'edit') {
            button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> রিফ্রেশ হচ্ছে...');
        } else {
            button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span>');
        }
        
        const action = context === 'edit' ? 'refresh_courier_data_edit' : 'refresh_courier_data_list';
        
        $.ajax({
            url: bdcourierSearchAjax.ajaxurl,
            type: 'POST',
            data: {
                action: action,
                order_id: orderId,
                nonce: bdcourierSearchAjax.refresh_nonce
            },
            success: function(response) {
                if (response.success) {
                    if (context === 'edit') {
                        $('#courier-data-table').html(response.data.table);
                        button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> রিফ্রেশ কুরিয়ার ডেটা');
                    } else {
                        // For list view, response.data.html contains the new HTML
                        $('#order-ratio-' + orderId).html(response.data.html || response.data.table || '');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span><span class="bdc-refresh-text">' + (button.find('.bdc-refresh-text').text() || 'Refresh') + '</span>');
                    }
                } else {
                    const errorMessage = response.data || 'Unknown error';
                    if (window.bdcourierShowNotification) {
                        window.bdcourierShowNotification('error', errorMessage, 'Error');
                    } else {
                        alert('Error: ' + errorMessage);
                    }
                    restoreButton(context, button, originalHtml);
                }
            },
            error: function(xhr, status, error) {
                const errorMessage = 'Ajax error: ' + error;
                if (window.bdcourierShowNotification) {
                    window.bdcourierShowNotification('error', errorMessage, 'Network Error');
                } else {
                    alert(errorMessage);
                }
                restoreButton(context, button, originalHtml);
            }
        });
        return false;
    });

    // Helper function to restore the button label after Ajax completes
    function restoreButton(context, button, originalHtml) {
        if (originalHtml) {
            button.prop('disabled', false).html(originalHtml);
        } else {
            if (context === 'edit') {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> রিফ্রেশ কুরিয়ার ডেটা');
            } else {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span>');
            }
        }
    }

    // Helper: Format phone number.
    function formatPhoneNumber(phone) {
        // Remove non-numeric characters.
        phone = phone.replace(/[^0-9]/g, '');
        // Check prefix with indexOf for broader browser support
        if (phone.indexOf('880') === 0) {
            phone = phone.slice(3);
        } else if (phone.indexOf('0') === 0) {
            phone = phone.slice(1);
        }
        if (phone.length === 10) {
            phone = '0' + phone;
        }
        return phone;
    }
});
