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
        $usercards = Mage::getModel('xpaymentsconnector/usercards');

        $data = array(
            'user_id'       => $customerId,
            'txnId'         => $cardData['txnId'],
            'last_4_cc_num' => $cardData['last4'],
            'first6'        => $cardData['first6'],
            'card_type'     => $cardData['type'],
            'expire_month'  => $cardData['expire_month'],
            'expire_year'   => $cardData['expire_year'],
            'usage_type'    => Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD,
        );

        $usercards->setData($data);

        $usercards->save();
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
     * Get error message from X-Payments callback data
     * 
     * @param array $data Callback data
     * 
     * @return string
     */
    protected function getResultMessage($data)
    {
        $message = array();

        // Regular message from X-Payments
        if (!empty($data['message'])) {
            $message[] = $data['message'];
        }

        if (isset($data['advinfo'])) {

            // Message from payment gateway
            if (isset($data['advinfo']['message'])) {
                $message[] = $data['advinfo']['message'];
            }

            // Message from 3-D Secure
            if (isset($data['advinfo']['s3d_message'])) {
                $message[] = $data['advinfo']['s3d_message'];
            }
        }

        $message = array_unique($message);

        return implode("\n", $message);
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
            return;
        }

        $status = $state = false;

        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        $message = $this->getResultMessage($data);

        if (
            $api::AUTH_STATUS == $data['status']
            || $api::CHARGED_STATUS == $data['status']
        ) {

            // Success

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
                ? Cdev_XPaymentsConnector_Helper_Data::STATE_XPAYMENTS_PENDING_PAYMENT
                : Mage_Sales_Model_Order::STATE_PROCESSING;


            // Set state 
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;

        } elseif ($api::DECLINED_STATUS == $data['status']) {

            // Failure

            $order->cancel();

            $state = $status = $order::STATE_CANCELED;

            // Save error message in quote
            $helper->appendQuoteXpcData(
                $quote, 
                array(
                    'xpc_message' => $message,
                )
            );

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

        $response = array(
            'status' => 'cart-changed',
        );

        $quote = Mage::getModel('sales/quote')->load($quoteId);

        // Place order
        $refId = $helper->funcPlaceOrder($quote);

        if ($refId) {
    
            // Cart data to update payment
            $preparedCart = $helper->prepareCart($quote, $refId);

            $response += array(
                'ref_id' => $refId,
                'cart'   => $preparedCart,
            );

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
            $api->getApiError('IP can\'t be validated as X-Payments server IP.');
            return;
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
        $data = Mage::helper('xpaymentsconnector')->loadQuoteXpcData($quote);

        if (!empty($data['address_saved'])) {
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

        if (
            $order->getStatus() == Mage_Sales_Model_Order::STATE_PROCESSING
            && $order->canInvoice()
        ) {

            // Auto create invoice for the charged payment

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);

            $invoice->register();

            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transaction->save();
        }

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

        $data = $helper->loadQuoteXpcData($quote);

        $message = !empty($data['xpc_message'])
            ? $data['xpc_message']
            : 'Order declined. Try again';

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

        $data = $helper->loadQuoteXpcData($quote);

        if (!empty($data['xpc_message'])) {
            $message = $data['xpc_message'];
        } else {
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

        try {

            $quoteId = Mage::app()->getRequest()->getParam('quote_id');
            $quote = Mage::getModel('sales/quote')->load($quoteId);
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

            // Login customer who's registered at checkout
            if ($helper->isCreateNewCustomer($quote, true)) {
                $customerId = $quote->getCustomer()->getId();
                Mage::getSingleton('customer/session')->loginById($customerId);
            } 

        } catch (Mage_Core_Exception $e) {

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
        $helper->saveCheckoutData($quote, $request->getPost());
        $quote->save();

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
