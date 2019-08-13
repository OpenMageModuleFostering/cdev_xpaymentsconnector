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
 * "Credit Card (X-Payments)" form block
 */

class Cdev_XPaymentsConnector_Block_Form_Cc extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/form/cc.phtml');
    }

    public function getIframeUrl()
    {

        $quotePayment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
        $isPaymentPlaceDisplayFlag = Mage::helper("xpaymentsconnector")->isIframePaymentPlaceDisplay();
        $methods = Mage::helper("xpaymentsconnector")->getAllowedPaymentsMethods();
        if ($quotePayment->getMethod()) {
            $currentPaymentMethodCode = $quotePayment->getMethodInstance()->getCode();
            $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();
            if ($isPaymentPlaceDisplayFlag) {
                if ($currentPaymentMethodCode == $xpaymentPaymentCode) {
                    $unsetParams = array("token");
                    Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
                    return Mage::helper("xpaymentsconnector")->getIframeUrl();
                }
            }
        }

        if($methods && $isPaymentPlaceDisplayFlag){
            if(count($methods) == 1){
                $currentMethod = current($methods);
                $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();
                if($currentMethod["method_code"] == $xpaymentPaymentCode){
                    $unsetParams = array("token");
                    Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
                    return Mage::helper("xpaymentsconnector")->getIframeUrl();
                }
            }
        }


        return "#";
    }

}
