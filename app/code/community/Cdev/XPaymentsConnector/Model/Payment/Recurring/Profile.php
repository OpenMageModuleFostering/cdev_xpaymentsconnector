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
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Payment
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Recurring payment profile
 * Extends from Mage_Core_Abstract for a reason: to make descendants have its own resource
 */
class Cdev_XPaymentsConnector_Model_Payment_Recurring_Profile extends  Mage_Payment_Model_Recurring_Profile
{

    public function exportScheduleInfo()
    {
        $result = array(
            new Varien_Object(array(
                'title'    => Mage::helper('payment')->__('Billing Period'),
                'schedule' => $this->_renderSchedule('period_unit', 'period_frequency', 'period_max_cycles',"init_amount"),
            ))
        );
        $trial = $this->_renderSchedule('trial_period_unit', 'trial_period_frequency', 'trial_period_max_cycles');
        if ($trial) {
            $result[] = new Varien_Object(array(
                'title'    => Mage::helper('payment')->__('Trial Period'),
                'schedule' => $trial,
            ));
        }
        return $result;
    }

    protected function _renderSchedule($periodKey, $frequencyKey, $cyclesKey,$initKey = null)
    {
        $result = array();

        $period = $this->_getData($periodKey);
        $frequency = (int)$this->_getData($frequencyKey);
        if (!$period || !$frequency) {
            return $result;
        }
        if (self::PERIOD_UNIT_SEMI_MONTH == $period) {
            $frequency = '';
        }

        $result[] = Mage::helper('payment')->__('%s %s cycle.', $frequency, $this->getPeriodUnitLabel($period));

        $cycles = (int)$this->_getData($cyclesKey);

        if ($cycles) {
            $result[] = Mage::helper('payment')->__('Repeats %s time(s).', $cycles);
        } else {
            $result[] = Mage::helper('payment')->__('Repeats until suspended or canceled.');
        }
        $init = $this->_getData($initKey);
        if($init){
            $result[] = Mage::helper('xpaymentsconnector')->__('Initial Fee %s.', Mage::helper('core')->currency($init,true,false));
        }

        return $result;
    }
}