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
 * Used to store all payment methods derived from the account of "X-Payments" server
 */

$installer = $this;
$installer->startSetup();
 
$installer->run("
CREATE TABLE {$this->getTable('xpayment_configurations')} (
    `confid` int(6) NOT NULL default 0,
    `name` varchar(255) NOT NULL default '',
    `module` varchar(255) NOT NULL default '',
    `auth_exp` int(11) NOT NULL default 0,
    `capture_min` decimal(12,2) NOT NULL default '0.00',
    `capture_max` decimal(12,2) NOT NULL default '0.00',
    `hash` char(32) NOT NULL default '',
    `is_auth` char(1) NOT NULL default '',
    `is_capture` char(1) NOT NULL default '',
    `is_void` char(1) NOT NULL default '',
    `is_refund` char(1) NOT NULL default '',
    `is_part_refund` char(1) NOT NULL default '',
    `is_accept` char(1) NOT NULL default '',
    `is_decline` char(1) NOT NULL default '',
    `is_get_info` char(1) NOT NULL default '',
    `is_enabled` char(1) NOT NULL default '',
    PRIMARY KEY (confid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE {$this->getTable('sales_flat_order')} ADD xpc_txnid varchar(32) NOT NULL default '';

");

$installer->endSetup();
