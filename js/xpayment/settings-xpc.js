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
 * @author Qualiteam Software <info@x-cart.com>
 * @category   Cdev
 * @packageCdev_XPaymentsConnector
 * @copyright  (c) 2010-present Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @licensehttp://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Show/hide row for iframe place
 */
function switchIframePlaceRow(event)
{
  var useIframeSelect = Event.element(event);

  if (useIframeSelect.value == 1){
    iframePlaceRow.show();
  } else {
    iframePlaceRow.hide();
  }
}

/**
 * Switch URL from HTTPS to HTTP
 */
function switchUrl(elm)
{
  if (typeof elm == 'undefined' || !$('xpay-url')) {
    return;
  }

  var url = $('xpay-url').innerText;

  if (elm.checked) {
    $('xpay-url').update(url.replace('https:', 'http:'));
  } else {
    $('xpay-url').update(url.replace('http:', 'https:'));
  }
}

/**
 * Show/hide sticky buttons for the payment methods form 
 */
function switchButtons()
{
  var formButtons = $$('.form-buttons');

  if (!formButtons) {
    return;
  }

  var paymentMethodsContent = $('settings_xpc_tabs_paymentmethods_section_content');

  if (
    paymentMethodsContent
    && paymentMethodsContent.visible()
  ) {
    formButtons.each( function (elm) { elm.show(); });
  } else {
    formButtons.each( function (elm) { elm.hide() });
  }
}

/**
 * Submit payment methods form by the sticky button
 */
function submitPaymentMethodsForm(elm) 
{
  $('payment-method-form-mode').value = elm.value;
  $('payment-methods-form').submit();
}

/** 
 * Disable payment configuration row
 */
function disableRow(elm) 
{
  $(elm).up('tr').addClassName('disabled');
  $(elm).disable();
}

/**
 * Enable payment configuration row
 */
function enableRow(elm) 
{
  $(elm).up('tr').removeClassName('disabled');
  $(elm).enable();
}

/**
 * Show/hide payment method block
 */
function switchPaymentMethod(elm)
{
  var paymentMethodId = elm.id.replace('active-checkbox', 'payment-method');
  var saveCardId = elm.id.replace('active-checkbox', 'savecard-checkbox');

  if (elm.checked) {
    $(paymentMethodId).show();

    if ($(saveCardId) && $(saveCardId).checked) {
      $('payment-method-savedcards').show();
    }

  } else {
    $(paymentMethodId).hide();
  }
}

/**
 * Process changes in checkboxes on payment methods form
 */
function processFormChange(event, elm)
{

  if (elm && $(elm).hasClassName('pm-active')) {

    $$('input[class^="pm-disable"]')[0].checked = false;

  } else if ($$('input[class^="pm-disable"]')[0].checked) {

    $$('input[class^="pm-active"]').each( function(elm) { elm.checked = false; });
  }

  var count = $$('input:checked[class^="pm-active"]').size();

  if (count >= MAX_SLOTS) {

    $$('input:not(:checked)[class^="pm-active"]').each( function(elm) { disableRow($(elm));});

  } else {

    $$('input[class^="pm-active"]').each( function(elm) { enableRow($(elm));});
  }

  $('payment-method-savedcards').hide();

  $$('input[class^="pm-active"]').each( function(elm) { switchPaymentMethod(elm); });
}

/**
 * Show/hide list of allowed countries for payment method
 */
function switchCountries(elm)
{
  if (elm.value == 1) {
    $(elm).up('tr').next('tr.specificcountry').removeClassName('hidden');
  } else {
    $(elm).up('tr').next('tr.specificcountry').addClassName('hidden');
  }
}

document.observe('dom:loaded', function() {

  var connectLink = $('connect-link');

  if (connectLink) {
    Event.observe(connectLink, 'click', function() { $('settings_xpc_tabs_connection_section').click(); });
  }

  var useIframeSelect = $('use-iframe-select');
  var iframePlaceRow = $('iframe-place-row');

  if (
    useIframeSelect
    && iframePlaceRow
    && useIframeSelect.value == 0
  ) {
    iframePlaceRow.hide();
  }

  if (useIframeSelect) {
    Event.observe(useIframeSelect, 'change', switchIframePlaceRow.bind(this));
  }

  var paymentMethodsForm = $('payment-methods-form');

  if (paymentMethodsForm) {
    paymentMethodsForm.on('change', 'input', function (event, elm) { processFormChange(event, elm); });
  }

  var allowSpecificBoxes = $$('.allowspecific');

  if (allowSpecificBoxes) {
    allowSpecificBoxes.invoke('observe' ,'change', function (event) { switchCountries(this); });
    allowSpecificBoxes.each( function(elm) { switchCountries(elm); });
  }

  var tabLinks = $$('.tab-item-link');

  if (tabLinks) {
      tabLinks.invoke('observe', 'click', function (event) { switchButtons(); });
  }

  var zeroAuthForm = new varienForm('zero-auth-form', true);
  Validation.add('validate-float', 'Amount is not correct', function(value) {
    return Validation.get('IsEmpty').test(value) || !isNaN(value)
  });

  if (paymentMethodsForm) {
    processFormChange();
  }

});
