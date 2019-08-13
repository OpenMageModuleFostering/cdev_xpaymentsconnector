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

class Cdev_XPaymentsConnector_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{
    /**
     * Get code for the button's onclick
     *
     * @param string $action Action
     * @param string $message Confirmation message
     *
     * @return string
     */
    private function getOnClick($action, $message)
    {
        $url = $this->getUrl('*/sales_order_payment/' . $action);
        
        $message = Mage::helper('xpaymentsconnector')->__($message);

        // Quotes here are important!
        return 'confirmSetLocation(\'' . $message .'\', \'' . $url . '\');';
    }

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $order = $this->getOrder();
        $txnid = $order->getData('xpc_txnid');

        $response = Mage::helper('api_xpc')->requestPaymentInfo($txnid, false, true);

        if (
            $response->getStatus()
            && $response->getField('payment')['isFraudStatus']
        ) {

            $session = Mage::getSingleton('adminhtml/session');
            
            $message = $this->__('This transaction has been identified as possibly fraudulent, press "Accept" '
                . 'to confirm the acceptance of the transaction or "Decline" to cancel it.');

            $session->addNotice($message);

            $this->_addButton(
                'active', 
                array(
                    'label'     => Mage::helper('xpaymentsconnector')->__('Accept'),
                    'onclick'   => $this->getOnClick('accept', 'Are you sure you want to accept this order transaction?'),
                    'class'     => 'fraud-button',
                ), 
                -1
            );

            $this->_addButton(
                'decline', 
                array(
                    'label'     => Mage::helper('xpaymentsconnector')->__('Decline'),
                    'onclick'   => $this->getOnClick('decline', 'Are you sure you want to decline this order transaction?'),
                    'class'     => 'fraud-button',
                ), 
                -1
            );
        }
    }
}
