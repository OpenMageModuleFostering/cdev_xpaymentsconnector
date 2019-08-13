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
 * Payment configuration RDMS-specific collection model
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */

class Cdev_XPaymentsConnector_Model_Mysql4_Paymentconfiguration_Collection extends Varien_Data_Collection_Db
{
    /**
     * Payment configuration table name (cache)
     * 
     * @var    string
     * @access protected
     * @see    ____var_see____
     * @since  1.0.0
     */
    protected $_paymentconfigurationTable;
 
    /**
     * Constructor
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function __construct()
    {
        $resources = Mage::getSingleton('core/resource');

        parent::__construct($resources->getConnection('paymentconfigurations_read'));

        $this->_paymentconfigurationTable = $resources->getTableName('xpaymentsconnector/paymentconfiguration');
 
        $this->_select->from(
            array('paymentconfiguration' => $this->_paymentconfigurationTable),
            array('*')
        );
        $this->setItemObjectClass(
            Mage::getConfig()->getModelClassName('xpaymentsconnector/paymentconfiguration')
        );

        $this->setOrder('name', self::SORT_ORDER_ASC);
    }

    /**
     * Get data as option array 
     * 
     * @return array
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function toOptionArray()
    {
        return $this->_toOptionArray('confid', 'name');
    }
}
