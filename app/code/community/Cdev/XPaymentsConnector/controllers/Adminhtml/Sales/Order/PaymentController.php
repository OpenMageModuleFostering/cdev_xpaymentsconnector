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
 *
 * Class Mage_Adminhtml_Sales_OrderController
 */
class Cdev_XPaymentsConnector_Adminhtml_Sales_Order_PaymentController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check if pay for order is allowed for user
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/xpaymentsconnector/pay_orders');
    }

    /**
     * Validate Form Key
     *
     * @return bool
     */
    protected function _validateFormKey()
    {
        $result = parent::_validateFormKey();

        if ($this->getRequest()->getParam('action') == 'return') {

            // This is necessary to allow POST request on return, which doesn't contain form key
            $result = true;
        }

        return $result;
    }

    /**
     * Pay action
     *
     * @return void
     */
    public function payAction()
    {
        $order = null;

        try {

            $order = $this->getOrder();

            if (!$order) {
                throw new Exception('Invalid order');
            }

            $paymentMethod = $order->getPayment()->getMethodInstance();

            $helper = Mage::helper('xpaymentsconnector');
            $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
                ->setXpcSlot($paymentMethod->getXpcSlot());

            // For some reason quote is empty here. But we actually need only its ID.
            // All the necessary data is taken from the quote XPC data
            $quote->setEntityId($order->getQuoteId())->setStoreId($order->getStoreId());

            $quote->getXpcData()->setData('backend_orderid', $order->getIncrementId())->save();

            if (!$paymentMethod->prepareToken($quote)) {
                throw new Exception('Unable to obtain payment token');
            }

            $fields = $paymentMethod->getFormFields($quote);

            $block = $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_sales_order_pay');

            $block->setFields($fields);

            echo $block->toHtml();
            exit;

        } catch (Exception $exception) {

            $this->processExceptionAndRedirect($exception, $order);
        }
    }

    /**
     * Return action
     *
     * @return void
     */
    public function returnAction()
    {
        try {

            $order = $this->getOrder();

            if ($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING) {

                $this->_getSession()->addSuccess('Order was paid');

            } else {

                $this->_getSession()->addError('Order was not paid');
            }

            $this->_redirect('*/sales_order/view', array('order_id' => $order->getEntityId()));

        } catch (Exception $exception) {

            $this->_getSession()->addError($this->__($e->getMessage()));
            $this->_redirect('*/*/');
        }
    }

    /**
     * Process X-Payments response and redirect
     *
     * @param Cdev_XPaymentsConnector_Transport_ApiResponse $response X-Payments response
     * @param string $successMessage Default success message
     * @param string $errorMessage Default error message
     *
     * @return void
     */
    private function processResponseAndRedirect(Cdev_XPaymentsConnector_Transport_ApiResponse $response, $successMessage, $errorMessage)
    {
        if ($response->getStatus()) {

            $message = $response->getMessage($successMessage);

            $this->_getSession()->addSuccess($this->__($message));

        } else {

            $message = $response->getErrorMessage($errorMessage);

            throw new Exception($message);
        }

        $this->_redirect('*/sales_order/view', array('order_id' => $this->getOrder()->getEntityId()));
    }

    /**
     * Process X-Payments response and redirect
     *
     * @param Exception $exception
     * @param Mage_Sales_Model_Order $order 
     *
     * @return void
     */
    private function processExceptionAndRedirect(Exception $exception, Mage_Sales_Model_Order $order = null)
    {
        $this->_getSession()->addError($this->__($exception->getMessage()));

        if ($order) {

            $this->_redirect('*/sales_order/view', array('order_id' => $order->getEntityId()));

        } elseif ($this->getRequest()->getParam('order_id')) {

            $this->_redirect('*/sales_order/view', array('order_id' => $this->getRequest()->getParam('order_id')));

        } else {

            $this->_redirect('*/sales_order/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }

    /**
     * Secondary action (capture, void, refund)
     *
     * @return void
     */
    public function secondaryAction()
    {
        $order = null;

        try {

            // Model is not necessary to perform the action
            // But we need to validate it
            $order = $this->getOrder();

            $api = Mage::helper('api_xpc');

            $txnId  = $this->getRequest()->getParam('xpc_txnid');
            $action = $this->getRequest()->getParam('xpc_action');
            $amount = $this->getRequest()->getParam('transaction_amount');

            switch ($action) {

                case 'refund':
                    $response = $api->requestPaymentRefund($txnId, $amount);
                    break;

                case 'capture':
                    $response = $api->requestPaymentCapture($txnId, $amount);
                    break;

                case 'void':
                    $response = $api->requestPaymentVoid($txnId, $amount);
                    break;

                default:
                    throw new Exception('Incorrect action');
                    break;
            }

            $this->processResponseAndRedirect(
                $response,
                'Operation successful',
                'Operation failed'
            );

        } catch (Exception $exception) {

            $this->processExceptionAndRedirect($exception, $order);
        }
    }

    /**
     * Accept action
     *
     * @return void
     */
    public function acceptAction()
    {
        $order = null;

        try {

            $order = $this->getOrder();

            $response = Mage::helper('api_xpc')->requestPaymentAccept($order->getData('xpc_txnid'));

            $this->processResponseAndRedirect(
                $response,
                'Transaction accepted',
                'Transaction was not accepted'
            );

        } catch (Exception $exception) {

            $this->processExceptionAndRedirect($exception, $order);
        }
    }

    /**
     * Decline action
     *
     * @return void
     */
    public function declineAction()
    {
        $order = null;

        try {

            $order = $this->getOrder();

            $response = Mage::helper('api_xpc')->requestPaymentDecline($order->getData('xpc_txnid'));

            $this->processResponseAndRedirect(
                $response,
                'Transaction declined',
                'Transaction was not declined'
            );

        } catch (Exception $exception) {

            $this->processExceptionAndRedirect($exception, $order);
        }
    }

    /**
     * Recharge action (for saved card payment)
     *
     * @return void
     */
    public function rechargeAction()
    {
        $order = null;

        try {

            $helper = Mage::helper('xpaymentsconnector');

            $order = $this->getOrder();

            $cardData = Mage::getModel('xpaymentsconnector/usercards')->load(
                $this->getRequest()->getParam('card_id')
            )->getData();

            $helper->saveMaskedCardToOrder($order, $cardData);
            $order->setData('xpc_txnid', $cardData['txnId'])->save();
            
            $paymentMethod = $order->getPayment()->getMethodInstance();

            $quote = Mage::getModel('xpaymentsconnector/quote')->load($order->getQuoteId())
                ->setXpcSlot($paymentMethod->getXpcSlot());

            // For some reason quote is empty here. But we actually need only its ID.
            // All the necessary data is taken from the quote XPC data
            $quote->setEntityId($order->getQuoteId());

            $quote->getXpcData()->setData('backend_orderid', $order->getIncrementId())->save();

            $response = $paymentMethod->processPayment($quote);

            $this->processResponseAndRedirect(
                $response,
                'Order was paid',
                'Order was not paid'
            );

        } catch (Exception $exception) {

            $this->processExceptionAndRedirect($exception, $order);
        }
    }

    /**
     * Initialize order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    private function getOrder()
    {
        $order = Mage::getModel('sales/order')->load(
            $this->getRequest()->getParam('order_id')
        );

        if (!$order->getEntityId()) {

            if ($this->getRequest()->getParam('refId')) {

                $order = Mage::getModel('sales/order')->loadByIncrementId(
                    $this->getRequest()->getParam('refId')
                );

            } elseif ($this->getRequest()->getParam('txnId')) {

                $order = Mage::helper('xpaymentsconnector')
                    ->getOrderByTxnId($this->getRequest()->getParam('txnId'));
            }
        }

        if (!$order->getEntityId()) {
            throw new Exception('This order no longer exists.'); // This ugly message exists in Magento core
        }

        if (!Mage::helper('settings_xpc')->isXpcMethod($order->getPayment()->getMethod())) {
            throw new Exception('Payment method for this order is not X-Payments');
        }

        return $order;
    }
}
