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
 * Used for generate unique order keys for "X-Payments" server
 * Also used for temporary storage of data received from the "X-Payments"
 */

$installer = $this;
$installer->startSetup();

$installer->run("
CREATE TABLE {$this->getTable('xpayment_prepare_order')} (
    `prepare_order_id` int(11) unsigned NOT NULL auto_increment,
    `quote_id` int(10) unsigned not null,
    `payment_response` text NOT NULL,
     PRIMARY KEY (prepare_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

");

$installer->endSetup();
