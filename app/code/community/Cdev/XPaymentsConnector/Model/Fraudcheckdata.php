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
 * Data from the fraud-check service (Kount, NoFraud, etc)
 * Class Cdev_XPaymentsConnector_Model_FraudCheckData
 */

class Cdev_XPaymentsConnector_Model_Fraudcheckdata extends Mage_Core_Model_Abstract
{
    /**
     * Internal constructor
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('xpaymentsconnector/fraudcheckdata');
    }

    /**
     * Get and unserialize some data
     *
     * @param string $name Data name
     *
     * @return array
     */
    private function getUnserializedData($name)
    {
        $list = $this->getData($name);

        $list = @unserialize($list);

        if (!is_array($list)) {
            $list = array();
        }
 
        return $list;
    }

    /**
     * Get list of the triggered rules
     *
     * @return array
     */
    public function getRulesList()
    {
        return $this->getUnserializedData('rules');
    }

    /**
     * Get list of the errors
     *
     * @return array
     */
    public function getErrorsList()
    {
        return $this->getUnserializedData('errors');
    }

    /**
     * Get list of the warnings
     *
     * @return array
     */
    public function getWarningsList()
    {
        return $this->getUnserializedData('warnings');
    }

    /**
     * Get data as list
     *
     * @return array
     */
    public function getDataList()
    {
        return $this->getUnserializedData('data');
    }
}
