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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
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

    $('xp-iframe').contentWindow.postMessage(message, '*');
}

/**
 * Check if X-Payments method is currently selected at checkout
 */
function isXpcMethod() 
{
    var block = $$('input:checked[type=radio][name=payment[method]][value=xpayments]');

    return Boolean(block.length);
}

document.observe('dom:loaded', function () {

    /**
     * Redirect or reload iframe
     */ 
    document.observe('xpc:redirectIframe', function (event) {

        console.log('xpc:redirectIframe', event.memo);

        var iframe = $('xp-iframe');

        if (typeof event.memo == 'string') {
            
            // Use passed src
            var src = event.memo;

        } else if ('' != iframe.getAttribute('src')) {


            // Reload iframe
            var src = iframe.getAttribute('src');

        } else {

            // Redirect iframe to the payment page
            var src = xpcData.url.redirectIframe;
        }

        iframe.setStyle( {'height' : '0px'} );
        $('paymentstep-ajax-loader').setStyle({'display' : 'block'});

        if (!xpcData.displayOnReviewStep) {
            $('payment_form_xpayments').setStyle( {'height' : 'auto'} );
        }

        iframe.setAttribute('src', src);
    });

    /**
     * Block with X-Payments iframe is loaded.
     */
    document.observe('xpc:iframeBlockLoaded', function (event) {

        console.log('xpc:iframeBlockLoaded', event.memo);

        if (isXpcMethod()) {
            document.fire('xpc:redirectIframe');
        }
    });

    /**
     * Checkout is changed. Probably we should do something with X-Payments iframe
     */
    document.observe('xpc:checkoutChanged', function (event) {

        console.log('xpc:checkoutChanged', event.memo);

        if (
            $('xpayment-iframe-block')
            && typeof xpcData != 'undefined'
        ) {
            document.fire('xpc:iframeBlockLoaded');
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
                        xpcData.url.checkAgreements,
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
                                        window.location.href = xpcData.url.redirectIframeUnsetOrder;
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
     * Place order via One Step Checkout.
     */
    document.observe('xpc:oneStepCheckoutPlaceOrder', function (event) {

        console.log('xpc:oneStepCheckoutPlaceOrder', event.memo);

        var data = $('onestepcheckout-form').serialize(true);

        // Save checkout data
        new Ajax.Request(
            xpcData.url.saveCheckoutData, 
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
    });

    /**
     * X-Payments iframe is ready.
     */
    document.observe('xpc:ready', function (event) {

        console.log('xpc:ready', event.memo);

        $('paymentstep-ajax-loader').hide();

        var iframe = $('xp-iframe');

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
    });

    /**
     * Notify store about customer's choice to register.
     */
    document.observe('xpc:setCheckoutMethod', function (event) {

        console.log('xpc:setCheckoutMethod', event.memo);

        if (typeof event.memo == 'boolean' && event.memo) {
            var src = xpcData.url.setMethodRegister;
        } else {
            var src = xpcData.url.setMethodGuest;
        }

        if (isXpcMethod()) {

            // Reload iframe
            document.fire('xpc:redirectIframe', src);

        } else {

            // Set checkout method in backgraud
            new Ajax.Request(src);

            // Remove iframe srs, so it's reloaded when shown
            $('xp-iframe').setAttribute('src', '');
        }
    });

    /**
     * Payment form is submitted from X-Payments.
     */
    document.observe('xpc:showMessage', function (event) {

        console.log('xpc:showMessage', event.memo);

        // TODO: This is better via some jQuery popup widget
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
            $('xp-iframe').setStyle( {'height': event.memo.height + 'px'} );
        }

        type = parseInt(event.memo.type);

        if (XPC_IFRAME_CLEAR_INIT_DATA == type) {

            document.fire('xpc:clearInitData');
            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:enableCheckout');

        } else if (XPC_IFRAME_CHANGE_METHOD == type) {

            document.fire('xpc:clearInitData');
            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:changeMethod');
            document.fire('xpc:enableCheckout');

        } else {

            // Alert or show message action

            document.fire('xpc:goToPaymentSection');
            document.fire('xpc:enableCheckout');
        }
    });


    /**
     * Clear init data and reload iframe.
     */
    document.observe('xpc:clearInitData', function (event) {

        console.log('xpc:clearInitData', event.memo);

        document.fire('xpc:redirectIframe', xpcData.url.redirectIframeUnsetOrder);
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
        if ($('firecheckout-form')) {

            checkout.setLoadWaiting(false);
            $('review-please-wait').hide();
        }

        // This is for default onepage checkout
        if ($('co-payment-form')) {
            checkout.setLoadWaiting(false);
        }
    });


    /**
     * Change payment method.
     */
    document.observe('xpc:changeMethod', function (event) {

        console.log('xpc:changeMethod', event.memo);

        window.location.href = xpcData.url.changeMethod;

    });


    /**
     * Check and re-trigger event from X-Payments iframe
     */
    Event.observe(window, 'message', function (event) {

        if (event.origin == xpcData.xpOrigin) {
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

                    // Save original "save" URL
                    this.urls.savedSave = this.urls.save;

                    this.urls.save = xpcData.url.saveCheckoutData;

                    parentMethod(urlSuffix, forceSave);

                    checkout.setLoadWaiting(true);
                    $('review-please-wait').show();

                    this.urls.save = this.urls.savedSave;

                    if (xpcData.useIframe) {
                        sendSubmitMessage();
                    } else {
                        window.location.href = xpcData.url.redirectIframeUnsetOrder;
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
});


