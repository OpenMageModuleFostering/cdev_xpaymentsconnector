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
 * Rewrite recurring profile view
 */
class Cdev_XPaymentsConnector_Block_Recurring_Profile_View extends Mage_Sales_Block_Recurring_Profile_View
{
    /**
     * Prepare profile payments info
     */
    public function prepareFeesInfo()
    {
        parent::prepareFeesInfo();
        $orderItemInfo = $this->_profile->getData('order_item_info');
        $discountAmount = $orderItemInfo['discount_amount'];
        if($discountAmount){
            $discountNominalModel = Mage::getModel('xpaymentsconnector/quote_address_total_nominal_recurring_discount');
            $this->_addInfo(array(
                'label' => $discountNominalModel->getLabel(),
                'value' => Mage::helper('core')->formatCurrency(-$discountAmount, false),
                'is_amount' => true,
            ));
        }
        $initialFeeTaxAmount = $orderItemInfo['initialfee_tax_amount'];
        if($initialFeeTaxAmount){
            $initialFeeTaxNominalModel = Mage::getModel('xpaymentsconnector/quote_address_total_nominal_recurring_initialfee_tax');
            $this->_addInfo(array(
                'label' => $initialFeeTaxNominalModel->getLabel(),
                'value' => Mage::helper('core')->formatCurrency($initialFeeTaxAmount, false),
                'is_amount' => true,
            ));
        }
    }

}
