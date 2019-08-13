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
 * Used to store saved customer payment cards
 */

$installer = $this;
$installer->startSetup();

$installer->run("
CREATE TABLE {$this->getTable('xpayment_quote_xpcdata')} (
    `data_id` int(11) unsigned NOT NULL auto_increment,    
    `quote_id` int(11) NOT NULL default 0,
	`payment_method_code` varchar(255) NOT NULL,
	`txn_id` varchar(255) NOT NULL,
	`token` varchar(255) NOT NULL,
    `address_saved` BOOL default false,
    `recurring_order_id` int(11) NOT NULL default 0,
    `recurring_profile_id` int(11) NOT NULL default 0,
    `xpc_message` TEXT,
	`checkout_data` TEXT,
     PRIMARY KEY (`data_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->run("
CREATE TABLE {$this->getTable('xpayment_fraud_check_data')} (
    `data_id` int(11) unsigned NOT NULL auto_increment,
    `order_id` int(11) NOT NULL default 0,
    `code` varchar(255) NOT NULL,
    `service` varchar(255) NOT NULL,
    `result` int(11) NOT NULL,
    `status` varchar(255) NOT NULL,
    `score` int(11) NOT NULL,
    `message` varchar(255) NOT NULL,
    `transaction_id` varchar(255) NOT NULL,
    `url` varchar(255) NOT NULL,
    `errors` varchar(255) NOT NULL,
    `warnings` varchar(255) NOT NULL,
    `rules` varchar(255) NOT NULL,
    `data` varchar(255) NOT NULL,
     PRIMARY KEY (data_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->endSetup();
