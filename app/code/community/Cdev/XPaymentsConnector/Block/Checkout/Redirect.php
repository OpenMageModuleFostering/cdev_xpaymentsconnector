<?php
// vim: set ts=4 sw=4 sts=4 et:
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
 * Redirect to X-Payments block
 */
class Cdev_XPaymentsConnector_Block_Checkout_Redirect extends Mage_Core_Block_Template
{
    /**
     * Quote model
     */
    private $quote = null;

    /**
     * Payment method object 
     */
    private $paymentMethod = null;

    /**
     * Get checkout quote
     *
     * @return Mage_Sales_Model_quote
     */
    private function getQuote()
    {
        if (is_null($this->quote)) {

            $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();
            $xpcSlot = $this->getPaymentMethod()->getXpcSlot();

            $this->quote = Mage::getModel('xpaymentsconnector/quote')->load($quoteId)
                ->setXpcSlot($xpcSlot);
        }

        return $this->quote;
    }

    /**
     * Get payment method model
     *
     * @return Cdev_XPaymentsConnector_Model_Payment_Cc
     */
    protected function getPaymentMethod()
    {
        if (is_null($this->paymentMethod)) {

            $xpcSlot = intval(Mage::app()->getRequest()->getParam('xpc_slot'));

            $this->paymentMethod = Mage::getModel('xpaymentsconnector/payment_cc' . $xpcSlot);
        }

        return $this->paymentMethod;
    }

    /**
     * Get form action (URL)
     * 
     * @return string
     */
    public function getFormAction()
    {
        return Mage::helper('settings_xpc')->getPaymentUrl();
    }

    /**
     * Get form data 
     * 
     * @return array
     */
    public function getFormData()
    {
        return $this->getPaymentMethod()->getFormFields($this->getQuote());
    }

    /**
     * Check if payment token is valid
     *
     * @return bool
     */
    public function prepareToken()
    {
        return $this->getPaymentMethod()->prepareToken($this->getQuote());
    }
}
