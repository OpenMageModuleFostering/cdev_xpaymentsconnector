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
 * Abstract class for X-Payments CC and saved CC payment methods  
 */
abstract class Cdev_XPaymentsConnector_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract 
    implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    /**
     * Order (cache)
     */
    private $order = null;

    /**
     * Get payment configuration model
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     */
    abstract public function getPaymentConfiguration();

    /**
     * Get internal XPC method code
     * (number from xpayments1, xpayments1, etc)
     *
     * @return int
     */
    abstract public function getXpcSlot();

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->order) {
            $this->order = $this->getInfoInstance()->getOrder();
        }

        return $this->order;
    }

    /**
     * Check method availability
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return boolean
     */
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote)
            && Mage::helper('settings_xpc')->isConfigured()
            && Mage::helper('settings_xpc')->checkRequirements()
            && '1' != Mage::getStoreConfig('advanced/modules_disable_output/Cdev_XPaymentsConnector');
    }

    /**
     * Check - can method Authorize transaction or not
     *
     * @return boolean
     */
    public function canAuthorize()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_auth');
    }

    /**
     * Check - can method Capture transaction or not
     *
     * @return boolean
     */
    public function canCapture()
    {
        $result = false;

        $order = $this->getOrder();

        if ($order->getData('xpc_txnid')) {

            $response = Mage::helper('api_xpc')->requestPaymentInfo($order->getData('xpc_txnid'));

            if (
                $response->getStatus() 
                && $response->getField('status') == Cdev_XPaymentsConnector_Helper_Api_Data::AUTH_ACTION
            ) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Check - can method Refund transaction or not
     *
     * @return boolean
     */
    public function canRefund()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_refund');
    }

    /**
     * Check - can method Partial refund transaction or not
     *
     * @return boolean
     */
    public function canRefundPartialPerInvoice()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_part_refund');
    }

    /**
     * Check - can method Void transaction or not
     *
     * @param Varien_Object $payment Payment
     *
     * @return boolean
     */
    public function canVoid(Varien_Object $payment)
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_void');
    }

    /**
     * Capture specified amount for payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->canCapture()) {

            Mage::throwException(
                Mage::helper('xpaymentsconnector')->__('Capture action is not available.')
            );
        }

        $response = Mage::helper('api_xpc')->requestPaymentCapture($this->getOrder()->getData('xpc_txnid'), $amount);

        if (!$response->getStatus()) {

            $message = $response->getErrorMessage('Capture operation failed');

            Mage::throwException(
                Mage::helper('xpaymentsconnector')->__($message)
            );
        }

        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund()) {
            Mage::throwException(
                Mage::helper('xpaymentsconnector')->__('Refund action is not available.')
            );
        }

        $response = Mage::helper('api_xpc')->requestPaymentRefund($this->getOrder()->getData('xpc_txnid'), $amount);

        if (!$response->getStatus()) {

            $message = $response->getErrorMessage('Refund operation failed');

            Mage::throwException(
                Mage::helper('xpaymentsconnector')->__($message)
            );
        }

        return $this;
    }

    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        // To be implemented.
    }

    /**
     * Process recurring profile when quote is converted to order
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     *
     * @return void
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo)
    {
        // To be implemented.
    }

    /**
     * Fetch details
     *
     * @param string $referenceId
     *
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        // To be implemented.
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
       // To be implemented.
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        // To be implemented.
    }
}
