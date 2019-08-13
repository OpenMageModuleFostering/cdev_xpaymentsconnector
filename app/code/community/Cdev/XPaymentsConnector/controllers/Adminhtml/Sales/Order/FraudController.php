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
 *
 * Class Mage_Adminhtml_Sales_OrderController
 */
class Cdev_XPaymentsConnector_Adminhtml_Sales_Order_FraudController extends Mage_Adminhtml_Controller_Action
{

    public function acceptAction()
    {

        if ($order = $this->_initOrder()) {

            $xpcTxnid = $order->getXpcTxnid();
            if(!empty($xpcTxnid)){
                $xpaymentCCModel = Mage::getModel('xpaymentsconnector/payment_cc');
                $result = $xpaymentCCModel->sendFraudRequest($xpcTxnid,'accept');

                if ($result) {
                    $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $order->save();
                }
            }


            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
        }
    }

    public function declineAction()
    {

        if ($order = $this->_initOrder()) {
            $xpcTxnid = $order->getXpcTxnid();
            if(!empty($xpcTxnid)){
                $xpaymentCCModel = Mage::getModel('xpaymentsconnector/payment_cc');
                $result = $xpaymentCCModel->sendFraudRequest($xpcTxnid,'decline');

                if ($result) {
                    $order->cancel();
                    $order->save();
                }
            }

            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
        }
    }


    /**
     * Initialize order model instance
     *
     * @return Mage_Sales_Model_Order || false
     */
    protected function _initOrder()
    {
        $id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($id);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('*/*/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return false;
        }
        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);
        return $order;
    }

}
