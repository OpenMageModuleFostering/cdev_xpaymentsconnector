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
 * Adminhtml order 'X-Payments Order State' tab
 */

class Cdev_XPaymentsConnector_Block_Adminhtml_Sales_Order_View_Tab_Xporderstate
    extends Mage_Adminhtml_Block_Sales_Order_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public $txnid;
    public $transactionStatus;
    public $transactionInfo;

    protected function _construct()
    {
        $this->txnid  = $this->getOrder()->getData("xpc_txnid");
        $this->setTemplate('xpaymentsconnector/order/view/tab/xporderstate.phtml');

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
     * ######################## TAB settings #################################
     */
    public function getTabLabel()
    {
        return Mage::helper('xpaymentsconnector')->__('X-Payment Order State');
    }

    public function getTabTitle()
    {
        return Mage::helper('xpaymentsconnector')->__('X-Payment Order State');
    }

    public function canShowTab()
    {
        return true;
    }

    public function getXpaymentsOrderInfo(){
        $result = array();

        if (empty($this->txnid)) {
            $orderBroken = Mage::helper("xpaymentsconnector")->__("This order has been broken. Parameter 'xpayments transaction id' is not available.");
            $result["success"] = false;
            $result["error_message"] = $orderBroken;
            return $result;
        }


        if(!$this->transactionStatus){
            $xpaymentsConnect = Mage::helper("xpaymentsconnector")->__("Can't get information about the order from X-Payments server. More information is available in log files.");
            $result["success"] = false;
            $result["error_message"] = $xpaymentsConnect;
        }else{
            if(!$this->isHidden()){
                $result["success"] = true;
                $result["info"] = $this->transactionInfo;
            }

        }

        return $result;
    }


    public function isHidden()
    {
        $currentPaymentCode = $this->getOrder()->getPayment()->getMethodInstance()->getCode();
        $isXpaymentsMethod  = Mage::helper("xpaymentsconnector")->isXpaymentsMethod($currentPaymentCode);
        if($isXpaymentsMethod){
            list($this->transactionStatus, $this->transactionInfo)
                =  Mage::getModel("xpaymentsconnector/payment_cc")->requestPaymentInfo($this->txnid,false,true);
        }

        return !$isXpaymentsMethod;

    }
}