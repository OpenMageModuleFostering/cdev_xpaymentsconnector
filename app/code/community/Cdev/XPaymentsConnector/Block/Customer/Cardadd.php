<?php
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

/**
 * Block to add new cards to the list (frontend)
 */

class Cdev_XPaymentsConnector_Block_Customer_Cardadd extends Mage_Core_Block_Template
{

    /**
     * Enter description here...
     *
     * @return string
     */
    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {
            return $this->getRefererUrl();
        }
        return $this->getUrl('customer/account/usercards/');
    }

    /**
     * @return string (url)
     */
    public function getIframeUrl(){

        // update standart iframe handshake request
        $refId =  "authorization";
        $updateSendData = array();
        $updateSendData["returnUrl"] = Mage::getUrl('xpaymentsconnector/customer/cardadd', array('order_refid' => $refId,'_secure' => true));
        $updateSendData["callbackUrl"] =  Mage::getUrl('xpaymentsconnector/customer/cardadd', array('order_refid' => $refId, '_secure' => true));
        $updateSendData["refId"] = $refId;

        $xpaymentFormData = Mage::helper('payment')->getMethodInstance("xpayments")->getFormFields();
        $xpaymentFormUrl = Mage::helper('payment')->getMethodInstance("xpayments")->getUrl();
        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        $result = $api->sendIframeHandshakeRequest($updateSendData,$isCardAuthorizePayment = true);

        $iframeUrlDataArray = array('target' => $xpaymentFormData['target'], 'token' => $result['response']['token']);
        $iframeUrl = $xpaymentFormUrl . "?" . http_build_query($iframeUrlDataArray);
        return $iframeUrl;
    }


}