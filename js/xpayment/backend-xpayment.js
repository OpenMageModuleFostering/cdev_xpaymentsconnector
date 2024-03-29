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
 * Submit secondary X-Payments transaction
 */
function submitXpcTransaction(action, formId, amountValidatorName, requiredValidatorName, amount) 
{
    // Amount field validation
    Validation.add(
        amountValidatorName, 
        'Please enter a valid amount. For example 100.00.',
        function (v) {
            var regex = /^([1-9]{1}[0-9]{0,2}(\,[0-9]{3})*(\.[0-9]{0,2})?|[1-9]{1}\d*(\.[0-9]{0,2})?|0(\.[0-9]{0,2})?|(\.[0-9]{1,2})?)$/;
            return Validation.get('IsEmpty').test(v) 
            ||  regex.test(v);
        }
    );

    // Required fields validation
    Validation.add(
        requiredValidatorName,
        'This is a required field.',
        function (v) {
            return !Validation.get('IsEmpty').test(v);
        }
    );

    var form = new varienForm(formId);
    $(formId).down('.xpc_action').value = action;

    if (action == 'void') {
        $(formId).down('.transaction-amount').value = amount;
        form.submit();
    } else {
        if (form.validator.validate()) {
            form.submit();
        }
    }
};

document.observe("dom:loaded", function () {

    $$('.xp-transaction-head-block').each(function(element) {

        element.on('click', function(event) {

            var grid = $(this).up('.entry-edit');

            if ( $(grid).down('.grid').getStyle('display') === 'none'){
                $(grid).down('.grid').show();

                $(grid).down('.transaction-down').hide();
                $(grid).down('.transaction-up').show();
            } else {
                $(grid).down('.grid').hide();

                $(grid).down('.transaction-down').show();
                $(grid).down('.transaction-up').hide();
            }

        });
    });

});

