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
     * Callback request
     *
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function callbackAction()
    {

        // Check request type
        if (!$this->getRequest()->isPost()) {
            Mage::throwException('Wrong request type.');
        }

        // Check IP addresses
        $ips = preg_grep(
            '/^.+$/Ss',
            explode(',', Mage::getStoreConfig('xpaymentsconnector/settings/xpay_allowed_ip_addresses'))
        );

        $api = Mage::getModel('xpaymentsconnector/payment_cc');


        if ($ips) {
            $helper = Mage::helper('core/http');
            if (method_exists($helper, 'getRemoteAddr')) {
                $remoteAddr = $helper->getRemoteAddr();

            } else {
                $request = $this->getRequest()->getServer();
                $remoteAddr = $request['REMOTE_ADDR'];
            }

            if (!in_array($remoteAddr, $ips)) {
                $api->getApiError('IP can\'t be validated as X-Payments server IP.');
            }
        }

        $xpaymentsHelper = Mage::helper('xpaymentsconnector');

        // Check request data
        $request = $this->getRequest()->getPost();

        if (empty($request)) {
            Mage::throwException('Request doesn\'t contain POST elements.');
        }

        // check txn id
        if (empty($request['txnId'])) {
            Mage::throwException('Missing or invalid transaction ID');
        }

        // add request to log
        if (isset($request['updateData'])) {
            $request['updateData'] = $api->decryptXML($request['updateData']);
        }

        Mage::log(sprintf('X-Payment server response: %s', serialize($request)),
            null,
            $xpaymentsHelper::XPAYMENTS_LOG_FILE,
            true);

        $quoteId = Mage::app()->getRequest()->getParam('quote_id');
        $customerId = Mage::app()->getRequest()->getParam('customer_id');

        if (isset($request['action'])) {
            switch ($request['action']) {
                case 'check_cart':

                    if ($quoteId) {
                        $cartModel = Mage::getModel('sales/quote')->load($quoteId);
                        $xpCallbackApprove = $cartModel->getData('xp_callback_approve');
                    } elseif ($customerId) {
                        $customer = Mage::getModel('customer/customer')->load($customerId);
                        $xpCallbackApprove = $customer->getData('xp_buffer');
                    }

                    $cartData = array();
                    if (!empty($xpCallbackApprove)) {
                        $cartData = unserialize($xpCallbackApprove);
                    }

                    $cartData['ref_id'] = $request['txnId'];
                    Mage::log(sprintf('X-Payment approve response: %s', serialize($cartData)),
                        null,
                        $xpaymentsHelper::XPAYMENTS_LOG_FILE,
                        true);

                    $xmlCallbackApprove = $api->convertHash2XML($cartData);
                    // Encrypt
                    $xmlEncrypt = $api->encrypt($xmlCallbackApprove);
                    echo $xmlEncrypt;

                    break;

                case 'callback':
                    if (isset($request['updateData']) && isset($request['updateData']['maskedCardData'])) {
                        $cardData = $request['updateData']['maskedCardData'];
                        $cardData['txnId'] = $request['txnId'];

                        if ($quoteId) {
                            $xpaymentsHelper->saveXpCardData($quoteId,$cardData);
                        } elseif ($customerId) {
                            $customer = Mage::getModel('customer/customer')->load($customerId);
                            $customer->setData('xp_buffer', serialize($cardData));
                            $customer->save();
                        }
                    }

                    break;
            }

        }

        // Check order
        $order = Mage::getModel('sales/order')->loadByAttribute('xpc_txnid', $request['txnId']);
        if ($order->getId()) {

            if (
                $request['updateData']
                && isset($request['updateData']['status'])
            ) {

                switch (intval($request['updateData']['status'])) {
                    case $api::DECLINED_STATUS:
                        $order->cancel();
                        $order->addStatusToHistory(
                            $order::STATE_CANCELED,
                            $xpaymentsHelper->__('charge: Callback request')
                        );
                        break;

                    case $api::CHARGED_STATUS:
                        $order->addStatusToHistory(
                            $order::STATE_COMPLETE,
                            $xpaymentsHelper->__('charge: Callback request')
                        );
                        break;
                }
                $order->save();
            }

        }

        exit(0);
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
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function processCancel()
    {
        $query = $this->getRequest()->getQuery();
        if ($query && isset($query['action']) && $query['action'] == 'cancel') {

            // Check order id
            if (empty($query['refId'])) {
                Mage::throwException('Missing or invalid order ID');
            }

            // Check order
            $order = Mage::getModel('sales/order')->loadByIncrementId(intval($query['refId']));
            $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();

            if (!$order->getId() && empty($profileIds)) {
                Mage::throwException('Order not found');
            }

            /*update recurring*/
            if (!empty($profileIds)) {
                foreach ($profileIds as $profileId) {
                    Mage::helper('xpaymentsconnector')->addRecurringTransactionError();
                    $profile = Mage::getModel('sales/recurring_profile')->load($profileId);
                    $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                    $profile->save();
                }

                $this->getResponse()->setBody(
                    $this->getLayout()
                        ->createBlock('xpaymentsconnector/cancel')
                        ->setOrder($order)
                        ->toHtml()
                );
                return true;
            }

            if ($order->getId()) {
                if ($order->canCancel()) {
                    $this->restoreCart($order);

                    $order->cancel();
                    $order->addStatusToHistory(
                        Mage_Sales_Model_Order::STATE_CANCELED,
                        Mage::helper('xpaymentsconnector')->__('The payment has been canceled')
                    );
                    $order->save();
                }

                $this->getResponse()->setBody(
                    $this->getLayout()
                        ->createBlock('xpaymentsconnector/cancel')
                        ->setOrder($order)
                        ->toHtml()
                );

                return true;
            }

        }

        return false;
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

    public function getPaymentIframeAction(){

        $api = Mage::getModel('xpaymentsconnector/payment_cc');
        $result = $api->sendIframeHandshakeRequest();

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        return;
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
     * Return customer from X-Payments
     *
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function returnAction()
    {
        if ($this->processCancel()) {
            return;
        }

        // Check request type
        if (!$this->getRequest()->isPost()) {
            Mage::throwException('Wrong request type.');
        }

        // Check request data
        $request = $this->getRequest()->getPost();
        if (empty($request)) {
            Mage::throwException('Request doesn\'t contain POST elements.');
        }

        $xpHelper = Mage::helper('xpaymentsconnector');

        if (empty($request)) {
            Mage::throwException("Request doesn\'t contain POST elements.");
        }

        // Check order id
        if (empty($request['refId'])) {
            Mage::throwException('Missing or invalid order ID');
        }

        if(!empty($request)){
            $xpHelper->savePaymentResponse($request);
        }

        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        $checkoutSession = Mage::getSingleton('checkout/session');
        $orderIncrementId = $checkoutSession->getLastRealOrderId();
        if(!$useIframe){
            $quoteId = Mage::app()->getRequest()->getParam('quote_id');
            $cardData = array();

            $profileIds = $checkoutSession->getLastRecurringProfileIds();
            $paymentCcModel = Mage::getModel('xpaymentsconnector/payment_cc');

            if ($orderIncrementId) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
                $quoteId = $order->getQuoteId();
                $cardData = $xpHelper->getXpCardData($quoteId);

                $orderId = $order->getId();
                $refId = $request['refId'];

                // save card data to order from quote
                $order->setData('xp_card_data', serialize($cardData));
                $order->save();

                $result = $paymentCcModel->updateOrderByXpaymentResponse($orderId,$cardData['txnId'],$refId);
                if (!$result['success']) {
                    $checkoutSession->addError($result['error_message']);
                    $checkoutSession->addNotice($xpHelper->getFailureCheckoutNoticeHelper());
                }else{
                    if (is_null($profileIds)) {
                        $paymentCcModel->saveUserCard($cardData);
                    }
                }

            }

            if($profileIds){
                if (empty($cardData)) {
                    $cardData = $xpHelper->getXpCardData($quoteId);
                }

                foreach($profileIds as $profileId){
                    $profile = Mage::getModel('sales/recurring_profile')->load($profileId);

                    if (isset($cardData['txnId'])) {
                        $profile->setReferenceId($cardData['txnId']);
                        if (is_null($paymentCcModel->_currentProfileId)) {
                            //save user card
                            $checkoutSession->setData('user_card_save', true);
                            $paymentCcModel->saveUserCard($cardData, $usageType = Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD);
                        }
                    } else {
                        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                    }

                    if (is_null($paymentCcModel->_currentProfileId) && !$orderIncrementId) {
                        if (!$xpHelper->getPrepareRecurringMasKey($profile)) {
                            $unsetParams = array('prepare_order_id');
                            $xpHelper->unsetXpaymentPrepareOrder($unsetParams);
                            $xpHelper->prepareOrderKey();
                            $xpHelper->updateRecurringMasKeys($profile);
                        }
                        $payDeferredSubscription = $xpHelper->payDeferredSubscription($profile);

                        if (!$payDeferredSubscription) {
                            $updateProfile = $paymentCcModel->createFirstRecurringOrder($profile);
                            $updateProfile->save();
                        }
                    }

                    if($profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_CANCELED){
                        $paymentCcModel->firstTransactionSuccess = false;
                    }else{
                        if (!$paymentCcModel->firstTransactionSuccess) {
                            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
                        }
                    };
                    $profile->save();
                    $paymentCcModel->_currentProfileId = $profile->getProfileId();
                }
            }

            $this->_redirect('checkout/onepage/success');
            return;
        }



        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('xpaymentsconnector/beforesuccess')
                ->toHtml()
        );

        return;
    }


    public function saveusercardAction(){
        $request = $this->getRequest()->getPost();
        if(!empty($request)){
            if($request['user_card_save']){
                Mage::getSingleton('checkout/session')->setData('user_card_save',$request['user_card_save']);
            }
        }
    }

    public function istokenactualAction(){
        $currentToken = $this->getRequest()->getParam('token');
        $result = array();
        if($currentToken){
            $savedToken = Mage::helper('xpaymentsconnector')->getIframeToken();
            $result['is_actual'] = ($currentToken == $savedToken) ? true: false;
        }else{
            $result['is_actual'] = false;
        }

        if(!$result['is_actual']){
            $result['error_message'] =
                Mage::helper('xpaymentsconnector')->__('Your order state has been changed during checkout. You will be redirected to the cart contents page.');
            $result['redirect'] = Mage::getUrl('checkout/cart',array('unset_xp_prepare_order'=>1));
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));

    }

    public function getcheckoutiframeurlAction(){
        $xpUrlPath = array();
        $xpUrlPath['iframe_url'] = Mage::helper('xpaymentsconnector')->getIframeUrl();
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($xpUrlPath));
    }
}
