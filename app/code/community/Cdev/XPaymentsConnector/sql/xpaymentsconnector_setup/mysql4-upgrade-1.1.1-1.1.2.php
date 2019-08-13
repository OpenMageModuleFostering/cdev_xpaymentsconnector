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
 *  Add columns for storage 'x-payment callback' data;
 *  Update card data table by additional params;
 *  Update 'add x-payment card'  functionality.
 */

$installer = $this;

$quoteTable = $installer->getTable('sales/quote');


$installer->getConnection()->addColumn($quoteTable, 'xp_callback_approve', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'Response for approve callback from X-payment server in serialize format'
));

$installer->getConnection()->addColumn($quoteTable, 'xp_card_data', array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'User card data in serialize format'
));

$userCardTable = $installer->getTable('xpayment_user_cards');

$installer->getConnection()
    ->addColumn($userCardTable,'first6',array(
        'nullable' => true,
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length' => '6',
        'comment' => 'The first 6 digits of the payment card'
    ));


$installer->getConnection()->addColumn($userCardTable,'expire_month',array(
        'nullable' => true,
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length' => '2',
        'comment' => 'Expiration month of the payment card'
    ));

$installer->getConnection()->addColumn($userCardTable,'expire_year',array(
        'nullable' => true,
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length' => '5',
        'comment' => 'Expiration year of the payment card'
    ));

$installer->getConnection()->changeColumn($userCardTable, 'last_4_cc_num', 'last_4_cc_num', array(
    'nullable' => true,
    'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => '4',
    'comment' => 'The last 4 digits of the payment card'
));


// add attribute to customer entity for 'add x-payment card' functionality
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$setup->removeAttribute('catalog_product','xp_callback_approve');

$setup->addAttribute('customer', 'xp_buffer', array(
    'input'         => 'text',
    'type'          => 'text',
    'label'         => 'Responses from X-payment server in serialize format',
    'visible'       => 0,
    'required'      => 0,
    'user_defined' => 1,
));

$setup->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'xp_buffer'
);

$installer->endSetup();
