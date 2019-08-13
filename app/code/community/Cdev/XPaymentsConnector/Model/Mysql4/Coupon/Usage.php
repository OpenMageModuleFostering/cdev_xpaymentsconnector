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
 * @author     Qualiteam Software <info@x-cart.com>
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-present Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Cdev_XPaymentsConnector_Model_Mysql4_Coupon_Usage extends Mage_SalesRule_Model_Resource_Coupon_Usage
{
    /**
     * Decrement times_used counter
     *
     * @param int $customerId
     * @param int $couponId
     *
     * @return void
     */
    public function decreaseCustomerCouponTimesUsed($customerId, $couponId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select();
        $select->from($this->getMainTable(), array('times_used'))
                ->where('coupon_id = :coupon_id')
                ->where('customer_id = :customer_id');

        $timesUsed = $read->fetchOne($select, array(':coupon_id' => $couponId, ':customer_id' => $customerId));

        if ($timesUsed > 0) {
            $this->_getWriteAdapter()->update(
                $this->getMainTable(),
                array(
                    'times_used' => $timesUsed - 1
                ),
                array(
                    'coupon_id = ?' => $couponId,
                    'customer_id = ?' => $customerId,
                )
            );
        } 
    }

    /**
     * This is the WA fix for default Magento code
     * Correctly updates zero coupon usage
     *
     * @param mixed $customerId
     * @param mixed $couponId
     *
     * @return void 
     */
    public function updateCustomerCouponTimesUsed($customerId, $couponId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select();
        $select->from($this->getMainTable(), array('times_used'))
                ->where('coupon_id = :coupon_id')
                ->where('customer_id = :customer_id');

        $timesUsed = $read->fetchOne($select, array(':coupon_id' => $couponId, ':customer_id' => $customerId));

        if (is_numeric($timesUsed)) { // Fix is here
            $this->_getWriteAdapter()->update(
                $this->getMainTable(),
                array(
                    'times_used' => $timesUsed + 1
                ),
                array(
                    'coupon_id = ?' => $couponId,
                    'customer_id = ?' => $customerId,
                )
            );
        } else {
            $this->_getWriteAdapter()->insert( 
                $this->getMainTable(),
                array(
                    'coupon_id' => $couponId,
                    'customer_id' => $customerId,
                    'times_used' => 1
                )
            );
        }
    }
}
