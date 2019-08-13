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
 * Payemnt successs block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Beforesuccess extends Mage_Core_Block_Template
{
    protected $_agreements = array();
    /**
     * Get block contecnt as HTML
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('xpaymentsconnector/checkout/onepage/beforesuccess.phtml');
    }

    public function getSaveOrderUrl(){
        return Mage::getUrl('checkout/onepage/saveorder');
    }

    public function getCheckoutSuccessUrl(){
        return Mage::getUrl('checkout/onepage/success');
    }

    public function getXpaymentsCode(){
        $xpaymentPaymentCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();
        return $xpaymentPaymentCode;
    }

    public function getAgreements(){
        if(empty($this->_agreements)){
            if (Mage::getStoreConfigFlag('checkout/options/enable_agreements')) {
                $agreements = Mage::getModel('checkout/agreement')->getCollection()
                    ->addStoreFilter(Mage::app()->getStore()->getId())
                    ->addFieldToFilter('is_active', 1);
                $this->_agreements = $agreements;
            }
        }
        return $this->_agreements;
    }

}
