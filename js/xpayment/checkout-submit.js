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
 * @author     Valerii Demidov
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

function submitXpaymentIframe(iframeCheckUrl) {

    var currentIframeUrl =  jQuery("#xp-iframe").attr("src");
    var currentToken = getUrlParameterByName("token",currentIframeUrl);
    var postData = {"token": currentToken};
    jQuery("#review-buttons-container .btn-checkout").hide();
    jQuery("#review-please-wait").show();

    jQuery.ajax({
        url: iframeCheckUrl,
        type: 'post',
        data: postData,
        success: function(data){

            var respnse = jQuery.parseJSON(data);
            if(respnse.is_actual == false){
                alert(respnse.error_message)
                window.location.replace(respnse.redirect);
                return false;
            }

            if (typeof Checkout != "undefined") {
                if (typeof checkout != "undefined") {
                    var message = {
                        message: 'submitPaymentForm',
                        params:  postData
                    };
                    var messageJson =  JSON.stringify(message);
                    var xpcShown = jQuery('.xp-iframe').get(0);
                    xpcShown.contentWindow.postMessage(messageJson, '*');
                    window.addEventListener("message", receiveMessage, false);

                }
            }
        },
        error: function(){
            alert("Can't check token state!");
        }
    });
}


function receiveMessage(event)
{
    jQuery("#review-buttons-container .btn-checkout").show();
    jQuery("#review-please-wait").hide();
}


function getUrlParameterByName(name,url) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(url);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}


