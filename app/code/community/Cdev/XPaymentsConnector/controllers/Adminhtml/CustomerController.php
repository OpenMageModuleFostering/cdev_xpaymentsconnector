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
 * Customer admin controller
 */
require_once 'Mage/Adminhtml/controllers/CustomerController.php';

class Cdev_XPaymentsConnector_Adminhtml_CustomerController extends Mage_Adminhtml_CustomerController
{
    /**
     * Check if X-Payments Connector configuration is allowed for user
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        $result = parent::_isAllowed();

        $action = $this->getRequest()->getParam('action');

        if (
            $result 
            && ('usercards' == $action || 'cardsMassDelete' == $action)
        ) {
            // Check role permissions for the XPC actions
            $result = Mage::getSingleton('admin/session')->isAllowed('system/xpaymentsconnector/payment_cards');
        }

        return $result;
    }

    /**
     * Display cards list
     *
     * @return void
     */
    public function usercardsAction()
    {
        echo $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_customer_edit_tab_usercards')->toHtml();
    }

    /**
     * Delete cards
     *
     * @return void
     */
    public function cardsMassDeleteAction()
    {
        $ids = $this->getRequest()->getPost('ids');

        $items = Mage::getModel('xpaymentsconnector/usercards')
            ->getCollection()
            ->addFieldToFilter('xp_card_id', array('in' => $ids));

        foreach($items as $item) {
            $item->delete();
        }
    }
}
