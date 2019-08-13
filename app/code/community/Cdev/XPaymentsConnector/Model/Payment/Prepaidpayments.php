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
 * "Prepaid Payments (X-Payments)" method (only for admin)
 * Class Cdev_XPaymentsConnector_Model_Payment_Prepaidpayments
 */

class Cdev_XPaymentsConnector_Model_Payment_Prepaidpayments extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = "prepaidpayments";
    protected $_formBlockType = 'xpaymentsconnector/form_prepaidpayments';
    protected $_infoBlockType = 'xpaymentsconnector/info_prepaidpayments';


    protected $_isGateway               = false;
    protected $_paymentMethod           = 'cc';
    protected $_defaultLocale           = 'en';
    protected $_canCapturePartial       = true;
    protected $_canCapture              = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = false;

    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;

    protected $_order = null;


    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }

        return $this->_order;
    }


    public function refund(Varien_Object $payment, $amount)
    {

        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }

        /*processing during create invoice*/
        $order = $this->getOrder();
        /*processing during capture invoice*/
        $data = array(
            'txnId' => $order->getData("xpc_txnid"),
            'amount' => number_format($amount, 2, '.', ''),
        );

        Mage::getModel("xpaymentsconnector/payment_cc")->authorizedTransactionRequest('refund', $data);

        return $this;
    }


}

