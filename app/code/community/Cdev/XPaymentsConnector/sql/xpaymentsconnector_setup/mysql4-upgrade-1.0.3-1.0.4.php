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
 * Add additional order status for all "X-Payments" without invoices
 */

$installer = $this;
$connection = $installer->getConnection();
$installer->startSetup();

$status = Mage::getModel('sales/order_status');
$status->setStatus('xp_pending_payment');
$status->setLabel('X-Payments Pending Payment');
$status->assignState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
$status->save();

$installer->endSetup();