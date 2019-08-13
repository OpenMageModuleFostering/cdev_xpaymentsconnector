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
 * "Use saved credit cards (X-Payments)"
 * Class Cdev_XPaymentsConnector_Model_Payment_Savedcards
 */

class Cdev_XPaymentsConnector_Model_Payment_Savedcards extends Mage_Payment_Model_Method_Abstract
    implements  Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code = "savedcards";
    protected $_formBlockType = 'xpaymentsconnector/form_savedcards';
    protected $_infoBlockType = 'xpaymentsconnector/info_savedcards';


    protected $_isGateway               = false;
    protected $_paymentMethod           = 'cc';
    protected $_defaultLocale           = 'en';
    protected $_canCapturePartial       = true;
    protected $_canCapture              = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
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

    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {

        if (!$this->canCapture()) {
            Mage::throwException(Mage::helper('payment')->__('Capture action is not available.'));
        }

        $order = $this->getOrder();
        $data = array(
            'txnId' => $order->getData("xpc_txnid"),
            'amount' => number_format($amount, 2, '.', ''),
        );

        Mage::getModel("xpaymentsconnector/payment_cc")->authorizedTransactionRequest('capture', $data);


        return $this;
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


    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){

    }

    /**
     * Submit to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo){

        $orderItemInfo = $profile->getData("order_item_info");
        $payDeferredSubscription = Mage::helper("xpaymentsconnector")->payDeferredSubscription($profile);
        if(!$payDeferredSubscription){
            $grandTotal = $orderItemInfo["nominal_row_total"];

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $paymentCardNumber = $quote->getPayment()->getData("xp_payment_card");
            $cardData = Mage::getModel("xpaymentsconnector/usercards")->load($paymentCardNumber);
            $txnid = $cardData->getData("txnId");
            if($txnid){
                $profile->setReferenceId($txnid);
                $orderId = Mage::helper("xpaymentsconnector")->createOrder($profile,$isFirstRecurringOrder = true);
                $response = Mage::getModel("xpaymentsconnector/payment_cc")->sendAgainTransactionRequest($orderId, NULL, $grandTotal);

                if ($response["success"]) {
                    $result = Mage::getModel("xpaymentsconnector/payment_cc")->updateOrderByXpaymentResponse($orderId, $response["response"]['transaction_id']);
                    if (!$result["success"]) {
                        Mage::getSingleton("checkout/session")->addError($result["error_message"]);
                        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                    } else {
                        // additional subscription profile setting for success transaction
                        $newTransactionDate = new Zend_Date(time());
                        $profile->setXpSuccessTransactionDate($newTransactionDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));
                        $profile->setXpCountSuccessTransaction(1);

                        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
                    }

                } else {
                    Mage::getSingleton("checkout/session")->addError($response["error_message"]);
                    $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                }
            }
        }


    }

    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result){
        // TODO
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails(){
        return true;
    }

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
       // TODO
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile){
        // TODO
    }

}
