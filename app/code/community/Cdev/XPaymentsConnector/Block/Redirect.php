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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Redirect to X-Payments block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Redirect extends Mage_Core_Block_Template
{
    /**
     * Get checkout session object
     * 
     * @return object
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    public $paymentMethod = Null;
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get order 
     * 
     * @return Mage_Sales_Model_Order or null
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _getOrder()
    {
        $order = null;

        if ($this->getOrder()) {
            $order = $this->getOrder();

        } else {
            $orderIncrementId = $this->_getCheckout()->getLastRealOrderId();
            if ($orderIncrementId) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
            }

        }

        return $order;
    }

    /**
     * Get payment method model
     *
     * @return
     */
    protected function getPaymentMethod()
    {
        if (is_null($this->paymentMethod)) {
            $this->paymentMethod = Mage::getModel('xpaymentsconnector/payment_cc');
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
        return $this->getPaymentMethod()->getUrl();
    }

    /**
     * Get form data 
     * 
     * @return array
     */
    public function getFormData()
    {
        return $this->getPaymentMethod()->getFormFields();
    }

    /**
     * Check if payment token is valid
     *
     * @return bool
     */
    public function checkToken()
    {
        return $this->getPaymentMethod()->checkToken();
    }
}
