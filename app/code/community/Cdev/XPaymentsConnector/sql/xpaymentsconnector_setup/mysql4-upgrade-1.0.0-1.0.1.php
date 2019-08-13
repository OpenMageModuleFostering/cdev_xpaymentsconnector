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
 * Used to store saved customer payment cards
 */

$installer = $this;
$installer->startSetup();

$installer->run("
CREATE TABLE {$this->getTable('xpayment_user_cards')} (
    `xp_card_id` int(11) unsigned NOT NULL auto_increment,
    `user_id` int(11) NOT NULL default 0,
    `txnId` varchar(255) NOT NULL default '',
    `last_4_cc_num` varchar(4) NOT NULL default '',
    `card_type` varchar(255) NOT NULL default '',
     PRIMARY KEY (xp_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->endSetup();
