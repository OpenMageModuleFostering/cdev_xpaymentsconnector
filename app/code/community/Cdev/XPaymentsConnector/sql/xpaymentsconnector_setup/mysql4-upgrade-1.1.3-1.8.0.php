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

/**
 * Used to store saved customer payment cards
 */

$installer = $this;
$installer->startSetup();

$paymentConfTable = $installer->getTable('xpayment_configurations');

$installer->getConnection()
    ->addColumn($paymentConfTable, 'can_save_cards', array(
        'nullable' => false,
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => '1',
        'comment'  => 'Possible to use for recharges or not'
    ));

$installer->getConnection()
    ->addColumn($paymentConfTable, 'save_cards', array(
        'nullable' => false,
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => '1',
        'comment'  => 'Use for recharges or not'
    ));

$installer->getConnection()
    ->addColumn($paymentConfTable, 'currency', array(
        'nullable' => false,
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => '3',
        'comment'  => 'Currency ISO code'
    ));

$installer->getConnection()
    ->addColumn($paymentConfTable, 'active', array(
        'nullable' => false,
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => '1',
        'comment'  => 'Is payment configuration active'
    ));

$installer->getConnection()
    ->addColumn($paymentConfTable, 'payment_method_data', array(
        'nullable' => false,
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'   => '65536',
        'comment'  => 'Payment method data'
    ));

$fraudCheckDataTable = $this->getTable('xpayment_fraud_check_data');

$installer->getConnection()->modifyColumn($fraudCheckDataTable, 'errors', 'TEXT NOT NULL DEFAULT ""');
$installer->getConnection()->modifyColumn($fraudCheckDataTable, 'warnings', 'TEXT NOT NULL DEFAULT ""');
$installer->getConnection()->modifyColumn($fraudCheckDataTable, 'rules', 'TEXT NOT NULL DEFAULT ""');
$installer->getConnection()->modifyColumn($fraudCheckDataTable, 'data', 'TEXT NOT NULL DEFAULT ""');

$quoteXpcDataTable = $installer->getTable('xpaymentsconnector/quote_xpcdata');

$installer->getConnection()->addColumn($quoteXpcDataTable, 'backend_orderid', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'comment' => 'Backend order ID'
));

$installer->getConnection()->addColumn($quoteXpcDataTable, 'xpc_slot', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'comment' => 'Slot index of the XPC payment method'
));

$installer->getConnection()->dropColumn($quoteXpcDataTable, 'payment_method_code');

$usercardsTable = $installer->getTable('xpaymentsconnector/usercards');

$installer->getConnection()->addColumn($usercardsTable, 'confid', array(
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'comment' => 'Payment configuration ID'
));

$quoteTable = $installer->getTable('sales/quote');

$installer->getConnection()->dropColumn($quoteTable, 'xp_callback_approve');

$paymentMethodTable = $installer->getTable('sales_flat_order_payment');

$installer->run('UPDATE ' . $paymentMethodTable . ' SET method = "xpayments1" WHERE method = "xpayments"');

$configTable = $installer->getTable('core_config_data');

$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/active" WHERE path = "payment/xpayments/active"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/title" WHERE path = "payment/xpayments/title"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/sort_order" WHERE path = "payment/xpayments/sort_order"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/use_authorize" WHERE path = "payment/xpayments/use_authorize"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/use_initialfee_authorize" WHERE path = "payment/xpayments/use_initialfee_authorize"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/allowspecific" WHERE path = "payment/xpayments/allowspecific"');
$installer->run('UPDATE ' . $configTable . ' SET path = "payment/xpayments1/specificcountry" WHERE path = "payment/xpayments/specificcountry"');

$installer->run('UPDATE ' . $configTable . ' SET path = "xpaymentsconnector/settings/xpay_use_iframe" WHERE path = "payment/xpayments/use_iframe"');
$installer->run('UPDATE ' . $configTable . ' SET path = "xpaymentsconnector/settings/xpay_iframe_place" WHERE path = "payment/xpayments/placedisplay"');

$installer->endSetup();
