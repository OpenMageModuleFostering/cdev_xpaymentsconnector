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
 * Process payment controller
 *
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_ProcessingController extends Mage_Core_Controller_Front_Action
{
    /**
     * Get checkout session
     *
     * @return object
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    /**
     * Check IP address of callback request
     *
     * @return bool
     */
    protected function checkIpAdress()
    {
        $result = true;

        $ips = preg_grep(
            '/^.+$/Ss',
            explode(',', Mage::getStoreConfig('xpaymentsconnector/settings/xpay_allowed_ip_addresses'))
        );

        if ($ips) {

            $helper = Mage::helper('core/http');

            if (method_exists($helper, 'getRemoteAddr')) {

                $remoteAddr = $helper->getRemoteAddr();

            } else {

                $request = $this->getRequest()->getServer();
                $remoteAddr = $request['REMOTE_ADDR'];
            }

            $result = in_array($remoteAddr, $ips);
        }

        if (!$result) {
            Mage::helper('xpaymentsconnector')->writeLog(
                'Received callback request from unallowed IP address: ' . $remoteAddr . PHP_EOL
                    . 'Allowed IP\'s are: ' . Mage::getStoreConfig('xpaymentsconnector/settings/xpay_allowed_ip_addresses')
            );
        }

        return $result;
    }

    /**
     * Process masked card data from the callback request
     *
     * @param array  $data Update data
     * @param int    $xpcSlot Slot index of the XPC payment method
     * @param int    $confId Payment configuration ID
     * @param string $txnId Payment reference
     * @param string $parentTxnId Parent payment reference
     *
     * @return void
     */
    protected function processMaskedCardData($data, $xpcSlot, $confId, $txnId, $parentTxnId = '')
    {
        if (!empty($data['maskedCardData'])) {

            $helper = Mage::helper('xpaymentsconnector');

            $order = $helper->getOrderByTxnId($txnId, $parentTxnId);
            $customerId = Mage::app()->getRequest()->getParam('customer_id');

            $cardData = $data['maskedCardData'];
            $cardData['txnId'] = $txnId;
            $cardData['confid'] = $confId;

            if (!empty($data['advinfo'])) {
                $cardData['advinfo'] = $data['advinfo'];
            }

            if ($order->getId()) {

                $helper->saveMaskedCardToOrder($order, $cardData);

                $customerId = $order->getData('customer_id');

                $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
                    ->setXpcSlot($xpcSlot);

                $isRecurring = (bool)$quote->getRecurringItem();

            } else {

                $isRecurring = false;
            }

            if (
                Mage::helper('api_xpc')->isSuccessStatus($data['status']) 
                && isset($data['saveCard'])
                && 'Y' == $data['saveCard']
            ) {

                $usageType = $isRecurring
                    ? Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD
                    : Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD;

                Mage::getModel('xpaymentsconnector/usercards')->saveUserCard($cardData, $customerId, $usageType);
            }
        }
    }

    /**
     * Process fraud check data from the callback request
     *
     * @param array  $data Update data
     * @param string $txnId Payment reference
     *
     * @return void
     */
    protected function processFraudCheckData($data, $txnId)
    {
        $helper = Mage::helper('xpaymentsconnector');
        $order = $helper->getOrderByTxnId($txnId);

        try {

            if (
                $order->getId()
                && !empty($data['fraudCheckData'])
                && is_array($data['fraudCheckData'])
            ) {

                foreach ($data['fraudCheckData'] as $fraudCheckData) {

                    $model = Mage::getModel('xpaymentsconnector/fraudcheckdata')
                        ->getCollection()
                        ->addFieldToFilter('order_id', $order->getId())
                        ->addFieldToFilter('code', $fraudCheckData['code'])
                        ->getFirstItem();

                    $modelData = array(
                        'order_id'       => $order->getId(),
                        'code'           => $fraudCheckData['code'],       // All these fields are considered as mandatory.
                        'service'        => $fraudCheckData['service'],    // So it's normal to cause a notice or an exception
                        'result'         => $fraudCheckData['result'],     // in case one of them is missing.
                        'status'         => isset($fraudCheckData['status']) ? $fraudCheckData['status'] : '',
                        'score'          => isset($fraudCheckData['score']) ? $fraudCheckData['score'] : 0,
                        'message'        => isset($fraudCheckData['message']) ? $fraudCheckData['message'] : '',
                        'transaction_id' => isset($fraudCheckData['transactionId']) ? $fraudCheckData['transactionId'] : '',
                        'url'            => isset($fraudCheckData['url']) ? $fraudCheckData['url'] : '',
                        'errors'         => isset($fraudCheckData['errors']) ? serialize($fraudCheckData['errors']) : '',
                        'warnings'       => isset($fraudCheckData['warnings']) ? serialize($fraudCheckData['warnings']) : '',
                        'rules'          => isset($fraudCheckData['rules']) ? serialize($fraudCheckData['rules']) : '',
                        'data'           => isset($fraudCheckData['data']) ? serialize($fraudCheckData['data']) : '',
                    );

                    if ($model->getData('data_id')) {
                        $modelData['data_id'] = $model->getData('data_id');
                    }

                    $model->setData($modelData)->save();
                }
            }

        } catch (Exception $e) {
            $helper->writeLog('Error while saving fraud check data: ' . $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Process flat order payment data from the callback request
     *
     * @param array  $data Update data
     * @param string $txnId Payment reference
     * @param string $parentTxnId Parent payment reference
     *
     * @return void
     */
    protected function processFlatOrderPaymentData($data, $txnId, $parentTxnId = '')
    {
        $helper = Mage::helper('xpaymentsconnector');
        $order = $helper->getOrderByTxnId($txnId, $parentTxnId);

        try {

            // Process AVS
            if (!empty($data['advinfo']['AVS'])) {

                $avs = $data['advinfo']['AVS'];

            } elseif (!empty($data['cardValidation'])) {

                $cardValidation = array(
                    'avs_z' => 'AVS ZIP/Postal code',
                    'avs_a' => 'AVS Address (street)',
                    'avs_c' => 'AVS Cardholder name',
                );

                $avs = array();

                foreach ($cardValidation as $key => $title) {
                    if ($data['cardValidation'][$key]) {
                        $avs[] = $title . ': ' . ('1' == $data['cardValidation'][$key] ? 'Match' : 'Not match');
                    }
                }

                $avs = implode(PHP_EOL, $avs); 

            } else {
 
                $avs = '';
            }

            // Process CVV
            if (!empty($data['advinfo']['CVV'])) {

                $cvv = $data['advinfo']['CVV'];

            } elseif (
                !empty($data['cardValidation'])
                && $data['cardValidation']['cvv']
            ) {

                $cvv = 'CVV2/CVD/Card secure code: ' . ('1' == $data['cardValidation']['cvv'] ? 'Match' : 'Not match');

            } else {

                $cvv = '';
            }

            $ccOwner = isset($data['maskedCardData']['cardholder_name'])
                ? $data['maskedCardData']['cardholder_name']
                : '';

            // This is for the USAePay module
            if (!empty($data['advinfo']['UMcardRef'])) {

                $encCardNumber = $data['advinfo']['UMcardRef'];

            } else {

                $parentOrder = $helper->getOrderByTxnId($parentTxnId);

                if (
                    $parentOrder->getEntityId()
                    && $parentOrder->getPayment()->getData('cc_number_enc')
                ) {

                    $encCardNumber = $parentOrder->getPayment()->getData('cc_number_enc');

                } else {

                    $encCardNumber = '';
                }
            }

            // This is for Payflow Pro module
            if (!empty($data['advinfo']['PNREF'])) {
                $lastTransId = $data['advinfo']['PNREF'];
            } else {
                $lastTransId = '';
            }

            $order->getPayment()
                ->setData('cc_avs_status', $avs)
                ->setData('cc_cid_status', $cvv)
                ->setData('cc_owner', $ccOwner)
                ->setData('cc_last4', $data['maskedCardData']['last4'])
                ->setData('cc_type', $data['maskedCardData']['type'])
                ->setData('cc_number_enc', $encCardNumber)
                ->setData('cc_exp_month', $data['maskedCardData']['expire_month'])
                ->setData('cc_exp_year', $data['maskedCardData']['expire_year'])
                ->setData('cc_trans_id', $data['advinfo']['txn_id'])
                ->setData('cc_status', $data['advinfo']['Message'])
                ->setData('last_trans_id', $lastTransId)
                ->save();

        } catch (Exception $e) {
            $helper->writeLog('Error while saving flat order payment data data: ' . $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Check returned transaction total and currency
     *
     * @param Mage_Sales_Model_Order $order
     * @param array  $data Update data
     *
     * @return bool
     */
    private function checkTotalAndCurrency(Mage_Sales_Model_Order $order, $data)
    {
        $total = $order->getGrandTotal();

        $currency = $order->getData('order_currency_code');

        return $currency == $data['currency']
            && 0.001 > abs($total - $data['amount']);
    }

    /**
     * Clear usage information of the coupon for the declined/canceled order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */ 
    private function clearCouponUsage(Mage_Sales_Model_Order $order)
    {
        $couponCode = $order->getCouponCode();

        if (!empty($couponCode)) {

            try {

                $coupon = Mage::getModel('salesrule/coupon');
                $coupon->load($couponCode, 'code');

                if ($coupon->getRuleId()) {

                    $coupon->setTimesUsed($coupon->getTimesUsed() - 1)->save();

                    $customerId = $order->getCustomerId();

                    if ($customerId) {

                        $customerCoupon = Mage::getModel('salesrule/rule_customer')->loadByCustomerRule($customerId, $coupon->getRuleId());

                        if ($customerCoupon) {
                            $customerCoupon->setTimesUsed($customerCoupon->getTimesUsed() - 1)->save();
                        }

                        $couponUsage = Mage::getResourceModel('salesrule/coupon_usage');
                        $couponUsage->decreaseCustomerCouponTimesUsed($customerId, $coupon->getId());
                    }
                }

                Mage::helper('xpaymentsconnector')->writeLog('Clear coupon usage. Code: ' . $couponCode);

            } catch (Exception $e) {
    
                Mage::helper('xpaymentsconnector')->writeLog(
                    'Error in clear coupon usage. Code: : ' . $couponCode . PHP_EOL . $e->getMessage(), 
                    $e->getTraceAsString()
                );
            }
        }
    }

    /**
     * Process payment status from the callback request. Chhange order status.
     *
     * @param array  $data Update data
     * @param int    $xpcSlot Slot index of the XPC payment method
     * @param string $txnId Payment reference
     * @param string $parentTxnId Parent payment reference
     *
     * @return array
     */
    protected function processPaymentStatus($data, $xpcSlot, $txnId, $parentTxnId = '')
    {
        $helper = Mage::helper('xpaymentsconnector');

        $order = $helper->getOrderByTxnId($txnId, $parentTxnId);

        if (
            !$order->getId()
            || empty($data['status'])
        ) {
            
            $helper->writeLog('Order not found for ' . $txnId);

            return $data;
        }

        $status = $state = false;

        $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
            ->setXpcSlot($xpcSlot);

        $api = Mage::helper('api_xpc');

        $message = $helper->getResultMessage($data);

        if (
            $api->isSuccessStatus($data['status'])
            && !$this->checkTotalAndCurrency($order, $data)
        ) {

            $data['status'] = $api::DECLINED_STATUS;

            $message = $this->__(
                'Gateway reported about the successfull transaction, but the transaction amount %s, or currency %s do not match the order. Original response was: %s',
                $data['amount'],
                $data['currency'],
                $message
            );            
        }

        if ($api->isSuccessStatus($data['status'])) {

            // Success

            try {

                // Set X-Payments payment reference
                $order->getPayment()->setTransactionId($txnId);

                // Set status
                $status = $api::AUTH_STATUS == $data['status']
                    ? Cdev_XPaymentsConnector_Helper_Data::STATUS_AUTHORIZED
                    : Cdev_XPaymentsConnector_Helper_Data::STATUS_CHARGED;

                // Set state 
                $state = Mage_Sales_Model_Order::STATE_PROCESSING;

                $recurringProfile = $helper->getOrderRecurringProfile($order);

                if ($recurringProfile) {
                    $recurringProfile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE)->save();
                }

            } catch (Exception $e) {

                // Something wrong has happened. 
                // Even if the payment is successfull, try to finalize the order correctly with failed status
                $helper->writeLog('Exception while processing successful payment', $e->getMessage());
                $data['status'] = $api::DECLINED_STATUS;

                $message = $this->__(
                    'Gateway reported about the successfull transaction, but the store cannot complete the order. Original response was: %s',
                    $message
                );
            }
        }

        if ($api::DECLINED_STATUS == $data['status']) {

            // Failure

            $order->cancel();

            $state = $status = $order::STATE_CANCELED;

            // Save error message for quote
            $quote->getXpcData()
                ->setData('xpc_message', $message)
                ->save(); 

            // Clear coupon usage information
            $this->clearCouponUsage($order);

            $quote->setIsActive(true)->save();

        } elseif ($api::REFUNDED_STATUS == $data['status']) {

            // Order is refunded

            $state = $status = $order::STATE_CLOSED;

            // Save error message for quote (if any)
            $quote->getXpcData()
                ->setData('xpc_message', $message)
                ->save();

        } elseif ($api::PART_REFUNDED_STATUS == $data['status']) {

            // Order is partially refunded
            // Curently processing is the most suitable state

            $state = $status = $order::STATE_PROCESSING;

            // Save error message for quote (if any)
            $quote->getXpcData()
                ->setData('xpc_message', $message)
                ->save();
        }

        if ($status) {

            // Message for log
            $logMessage = 'Order #' . $order->getIncrementId() . PHP_EOL
                . 'Entity ID: ' . $order->getEntityId() . PHP_EOL 
                . 'X-Payments message: ' . $message . PHP_EOL
                . 'New order status: ' . $status . PHP_EOL
                . 'New order state: ' . $state;

            // Message for status change
            $statusMessage = 'Callback request. ' . $message;

            if (
                $order::STATE_CLOSED == $state
                || $order::STATE_COMPLETE == $state
            ) {

                // These states cannot be set manually.
                // So use this way to avoid error.

                $order->setData('state', $state);
                $order->setData('status', $status);
            
            } else {

                $order->setState($state, $status, $statusMessage, false);

            }

            $order->save();

            $helper->writeLog('Order status changed by callback request.', $logMessage);
        }

        return $data;
    }

    /**
     * Get check cart response for checkout
     *
     * @param string $quoteId
     * @param int    $xpcSlot Slot index of the XPC payment method
     * @param int    $storeId Store ID
     *
     * @return array
     */
    protected function getQuoteCheckCartResponse($quoteId, $xpcSlot, $storeId = 0)
    {
        $helper = Mage::helper('xpaymentsconnector');

        $quote = Mage::getModel('xpaymentsconnector/quote');

        if (!empty($storeId)) {

            // Set current store (if passed)
            $store = Mage::getSingleton('core/store')->load($storeId);
            $quote->setStore($store);
        }

        $quote->load($quoteId)->setXpcSlot($xpcSlot);

        if ($quote->isBackendOrderQuote()) {

            // This order has been created in the backend. We'll not place it again
            $refId = $quote->getXpcData()->getData('backend_orderid');

            // Send items "as is"
            $response = array(
                'status' => 'cart-not-changed',
            );

        } elseif ($quote->getRecurringItem($quote)) {

            // Place order with recurring profile. 
            // After that checking for nominal item is not possible.
            $refId = $helper->funcPlaceOrder($quote, $xpcSlot);

            // Send nominal items "as is"
            $response = array(
                'status' => 'cart-not-changed',
            );

        } else {

            // Place regular order.
            $refId = $helper->funcPlaceOrder($quote, $xpcSlot);

            if ($refId) {

                // Cart data to update payment
                $preparedCart = Mage::helper('cart_xpc')->prepareCart($quote, $refId);

                $response = array(
                    'status' => 'cart-changed',
                    'ref_id' => $refId,
                    'cart'   => $preparedCart,
                );

            } else {

                // This will decline payment
                $response = array(
                    'status' => 'cart-changed',
                );
            }
        }

        return $response;
    }

    /**
     * Get check cart response for zero auth
     *
     * @param string $customerId
     *
     * @return array
     */
    protected function getCustomerCheckCartResponse($customerId)
    {
        $data = array(
            'status' => 'cart-not-changed',
            'ref_id' => 'Authorization',
        );

        return $data;
    }

    /**
     * Flush response
     *
     * @param string $response Response
     *
     * @return void
     */
    protected function flushResponse($response = '')
    {
        ob_end_clean();

        header('Connection: close');

        ignore_user_abort(true);

        ob_start();

        echo $response;

        $size = ob_get_length();

        header('Content-Length: ' . $size);
        header('Content-Encoding: none');

        ob_end_flush();
        flush();

        if (is_callable('fastcgi_finish_request')) {
            // Required for nginx
            session_write_close();
            fastcgi_finish_request();
        }

        exit;
    }

    /**
     * Process callback request
     *
     * @return void
     */
    public function callbackAction()
    {
        ini_set('html_errors', false);

        // Check request data
        $request = $this->getRequest()->getPost();

        if (
            !$this->getRequest()->isPost()
            || empty($request)
            || empty($request['txnId'])
            || empty($request['action'])
        ) {
            Mage::throwException('Invalid request');
        }

        $api = Mage::helper('api_xpc');

        // Check IP addresses
        if (!$this->checkIpAdress()) {
            exit;
        }

        $helper = Mage::helper('xpaymentsconnector');

        $quoteId = Mage::app()->getRequest()->getParam('quote_id');
        $customerId = Mage::app()->getRequest()->getParam('customer_id');
        $xpcSlot = Mage::app()->getRequest()->getParam('xpc_slot');
        $storeId = Mage::app()->getRequest()->getParam('store_id');
        $confId = Mage::helper('settings_xpc')->getConfidByXpcSlot($xpcSlot);

        if (
            'check_cart' == $request['action']
            && !empty($xpcSlot)
            && (
                !empty($quoteId)
                || !empty($customerId)
            )
        ) {

            // Process check-cart callback request
            
            $data = $quoteId
                ? $this->getQuoteCheckCartResponse($quoteId, $xpcSlot, $storeId)
                : $this->getCustomerCheckCartResponse($customerId, $request['txnId']);

            $helper->writeLog('Response for check-cart request', $data);

            // Convert to XML and encrypt
            $xml = $api->convertHash2XML($data);
            $xml = $api->encrypt($xml);

            // Flush response, close connection and exit
            $this->flushResponse($xml);

        } elseif (
            'callback' == $request['action']
            && !empty($request['updateData'])
        ) {

            // Process callback request
        
            // Decrypt data
            $data = $api->decryptXML($request['updateData']);

            $helper->writeLog('Callback request received', $data);

            $txnId = $request['txnId'];
            $parentTxnId = !empty($data['parentId']) ? $data['parentId'] : '';

            // Change order status according to the X-Payments payment status
            $data = $this->processPaymentStatus($data, $xpcSlot, $txnId, $parentTxnId);

            // Save used credit card
            $this->processMaskedCardData($data, $xpcSlot, $confId, $txnId, $parentTxnId);

            // Process fraud check data
            $this->processFraudCheckData($data, $txnId);

            // Process flat order payment data
            $this->processFlatOrderPaymentData($data, $txnId, $parentTxnId);

            // Close connection and exit
            $this->flushResponse();

        } else {

            $helper->writeLog('Invalid callback request', $request);
        }

    }

    /**
     * Process cancel by customer (from X-Payments interface)
     *
     * @return bool
     */
    protected function processCancel()
    {
        $result = false;

        $query = $this->getRequest()->getQuery();

        if (
            isset($query['action'])
            && 'cancel' == $query['action']
        ) {

            Mage::getSingleton('core/session')->addError('Payment canceled');

            $url = Mage::getUrl('checkout/cart');
            $this->_redirectUrl($url);

            $result = true;
        }

        return $result;
    }

    /**
     * Restore cart content on payment failure
     *
     * @return void
     * @access puplic
     * @see    ____func_see____
     * @since  1.0.6
     */
    public function restoreCart($order)
    {

        $session = Mage::getSingleton('checkout/session');

        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

        //Return quote
        if ($quote->getId()) {

            $quote->setIsActive(1)->setReservedOrderId(NULL)->save();
            $session->replaceQuote($quote);

        }

    }

    /**
     * Save address in the address book
     *
     * @param array $data Address data to save
     * @param int   $customerId Customer Id
     *
     * @return void
     */
    private function saveAddress($data, $customerId)
    {
        if (!is_numeric($customerId)) {
            return;
        }

        try {

            $newAddress = Mage::getModel('customer/address');

            $newAddress->setData($data)
                ->setCustomerId($customerId)
                ->setSaveInAddressBook(true);

            $newAddress->save();

        } catch (Exception $e) {

            // Unable to save address for some reason
            Mage::helper('xpaymentsconnector')->writeLog('Error saving address in address book', $e->getMessage());            
        }
    }

    /**
     * Save addresses in address book (if necessary) 
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    private function processSaveAddresses(Cdev_XPaymentsConnector_Model_Quote $quote, Mage_Sales_Model_Order $order)
    {
        $customerId = $quote->getCustomer()->getId();

        if ($quote->getXpcData()->getData('address_saved')) {
            // Address already saved during customer registration
            return;
        }

        $customerId = $quote->getCustomer()->getId();

        if ($quote->isSaveBillingAddressInAddressBook()) {
            $this->saveAddress($order->getBillingAddress()->getData(), $customerId);
        }

        if ($quote->isSaveShippingAddressInAddressBook()) {
            $this->saveAddress($order->getShippingAddress()->getData(), $customerId);
        }
    }

    /**
     * Process return after successful payment
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    private function processReturnSuccess(Cdev_XPaymentsConnector_Model_Quote $quote, Mage_Sales_Model_Order $order)
    {
        $quoteId = $quote->getId(); 

        $session = $this->getOnePage()->getCheckout();

        $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
        $session->setLastOrderId($order->getId())->setLastRealOrderId($order->getIncrementId());

        $profile = Mage::helper('xpaymentsconnector')->getOrderRecurringProfile($order);

        if ($profile) {
            $session->setLastRecurringProfileIds(array($profile->getProfileId()));
        }

        // Auto create invoice if necessary
        Mage::helper('xpaymentsconnector')->processCreateInvoice($order);

        // Save addresses in the adress book if necessary
        $this->processSaveAddresses($quote, $order);

        $session->setXpcRedirectUrl(Mage::getUrl('checkout/onepage/success'));
    }

    /**
     * Process return after declined payment
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param Mage_Sales_Model_Order $order
     *
     * @return void 
     */
    private function processReturnDecline(Cdev_XPaymentsConnector_Model_Quote $quote, Mage_Sales_Model_Order $order)
    {
        $session = $this->getOnePage()->getCheckout();
        $helper = Mage::helper('xpaymentsconnector');

        $message = $quote->getXpcData()->getData('xpc_message');

        if (!$message) {
            $message = 'Order declined. Try again';
        }

        $session->clearHelperData();

        $quote->setAsActive(true);

        $helper->resetInitData($quote);

        Mage::getSingleton('core/session')->addError($message);
        $this->_getCheckout()->addError($message);
    }

    /**
     * Process return when order is lost
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return void
     */
    private function processReturnLostOrder(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $helper = Mage::helper('xpaymentsconnector');

        $message = $quote->getXpcData()->getData('xpc_message');

        if (!$message) {
            $message = 'Order was lost';
        }

        $helper->resetInitData($quote);

        Mage::throwException($message);
    }

    /**
     * Send confirmation email
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    private function processConfirmationEmail(Mage_Sales_Model_Order $order)
    {
        $notified = false;

        try {

            // Send confirmation email
            $order->getSendConfirmation(null);
            $order->sendNewOrderEmail();

            $notified = true;

        } catch (Exception $e) {

            // Do not cancel order if we couldn't send email
            Mage::helper('xpaymentsconnector')->writeLog('Error sending email', $e->getMessage());
        }

        $order->addStatusToHistory($order->getStatus(), 'Customer returned to the store', $notified);
    }

    /**
     * Return customer from X-Payments
     *
     * @return void
     */
    public function returnAction()
    {
        if ($this->processCancel()) {
            return;
        }

        // Check request data
        $request = $this->getRequest()->getPost();

        if (
            !$this->getRequest()->isPost()
            || empty($request)
            || empty($request['refId'])
            || empty($request['txnId'])
        ) {
            Mage::throwException('Invalid request');
        }

        $session = $this->getOnePage()->getCheckout();

        $helper = Mage::helper('xpaymentsconnector');
        $helper->writeLog('Customer returned from X-Payments', $request);        

        $quoteId = Mage::app()->getRequest()->getParam('quote_id');
        $xpcSlot = Mage::app()->getRequest()->getParam('xpc_slot');

        $quote = Mage::getModel('xpaymentsconnector/quote')->load($quoteId)
            ->setXpcSlot($xpcSlot);

        try {

            // Log in customer who's registered at checkout
            if ($helper->isCreateNewCustomer($quote, true)) {

                $customerId = $quote->getCustomer()->getId();
                $result = Mage::getSingleton('customer/session')->loginById($customerId);

                $helper->writeLog('Log in customer who\'s registered at checkout: user #' . $customerId, $result);
            }

        } catch (Exception $e) {

            $helper->writeLog('Unable to log in user registered at checkout. ' . $e->getMessage(), $e->getTraceAsString());
        }

        try {

            $order = $helper->getOrderByTxnId($request['txnId']);

            if (!$order->getId()) {

                // Process return when order is lost
                $this->processReturnLostOrder($quote);

            } elseif ($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {

                // Process return after successful payment
                $this->processReturnSuccess($quote, $order);

                // Send confirmation email
                $this->processConfirmationEmail($order);

            } else {

                // Process return after declined payment
                $this->processReturnDecline($quote, $order);
            }

            $order->save();

        } catch (Mage_Core_Exception $e) {

            $helper->writeLog($e->getMessage(), $e->getTraceAsString());

            $this->_getCheckout()->addError($e->getMessage());

            $session->setXpcRedirectUrl(Mage::getUrl('checkout/onepage/failure'));
        }

        $this->loadLayout();

        $this->renderLayout();
    }

    /**
     * Get quote model
     *
     * @param int $xpcSlot Slot index of the XPC payment method
     *
     * @return Cdev_XPaymentsConnector_Model_Quote
     */
    private function getCheckoutSessionQuote($xpcSlot)
    {
        $quoteId = Mage::getSingleton('checkout/session')->getQuoteId();

        $quote = Mage::getModel('xpaymentsconnector/quote')->load($quoteId)
            ->setXpcSlot($xpcSlot);
    
        return $quote;
    }

    /**
     * Redirect action
     * Submit form to X-Payments payment start action
     *
     * @return void
     */
    public function redirectAction()
    {
        $xpcSlot = Mage::app()->getRequest()->getParam('xpc_slot');

        if (!Mage::helper('settings_xpc')->checkXpcSlot($xpcSlot, false)) {
            Mage::throwException('Invalid request');
        }

        if (Mage::app()->getRequest()->getParam('checkout_method')) {

            Mage::getSingleton('checkout/session')->setData('xpc_checkout_method', Mage::app()->getRequest()->getParam('checkout_method'));
        }        

        if (Mage::app()->getRequest()->getParam('drop_token')) {

            $quote = $this->getCheckoutSessionQuote($xpcSlot);

            Mage::helper('xpaymentsconnector')->resetInitData($quote);
        }

        if (!Mage::app()->getRequest()->isXmlHttpRequest()) {

            $this->loadLayout();

            $this->renderLayout();
        }
    }

    /**
     * Recharge action
     * Pay by saved card at checkout
     *
     * @return void
     */
    public function rechargeAction()
    {
        $session = Mage::getSingleton('checkout/session');

        $cardId = $session->getXpcSaveCardId();
        $order = Mage::getModel('sales/order')->load(
            $session->getLastOrderId()
        );

        $card = Mage::getModel('xpaymentsconnector/usercards')->load($cardId);

        $helper = Mage::helper('xpaymentsconnector');

        $helper->saveMaskedCardToOrder($order, $card->getData());
        $order->setData('xpc_txnid', $card->getData('txnId'))->save();

        $paymentMethod = $order->getPayment()->getMethodInstance();

        $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
            ->setXpcSlot($paymentMethod->getXpcSlot());

        $response = $paymentMethod->processPayment($quote);

        // Reload order and quote data, since it has been changed
        $order = Mage::getModel('sales/order')->load($order->getId());
        $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
            ->setXpcSlot($paymentMethod->getXpcSlot());

        if (
            !$response->getStatus()
            || !Mage::helper('api_xpc')->isSuccessStatus($response->getField('status'))
        ) {

            // Payment is declined (or something went wrong)

            if ($quote->getXpcData()->getData('xpc_message')) {
                $message = $quote->getXpcData()->getData('xpc_message');
            } else {
                $message = $response->getErrorMessage('Transaction failed');
            }

            // Cancel order if it's not canceled yet
            if ($order::STATE_CANCELED != $order->getState()) {

                $order->cancel();
                $order->setState($order::STATE_CANCELED, false, $message, false)->save();

                $quote->setIsActive(true)->save();
            }

            Mage::getSingleton('core/session')->addError($message);

            $this->_redirect('checkout/cart');

        } else {

            // Payment is processed

            // Auto create invoice if necessary
            Mage::helper('xpaymentsconnector')->processCreateInvoice($order);

            // Send confirmation email
            $this->processConfirmationEmail($order);

            $this->_redirect('checkout/onepage/success');
        }
    }

    /**
     * Save checkout data before submitting the order
     *
     * @return void
     */
    public function save_checkout_dataAction()
    {
        $request = $this->getRequest();

        $xpcSlot = Mage::app()->getRequest()->getParam('xpc_slot');

        if (
            !$request->isPost()
            || !$request->isXmlHttpRequest()
            || !Mage::helper('settings_xpc')->checkXpcSlot($xpcSlot, false)
        ) {
             Mage::throwException('Invalid request');
        }

        $quote = $this->getCheckoutSessionQuote($xpcSlot);
        
        $quote->getXpcData()
            ->setData('checkout_data', serialize($request->getPost()))
            ->save();

        if (Mage::helper('settings_xpc')->checkFirecheckoutModuleEnabled()) {
            // return properly formatted {} for Firecheckout 
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array()));
        }
    }

    /**
     * Check agreements 
     *
     * @return void
     */
    public function check_agreementsAction()
    {
        $result = array(
            'success' => true,
            'error'   => false,
        );

        $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
        if ($requiredAgreements) {

            $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));

            if (array_diff($requiredAgreements, $postedAgreements)) {
                $result['success'] = false;
                $result['error'] = true;
                $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
            }
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
