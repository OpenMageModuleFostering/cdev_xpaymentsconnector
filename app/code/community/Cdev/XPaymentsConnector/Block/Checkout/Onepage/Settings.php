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
 * Additional settings for "IFrame" variant of payment method (frontend)
 */

class Cdev_XPaymentsConnector_Block_Checkout_Onepage_Settings extends Mage_Core_Block_Template
{
   public function getIframeUrl(){

       $xpaymentFormData = Mage::helper('payment')->getMethodInstance("xpayments")->getFormFields();
       $xpaymentFormUrl = Mage::helper('payment')->getMethodInstance("xpayments")->getUrl();

       Mage::helper("xpaymentsconnector")->prepareOrderKey();
       $token = Mage::helper("xpaymentsconnector")->getIframeToken();
       $iframeUrlDataArray = array('target' => $xpaymentFormData['target'], 'token' => $token);
       $iframeUrl = $xpaymentFormUrl . "?" . http_build_query($iframeUrlDataArray);
       return $iframeUrl;
   }

    public function isXpaymentMethod(){

        $paymentCode = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
        if($paymentCode == "xpayments"){
            return true;
        }
        else{
            return false;
        }
    }


}
