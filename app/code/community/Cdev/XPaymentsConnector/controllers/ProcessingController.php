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
     * @param array $cardData Card data
     * @param int $customerId Customer ID
     *
     * @return void
     */
    protected function saveUserCard($cardData, $customerId)
    {
        $txnId = $cardData['txnId'];

        // Try to find card with already existing txnId.
        // Then update it or fill the empty found entity.
        $usercards = Mage::getModel('xpaymentsconnector/usercards')
            ->getCollection()
            ->addFieldToFilter('txnId', $txnId)
            ->getFirstItem();

        $data = array(
            'user_id'       => $customerId,
            'txnId'         => $txnId,
            'last_4_cc_num' => $cardData['last4'],
            'first6'        => $cardData['first6'],
            'card_type'     => $cardData['type'],
            'expire_month'  => $cardData['expire_month'],
            'expire_year'   => $cardData['expire_year'],
            'usage_type'    => Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD,
        );

        if ($usercards->getData('xp_card_id')) {
            $data['xp_card_id'] = $usercards->getData('xp_card_id');
        }

        $usercards->setData($data)->save();
    }

    /**
     * Process masked card data from the callback request
     *
     * @param array  $data Update data
     * @param string $txnId Payment reference
     *
     * @return void
     */
    protected function processMaskedCardData($data, $txnId)
    {
        if (!empty($data['maskedCardData'])) {

            $helper = Mage::helper('xpaymentsconnector');

            $order = $helper->getOrderByTxnId($txnId);
            $customerId = Mage::app()->getRequest()->getParam('customer_id');

            $cardData = $data['maskedCardData'];
            $cardData['txnId'] = $txnId;

            if (!empty($data['advinfo'])) {
                $cardData['advinfo'] = $data['advinfo'];
            }

            if ($order->getId()) {

                $helper->saveMaskedCardToOrder($order, $cardData);

                $customerId = $order->getData('customer_id');
            }

            $successStatus = Cdev_XPaymentsConnector_Model_Payment_Cc::AUTH_STATUS == $data['status']
                || Cdev_XPaymentsConnector_Model_Payment_Cc::CHARGED_STATUS == $data['status'];

            if (
                $successStatus
                && isset($data['saveCard'])
                && 'Y' == $data['saveCard']
            ) {
                $this->saveUserCard($cardData, $customerId);
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
                        'code'           => $fraudCheckData['code'],
                        'service'        => $fraudCheckData['service'],
                        'result'         => $fraudCheckData['result'],
                        'status'         => $fraudCheckData['status'],
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
     * Process payment status from the callback request. Chhange order status.
     *
     * @param array  $data Update data
     * @param string $txnId Payment reference
     *
     * @return void
     */
    protected function processPaymentStatus($data, $txnId)
    {
        $helper = Mage::helper('xpaymentsconnector');

        $order = $helper->getOrderByTxnId($txnId);

        if (
            !$order->getId()
            || empty($data['status'])
        ) {
            
            $helper->writeLog('Order not found for ' . $txnId);

            return;
        }

        $status = $state = false;

        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        $message = $helper->getResultMessage($data);

        if (
            $api::AUTH_STATUS == $data['status']
            || $api::CHARGED_STATUS == $data['status']
        ) {

            // Success

            try {

                // Set X-Payments payment reference
                $order->getPayment()->setTransactionId($txnId);

                // Set AVS. Something wrong actually. Need to add cardValidation
                if (
                    isset($data['advinfo']) 
                    && isset($data['advinfo']['AVS'])
                ) {
                    $order->getPayment()->setCcAvsStatus($data['advinfo']['AVS']);
                }

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
            $helper->getQuoteXpcData($quote)
                ->setData('xpc_message', $message)
                ->save(); 

            $quote->setIsActive(true)->save();
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

            $order->setState($state, $status, $statusMessage, false);

            $order->save();

            $helper->writeLog('Order status changed by callback request.', $logMessage);
        }
    }

    /**
     * Get check cart response for checkout
     *
     * @param string $quoteId
     *
     * @return array
     */
    protected function getQuoteCheckCartResponse($quoteId)
    {
        $helper = Mage::helper('xpaymentsconnector');

        $quote = Mage::getModel('sales/quote')->load($quoteId);

        if ($helper->getRecurringQuoteItem($quote)) {

            // Place order with recurring profile. 
            // After that checking for nominal item is not possible.
            $refId = $helper->funcPlaceOrder($quote);

            // Send nominal items "as is"
            $response = array(
                'status' => 'cart-not-changed',
            );

        } else {

            // Place regular order.
            $refId = $helper->funcPlaceOrder($quote);

            if ($refId) {

                // Cart data to update payment
                $preparedCart = $helper->prepareCart($quote, $refId);

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
        $helper = Mage::helper('xpaymentsconnector');

        $customer = Mage::getModel('customer/customer')->load($customerId);
        $preparedCart = $helper->prepareFakeCart($customer);

        $data = array(
            'status' => 'cart-changed',
            'ref_id' => 'Authorization',
            'cart'   => $preparedCart,
        );

        return $data;
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

        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        // Check IP addresses
        if (!$this->checkIpAdress()) {
            exit;
        }

        $helper = Mage::helper('xpaymentsconnector');

        $quoteId = Mage::app()->getRequest()->getParam('quote_id');
        $customerId = Mage::app()->getRequest()->getParam('customer_id');

        if (
            'check_cart' == $request['action']
            && (
                !empty($quoteId)
                || !empty($customerId)
            )
        ) {

            // Process check-cart callback request
            
            $data = $quoteId
                ? $this->getQuoteCheckCartResponse($quoteId)
                : $this->getCustomerCheckCartResponse($customerId, $request['txnId']);

            $helper->writeLog('Response for check-cart request', $data);

            // Convert to XML and encrypt
            $xml = $api->convertHash2XML($data);
            $xml = $api->encrypt($xml);

            echo $xml;

            exit;

        } elseif (
            'callback' == $request['action']
            && !empty($request['updateData'])
        ) {

            // Process callback request
        
            // Decrypt data
            $data = $api->decryptXML($request['updateData']);

            $helper->writeLog('Callback request received', $data);

            // Save used credit card
            $this->processMaskedCardData($data, $request['txnId']);

            // Process fraud check data
            $this->processFraudCheckData($data, $request['txnId']);

            // Change order status according to the X-Payments payment status
            $this->processPaymentStatus($data, $request['txnId']);

        } else {

            $helper->writeLog('Invalid callback request', $request);
        }

    }

    /**
     * Payment is success
     *
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function successAction()
    {
        Mage::getSingleton('checkout/session')->setData('xpayments_token', null);
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Payment is cancelled
     *
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function cancelAction()
    {
        Mage::helper('xpaymentsconnector')->unsetXpaymentPrepareOrder();
        $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
        if(empty($profileIds)){
            $this->_getCheckout()->addError(Mage::helper('xpaymentsconnector')->__('The order has been canceled.'));
        }
        $this->_redirect('checkout/cart');
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
     * Start payment (handshake + redirect to X-Payments)
     *
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();

            // Get order id
            $order = Mage::getModel('sales/order');
            $orderId = $session->getLastRealOrderId();
            $api = Mage::getModel('xpaymentsconnector/payment_cc');

            if($orderId){

                $order->loadByIncrementId($orderId);

                $result = $api->sendHandshakeRequest($order);

                if (!$result) {
                    $failedCompleteMessage = 'Failed to complete the payment transaction.'
                        .' Please use another payment method or contact the store administrator.';
                    $this->_getCheckout()->addError($failedCompleteMessage);

                } else {

                    // Update order
                    if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                            (bool)Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                            Mage::helper('xpaymentsconnector')->__('Customer has been redirected to X-Payments.')
                        )->save();
                    }

                    $this->loadLayout();

                    $this->renderLayout();

                    return;
                }
            }

            $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
            if(!empty($profileIds)){
                $this->loadLayout();
                $this->renderLayout();
                return;
            }

            if (!$orderId || $profileIds) {
                Mage::throwException('No order or profile for processing found');
            }



        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());

        } catch(Exception $e) {
            Mage::logException($e);
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Save address in the address book
     *
     * @param array $data Address data to save
     *
     * @return void
     */
    private function saveAddress($data)
    {
        if (empty($data['customer_id'])) {
            return;
        }

        $newAddress = Mage::getModel('customer/address');

        $newAddress->setData($data)
            ->setCustomerId($data['customer_id'])
            ->setSaveInAddressBook('1');

        $newAddress->save();
    }

    /**
     * Save addresses in address book (if necessary) 
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    private function processSaveAddresses(Mage_Sales_Model_Quote $quote)
    {
        if (Mage::helper('xpaymentsconnector')->getQuoteXpcData($quote)->getData('address_saved')) {
            // Address already saved during customer registration
            return;
        }

        if ($quote->getBillingAddress()->getData('save_in_address_book')) {
            $this->saveAddress($quote->getBillingAddress()->getData());
        }

        if (
            $quote->getShippingAddress()->getData('save_in_address_book')
            && !$quote->getShippingAddress()->getData('same_as_billing')
        ) {
            $this->saveAddress($quote->getShippingAddress()->getData());
        }
    }

    /**
     * Process return after successful payment
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */
    private function processReturnSuccess(Mage_Sales_Model_Quote $quote, Mage_Sales_Model_Order $order)
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
        $this->processSaveAddresses($quote);

        $session->setXpcRedirectUrl(Mage::getUrl('checkout/onepage/success'));
    }

    /**
     * Process return after declined payment
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Sales_Model_Order $order
     *
     * @return void 
     */
    private function processReturnDecline(Mage_Sales_Model_Quote $quote, Mage_Sales_Model_Order $order)
    {
        $session = $this->getOnePage()->getCheckout();
        $helper = Mage::helper('xpaymentsconnector');

        $message = $helper->getQuoteXpcData($quote)->getData('xpc_message');

        if (!$message) {
            $message = 'Order declined. Try again';
        }

        $session->clearHelperData();

        $quote->setAsActive(true);

        $helper->resetInitData();

        Mage::getSingleton('core/session')->addError($message);
        $this->_getCheckout()->addError($message);
    }

    /**
     * Process return when order is lost
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    private function processReturnLostOrder(Mage_Sales_Model_Quote $quote)
    {
        $helper = Mage::helper('xpaymentsconnector');

        $message = $helper->getQuoteXpcData($quote)->getData('xpc_message');

        if (!$message) {
            $message = 'Order was lost';
        }

        $helper->resetInitData();

        Mage::throwException($message);
    }

    /**
     * Send confirmation email
     *
     * @param Mage_Sales_Model_Quote $quote
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
        $quote = Mage::getModel('sales/quote')->load($quoteId);

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

            } else {

                // Process return after declined payment
                $this->processReturnDecline($quote, $order);
            }

            // Send confirmation email
            $this->processConfirmationEmail($order);

            $order->save();

        } catch (Mage_Core_Exception $e) {

            $helper->writeLog($e->getMessage(), $e->getTraceAsString());

            $this->_getCheckout()->addError($e->getMessage());

            $session->setXpcRedirectUrl(Mage::getUrl('checkout/onepage/failure'));
        }

        $this->loadLayout();

        $this->renderLayout();
    }


    public function saveusercardAction(){
        $request = $this->getRequest()->getPost();
        if(!empty($request)){
            if($request['user_card_save']){
                Mage::getSingleton('checkout/session')->setData('user_card_save',$request['user_card_save']);
            }
        }
    }

    /**
     * Redirect iframe to the X-Payments URL
     *
     * @return void
     */
    public function redirectiframeAction()
    {
        if (Mage::app()->getRequest()->getParam('checkout_method')) {

            Mage::getSingleton('checkout/session')->setData('xpc_checkout_method', Mage::app()->getRequest()->getParam('checkout_method'));
        }        

        if (Mage::app()->getRequest()->getParam('unset_xp_prepare_order')) {

            $helper = Mage::helper('xpaymentsconnector');
            $helper->resetInitData();
        }

        if (!Mage::app()->getRequest()->isXmlHttpRequest()) {

            $this->loadLayout();

            $this->renderLayout();
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

        if (
            !$request->isPost()
            || !$request->isXmlHttpRequest()
        ) {
             Mage::throwException('Invalid request');
        }

        $helper = Mage::helper('xpaymentsconnector');

        $quote = Mage::getSingleton('checkout/session')->getQuote();

        $helper->getQuoteXpcData($quote)
            ->setData('checkout_data', serialize($request->getPost()))
            ->save();

        if ($helper->checkFirecheckoutModuleEnabled()) {
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
