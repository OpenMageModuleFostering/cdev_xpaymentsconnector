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
 * Total model for recurring profile tax for initial fee
 */
class Cdev_XPaymentsConnector_Model_Quote_Address_Total_Nominal_Recurring_Initialfee_Tax
    extends Mage_Sales_Model_Quote_Address_Total_Nominal_RecurringAbstract
{
    /**
     * Custom row total/profile keys
     *
     * @var string
     */
    protected $_itemRowTotalKey = 'recurring_initialfee_tax';
    protected $_profileDataKey = 'initialfee_tax_amount';

    /**
     * Get initial fee label
     *
     * @return string
     */
    public function getLabel()
    {
        return Mage::helper('sales')->__('Initial Fee Tax');
    }


    /**
     * Getter for row default total
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return float
     */
    public function getItemRowTotal(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        $xpHelper = Mage::helper('xpaymentsconnector');
        $xpRecurringInitialFee =  $item->getXpRecurringInitialFee();
        if (!is_null($xpRecurringInitialFee) && !empty($xpRecurringInitialFee)) {
            $currentProduct = $item->getProduct();
            $taxInitialFee = $xpHelper->calculateTaxForProductCustomPrice($currentProduct,$xpRecurringInitialFee);
            $item->setInitialfeeTaxAmount($taxInitialFee);
            $item->save();

            return $taxInitialFee;
        }
    }
}