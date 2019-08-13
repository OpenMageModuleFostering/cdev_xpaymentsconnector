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
 * "Use saved credit cards (X-Payments)" form block
 */

class Cdev_XPaymentsConnector_Block_Form_Savedcards extends Mage_Payment_Block_Form
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/form/savedcards.phtml');
    }

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
            return false;
        }

    }


    public function getAdminhtmlUserCreditCardsList(){
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $customerId = $quote->getData('customer_id');

        if($customerId){
            $userCardsCollection = Mage::getModel('xpaymentsconnector/usercards')
                ->getCollection()
                ->addFilter('user_id',$customerId)
                ->addFilter('usage_type',Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD);
            return  $userCardsCollection;
        }
        else{
            return false;
        }

    }


}
