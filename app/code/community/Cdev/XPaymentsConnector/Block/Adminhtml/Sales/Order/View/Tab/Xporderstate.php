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
 * Adminhtml order 'X-Payments Order State' tab
 */

class Cdev_XPaymentsConnector_Block_Adminhtml_Sales_Order_View_Tab_Xporderstate
    extends Mage_Adminhtml_Block_Sales_Order_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Is it X-Payments payment method or not
     */
    private $isXpc = null;

    /**
     * List of payment transactions
     */
    private $transactions = array();

    /**
     * List of order/payment data
     */
    private $orderData = array();

    /**
     * Error message
     */
    private $error = '';

    /**
     * ######################## TAB settings #################################
     */
    public function getTabLabel()
    {
        return Mage::helper('xpaymentsconnector')->__('X-Payments Order State');
    }

    public function getTabTitle()
    {
        return Mage::helper('xpaymentsconnector')->__('X-Payments Order State');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return !$this->isXpc();
    }
    /**
     * ######################## /TAB settings #################################
     */


    /**
     * Check method code. Is it X-Payments paymet method or not
     *
     * @return bool
     */
    private function isXpc()
    {
        if (is_null($this->isXpc)) {

            $this->isXpc = false;

            if (
                $this->getOrder()->getPayment()
                && $this->getOrder()->getPayment()->getMethodInstance()->getCode()
            ) {
                $this->isXpc = Mage::helper('settings_xpc')->isXpcMethod(
                    $this->getOrder()->getPayment()->getMethodInstance()->getCode()
                );
            } 
        }   
         
        return $this->isXpc;
    }

    /**
     * Process payment info request and save data internally
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param bool $saveError Is it necessary to save the error message
     *
     * @return void
     */
    private function processPaymentInfoRequest(Mage_Sales_Model_Order $order, $saveError = true)
    {
        if (
            empty($order->getId())
            || empty($order->getData('xpc_txnid'))
        ) {
            return;
        }

        $orderId = $order->getIncrementId();
        $txnId = $order->getData('xpc_txnid');

        $response = Mage::helper('api_xpc')
            ->requestPaymentInfo($txnId, false, true);

        if (
            !$response->getStatus()
            && $saveError
        ) {

            $this->error = $response->getErrorMessage(
                'Can\'t get information about the order from X-Payments server. More information is available in the log files.'
            );
        }

        if (!empty($response->getField('transactions'))) {
            $this->transactions[$orderId] = $response->getField('transactions');
        }

        if (!empty($response->getField('payment'))) {
            $this->orderData[$orderId] = $response->getField('payment');
            $this->orderData[$orderId]['xpc_txnid'] = $order->getData('xpc_txnid');
        }
    }

    /**
     * Constructor
     * Communicate with X-Payments and adjust properties
     *
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('xpaymentsconnector/order/view/tab/xporderstate.phtml');

        if ($this->isXpc()) {

            $this->processPaymentInfoRequest($this->getOrder());

            $order = $this->getOrder();

            while (!is_null($parentId = $order->getRelationParentId())) {

                $order = Mage::getModel('sales/order')->load($parentId);

                $this->processPaymentInfoRequest($order, false);
            } 
        }
    }

    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Retrieve source model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getSource()
    {
        return $this->getOrder();
    }

    /**
     * Get transactions associated with order
     *
     * @return array
     */
    public function getTransactions()
    {
        return $this->transactions;
    }

    /**
     * Get Order/Payment data associated with order
     *
     * @return array
     */
    public function getOrderData()
    {
        return $this->orderData;
    }

    /**
     * Get error message
     *
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Calculate order action available amount
     * 
     * @param array $data Payment data
     *
     * @return float
     */
    public function getActionAmount($data)
    {
        // Legacy logic in calculation is used here, and it works.
        // So let it be for now.

        $amount = 0.00;

        if (
            isset($data['capturedAmountAvail'])
            && ($data['refundedAmountAvail'])
        ) {

            if (
                $data['capturedAmountAvail'] > 0 
                && $data['refundedAmountAvail'] < 0.01
            ) {

                $amount = $data['capturedAmountAvailGateway'];

            } elseif (
                $data['refundedAmountAvail'] > 0 
                && $data['capturedAmountAvail'] < 0.01
            ) {

                $amount = $data['refundedAmountAvail'];
            }
        }

        if (
            isset($data['chargedAmount'])
            && $amount < 0.01
            && $data['chargedAmount'] > 0
        ) {

            $amount = $data['chargedAmount'];
        }

        return $amount;
    }

    /**
     * Return image SRC for arrow
     *
     * @param string $arrow Arrow, up or down
     * 
     * @return string
     */
    public function getArrowImg($arrow)
    {
        if (!in_array($arrow, array('up', 'down'))) {
            $arrow = 'up';
        }

        return Mage::getBaseUrl('js') . 'xpayment/images/arrow-' . $arrow . '.png';
    }

    /**
     * Get CSS class for the row in transactions table.
     *
     * @param int $row Row number. By default it should be "odd", so default value is 1.
     *
     * @return string
     */
    public function getRowClass($row = 1)
    {
        return ($row % 2 == 0)
            ? 'even'
            : 'odd';
    }

    /**
     * Format transaction date and time
     *
     * @param array $data Transaction data
     *
     * @return string
     */
    public function getTransactionDateTime($data)
    {
        return date('F d, Y H:i:s e', $data['date']);
    }

    /**
     * Format transaction date
     *
     * @param array $data Transaction data
     *
     * @return string
     */
    public function getTransactionDate($data)
    {
        return date('F d, Y', $data['date']);
    }

    /**
     * Format transaction time
     *
     * @param array $data Transaction data
     *
     * @return string
     */
    public function getTransactionTime($data)
    {
        return date('H:i', $data['date']);
    }

    /**
     * Get DOM form ID
     *
     * @param string $orderId Order ID
     *
     * @return string
     */
    public function getFormId($orderId)
    {
        return 'xp_fast_transactions_' . $orderId;
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormUrl()
    {
        $params = array(
            'order_id' => $this->getOrder()->getEntityId(),
        );

        return $this->getUrl('adminhtml/sales_order_payment/secondary', $params);
    }

    /**
     * Get JS validator name for the amount field
     *
     * @param string $orderId Order ID
     *
     * @return string
     */
    public function getAmountValidatorName($orderId)
    {
        return 'validate-transaction-amount_' . $orderId;
    }

    /**
     * Get JS required validator name for the amount field
     *
     * @param string $orderId Order ID
     *
     * @return string
     */
    public function getRequiredValidatorName($orderId)
    {
        return 'required-entry_' . $orderId;
    }

    /**
     * Get CSS class name for the transaction amount input field
     *
     * @param string $orderId Order ID
     *
     * @return string
     */     
    public function getAmountInputClass($orderId)
    {
        $class = array(
            $this->getAmountValidatorName($orderId),
            $this->getRequiredValidatorName($orderId),
            'input-text',
            'transaction-amount',
        );

        return implode(' ', $class);
    }

    /**
     * Get onclick JS code for action button (Capture, Refund, Void)
     * 
     * @param string $action Action
     * @param string $orderId Order ID
     * @param string $amount Amount
     *
     * @return string
     */
    public function getOnClick($action, $orderId, $amount = false)
    {
        $params = array(
            $action,
            $this->getFormId($orderId),
            $this->getAmountValidatorName($orderId),
            $this->getRequiredValidatorName($orderId),
        );

        if ($amount) {
            $params[] = $amount;
        }

        return 'submitXpcTransaction(\'' . implode("', '", $params) . '\')';
    }

    /**
     * Get name for the Void button with amount
     *
     * @param array $data Order/Payment data
     *
     * @return string
     */
    public function getVoidValue($data)
    {
        return 'Void (' . Mage::helper('core')->currency($data['voidedAmountAvail'], true, false) . ')';
    }

    /**
     * Get fraud check data for Order/Payment
     *
     * @return Cdev_XPaymentsConnector_Model_Fraudcheckdata
     */
    public function getFraudCheckData()
    {
        return Mage::getModel('xpaymentsconnector/fraudcheckdata')
            ->getCollection()
            ->addFieldToFilter('order_id', $this->getOrder()->getId());
    }

    /**
     * Check if payment is required for the order created in the backend
     *
     * @return bool
     */
    public function isPaymentRequired()
    {
        // Order has no transactions and was created in the backend
        // It's checked by the remote_ip field. Usual way in Magento.
        $isOrderNotPaid = empty($this->transactions)
            && empty($this->getOrder()->getRemoteIp())
            && empty($this->error);

        // Check if order created in the admin backend was declined
        $isOrderDeclined = !empty($this->transactions)
            && empty($this->getOrder()->getRemoteIp())
            && (
                Mage_Sales_Model_Order::STATE_CANCELED == $this->getOrder()->getState()
                || Mage_Sales_Model_Order::STATE_NEW == $this->getOrder()->getState()
            );

        return $isOrderNotPaid || $isOrderDeclined;
    }

    /**
     * Get URL to pay for the backend order
     *
     * @return string
     */
    public function getBackendPaymentUrl()
    {
        $params = array(
            'order_id' => $this->getOrder()->getEntityId(),
        );

        return $this->getUrl('adminhtml/sales_order_payment/pay', $params);
    }
}
