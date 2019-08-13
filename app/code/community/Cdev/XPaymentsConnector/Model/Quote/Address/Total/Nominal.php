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
 * Rewrite nominal items total
 * Collects only items segregated by isNominal property
 * Aggregates row totals per item
 */
class Cdev_XPaymentsConnector_Model_Quote_Address_Total_Nominal extends Mage_Sales_Model_Quote_Address_Total_Nominal
{
    /**
     * Invoke collector for nominal items
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        Mage::helper('xpaymentsconnector')->updateAllRecurringQuoteItem();

        $collector = Mage::getSingleton('sales/quote_address_total_nominal_collector',
            array('store' => $address->getQuote()->getStore())
        );

        // invoke nominal totals
        foreach ($collector->getCollectors() as $model) {
            $model->collect($address);
        }

        // aggregate collected amounts into one to have sort of grand total per item
        $totals = array();
        foreach ($address->getAllNominalItems() as $item) {
            $rowTotal = 0;
            $baseRowTotal = 0;
            $totalDetails = array();
            foreach ($collector->getCollectors() as $model) {
                $itemRowTotal = $model->getItemRowTotal($item);
                if ($model->getIsItemRowTotalCompoundable($item)) {
                    $totals[] = $itemRowTotal;
                    if ($model->getCode() == "recurring_discount") {
                        $rowTotal -= $itemRowTotal;
                        $baseRowTotal -= $itemRowTotal;
                    }elseif (($model->getCode() == "recurring_initial_fee" ) &&
                              !is_null($item->getXpRecurringInitialFee())
                    ) {
                        $rowTotal += $item->getXpRecurringInitialFee();
                        $baseRowTotal += $item->getXpRecurringInitialFee();
                    } else {
                        $rowTotal += $itemRowTotal;
                        $baseRowTotal += $model->getItemBaseRowTotal($item);
                    }
                    $isCompounded = true;
                } else {
                    $isCompounded = false;
                }
                if ((float)$itemRowTotal > 0 && $label = $model->getLabel()) {
                    if ($model->getCode() == "recurring_discount") {
                        $itemRowTotal = -$itemRowTotal;
                    }elseif ($model->getCode() == "recurring_initial_fee") {
                        if(!is_null($item->getXpRecurringInitialFee())){
                            $itemRowTotal = $item->getXpRecurringInitialFee();
                        }
                    }
                    $totalDetails[] = new Varien_Object(array(
                        'label' => $label,
                        'amount' => $itemRowTotal,
                        'is_compounded' => $isCompounded,
                    ));
                }
            }

            $item->setNominalRowTotal($rowTotal);
            $item->setBaseNominalRowTotal($baseRowTotal);
            $item->setNominalTotalDetails($totalDetails);
        }

        return $this;
    }

}
