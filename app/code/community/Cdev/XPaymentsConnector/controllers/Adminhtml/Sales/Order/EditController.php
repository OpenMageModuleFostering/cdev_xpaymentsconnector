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
 * Edit order controller
 */
require_once 'Mage/Adminhtml/controllers/Sales/Order/EditController.php';

class Cdev_XPaymentsConnector_Adminhtml_Sales_Order_EditController extends Mage_Adminhtml_Sales_Order_EditController 
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
     * Saving quote and create order
     *
     * @return void
     */
    public function saveAction()
    {
        parent::saveAction();

        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $incrementId = $quote->getData('reserved_order_id');
        $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');

        $payment = $this->getRequest()->getPost('payment');

        if (
            isset($payment['method'])
            && $order->getEntityId()
        ) {
            if (Mage::helper('settings_xpc')->isXpcMethod($payment['method'], false)) {

                // Redirect user to the payment page
                $this->_redirect('adminhtml/sales_order_payment/pay', array('order_id' => $order->getEntityId()));

            } elseif (Mage::helper('settings_xpc')->isSavedCardsMethod($payment['method'])) {

                // Process payment by saved card
                // Redirect to the different controller to process the recharge action

                $params = array(
                    'order_id' => $order->getEntityId(),
                    'card_id'  => $payment['xp_payment_card'],
                );

                $this->_redirect('adminhtml/sales_order_payment/recharge', $params);
            }
        }
    }
}
