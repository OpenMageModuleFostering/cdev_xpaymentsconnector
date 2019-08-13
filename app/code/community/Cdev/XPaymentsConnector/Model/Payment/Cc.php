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
 * Abstract class for X-Payments payment methods (3 slots) 
 */
abstract class Cdev_XPaymentsConnector_Model_Payment_Cc extends Cdev_XPaymentsConnector_Model_Payment_Abstract 
{
    /**
     * Show or not save card checkbox statuses
     */
    const SAVE_CARD_DISABLED = 'N';
    const SAVE_CARD_REQUIRED = 'Y';
    const SAVE_CARD_OPTIONAL = 'O';

    protected $_isGateway               = false;

    protected $_defaultLocale           = 'en';

    protected $_canUseCheckout          = true;
    protected $_canUseInternal          = true;
    protected $_canUseForMultishipping  = false;

    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;

    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment method info block
     */
    protected $_infoBlockType = 'xpaymentsconnector/info_cc';

    /**
     * Payment method form block
     */
    protected $_formBlockType = 'xpaymentsconnector/form_cc';

    public $_currentProfileId = null;

    /**
     * Get slot index of the XPC payment method
     * (number from xpayments1, xpayments2, etc)
     *
     * @return int
     */
    public function getXpcSlot()
    {
        return substr($this->getCode(), -1);
    }

    /**
     * Get payment configuration model
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     */
    public function getPaymentConfiguration()
    {
        return Mage::getModel('xpaymentsconnector/paymentconfiguration')
            ->load($this->getConfigData('confid'));
    }

    /**
     * Get temporary reference ID
     *
     * @param mixed $entity Quote or customer
     *
     * @return string
     */
    protected function getTmpRefId($entity)
    {
        $ref = $entity->getId() . Mage::getModel('core/date')->date('U');
        $ref = substr(md5($ref), 0, 8);
        $ref = $entity->getId() . '_' . $ref;

        return $ref;
    }

    /**
     * Obtain token from X-Payments via initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote Quote (for checkout or backend order)
     * @param Mage_Customer_Model_Customer $customer (for zero auth)
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function obtainToken(Cdev_XPaymentsConnector_Model_Quote $quote = null, Mage_Customer_Model_Customer $customer = null)
    {
        $helper = Mage::helper('xpaymentsconnector');

        if (!is_null($customer)) {
            $isZeroAuth = true;
        } elseif (!is_null($quote)) {
            $isZeroAuth = false;
        } else {
            throw new Exception('Incorrect data for initial payment request');
        }

        if ($isZeroAuth) {

            $refId = 'authorization';
            $entityId = $customer->getId();

            $preparedCart = Mage::helper('cart_xpc')->prepareFakeCart($customer, $this->getXpcSlot());

            $isBackend = (bool)$customer->getXpBufer();

        } else {

            $entityId = $quote->getEntityId();

            if ($quote->isBackendOrderQuote()) {
                $refId = $quote->getXpcData()->getData('backend_orderid');
                $isBackend = true;
            } else {
                $refId = $this->getTmpRefId($quote);
                $isBackend = false;
            }

            $preparedCart = Mage::helper('cart_xpc')->prepareCart($quote, $refId);
        }

        // Data to send to X-Payments
        $data = array(
            'confId'      => intval($this->getPaymentConfiguration()->getData('confid')),
            'refId'       => $refId,
            'cart'        => $preparedCart,
            'returnUrl'   => $helper->getReturnUrl($entityId, $this->getXpcSlot(), $isZeroAuth, $isBackend),
            'callbackUrl' => $helper->getCallbackUrl($entityId, $this->getXpcSlot(), $isZeroAuth),
        );

        $response = Mage::helper('api_xpc')->initPayment($data);

        $response->setData('order_refid', $refId);

        if (
            $response->getStatus() 
            && !$isZeroAuth
        ) {
            $quote->getXpcData()
                ->setData('token', $response->getField('token'))
                ->setData('txn_id', $response->getField('txnId'))
                ->save(); 

            if ($quote->isBackendOrderQuote()) {
                $quote->getBackendOrder()->setData('xpc_txnid', $response->getField('txnId'))->save();
            }
        }

        return $response;
    }

    /**
     * Checks if Save Card checkbox must be forced to be Required
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return string
     */
    protected function getAllowSaveCard(Mage_Sales_Model_Quote $quote)
    {
        $helper = Mage::helper('xpaymentsconnector');

        // Check if save card feature is available for customer
        $checkCustomer = $quote->isBackendOrderQuote()
            || Mage::helper('xpaymentsconnector')->isRegisteredUser();

        // Check if feature is available
        $checkSettings = Mage::helper('settings_xpc')->isCanSaveCards();

        if ($checkCustomer && $checkSettings) {

            // Check if recurring product is purchased
            $allowSaveCard = $quote->getRecurringItem()
                ? static::SAVE_CARD_REQUIRED
                : static::SAVE_CARD_OPTIONAL;

        } else {

            $allowSaveCard = static::SAVE_CARD_DISABLED;
        }

        return $allowSaveCard;
    }

