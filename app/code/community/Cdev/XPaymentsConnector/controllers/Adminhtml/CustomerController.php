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
 * Customer admin controller
 */

require_once 'Mage/Adminhtml/controllers/CustomerController.php';

class Cdev_XPaymentsConnector_Adminhtml_CustomerController extends Mage_Adminhtml_CustomerController
{
        public function usercardsAction(){
            echo $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_customer_edit_tab_usercards')->toHtml();
        }

       public function cardsMassDeleteAction(){
           $xpCardIds = $this->getRequest()->getPost('ids');
           $itemCollection = Mage::getModel("xpaymentsconnector/usercards")
               ->getCollection()
               ->addFieldToFilter('xp_card_id', array('in' => $xpCardIds));
           foreach($itemCollection as $item) {
               $item->delete();
           }

       }
}
