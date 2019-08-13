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
 * X-Payments Connector Data in quote 
 * Class Cdev_XPaymentsConnector_Model_FraudCheckData
 */

class Cdev_XPaymentsConnector_Model_Quote_Xpcdata extends Mage_Core_Model_Abstract
{
    /**
     * Internal constructor
     * 
     * @return void
     */
    protected function _construct()
    {
        $this->_init('xpaymentsconnector/quote_xpcdata');
    }

    /**
     * Clear data
     *
     * @return void
     */
    public function clear()
    {
        $this->setData('txn_id', '')
            ->setData('token', '')
            ->setData('address_saved', false)
            ->setData('recurring_order_id', 0)
            ->setData('recurring_profile_id', 0)
            ->setData('xpc_message', '')
            ->setData('checkout_data', '')
            ->save();
    }
}
