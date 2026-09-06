/**
 * WooCommerce Checkout Browser Fingerprint Integration
 * Collects browser fingerprint and sends it with checkout form
 * Also tracks form changes in real-time and updates incomplete orders
 */

(function($) {
    'use strict';

    var updateTimeout = null;
    var lastUpdateData = {};

    function initFingerprint() {
        // Only run on checkout page - support both WooCommerce and CartFlows
        var isCheckoutPage = $('body').hasClass('woocommerce-checkout') || 
                            $('body').hasClass('cartflows-checkout') ||
                            $('body').hasClass('cartflow-checkout') ||
                            $('.cartflows-checkout-form').length > 0 ||
                            $('form.checkout').length > 0;
        
        if (!isCheckoutPage) {
            return;
        }

        // Wait for fingerprint script to load - try multiple times
        var attempts = 0;
        var maxAttempts = 10;
        
        function tryGetFingerprint() {
            attempts++;
            
            if (typeof window.bdcGetFingerprint === 'undefined') {
                if (attempts < maxAttempts) {
                    setTimeout(tryGetFingerprint, 200);
                    return;
                }
                return;
            }

            // Get browser fingerprint
            var fingerprint = window.bdcGetFingerprint();
            
            if (!fingerprint) {
                return;
            }


            // Add hidden field to checkout form - support both WooCommerce and CartFlows
            var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
            
            if ($checkoutForm.length) {
                // Remove existing fingerprint field if present
                $checkoutForm.find('input[name="bdc_browser_fingerprint"]').remove();
                
                // Add fingerprint as hidden field
                var $fingerprintInput = $('<input>', {
                    type: 'hidden',
                    name: 'bdc_browser_fingerprint',
                    value: fingerprint
                });
                
                $checkoutForm.append($fingerprintInput);
                
                // Store fingerprint globally for real-time updates
                window.bdcFingerprint = fingerprint;
                
                // Initialize real-time tracking
                initRealtimeTracking(fingerprint);
            } else {
                // Retry if form not found (CartFlows may load dynamically)
                setTimeout(function() {
                    $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
                    if ($checkoutForm.length) {
                        $checkoutForm.find('input[name="bdc_browser_fingerprint"]').remove();
                        var $fingerprintInput = $('<input>', {
                            type: 'hidden',
                            name: 'bdc_browser_fingerprint',
                            value: fingerprint
                        });
                        $checkoutForm.append($fingerprintInput);
                        window.bdcFingerprint = fingerprint;
                        initRealtimeTracking(fingerprint);
                    }
                }, 500);
            }
        }
        
        tryGetFingerprint();
    }

    /**
     * Initialize real-time tracking of form changes
     */
    function initRealtimeTracking(fingerprint) {
        if (!window.bdcFingerprintAjax || !window.bdcFingerprintAjax.ajaxurl) {
            return;
        }

        // Support both WooCommerce and CartFlows checkout forms
        var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"]');
        if (!$checkoutForm.length) {
            return;
        }

        // Debounced function to update incomplete order
        function updateIncompleteOrder() {
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }

            updateTimeout = setTimeout(function() {
                var phone = $checkoutForm.find('#billing_phone').val() || '';
                var phoneDigits = phone.replace(/[^0-9]/g, '');
                
                // Only update if phone number is valid (at least 7 digits)
                if (phoneDigits.length < 7) {
                    return; // Don't update if phone is invalid
                }

                // Collect form data
                var formData = collectFormData($checkoutForm);
                
                // Only send if data has changed
                if (JSON.stringify(formData) === JSON.stringify(lastUpdateData)) {
                    return;
                }
                
                lastUpdateData = formData;

                // Send AJAX request to update incomplete order
                $.ajax({
                    url: window.bdcFingerprintAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bdc_update_incomplete_order',
                        nonce: window.bdcFingerprintAjax.nonce,
                        fingerprint: fingerprint,
                        phone: phoneDigits,
                        email: formData.email || '',
                        billing_data: JSON.stringify(formData.billing),
                        shipping_data: JSON.stringify(formData.shipping),
                        cart_data: JSON.stringify(formData.cart),
                        checkout_step: determineCheckoutStep($checkoutForm),
                        payment_method: $checkoutForm.find('input[name="payment_method"]:checked').val() || ''
                    },
                    success: function(response) {
                        if (response.success) {
                        }
                    },
                    error: function(xhr, status, error) {
                    }
                });
            }, 1000); // Wait 1 second after last change
        }

        // Track changes on billing phone field (most important)
        $checkoutForm.on('input change blur', '#billing_phone', function() {
            updateIncompleteOrder();
        });

        // Track changes on other billing fields
        $checkoutForm.on('input change blur', '#billing_email, #billing_first_name, #billing_last_name, #billing_company, #billing_address_1, #billing_address_2, #billing_city, #billing_state, #billing_postcode, #billing_country', function() {
            updateIncompleteOrder();
        });

        // Track changes on shipping fields
        $checkoutForm.on('input change blur', '#shipping_first_name, #shipping_last_name, #shipping_company, #shipping_address_1, #shipping_address_2, #shipping_city, #shipping_state, #shipping_postcode, #shipping_country', function() {
            updateIncompleteOrder();
        });

        // Track payment method changes
        $checkoutForm.on('change', 'input[name="payment_method"]', function() {
            updateIncompleteOrder();
        });

        // Track when WooCommerce updates the checkout (AJAX updates)
        $(document.body).on('updated_checkout', function() {
            updateIncompleteOrder();
        });

        // Also update on form submit
        $checkoutForm.on('checkout_place_order', function() {
            updateIncompleteOrder();
        });
    }

    /**
     * Collect form data from checkout form
     */
    function collectFormData($form) {
        var data = {
            billing: {},
            shipping: {},
            cart: {},
            email: ''
        };

        // Collect billing data
        data.billing = {
            first_name: $form.find('#billing_first_name').val() || '',
            last_name: $form.find('#billing_last_name').val() || '',
            company: $form.find('#billing_company').val() || '',
            address_1: $form.find('#billing_address_1').val() || '',
            address_2: $form.find('#billing_address_2').val() || '',
            city: $form.find('#billing_city').val() || '',
            state: $form.find('#billing_state').val() || '',
            postcode: $form.find('#billing_postcode').val() || '',
            country: $form.find('#billing_country').val() || '',
            phone: $form.find('#billing_phone').val() || '',
            email: $form.find('#billing_email').val() || ''
        };

        data.email = data.billing.email;

        // Collect shipping data
        var shipToDifferent = $form.find('#ship-to-different-address-checkbox').is(':checked');
        if (shipToDifferent) {
            data.shipping = {
                first_name: $form.find('#shipping_first_name').val() || '',
                last_name: $form.find('#shipping_last_name').val() || '',
                company: $form.find('#shipping_company').val() || '',
                address_1: $form.find('#shipping_address_1').val() || '',
                address_2: $form.find('#shipping_address_2').val() || '',
                city: $form.find('#shipping_city').val() || '',
                state: $form.find('#shipping_state').val() || '',
                postcode: $form.find('#shipping_postcode').val() || '',
                country: $form.find('#shipping_country').val() || ''
            };
        } else {
            data.shipping = data.billing;
        }

        // Collect cart data from WooCommerce
        data.cart = collectCartData();

        return data;
    }

    /**
     * Collect cart data from WooCommerce
     */
    function collectCartData() {
        var cartData = {
            items: [],
            cart_total: '0',
            cart_subtotal: '0',
            cart_tax: '0',
            cart_shipping: '0',
            cart_discount: '0',
            item_count: 0,
            currency: ''
        };

        try {
            // Try to get cart data from order review section
            var $orderReview = $('.woocommerce-checkout-review-order-table, #order_review');
            
            if ($orderReview.length) {
                // Get cart items
                $orderReview.find('tr.cart-item, tr.order_item').each(function() {
                    var $item = $(this);
                    var productName = $item.find('.product-name').text().trim() || 
                                     $item.find('td.product-name').text().trim() || '';
                    var quantity = $item.find('.product-quantity').text().trim() || 
                                  $item.find('td.product-quantity').text().trim() || '1';
                    var price = $item.find('.product-total').text().trim() || 
                               $item.find('td.product-total').text().trim() || '0';
                    
                    // Extract quantity number
                    var qtyMatch = quantity.match(/\d+/);
                    var qty = qtyMatch ? parseInt(qtyMatch[0]) : 1;
                    
                    cartData.items.push({
                        product_name: productName,
                        quantity: qty,
                        line_total: price
                    });
                });

                // Get totals
                var $subtotal = $orderReview.find('tr.cart-subtotal td, tr.order-subtotal td').last();
                if ($subtotal.length) {
                    cartData.cart_subtotal = $subtotal.text().trim() || '0';
                }

                var $shipping = $orderReview.find('tr.shipping td, tr.order-shipping td').last();
                if ($shipping.length) {
                    cartData.cart_shipping = $shipping.text().trim() || '0';
                }

                var $tax = $orderReview.find('tr.tax-rate td, tr.order-tax td').last();
                if ($tax.length) {
                    cartData.cart_tax = $tax.text().trim() || '0';
                }

                var $total = $orderReview.find('tr.order-total td, tr.cart-total td').last();
                if ($total.length) {
                    cartData.cart_total = $total.text().trim() || '0';
                }

                cartData.item_count = cartData.items.length;
            }

            // Try to get currency from page
            if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.currency) {
                cartData.currency = wc_checkout_params.currency;
            } else if (typeof wc_cart_params !== 'undefined' && wc_cart_params.currency) {
                cartData.currency = wc_cart_params.currency;
            } else {
                // Try to extract from total
                var currencyMatch = cartData.cart_total.match(/[^\d\s.,]+/);
                if (currencyMatch) {
                    cartData.currency = currencyMatch[0];
                }
            }

        } catch (e) {
        }

        return cartData;
    }

    /**
     * Determine current checkout step
     */
    function determineCheckoutStep($form) {
        var phone = $form.find('#billing_phone').val() || '';
        var email = $form.find('#billing_email').val() || '';
        var paymentMethod = $form.find('input[name="payment_method"]:checked').val();
        var terms = $form.find('#terms').is(':checked');

        if (!phone || !email) {
            return 'billing_info';
        } else if (!paymentMethod) {
            return 'payment_method';
        } else if (!terms) {
            return 'review';
        } else {
            return 'processing';
        }
    }

    /**
     * Check blocked entities on page load and when phone number is entered
     */
    var blockedCheckTimeout = null;
    var isBlocked = false;

    function checkBlockedEntitiesOnLoad() {
        // Support both WooCommerce and CartFlows checkout pages
        var isCheckoutPage = $('body').hasClass('woocommerce-checkout') || 
                            $('body').hasClass('cartflows-checkout') ||
                            $('body').hasClass('cartflow-checkout') ||
                            $('.cartflows-checkout-form').length > 0 ||
                            $('form.checkout').length > 0;
        
        if (!isCheckoutPage) {
            return;
        }

        // Support both WooCommerce and CartFlows checkout forms
        var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
        if (!$checkoutForm.length) {
            // Wait a bit for form to load (CartFlows may load dynamically)
            setTimeout(function() {
                $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
                if ($checkoutForm.length) {
                    performBlockedEntitiesCheck($checkoutForm);
                }
            }, 500);
            return;
        }
        // Check on page load
        performBlockedEntitiesCheck($checkoutForm);

        // Check when phone number is entered (with debouncing)
        $checkoutForm.on('input change blur', '#billing_phone', function() {
            var phone = $(this).val() || '';
            var phoneDigits = phone.replace(/[^0-9]/g, '');
            
            // Only check if phone has at least 7 digits
            if (phoneDigits.length >= 7) {
                if (blockedCheckTimeout) {
                    clearTimeout(blockedCheckTimeout);
                }
                
                blockedCheckTimeout = setTimeout(function() {
                    performBlockedEntitiesCheck($checkoutForm);
                }, 500); // Wait 500ms after user stops typing
            }
        });

        // Also check when email is entered
        $checkoutForm.on('input change blur', '#billing_email', function() {
            var email = $(this).val() || '';
            if (email && email.indexOf('@') > 0) {
                if (blockedCheckTimeout) {
                    clearTimeout(blockedCheckTimeout);
                }
                
                blockedCheckTimeout = setTimeout(function() {
                    performBlockedEntitiesCheck($checkoutForm);
                }, 500);
            }
        });
    }

    /**
     * Perform blocked entities check and show popup if blocked
     */
    function performBlockedEntitiesCheck($form) {
        if (!window.bdcCheckoutValidation || !window.bdcCheckoutValidation.ajaxurl) {
            return;
        }

        var phone = $form.find('#billing_phone').val() || '';
        var email = $form.find('#billing_email').val() || '';
        var fingerprint = window.bdcFingerprint || '';
        var phoneDigits = phone.replace(/[^0-9]/g, '');

        // Get IP from server via AJAX
        $.ajax({
            url: window.bdcCheckoutValidation.ajaxurl,
            type: 'POST',
            data: {
                action: 'bdc_validate_checkout_blocked_entities',
                nonce: window.bdcCheckoutValidation.nonce,
                phone: phoneDigits,
                email: email,
                fingerprint: fingerprint,
                ip: '' // Server will detect IP
            },
            success: function(response) {
                // Check response format - WordPress AJAX returns {success: true, data: {...}}
                var isBlocked = false;
                var blockedEntities = [];
                
                if (response.success && response.data) {
                    isBlocked = response.data.is_blocked === true || response.data.is_blocked === 'true';
                    blockedEntities = response.data.blocked_entities || [];
                }
                
                if (isBlocked && blockedEntities.length > 0) {
                    window.isBlocked = true; // Set global flag
                    var blockedItems = blockedEntities.map(function(item) {
                        var type = item.type;
                        if (type === 'phone') return 'Phone number';
                        if (type === 'ip') return 'IP address';
                        if (type === 'email') return 'Email address';
                        if (type === 'fingerprint') return 'Browser fingerprint';
                        return type;
                    });
                    
                    var errorMessage = 'Your order cannot be processed due to security restrictions. Blocked items: ' + blockedItems.join(', ') + '. Please contact support for assistance.';
                    
                    // Show beautiful popup modal using SweetAlert2
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: '<div style="font-size: 28px; font-weight: 600; color: #1a1a1a; margin-bottom: 10px;">⚠️ Order Blocked</div>',
                            html: '<div style="text-align: left; padding: 20px 0;">' +
                                '<p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin-bottom: 15px;">' +
                                'Your order cannot be processed due to security restrictions.</p>' +
                                '<div style="background: #fef2f2; border-left: 4px solid #d63638; padding: 15px; border-radius: 6px; margin: 15px 0;">' +
                                '<p style="margin: 0; font-size: 14px; color: #721c24;"><strong>Blocked Items:</strong></p>' +
                                '<ul style="margin: 10px 0 0 20px; padding: 0; color: #721c24;">' +
                                blockedItems.map(function(item) {
                                    return '<li style="margin: 5px 0;">' + item + '</li>';
                                }).join('') +
                                '</ul>' +
                                '</div>' +
                                '<p style="font-size: 14px; color: #6b7280; margin-top: 15px;">If you believe this is an error, please contact our support team for assistance.</p>' +
                                '</div>',
                            confirmButtonText: 'I Understand',
                            confirmButtonColor: '#d63638',
                            confirmButtonClass: 'swal2-confirm-custom',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            customClass: {
                                popup: 'bdc-blocked-modal',
                                title: 'bdc-blocked-title',
                                htmlContainer: 'bdc-blocked-content'
                            },
                            width: '500px',
                            padding: '2rem',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });
                    } else {
                        // Fallback: Show as styled alert if SweetAlert2 not available
                        var styledAlert = '<div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #fff; border: 2px solid #d63638; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 99999; max-width: 500px;">' +
                            '<h3 style="margin: 0 0 15px 0; color: #d63638;">⚠️ Order Blocked</h3>' +
                            '<p style="margin: 0 0 10px 0; color: #4a4a4a;">' + errorMessage + '</p>' +
                            '<button onclick="this.parentElement.remove()" style="background: #d63638; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">I Understand</button>' +
                            '</div>';
                        $('body').append(styledAlert);
                    }

                    // Disable checkout form
                    $form.find('button[type="submit"], input[type="submit"]').prop('disabled', true).addClass('disabled');
                    
                    // Add visual indicator
                    if (!$form.find('.bdc-blocked-message').length) {
                        $form.prepend('<div class="woocommerce-error bdc-blocked-message" style="display: block; margin-bottom: 20px; padding: 15px; background: #fef2f2; border-left: 4px solid #d63638;">' + errorMessage + '</div>');
                    }
                    
                    // Scroll to top to show error
                    $('html, body').animate({ scrollTop: 0 }, 500);
                } else {
                    window.isBlocked = false; // Clear global flag
                    // Re-enable checkout form if it was disabled
                    $form.find('button[type="submit"], input[type="submit"]').prop('disabled', false).removeClass('disabled');
                    $form.find('.bdc-blocked-message').remove();
                }
            },
            error: function(xhr, status, error) {
                // Don't block on error - fail open
                isBlocked = false;
            }
        });
    }

    // Intercept WooCommerce error notices and show as beautiful modal
    function interceptWooCommerceErrors() {
        // Monitor for error notices related to blocked entities
        var observer = new MutationObserver(function(mutations) {
            var $errorNotice = $('.woocommerce-error:contains("Order Blocked"), .woocommerce-error:contains("security restrictions"), .woocommerce-error:contains("Blocked items")');
            
            if ($errorNotice.length > 0 && typeof Swal !== 'undefined' && !$errorNotice.hasClass('bdc-modal-shown')) {
                $errorNotice.addClass('bdc-modal-shown');
                
                var errorText = $errorNotice.text();
                var blockedItemsMatch = errorText.match(/Blocked items: ([^.]+)/);
                var blockedItems = blockedItemsMatch ? blockedItemsMatch[1].split(',').map(function(item) {
                    return item.trim();
                }) : [];
                
                // Hide the default error notice
                $errorNotice.hide();
                
                // Show beautiful modal
                Swal.fire({
                    icon: 'error',
                    title: '<div style="font-size: 28px; font-weight: 600; color: #1a1a1a; margin-bottom: 10px;">⚠️ Order Blocked</div>',
                    html: '<div style="text-align: left; padding: 20px 0;">' +
                        '<p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin-bottom: 15px;">' +
                        'Your order cannot be processed due to security restrictions.</p>' +
                        (blockedItems.length > 0 ? '<div style="background: #fef2f2; border-left: 4px solid #d63638; padding: 15px; border-radius: 6px; margin: 15px 0;">' +
                        '<p style="margin: 0; font-size: 14px; color: #721c24;"><strong>Blocked Items:</strong></p>' +
                        '<ul style="margin: 10px 0 0 20px; padding: 0; color: #721c24;">' +
                        blockedItems.map(function(item) {
                            return '<li style="margin: 5px 0;">' + item + '</li>';
                        }).join('') +
                        '</ul>' +
                        '</div>' : '') +
                        '<p style="font-size: 14px; color: #6b7280; margin-top: 15px;">If you believe this is an error, please contact our support team for assistance.</p>' +
                        '</div>',
                    confirmButtonText: 'I Understand',
                    confirmButtonColor: '#d63638',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    customClass: {
                        popup: 'bdc-blocked-modal',
                        title: 'bdc-blocked-title',
                        htmlContainer: 'bdc-blocked-content'
                    },
                    width: '500px',
                    padding: '2rem',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            }
        });
        
        // Observe the checkout form area for error notices - support both WooCommerce and CartFlows
        var checkoutArea = document.querySelector('.woocommerce-checkout, .woocommerce-notices-wrapper, .cartflows-checkout-form, .cartflows-checkout-wrap');
        if (checkoutArea) {
            observer.observe(checkoutArea, {
                childList: true,
                subtree: true
            });
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initFingerprint();
        checkBlockedEntitiesOnLoad();
        interceptWooCommerceErrors();
    });

    // Also initialize on checkout update (WooCommerce AJAX) - also support CartFlows
    $(document.body).on('updated_checkout cartflows_checkout_update', function() {
        // Re-add fingerprint field if form was updated - support both WooCommerce and CartFlows
        if (window.bdcFingerprint) {
            var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
            if ($checkoutForm.length && !$checkoutForm.find('input[name="bdc_browser_fingerprint"]').length) {
                $checkoutForm.append($('<input>', {
                    type: 'hidden',
                    name: 'bdc_browser_fingerprint',
                    value: window.bdcFingerprint
                }));
            }
        }
        
        // Re-check blocked entities after checkout update
        var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
        if ($checkoutForm.length) {
            performBlockedEntitiesCheck($checkoutForm);
        }
    });

    // Add fingerprint on form submit - support both WooCommerce and CartFlows
    $(document.body).on('checkout_place_order cartflows_checkout_place_order', function() {
        if (window.bdcFingerprint) {
            // Support both WooCommerce and CartFlows checkout forms
            var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
            if ($checkoutForm.length) {
                $checkoutForm.find('input[name="bdc_browser_fingerprint"]').remove();
                $checkoutForm.append($('<input>', {
                    type: 'hidden',
                    name: 'bdc_browser_fingerprint',
                    value: window.bdcFingerprint
                }));
            }
        }

        // Validate blocked entities before checkout
        var $checkoutForm = $('form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout');
        if ($checkoutForm.length) {
            return validateBlockedEntities($checkoutForm);
        }
        return true;
    });

    // Also listen for CartFlows specific submit events
    $(document.body).on('submit', 'form.checkout, .cartflows-checkout-form, form[name="checkout"], #checkout', function(e) {
        if (window.bdcFingerprint) {
            var $checkoutForm = $(this);
            if ($checkoutForm.length && !$checkoutForm.find('input[name="bdc_browser_fingerprint"]').length) {
                $checkoutForm.append($('<input>', {
                    type: 'hidden',
                    name: 'bdc_browser_fingerprint',
                    value: window.bdcFingerprint
                }));
            }
        }
    });

    /**
     * Validate blocked entities during checkout (on form submit)
     */
    function validateBlockedEntities($form) {
        // Skip validation for admins
        if (typeof window.bdcCheckoutValidation !== 'undefined' && window.bdcCheckoutValidation.skip_validation) {
            return true;
        }

        // If already blocked from previous check, prevent submission
        if (window.isBlocked === true) {
            return false;
        }

        var phone = $form.find('#billing_phone').val() || '';
        var email = $form.find('#billing_email').val() || '';
        var fingerprint = window.bdcFingerprint || '';
        var phoneDigits = phone.replace(/[^0-9]/g, '');

        // Prepare validation data
        var validationData = {
            phone: phoneDigits,
            email: email,
            fingerprint: fingerprint,
            ip: '' // Server will detect IP
        };

        // Make synchronous AJAX call to validate
        var isValid = true;
        var errorMessage = '';

        $.ajax({
            url: window.bdcCheckoutValidation ? window.bdcCheckoutValidation.ajaxurl : '',
            type: 'POST',
            async: false, // Synchronous to block form submission
            data: {
                action: 'bdc_validate_checkout_blocked_entities',
                nonce: window.bdcCheckoutValidation ? window.bdcCheckoutValidation.nonce : '',
                phone: validationData.phone,
                email: validationData.email,
                fingerprint: validationData.fingerprint,
                ip: validationData.ip
            },
            success: function(response) {
                if (response.success && response.data && response.data.is_blocked) {
                    isValid = false;
                    isBlocked = true;
                    var blockedItems = response.data.blocked_entities.map(function(item) {
                        var type = item.type;
                        if (type === 'phone') return 'Phone number';
                        if (type === 'ip') return 'IP address';
                        if (type === 'email') return 'Email address';
                        if (type === 'fingerprint') return 'Browser fingerprint';
                        return type;
                    });
                    errorMessage = 'Your order cannot be processed due to security restrictions. Blocked items: ' + blockedItems.join(', ') + '. Please contact support for assistance.';
                } else {
                    window.isBlocked = false;
                }
            },
            error: function(xhr, status, error) {
                // Allow checkout to continue if validation fails (fail-open approach)
                isValid = true;
                isBlocked = false;
            }
        });

        if (!isValid) {
            // Parse blocked items from error message
            var blockedItemsMatch = errorMessage.match(/Blocked items: ([^.]+)/);
            var blockedItemsList = blockedItemsMatch ? blockedItemsMatch[1].split(',').map(function(item) {
                return item.trim();
            }) : [];
            
            // Show beautiful popup modal using SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: '<div style="font-size: 28px; font-weight: 600; color: #1a1a1a; margin-bottom: 10px;">⚠️ Order Blocked</div>',
                    html: '<div style="text-align: left; padding: 20px 0;">' +
                        '<p style="font-size: 16px; color: #4a4a4a; line-height: 1.6; margin-bottom: 15px;">' +
                        'Your order cannot be processed due to security restrictions.</p>' +
                        (blockedItemsList.length > 0 ? '<div style="background: #fef2f2; border-left: 4px solid #d63638; padding: 15px; border-radius: 6px; margin: 15px 0;">' +
                        '<p style="margin: 0; font-size: 14px; color: #721c24;"><strong>Blocked Items:</strong></p>' +
                        '<ul style="margin: 10px 0 0 20px; padding: 0; color: #721c24;">' +
                        blockedItemsList.map(function(item) {
                            return '<li style="margin: 5px 0;">' + item + '</li>';
                        }).join('') +
                        '</ul>' +
                        '</div>' : '') +
                        '<p style="font-size: 14px; color: #6b7280; margin-top: 15px;">If you believe this is an error, please contact our support team for assistance.</p>' +
                        '</div>',
                    confirmButtonText: 'I Understand',
                    confirmButtonColor: '#d63638',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    customClass: {
                        popup: 'bdc-blocked-modal',
                        title: 'bdc-blocked-title',
                        htmlContainer: 'bdc-blocked-content'
                    },
                    width: '500px',
                    padding: '2rem',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            } else {
                // Fallback: Show as styled alert if SweetAlert2 not available
                var styledAlert = '<div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #fff; border: 2px solid #d63638; border-radius: 8px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 99999; max-width: 500px;">' +
                    '<h3 style="margin: 0 0 15px 0; color: #d63638;">⚠️ Order Blocked</h3>' +
                    '<p style="margin: 0 0 10px 0; color: #4a4a4a;">' + errorMessage + '</p>' +
                    '<button onclick="this.parentElement.remove()" style="background: #d63638; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">I Understand</button>' +
                    '</div>';
                $('body').append(styledAlert);
            }

            // Disable checkout form
            $form.find('button[type="submit"], input[type="submit"]').prop('disabled', true).addClass('disabled');

            // Scroll to top to show error
            $('html, body').animate({ scrollTop: 0 }, 500);

            return false;
        }

        return true;
    }

    /**
     * Get approximate client IP (not reliable but better than nothing)
     */
    function getClientIP() {
        // This is a very basic approximation - real IP detection should be done server-side
        // We'll leave this empty for now and let server-side detection handle it
        return '';
    }

})(jQuery);
