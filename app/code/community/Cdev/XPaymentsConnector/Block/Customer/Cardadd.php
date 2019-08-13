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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block to add new cards to the list (frontend)
 */

class Cdev_XPaymentsConnector_Block_Customer_Cardadd extends Mage_Core_Block_Template
{
    protected $_customerSession = null;
    protected $_defaultBillingAddress = null;



    /**
     * Internal constructor, that is called from real constructor
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_customerSession = Mage::getSingleton('customer/session');
        $customer = $this->_customerSession->getCustomer();
        $this->_defaultBillingAddress = $customer->getDefaultBillingAddress();

    }

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
    public function getAuthorizeIframeUrl(){

        // update standart iframe handshake request
        $refId =  'authorization';
        $updateSendData = array();

        $customerId = $this->_customerSession->getId();

        $updateSendData['returnUrl'] = Mage::getUrl('xpaymentsconnector/customer/cardadd',
            array('order_refid' => $refId,'customer_id' => $customerId,'_secure' => true));
        $updateSendData['callbackUrl'] =  Mage::getUrl('xpaymentsconnector/processing/callback',
            array('order_refid' => $refId,'customer_id' => $customerId,'_secure' => true));
        $updateSendData['refId'] = $refId;
        $updateSendData['template'] = 'magento_iframe';

        $xpaymentFormData = Mage::helper('payment')->getMethodInstance('xpayments')->getFormFields();
        $xpaymentFormUrl = Mage::helper('payment')->getMethodInstance('xpayments')->getUrl();
        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        $result = $api->sendIframeHandshakeRequest(true);
        if($result['success']){
            $iframeUrlDataArray = array('target' => $xpaymentFormData['target'], 'token' => $result['response']['token']);
            $iframeUrl = $xpaymentFormUrl . '?' . http_build_query($iframeUrlDataArray);
            $result['iframe_url'] = $iframeUrl;
        }

        return $result;
    }

    /**
     * @return string (url)
     */
    public function getXpayUrl(){
        $xpayUrlMas =  parse_url(Mage::getModel('xpaymentsconnector/payment_cc')->getConfig('xpay_url'));
        $xpayUrl =  $xpayUrlMas["scheme"]."://".$xpayUrlMas["host"];
        return $xpayUrl;
    }

    public function getDefaultAddressHtml(){
        return ($this->_defaultBillingAddress) ? $this->_defaultBillingAddress->format('html') : "";
    }

    public function getAddressEditUrl()
    {
        if (!empty($this->getDefaultAddressHtml())) {
            return $this->getUrl('customer/address/edit', array('_secure' => true, 'id' => $this->_defaultBillingAddress->getId()));
        }
        return $this->getUrl('customer/address/edit');
    }


}
