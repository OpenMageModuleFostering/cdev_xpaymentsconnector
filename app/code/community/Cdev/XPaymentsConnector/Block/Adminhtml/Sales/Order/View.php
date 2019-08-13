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

class Cdev_XPaymentsConnector_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{

    public function __construct()
    {

        parent::__construct();

        $order = $this->getOrder();
        $txnid = $order->getData('xpc_txnid');
        list($transactionStatus, $transactionInfo)
            = Mage::getModel('xpaymentsconnector/payment_cc')->requestPaymentInfo($txnid, false, true);

        if ($transactionStatus) {
            if(isset($transactionInfo['payment']['isFraudStatus']) && $transactionInfo['payment']['isFraudStatus']){
                $admSession = Mage::getSingleton('adminhtml/session');
                $messageText = "This transaction has been identified as possibly fraudulent, press 'Accept'".
                " to confirm the acceptance of the transaction or 'Decline' to cancel it.";
                $message = $this->__($messageText);
                $admSession->addNotice($message);

                $activeMessage = Mage::helper('xpaymentsconnector')->__('Are you sure you want to accept this order transaction?');
                $this->_addButton('active', array(
                    'label'     => Mage::helper('xpaymentsconnector')->__('Accept'),
                    'onclick'   => "confirmSetLocation('{$activeMessage}', '{$this->getRequestFraudUrl('accept')}')",
                    'class'     => 'fraud-button'
                ), -1);

                $cancelMessage = Mage::helper('xpaymentsconnector')->__('Are you sure you want to decline this order transaction?');
                $this->_addButton('decline', array(
                    'label'     => Mage::helper('xpaymentsconnector')->__('Decline'),
                    'onclick'   => "confirmSetLocation('{$cancelMessage}', '{$this->getRequestFraudUrl('decline')}')",
                    'class'     => 'fraud-button'
                ), -1);
            }

        }
    }


    public function getRequestFraudUrl($action)
    {
        return $this->getUrl('*/sales_order_fraud/'.$action);
    }
}
