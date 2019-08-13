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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Additional settings for "IFrame" variant of payment method (frontend)
 */
class Cdev_XPaymentsConnector_Block_Checkout_Onepage_Settings extends Mage_Core_Block_Template
{
    /**
     * Check if X-Payments payment method was selected on the payment step
     * 
     * @return bool
     */
    private function isXpcMethod()
    {
        $code = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
        $xpcCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();

        return $code == $xpcCode;
    }

    /**
     * Display iframe on the review step (after payment) or not 
     *
     * @return bool
     */
    protected function isVisible()
    {
        return $this->isXpcMethod()
            && Mage::helper('xpaymentsconnector')->isUseIframe()
            && 'review' == Mage::helper('xpaymentsconnector')->getIframePlaceDisplay();
    }
}
