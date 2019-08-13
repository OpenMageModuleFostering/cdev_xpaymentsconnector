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
 * Success page after order is placed
 */
class Cdev_XPaymentsConnector_Block_Checkout_Onepage_Success extends Mage_Checkout_Block_Onepage_Success
{
    /**
     * Prepare last order
     *
     * @return void
     */
    protected function _prepareLastOrder()
    {
        $order = Mage::getModel('sales/order')->load(
            Mage::getSingleton('checkout/session')->getLastOrderId()
        );

        if ($order->getId()) {

            $orderId = $order->getEntityId();
        
            $isVisible = !in_array(
                $order->getState(),
                Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates()
            );

            $recurringProfile = Mage::helper('xpaymentsconnector')->getOrderRecurringProfile($order);

            $params = array(
                'order_id' => $orderId,
                '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
            );

            $data = array(
                'is_order_visible'  => $isVisible,
                'view_order_id'     => $this->getUrl('sales/order/view/', $params),
                'print_url'         => $this->getUrl('sales/order/print', $params),
                'can_print_order'   => $isVisible,
                'can_view_order'    => Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible,
                'order_id'          => $order->getIncrementId(),
                'order_status'      => $order->getStatus(),
                'order_entity_id'   => $orderId,
                'recurring_profile' => $recurringProfile,
            );

            $this->addData($data);

        } else {

            // This should not happen at the success page, but add error just in case
            Mage::getSingleton('core/session')->addError('Order was lost');
        }
    }
}
