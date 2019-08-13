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
 * Block for customer payment cards list (frontend)
 * Customer account page, "My Payment Cards" tab
 */

class Cdev_XPaymentsConnector_Block_Customer_Usercards extends Mage_Core_Block_Template
{
    public function getUserCreditCardsList(){
        if($customer = Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerData = Mage::getSingleton('customer/session')->getCustomer();
            $customerId =  $customerData->getId();
            $userCardsCollection = Mage::getModel('xpaymentsconnector/usercards')
                ->getCollection()
                ->addFilter('user_id',$customerId)
                ->addFilter('usage_type',Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD);
            return  $userCardsCollection;
        }else{
            return array();
        }

    }

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $session = Mage::getSingleton('customer/session');
        $purchased = Mage::getResourceModel('xpaymentsconnector/usercards_collection')
            ->addFieldToFilter('user_id', $session->getCustomerId())
            ->addFieldToFilter('usage_type', array('in' =>
                array(
                    Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD,
                    Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD
                )
              )
            )
            ->addOrder('xp_card_id', 'desc');
        $this->setPurchased($purchased);
        $this->setItems($purchased);

    }

    /**
     * Enter description here...
     *
     * @return Mage_Downloadable_Block_Customer_Products_List
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'xpaymentsconnector.customer.cards.pager')
            ->setCollection($this->getItems());
        $this->setChild('pager', $pager);
        $this->getItems()->load();
        foreach ($this->getItems() as $item) {
            $item->setPurchased($this->getPurchased()->getItemById($item->getPurchasedId()));
        }
        return $this;
    }


    /**
     * Enter description here...
     *
     * @return string
     */
    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {
            return $this->getRefererUrl();
        }
        return $this->getUrl('customer/account/');
    }


    public function getAddCardUrl(){
        return $this->getUrl('xpaymentsconnector/customer/cardadd');
    }

    /**
     * @return array
     */
    public function getCardsUsageOptions(){
        $cardUsageOptions = Mage::getModel("xpaymentsconnector/usercards")->getCardsUsageOptions();
        return $cardUsageOptions;
    }

}