    /**
     * Check if payment initialization token is valid.
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote Quote
     *
     * @return bool
     */
    public function prepareToken(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        if (!$quote->getXpcData()->getData('token')) {
            // This saves token in the quote XPC data model
            $this->obtainToken($quote);
        }

        return (bool)$quote->getXpcData()->getData('token');
    }

    /**
     * Fields for form redirecting to the payment page
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return array
     */
    public function getFormFields(Mage_Sales_Model_Quote $quote)
    {
        $token = $quote->getXpcData()->getData('token');

        return array(
            'target' => 'main',
            'action' => 'start',
            'token'  => $token,
            'allow_save_card' => $this->getAllowSaveCard($quote),
        );
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
        $helper = Mage::helper('xpaymentsconnector');

        // Just in case. We don't know the status of the transaction yet
        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_UNKNOWN);

        $quote = $profile->getQuote();
        $txnId = $quote->getXpcData()->getData('txn_id');

        if (empty($txnId)) {

            // Processing of exception is performed in helper's funcPlaceOrder()
            throw new Exception('Unable to submit recurring profile. TxnId is empty');
        }

        $helper->writeLog('Quote customer ID', $quote->getCustomer()->getId());

        if ($quote->getCustomer()->getId()) {
            $profile->setCustomerId($quote->getCustomer()->getId());
        }

        $profile->setReferenceId($txnId)->save();        

        $orderId = $helper->createOrder($profile, true);

        $quote->getXpcData()
            ->setData('recurring_order_id', $orderId)
            ->setData('recurring_profile_id', $profile->getInternalReferenceId())
            ->save();

        $helper->writeLog('Submit recurring profile #' . $profile->getInternalReferenceId() . ' TxnId: ' . $txnId);
    }

    /**
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function createFirstRecurringOrder(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $helper = Mage::helper('xpaymentsconnector');
        $cardData = $helper->getXpCardData();


        $helper->writeLog('Create first recurring order', $cardData, true);

        $cardData = $xpHelper->getXpCardData();
        $orderId = $xpHelper->createOrder($profile, $isFirstRecurringOrder = true);

        /*update order by card data*/
        $order = Mage::getModel('sales/order')->load($orderId);
        $order->setData('xp_card_data', serialize($cardData));
        $order->save();
        $orderItemInfo = $profile->getData('order_item_info');

        $profile->setReferenceId($cardData['txnId']);
        if (is_null($this->_currentProfileId)) {
            $result = $this->updateOrderByXpaymentResponse($orderId, $cardData['txnId']);
        } else {
            $grandTotal = $orderItemInfo['nominal_row_total'];
            $response = $this->sendAgainTransactionRequest($orderId, NULL, $grandTotal, $cardData);

            if ($response['success']) {
                $result = $this->updateOrderByXpaymentResponse($orderId, $response['response']['transaction_id']);
            }
        }

        if (!$result['success']) {
            Mage::getSingleton('checkout/session')->addError($result['error_message']);
            Mage::getSingleton('checkout/session')->addNotice($xpHelper->getFailureCheckoutNoticeHelper());
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
        } else {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            // additional subscription profile setting for success transaction
            $newTransactionDate = new Zend_Date(time());
            $profile->setXpSuccessTransactionDate($newTransactionDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));
            $profile->setXpCountSuccessTransaction(1);
        }

        $this->_currentProfileId = $profile->getProfileId();

        return $profile;

    }
}
