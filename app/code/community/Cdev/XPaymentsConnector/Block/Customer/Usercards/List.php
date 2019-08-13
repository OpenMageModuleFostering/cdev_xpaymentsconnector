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
 * Block for customer payment cards list (frontend)
 * Customer account page, "My Payment Cards" tab
 */
class Cdev_XPaymentsConnector_Block_Customer_Usercards_List extends Mage_Core_Block_Template
{
    /**
     * List of cards
     */
    private $cards = null;

    /**
     * Card types/usage options
     */
    private $cardUsageTypes = array();

	/**
	 * Get customer model
	 *
     * @return Mage_Customer_Model_Customer
     */
    private function getCustomer()
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }	

    /**
     * Wrapper for getItems() method
     *
     * @return array
     */
    public function getCards()
    {
        if (is_null($this->cards)) {
            $this->cards = $this->getItems();
        }

        return $this->cards;
    }

    /**
     * Constructor. Prepare user cards list
     *
     * @return Mage_Core_Block_Template 
     */
    public function __construct()
    {
        parent::__construct();

        $usageTypeCondition = array(
            'in' => array(
                Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD,
                Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD
             )
        );

        $items = Mage::getResourceModel('xpaymentsconnector/usercards_collection')
            ->addFieldToFilter('user_id', $this->getCustomer()->getId())
            ->addFieldToFilter('usage_type', $usageTypeCondition)
            ->addOrder('xp_card_id', 'desc');

        $this->setItems($items);

        $this->cardUsageTypes = Mage::getModel("xpaymentsconnector/usercards")
            ->getCardsUsageOptions();

        return $this;
    }

    /**
     * Prepare layout
     *
     * @return Mage_Core_Block_Template
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'xpaymentsconnector.customer.cards.pager')
            ->setCollection($this->getItems());

        $this->setChild('pager', $pager);

        $this->getItems()->load();

        return $this;
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormUrl()
    {
        $params = array(
            '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
        );

        return Mage::getUrl('xpaymentsconnector/customer/removecard', $params);
    }

    /**
     * Get "Back" URL 
     *
     * @return string
     */
    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {

            $url = $this->getRefererUrl();

        } else {

            $params = array(
                '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
            );

            $url = Mage::getUrl('customer/account/', $params);            
        }

        return $url;
    }

    /**
     * Get Add new card URL
     *
     * @return string
     */
    public function getAddCardUrl()
    {
        $params = array(
            '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
        );

        return Mage::getUrl('xpaymentsconnector/customer/cardadd', $params);
    }

    /**
     * Get human-readable card string (type, number, expire date)
     *
     * @param $card Cdev_XPaymentsConnector_Model_Usercards Card model
     *
     * @return string
     */
    public function getCardString($card)
    {
        return Mage::helper('xpaymentsconnector')->prepareCardDataString($card->getData()); 
    }

    /** 
     * Get human-readable card usage type
     *
     * @param $card Cdev_XPaymentsConnector_Model_Usercards Card model 
     *
     * @return string
     */
    public function getCardUsageType($card)
    {
        return $this->cardUsageTypes[$card->getUsageType()];
    }
}
