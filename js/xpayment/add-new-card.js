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

    $('xpc-iframe').contentWindow.postMessage(message, '*');
}

document.observe('dom:loaded', function () {

    /**
     * X-Payments iframe is ready.
     */
    document.observe('xpc:ready', function (event) {

        console.log('xpc:ready', event.memo);

        $('loading-container').hide();

        var iframe = $('xpc-iframe');

        if (event.memo.height) {
            // the iframe is hidden and shown in case of the error
            var height = event.memo.height;
            iframe.setStyle( {'height': height + 'px'} );
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

        sendSubmitMessage();
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
            $('xpc-iframe').setStyle( {'height': event.memo.height + 'px'} );
        }

        type = parseInt(event.memo.type);

        if (XPC_IFRAME_CLEAR_INIT_DATA == type) {

            document.fire('xpc:clearInitData');

        } else if (XPC_IFRAME_CHANGE_METHOD == type) {

            document.fire('xpc:changeMethod');
        }
    });


    /**
     * Clear init data and reload iframe.
     */
    document.observe('xpc:clearInitData', function (event) {

        console.log('xpc:clearInitData', event.memo);

        // Anyway this will create a new token
        window.location.reload();
    });

    /**
     * Clear init data and return to payment cards page.
     */
    document.observe('xpc:changeMethod', function (event) {

        console.log('xpc:changeMethod', event.memo);

        // Anyway this will create a new token
        window.location.href = xpcData.url.paymentCards;
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

    var submitButton = $('submit-button');
    if (submitButton) {
      Event.observe($('submit-button'), 'click', function() {
          sendSubmitMessage();
      });
    }

    var addressSelect = $('billing-address');
    if (addressSelect) {
        Event.observe(addressSelect, 'change', function() {
            window.location.href = url.replace('ADDRESSID', addressSelect.value);
        });
    }

    var selectAlls = $$('.select-all-link');
    var checkboxes = $$('.related-checkbox');
    if (selectAlls && checkboxes) {
        selectAlls.each( function(elm) {
            elm.observe('click', function (event) { 
                var isChecked = !this.hasClassName('unselect');
                checkboxes.each( function (elm) {
                    elm.checked = isChecked;
                });
            });
        })
    }
});
