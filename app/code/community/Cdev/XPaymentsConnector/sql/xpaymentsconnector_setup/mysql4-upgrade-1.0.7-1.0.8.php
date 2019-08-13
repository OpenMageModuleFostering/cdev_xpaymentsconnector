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
 *  Adding  columns for 'count of failed transactions during one cycle' and
 * 'Date of success transaction'
 */

$installer = $this;

$installer->startSetup();

$table = $installer->getTable('sales/recurring_profile');

$installer->getConnection()->addColumn($table,
    "xp_cycle_failure_count", "smallint(5) NULL DEFAULT '0' COMMENT 'Count of failure transaction in cycle'");

$installer->getConnection()->addColumn($table,
    "xp_success_transaction_date", "timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Date of success transaction'");

$installer->getConnection()->addColumn($table,
    "xp_count_success_transaction", "int(11) NULL DEFAULT '0' COMMENT 'Count of success transaction'");


$installer->endSetup();