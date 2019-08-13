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
 * Adminhtml customer X-Payments 'Payment cards' tab
 */

class Cdev_XPaymentsConnector_Block_Adminhtml_Usercards_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('xpaymentsconnector/usercards_collection')
            ->addFieldToFilter('user_id', $this->getRequest()->getParam('id'));
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    public function __construct(){
        parent::__construct();
        $this->setId('xp_card_id');
        $this->setDefaultSort('xp_card_id', 'desc');
        $this->setUseAjax(true);
    }


    /**
     * Configure grid mass actions
     *
     * @return Mage_Adminhtml_Block_Promo_Quote_Edit_Tab_Coupons_Grid
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('xp_card_id');
        $this->getMassactionBlock()->setFormFieldName('ids');
        $this->getMassactionBlock()->setUseAjax(true);
        $this->getMassactionBlock()->setHideFormElement(true);

        $this->getMassactionBlock()->addItem('delete', array(
            'label'=> Mage::helper('adminhtml')->__('Delete'),
            'url'  => $this->getUrl('*/*/cardsMassDelete', array('_current' => true)),
            'confirm' => Mage::helper('salesrule')->__('Are you sure you want to delete the selected card(s)?'),
            'complete' => 'refreshUsercardsGrid'
        ));

        return $this;
    }

    /**
     * Add columns to grid
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */

    protected function _prepareColumns()
    {
        $collection = Mage::getModel('xpaymentsconnector/usercards')->getCollection()->addFieldToSelect('card_type')->distinct(true);
        $cardTypes = $collection->getColumnValues('card_type');
        $cardsTypeOptions = array_combine($cardTypes,$cardTypes);

        $cardUsageOptions = Mage::getModel('xpaymentsconnector/usercards')->getCardsUsageOptions();

        $this->addColumn('xp_card_id', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('Card id'),
            'index'             => 'xp_card_id',
            'type'              => 'text',
            'width'             => '1',
        ));

        $this->addColumn('txnId', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('X-Payments order url'),
            'index'             => 'txnId',
            'type'              => 'text',
            'renderer'          => 'xpaymentsconnector/adminhtml_customer_edit_renderer_txnid',
            'escape'            => true
        ));

        $this->addColumn('card_number', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('Card number'),
            'type'              => 'text',
            'width'             => '1',
            'renderer'          => 'xpaymentsconnector/adminhtml_customer_edit_renderer_cardnumber',
            'escape'            => true,

        ));

        $this->addColumn('card_type', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('Card type'),
            'index'             => 'card_type',
            'type'              => 'options',
            'escape'            => true,
            'renderer'          => 'xpaymentsconnector/adminhtml_customer_edit_renderer_cardtype',
            'options'           => $cardsTypeOptions
        ));

        $this->addColumn('usage_type', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('Usage card type'),
            'index'             => 'usage_type',
            'type'              => 'options',
            'escape'            => true,
            'width'             => '1',
            'options'           => $cardUsageOptions
        ));

        $this->addColumn('amount', array(
            'header'            => Mage::helper('xpaymentsconnector')->__('Amount'),
            'type'              => 'price',
            'currency_code'     => (string)Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE),
            'index'             => 'amount',
        ));

        return parent::_prepareColumns();
    }


}
