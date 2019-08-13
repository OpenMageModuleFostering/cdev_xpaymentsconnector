// vim: set ts=2 sw=2 sts=2 et:

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @author     Qualiteam Software <info@x-cart.com>
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-present Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * IFRAME actions
 */
var XPC_IFRAME_DO_NOTHING       = 0;
var XPC_IFRAME_CHANGE_METHOD    = 1;
var XPC_IFRAME_CLEAR_INIT_DATA  = 2;
var XPC_IFRAME_ALERT            = 3;
var XPC_IFRAME_TOP_MESSAGE      = 4;

var disabledButtons = [];

/**
 * Submit payment in X-Payments
 */ 
function sendSubmitMessage() 
{
    var message = {
        message: 'submitPaymentForm',
        params: {}
    };

    message = JSON.stringify(message);

    getXpcIframe().contentWindow.postMessage(message, '*');
}

/**
 * Check if X-Payments method is currently selected at checkout
 */
function isXpcMethod() 
{
    var result = false;

    if ($$('input:checked[type=radio][name=payment[method]][value^=xpayments]').length) {

        // This is for other checkout modules
        result = true;

    } else if (null != $('iwd_opc_payment_method_select')) {

        // This is for IWD Checkout suite
        result = $('iwd_opc_payment_method_select').value.match(/xpayments/);
    }

    return result;
}

function getCurrentXpcMethod()
{
    var code = '';

    var block = $$('input:checked[type=radio][name=payment[method]][value^=xpayments]');

    if (block.length) {
        code = block[0].value;
    } else if (null != $('iwd_opc_payment_method_select')) {
        code = $('iwd_opc_payment_method_select').value;
    }

    return code;
}

function getXpcIframe()
{
    var iframeId = $('xp-iframe-' + getCurrentXpcMethod());

    return $(iframeId);
}

