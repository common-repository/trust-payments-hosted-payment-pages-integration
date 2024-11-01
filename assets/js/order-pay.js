(function ($) {
    'use strict';

    // Document ready
    $(function () {
        /* Remove payment method */
        $('.payment_method_securetrading_google_pay').remove();
        $('.payment_method_securetrading_apple_pay').remove();
        $('.payment_method_securetrading_paypal').remove();

        // If user ticks the field to select saved credit card.
        $( document.body ).on( 'click', '.select_credit_card_checkbox, .hpp_select_credit_card_checkbox', function() {
            $( document.body ).trigger( 'payment_method_selected' );
        });

        // If user ticks the field to use a new credit card.
        $( document ).unbind( 'click' ).on( 'click', '#use_new_credit_card_checkbox, #hpp_use_new_credit_card_checkbox', function() {
            $( document.body ).trigger( 'payment_method_selected' );
        });

        /* Paypal add payment method */
        $( document.body ).on( 'click', '#paypal_place_order', function() {
            var method = $("input[name='payment_method']:checked").val();
            if ( method == 'securetrading_api' ) {
                $("input[name='payment_method']:checked").val(tp_order_pay.tp_paypal_gateway.id);
                $( document.body ).trigger( 'payment_method_selected' );
            }
        });

        /* Select Payment method */
        $( document.body ).on( 'payment_method_selected', function () {
            var method = $("input[name='payment_method']:checked").val();

            /* Process selected */
            if ( ( method == tp_order_pay.tp_hpp_gateway.id && $('input[name="hpp_saved_card"]').length ) || ( method != tp_order_pay.tp_hpp_gateway.id && tp_order_pay.tp_api_gateway.enabled == 'no' ) ) {
                var label = $('label[for="' + $("input[name='hpp_saved_card']:checked").val() + '"]');
                $('#hpp-show-credit-card-details').html(label.html());
                $('.st-card_wapper').appendTo('#securetrading_iframe-payment-data');
                $('.st-card_wapper').show();
                $('.hpp-selected-saved-card').show();
                $('.hpp-SavedPaymentMethods-saveNew').hide();
                if ( $("input#hpp_use_new_credit_card_checkbox").is(':checked') ) {
                    $('.hpp-selected-saved-card').hide();
                    $('.st-card_wapper').hide();
                    $('.hpp-SavedPaymentMethods-saveNew').show();
                }
            } else {
                var label = $('label[for="' + $("input[name='saved_card']:checked").val() + '"]');
                $('#js-show-credit-card-details').html(label.html());
                $('.st-card_wapper').appendTo('#securetrading_api-payment-data');
                $('.st-card_wapper').show();
                $('.js-selected-saved-card').show();
                if ( $('input[name="saved_card"]').length ) {
                    $('.js-SavedPaymentMethods-saveNew').hide();
                }
                if ( $("input#use_new_credit_card_checkbox").is(':checked') ) {
                    $('.js-selected-saved-card').hide();
                    $('.js-SavedPaymentMethods-saveNew').show();
                }
            }

            /* Payload */
            var order_pay = {
                jwt: tp_order_pay.tp_api_gateway.jwt,
                deferInit: true,
                panIcon: true,
                submitOnCancel: false,
                submitOnError: false,
                submitOnSuccess: false,
                stopSubmitFormOnEnter: true,
                formId: 'order_review',
                disabledAutoPaymentStart: ["GOOGLEPAY", "APPLEPAY", "CARD"],
                submitCallback : stGatewaySubmitCallback,
                successCallback : stGatewaySuccessCallback,
                errorCallback : stGatewayErrorCallback
            };

            /* Google Pay add payment method */
            $( document.body ).on( 'click', '#gpay_place_order', function(e) {
                $("input[name='payment_method']:checked").val(tp_order_pay.tp_google_gateway.id);
                method = tp_order_pay.tp_google_gateway.id;
            });

            /* Paypal add payment method */
            $( document.body ).on( 'click', '#paypal_place_order', function() {
                $("input[name='payment_method']:checked").val(tp_order_pay.tp_paypal_gateway.id);
                method = tp_order_pay.tp_paypal_gateway.id;

                if ( st ) {
                    st.destroy();
                }
            });

            /* Apple Pay add payment method */
            $( document.body ).on( 'click', '#apple_place_order', function() {
                $("input[name='payment_method']:checked").val(tp_order_pay.tp_apple_gateway.id);
                method = tp_order_pay.tp_apple_gateway.id;
            });

            /* Another payment method */
            $( document.body ).on( 'click', '#place_order', function() {
                var current_method = $("input[name='payment_method']:checked").val();
                if ( current_method !== tp_order_pay.tp_api_gateway.id && ( current_method !== tp_order_pay.tp_hpp_gateway.id || current_method == tp_order_pay.tp_hpp_gateway.id && $("input#hpp_use_new_credit_card_checkbox").is(":checked") ) ) {
                    st.destroy();
                }
                if ( current_method == tp_order_pay.tp_api_gateway.id ) {
                    $('#st_place_order').trigger('click');
                }
                if ( current_method == tp_order_pay.tp_hpp_gateway.id && $('.hpp_select_credit_card_checkbox').is( ':checked' ) ) {
                    $('#st_hpp_place_order').trigger('click');
                }
            });

            if ( method == tp_order_pay.tp_hpp_gateway.id ) {
                var save_card = $("input[name='hpp_saved_card']:checked").attr('id');
                order_pay.jwt = tp_order_pay.tp_hpp_gateway.jwt;
                order_pay.livestatus = ( tp_order_pay.tp_hpp_gateway.testmode == 1 ) ? 0 : 1;

                if ( $('input[name="hpp_saved_card"]').length && save_card != 'hpp_use_new_credit_card_checkbox' ) {
                    $('.st-card_form').addClass('pay-save-card');
                    order_pay.fieldsToSubmit = ['securitycode'];
                    order_pay.formId = 'order_review';
                } else {
                    $('.st-card_form').removeClass('pay-save-card');
                }

                /* buttonId place order */
                order_pay.buttonId = 'st_hpp_place_order';
                if ( !$('#st_hpp_place_order').length ) {
                    $( '<input id="st_hpp_place_order" type="hidden" />' ).insertAfter( '#place_order' );
                }

                if ( $('#st_place_order').length ) {
                    $('#st_place_order').remove();
                }

                if ( $('#st_default_place_order').length ) {
                    $('#st_default_place_order').remove();
                }
            } else if ( method == tp_order_pay.tp_api_gateway.id ) {
                var save_card = $("input[name='saved_card']:checked").attr('id');
                order_pay.jwt = tp_order_pay.tp_api_gateway.jwt;
                order_pay.livestatus = ( tp_order_pay.tp_api_gateway.testmode == 1 ) ? 0 : 1;
                order_pay.formId = 'order_review';

                if ( $('input[name="saved_card"]').length && save_card != 'use_new_credit_card_checkbox' ) {
                    $('.st-card_form').addClass('pay-save-card');
                    order_pay.fieldsToSubmit = ['securitycode'];
                } else {
                    $('.st-card_form').removeClass('pay-save-card');
                }

                /* buttonId place order */
                order_pay.buttonId = 'st_place_order';
                if ( !$('#st_place_order').length ) {
                    $( '<input id="st_place_order" type="hidden" />' ).insertAfter( '#place_order' );
                }

                if ( $('#st_hpp_place_order').length ) {
                    $('#st_hpp_place_order').remove();
                }

                if ( $('#st_default_place_order').length ) {
                    $('#st_default_place_order').remove();
                }
            } else {
                /* buttonId place order */
                order_pay.buttonId = 'st_default_place_order';
                if ( !$('#st_default_place_order').length ) {
                    $( '<input id="st_default_place_order" type="hidden" />' ).insertAfter( '#place_order' );
                }

                if ( $('#st_place_order').length ) {
                    $('#st_place_order').remove();
                }

                if ( $('#st_hpp_place_order').length ) {
                    $('#st_hpp_place_order').remove();
                }
            }

            var st = SecureTrading(order_pay);

            /* Initialise */
            st.Components();
            st_component_apple_google_pay(st);

            /* Initialized Started */
            st.on('paymentInitStarted', (data) => {
                const paymentMethod = data.name;
                if ( ( paymentMethod === 'GooglePay' ) || paymentMethod === 'ApplePay' ) {
                    // Remove element empty
                    $('.gpay-inner .button > div, .apple-inner .button > div').each(function() {
                        if( $(this).text().trim() === '' )
                            $(this).remove();
                    });
                }
            });

            /* Initialized successfully */
            st.on('paymentInitCompleted', (data) => {
                const paymentMethod = data.name;
                $( '.st-loading, .st-gpay-loading, .st-apple-loading' ).fadeOut();

                if ( ( paymentMethod === 'GooglePay' ) || paymentMethod === 'ApplePay' ) {
                    $( '.gateway-button' ).fadeIn();
                    $( '.test-label' ).fadeIn();
                }
            });

            /* Precheck Payment */
            st.on('paymentPreCheck', (data) => {
                $('#order_review .validate-required input[type="checkbox"]').each(function() {
                    if ( ! $(this).is( ':checked' ) ) {
                        $('#order_review').submit();
                        return;
                    }
                });

                const paymentStart = data.paymentStart;

                var payment_method = $("input[name='payment_method']:checked").val();
                if ( method == tp_order_pay.tp_api_gateway.id ) {
                    var use_users_saved_card = $('.select_credit_card_checkbox:checked').val();
                } else if ( method == tp_order_pay.tp_hpp_gateway.id ) {
                    var use_users_saved_card = $('.hpp_select_credit_card_checkbox:checked').val();
                }
                var save_card = $('#save_credit_card_details_checkbox').is( ':checked' );

                // Update JWT
                $.when(
                    jQuery.ajax({
                        url: tp_order_pay.ajax_url,
                        type: 'post',
                        data: {
                            action: 'tp_order_pay_update_jwt',
                            tp_oder_pay_nonce: tp_order_pay.tp_oder_pay_nonce,
                            order_id: tp_order_pay.order_id,
                            payment_method: payment_method,
                            save_card: save_card,
                            use_users_saved_card: use_users_saved_card
                        },
                        beforeSend : function() {
                        },
                        error : function(err) {
                            console.log(err);
                            // Display error message.
                            let missingFields = '<ul class="woocommerce-error" role="alert">';
                            missingFields+= '<li class="errorCallback">Unfortunately, the following error has occurred.</li>';
                            missingFields+= '<li>Cannot update JWT field. Please try again, if the issue persists please contact the merchant.</li>';
                            missingFields+= '</ul>';

                            if ( $( '.woocommerce-notices-wrapper' ) ) {
                                $( '.woocommerce-notices-wrapper' ).html(missingFields);
                                window.scrollTo({ top: '#order_review', behavior: 'smooth'});
                            }

                            // Remove the loading overlay.
                            $( '#order_review' ).unblock();
                        },
                        success : function(response) {
                            // on success, update JWT.
                            console.log( 'updateJWT' );
                            st.updateJWT( response.slice( 0, -1 ) );
                        }
                    })
                ).done(function() {
                    paymentStart();
                });
            });
        });

        function st_component_apple_google_pay(st) {
            /* Google Pay */
            if ( tp_order_pay.tp_google_gateway.enabled == 'yes' ) {
                if ( $('#gpay_place_order .gpay-card-info-container').length ) {
                    $('#gpay_place_order .gpay-card-info-container').remove();
                }
                st.GooglePay({
                    "buttonOptions": {
                        "buttonRootNode": "gpay_place_order"
                    },
                    "paymentRequest": {
                        "allowedPaymentMethods": [{
                            "parameters": {
                                "allowedAuthMethods": ["PAN_ONLY", "CRYPTOGRAM_3DS"],
                                "allowedCardNetworks": ["AMEX", "DISCOVER", "JCB", "MASTERCARD", "VISA"]
                            },
                            "tokenizationSpecification": {
                                "parameters": {
                                    "gateway": "trustpayments",
                                    "gatewayMerchantId": tp_order_pay.tp_google_gateway.site_reference
                                },
                                "type": "PAYMENT_GATEWAY"
                            },
                            "type": "CARD"
                        }],
                        "environment": ( tp_order_pay.tp_api_gateway.testmode == 1 ) ? "TEST" : "PRODUCTION",
                        "apiVersion": 2,
                        "apiVersionMinor": 0,
                        "merchantInfo": {
                            "merchantId": tp_order_pay.tp_google_gateway.merchant_id,
                            "merchantName": tp_order_pay.tp_google_gateway.merchant_name
                        },
                        "transactionInfo": {
                            "countryCode": "GB",
                            "currencyCode": tp_order_pay.currency,
                            "checkoutOption": "COMPLETE_IMMEDIATE_PURCHASE",
                            "totalPriceStatus": "FINAL",
                            "totalPrice": tp_order_pay.total
                        }
                    }
                });
                $('#gpay_place_order').addClass('gateway-button');
            }

            /* Apple Pay */
            if ( tp_order_pay.tp_apple_gateway.enabled == 'yes' ) {
                st.ApplePay({
                    buttonStyle: tp_order_pay.tp_apple_gateway.button_style,
                    buttonText: 'plain',
                    merchantId: tp_order_pay.tp_apple_gateway.merchant_id,
                    paymentRequest: {
                        countryCode: $('#billing_country').val(),
                        currencyCode: tp_order_pay.currency,
                        merchantCapabilities: ['supports3DS', 'supportsCredit', 'supportsDebit'],
                        supportedNetworks: ["visa","masterCard","amex"],
                        requiredBillingContactFields: ["postalAddress"],
                        requiredShippingContactFields: ["postalAddress","name", "phone", "email"],
                        total: {
                            label: tp_order_pay.tp_apple_gateway.merchant_name,
                            type: 'final',
                            amount: tp_order_pay.total
                        }
                    },
                    buttonPlacement: 'apple_place_order',
                    placement: 'apple_place_order'
                });

                /* Button gateway */
                $('#apple_place_order').addClass('gateway-button');
                $('#apple_place_order').addClass('gateway-apple-button');
            }
        }

        /* Form card Success callback */
        function stGatewaySuccessCallback( data ) {
            // console.log('stGatewaySuccessCallback'+JSON.stringify(window.transactiondata));
            // Save option to reuse same credit card if user ticked box.
            var orderId = tp_order_pay.order_id;
            var method = $("input[name='payment_method']:checked").val();
            var transactionreference = window.transactiondata.transactionreference,
                transactiondata = JSON.stringify( window.transactiondata, null, 1),
                walletsource = window.transactiondata.walletsource,
                data = {
                    action: 'tp_process_order',
                    transactionreference: transactionreference,
                    transactiondata: transactiondata,
                    order_id: orderId,
                    method: method,
                    walletsource: walletsource,
                    is_order_pay: true
                };
            $.ajax({
                type: 'POST',
                data: data,
                showLoader: true,
                url: tp_order_pay.ajax_url,
                beforeSend: function () {
                    // console.log(jwt);
                },
                success: function (response) {
                    var obj = JSON.parse(response);
                    // console.log(obj);
                    window.top.location.href = obj.url;
                }
            });
        }

        /* Function called on submission */
        function stGatewaySubmitCallback( data ) {
            // console.log('stGatewaySubmitCallback'+JSON.stringify(data));
            // Save transaction data
            window.transactiondata = data;
            // Save the transaction reference
            window.transactionreference = data['transactionreference'];
            // Save maskedpan
            window.transaction_maskedpan = data['maskedpan'];
            // Save paymenttypedescription
            window.transaction_paymenttypedescription = data['paymenttypedescription'];

            // Set error msg.
            // https://webapp.securetrading.net/errorcodes.html.
            let error = false;

            switch( Number( data.errorcode ) ) {
                case 70000:
                    error = 'Transaction declined by card issuer. Please re-attempt with another card or contact your card issuer.';
                    break;
                case 71000:
                    error = 'Transaction declined by card issuer. SCA Required. Please contact the merchant.';
                    break;
                case 60010:
                    error = 'Unable to process transaction. Please try again and contact the merchant if the issue persists.';
                    break;
                case 60110:
                    error = 'Unable to process transaction.';
                    break;
                case 60022:
                    error = 'Transaction declined, 3-D Secure authentication has failed.';
                    break;
                case 60102:
                    error = 'Transaction has been declined.';
                    break;
                case 60103:
                    error = 'Transaction has been declined.';
                    break;
                case 60104:
                    error = 'Transaction has been declined.';
                    break;
                case 60105:
                    error = 'Transaction has been declined.';
                    break;
                case 60106:
                    error = 'Transaction has been declined.';
                    break;
                case 60108:
                    error = 'Transaction declined, 3-D Secure authentication has failed.';
                    break;
                case 50003:
                    error = 'JWT invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
                    break;
                case 30006:
                    error = 'Incorrect sitereference, please contact the merchant - Invalid data received (30006)';
                    break;
                case 30000:
                    error = 'Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
                    break;
            }

            if ( error ) {
                // Display error message.
                let missingFields = '<ul class="woocommerce-error" role="alert">';
                missingFields+= '<li class="errorCallback">Unfortunately, the following error has occurred.</li>';
                if ( data.errordata && data.errordata[0] === 'jwt' ) {
                    missingFields+= '<li>JWT invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.</li>';
                } else if ( data.errordata && data.errordata[0] === 'sitereference' ) {
                    missingFields+= '<li>Incorrect sitereference, please contact the merchant - Invalid data received (30000)</li>';
                } else if ( data.errordata && data.errordata[0] === 'billingpostcode' ) {
                    missingFields+= '<li>Incorrect billingpostcode, please contact the merchant - Invalid data received (30000)</li>';
                } else {
                    missingFields+= '<li> - ' + error + '</li>';
                }
                missingFields+= '</ul>';

                if ( $( '.woocommerce-notices-wrapper' ) ) {
                    $( '.woocommerce-notices-wrapper' ).html(missingFields);
                    window.scrollTo({ top: '#order_review', behavior: 'smooth'});
                }
            }
        }

        /* Function called on an error. */
        function stGatewayErrorCallback( data ) {
            // Hide the processing order notice.
            // $( '.woocommerce-NoticeGroup-checkout .woocommerce-info' ).remove();
            // Remove loading overlay.
            $( '#order_review' ).unblock();

            return false;
        }
    });
})(jQuery);