document.observe('dom:loaded', function () {

    /**
     * Redirect or reload iframe
     */ 
    document.observe('xpc:redirect', function (event) {

        console.log('xpc:redirect', event.memo);

        var iframe = getXpcIframe();

        if (typeof event.memo == 'string') {
            
            // Use passed src
            var src = event.memo;

        } else if ('' != iframe.getAttribute('src')) {


            // Reload iframe
            var src = iframe.getAttribute('src');

        } else {

            // Redirect iframe to the payment page
            var src = xpcData.url[getCurrentXpcMethod()].redirect;
        }

        document.fire('xpc:disableButtons');

        $$('.xp-iframe').each( function(elm) { elm.setStyle( {'height' : '0'} )});
        $('paymentstep-ajax-loader').setStyle({'display' : 'block'});

        $('payment-form-' + getCurrentXpcMethod()).setStyle( {'height' : 'auto'} );

        iframe.setAttribute('src', src);
    });

    /**
     * Checkout is changed. Probably we should do something with X-Payments iframe
     */
    document.observe('xpc:checkoutChanged', function (event) {

        console.log('xpc:checkoutChanged', event.memo);

        if (
            $$('.xp-iframe').length
            && typeof xpcData != 'undefined'
        ) {

            if (isXpcMethod()) {

                // Process redirect to the payment page
                document.fire('xpc:redirect');

            } else {

                // Hide iframe with CC form for non X-Payments payment methods 
                $$('.xp-iframe').each( function(elm) { elm.setStyle( {'height' : '0'} )});
            }
        }
    });

    /**
     * Review is loaded somewhere. Somehow. It contsins the place order button.
     */
    document.observe('xpc:reviewBlockLoaded', function (event) {

        console.log('xpc:reviewBlockLoaded', event.memo);

        Review.prototype.save = Review.prototype.save.wrap(
            function(parentMethod) {

                if (isXpcMethod()) {

                    if (checkout.loadWaiting != false) {
                        return;
                    }

                    checkout.setLoadWaiting('review');

                    if (this.agreementsForm) {
                        params = Form.serialize(this.agreementsForm);
                    } else {
                        params = '';
                    }

                    new Ajax.Request(
                        xpcData.url[getCurrentXpcMethod()].checkAgreements,
                        {
                            method: 'Post',
                            parameters: params,
                            onComplete: function (response) {

                                response = JSON.parse(response.responseText);

                                if (response.error_messages) {

                                    alert(response.error_messages);
                                    checkout.setLoadWaiting(false);

                                } else {

                                    if (xpcData.useIframe) {
                                        sendSubmitMessage();
                                    } else {
                                        window.location.href = xpcData.url[getCurrentXpcMethod()].dropTokenAndRedirect;
                                    }

                                }
                            }
                        }
                    );

                } else {
                    return parentMethod();
                }
            }
        );

        if (
            typeof xpcData != 'undefined'
            && xpcData.displayOnReviewStep
        ) {
            document.fire('xpc:checkoutChanged');
        }
    });

    /**
     * X-Payments iframe is ready.
     */
    document.observe('xpc:ready', function (event) {

        console.log('xpc:ready', event.memo);

        $('paymentstep-ajax-loader').hide();

        var iframe = getXpcIframe();

        if (
            event.memo.height
            && (
                xpcData.displayOnReviewStep
                || !xpcData.isOneStepCheckout && !xpcData.height
                || xpcData.isOneStepCheckout
                || xpcData.isFirecheckout
            )
        ) {

            // Height is sent correctly only if iframe is visible.
            // For default onepage checkout if iframe is displayed on the payment step
            // the iframe is hidden and shown in case of the error
            var height = event.memo.height;

            // Save height for future
            xpcData.height = height;

        } else {

            // Use previously saved value
            var height = xpcData.height;
        }

        iframe.setStyle( {'height': height + 'px'} );

        document.fire('xpc:enableButtons');
    });

    /**
     * Notify store about customer's choice to register.
     */
    document.observe('xpc:setCheckoutMethod', function (event) {

        console.log('xpc:setCheckoutMethod', event.memo);

        currentXpcMethod = getCurrentXpcMethod();
        if (!currentXpcMethod) {
            // For non X-Payments payment method use the first slot
            currentXpcMethod = 'xpayments1';
        }

        if (typeof xpcData.url[currentXpcMethod] == 'undefined') {
            // Just in case check the URL
            return;
        }

        if (typeof event.memo == 'boolean' && event.memo) {
            var src = xpcData.url[currentXpcMethod].setMethodRegister;
        } else {
            var src = xpcData.url[currentXpcMethod].setMethodGuest;
        }

        if (isXpcMethod()) {

            // Reload iframe
            document.fire('xpc:redirect', src);

        } else {

            // Set checkout method in background
            new Ajax.Request(src);

            // Remove iframes src, so they're reloaded when shown
            $$('.xp-iframe').each( function(elm) { 
                elm.setAttribute('src', '');
            });
        }
    });

    /**
     * Payment form is submitted from X-Payments.
     */
    document.observe('xpc:showMessage', function (event) {

        console.log('xpc:showMessage', event.memo);

        alert(event.memo.text);
    });

    /**
     * Payment form is submitted from X-Payments.
     */
    document.observe('xpc:paymentFormSubmit', function (event) {

        console.log('xpc:paymentFormSubmit', event.memo);

        jQuery('.button.btn-checkout').click();        
    });

    /**
     * Error in submitting payment form from X-Payments.
     */
    document.observe('xpc:paymentFormSubmitError', function (event) {

        console.log('xpc:paymentFormSubmitError', event.memo);

        if (event.memo.message) {
            document.fire('xpc:showMessage', {text: event.memo.message});
        } else if (event.memo.error) {
            document.fire('xpc:showMessage', {text: event.memo.error});
        }

        if (event.memo.height) {
            getXpcIframe().setStyle( {'height': event.memo.height + 'px'} );
        }

        type = parseInt(event.memo.type);

        if (XPC_IFRAME_CLEAR_INIT_DATA == type) {

            document.fire('xpc:clearInitData');
            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:enableCheckout');

        } else if (XPC_IFRAME_CHANGE_METHOD == type) {

            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:enableCheckout');
            document.fire('xpc:changeMethod');

        } else {

            // Alert or show message action

            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:enableCheckout');
        }

        document.fire('xpc:enableButtons');
    });


    /**
     * Clear init data and reload iframe.
     */
    document.observe('xpc:clearInitData', function (event) {

        console.log('xpc:clearInitData', event.memo);

        document.fire('xpc:redirect', xpcData.url[getCurrentXpcMethod()].dropTokenAndRedirect);
    });


    /**
     * Return customer to the payment section/step of checkout
     */
    document.observe('xpc:goToPaymentSection', function (event) {

        console.log('xpc:goToPaymentSection', event.memo);

        if (
            typeof checkout != 'undefined'
            && checkout.gotoSection
            && !xpcData.displayOnReviewStep
        ) {

            checkout.gotoSection('payment', false);
        }
    });


    /**
     * Re-enable checkout to allow place order.
     */
    document.observe('xpc:enableCheckout', function (event) {

        console.log('xpc:enableCheckout', event.memo);

        // This is for One Step Checkout
        if ($('onestepcheckout-form')) {

            var submitelement = $('onestepcheckout-place-order');

            if (submitelement) {
                submitelement.removeClassName('grey');
                submitelement.addClassName('orange');
                submitelement.disabled = false;
            }

            var loaderelement = $$('span.onestepcheckout-place-order-loading')[0];

            if (loaderelement) {
                loaderelement.remove();
            }

            already_placing_order = false;
        }

        // This is for Firecheckout
        // And Venedor Theme 1.6.3
        if (
            $('firecheckout-form')
            || $('onepagecheckout_orderform') 
        ) {

            checkout.setLoadWaiting(false);
            $('review-please-wait').hide();
        }

        // This is for default onepage checkout
        if ($('co-payment-form')) {
            checkout.setLoadWaiting(false);
        }

        $('paymentstep-ajax-loader').hide();
    });

    /**
     * Disable buttons at checkout while form is loading.
     */
    document.observe('xpc:disableButtons', function (event) {

        console.log('xpc:disableButtons', event.memo);

        // Continue button at Onepage Checkout
        if ('undefined' != typeof $$('#payment-buttons-container button')[0]) {
            disabledButtons.push($$('#payment-buttons-container button')[0]);
        }

        // Place order button
        if ('undefined' != typeof $$('#review-buttons-container button')[0]) { 
            disabledButtons.push($$('#review-buttons-container button')[0]);
        }

        if (disabledButtons.length) {
            disabledButtons.each(function(e) { e.disable(); });
        }
    });

    /**
     * Enable disabled buttons at checkout after the form loaded.
     */
    document.observe('xpc:enableButtons', function (event) {

        console.log('xpc:enableButtons', event.memo);

        if (disabledButtons.length) {
            disabledButtons.each(
                function(e) { 
                    if ('undefined' != typeof e) {
                        e.enable(); 
                    }
                }
            );
        }

        disabledButtons = [];
    });

    /**
     * Change payment method.
     */
    document.observe('xpc:changeMethod', function (event) {

        console.log('xpc:changeMethod', event.memo);

        var pm = $$('input[type=radio][name=payment[method]]:not([value*=xpayments])');

        if (pm.length) {
            pm[0].click();
        } else {
            window.location.href = xpcData.url[getCurrentXpcMethod()].changeMethod;
        }
    });


    /**
     * Check and re-trigger event from X-Payments iframe
     */
    Event.observe(window, 'message', function (event) {

        var found = false;

        for (var i in xpcData.origins) {
            if (event.origin == xpcData.origins[i]) {
                found = true;
                break;
            }
        }

        if (found) {
            
            var data = JSON.parse(event.data)

            document.fire('xpc:' + data.message, data.params);
        }
    });


    // This is for One Step Checkout
    if ($('onestepcheckout-form')) {

        // Checkout is loaded
        document.fire('xpc:checkoutChanged', 'loaded');

        get_separate_save_methods_function = get_separate_save_methods_function.wrap(
            function (originalMethod, url, update_payments) {

                var result = originalMethod(url, update_payments);

                // Shipping method changed
                document.fire('xpc:checkoutChanged', 'shipping');

                $('onestepcheckout-form').on('change', '.radio', function() {
                    // Payment method changed
                    document.fire('xpc:checkoutChanged', 'payment');
                });
                
                return result;
            }
        );
      
        if ($('id_create_account')) {
            $('id_create_account').on('change', function(event, elm) {
                document.fire('xpc:setCheckoutMethod', elm.checked);
            });
        }

        $('onestepcheckout-form').submit = $('onestepcheckout-form').submit.wrap(
            function (parentMethod) {

                if (isXpcMethod()) {

                    var data = $('onestepcheckout-form').serialize(true);

                    // Save checkout data
                    new Ajax.Request(
                        xpcData.url[getCurrentXpcMethod()].saveCheckoutData,
                        {
                            method: 'Post',
                            parameters: data,
                            onComplete: function(response) {
                                if (200 == response.status) {
                                    sendSubmitMessage();
                                } else {
                                    document.fire('xpc:showMessage', {text: 'Error processing request'});
                                }
                            }
                        }
                    );

                } else {

                    return parentMethod();
                }

            }
        );
    }

    // This is for One Step Checkout by AheadWorks
    if ($('aw-onestepcheckout-general-form')) {

        // Checkout is loaded
        document.fire('xpc:checkoutChanged', 'loaded');

        $('aw-onestepcheckout-general-form').on('change', '.radio', function() {

            // Shipping or payment method changed
            document.fire('xpc:checkoutChanged', 'paymentOrShipping');
        });

        AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest = AWOnestepcheckoutForm.prototype._sendPlaceOrderRequest.wrap(
            function(parentMethod) {

                if (isXpcMethod()) {

                    if (xpcData.useIframe) {
                        sendSubmitMessage();
                    } else {
                        window.location.href = xpcData.url[getCurrentXpcMethod()].dropTokenAndRedirect;
                    }

                } else {

                    return parentMethod();
                }
            }
        );

    }

    // This is for Firecheckout
    if ($('firecheckout-form')) {

        // Checkout is loaded
        document.fire('xpc:checkoutChanged', 'loaded');

        $('firecheckout-form').on('change', '.radio', function() {

            // Shipping or payment method changed
            document.fire('xpc:checkoutChanged', 'paymentOrShipping');
        });

        if ($('billing:register_account')) {
            $('billing:register_account').on('change', function(event, elm) {
                document.fire('xpc:setCheckoutMethod', elm.checked);
            });
        }

        FireCheckout.prototype.save = FireCheckout.prototype.save.wrap(
            function(parentMethod, urlSuffix, forceSave) {

                if (isXpcMethod()) {

                    if (this.loadWaiting != false) {
                        return;
                    }

                    // Validate form
                    if (!this.validate()) {
                        return;
                    }

                    // Save original "save" URL
                    this.urls.savedSave = this.urls.save;

                    this.urls.save = xpcData.url[getCurrentXpcMethod()].saveCheckoutData;

                    parentMethod(urlSuffix, forceSave);

                    checkout.setLoadWaiting(true);
                    $('review-please-wait').show();

                    this.urls.save = this.urls.savedSave;

                    if (xpcData.useIframe) {
                        sendSubmitMessage();
                    } else {
                        window.location.href = xpcData.url[getCurrentXpcMethod()].dropTokenAndRedirect;
                    }

                } else {

                    return parentMethod(urlSuffix, forceSave);
                }
            }
        );

    }


    // This is for default onepage checkout
    if ($('co-payment-form')) {

        // Checkout is loaded
        document.fire('xpc:checkoutChanged', 'loaded');        

        $('co-payment-form').on('change', '.radio', function() {

            // Payment method changed
            document.fire('xpc:checkoutChanged', 'payment');
        });

        ShippingMethod.prototype.save = ShippingMethod.prototype.save.wrap(
            function(parentMethod) {
                parentMethod();
  
                // Shipping method changed
                document.fire('xpc:checkoutChanged', 'shipping');
            }
        );
    }

    // This is for Venedor Theme (1.6.3) and Onepagecheckout
    if ($('onepagecheckout_orderform')) {

        // Checkout is loaded
        document.fire('xpc:checkoutChanged', 'loaded');

        payment.switchMethod = payment.switchMethod.wrap(
            function(parentMethod) {
                parentMethod();

                // Payment method changed
                document.fire('xpc:checkoutChanged', 'payment');
            }
        );

        OPC.prototype.save = OPC.prototype.save.wrap(
            function(parentMethod) {

                if (isXpcMethod()) {

                    if (this.loadWaiting != false) {
                        return;
                    }

                    // Save original "save" URL
                    this.savedSaveUrl = this.saveUrl;

                    this.saveUrl = xpcData.url[getCurrentXpcMethod()].saveCheckoutData;

                    parentMethod();

                    checkout.setLoadWaiting(true);
                    $('review-please-wait').show();

                    this.saveUrl = this.savedSaveUrl;

                    if (xpcData.useIframe) {
                        sendSubmitMessage();
                    } else {
                        window.location.href = xpcData.url[getCurrentXpcMethod()].dropTokenAndRedirect;
                    }

                } else {

                    return parentMethod();
                }
            }
        );
    }
});


