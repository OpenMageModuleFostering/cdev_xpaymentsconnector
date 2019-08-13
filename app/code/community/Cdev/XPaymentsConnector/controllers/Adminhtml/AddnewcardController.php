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
 *
 * Class Cdev_XPaymentsConnector_Adminhtml_AddnewcardController
 */
class Cdev_XPaymentsConnector_Adminhtml_AddnewcardController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check if pay for order is allowed for user
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/xpaymentsconnector/payment_cards');
    }

    /**
     * Default action
     *
     * @return void
     */
    public function indexAction()
    {
        try {

            $customer = $this->getCustomer();

            $customer->setXpBufer(true);

            $this->loadLayout();

            $block = $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_usercards_add');

            $block->setCustomer($customer);
            $block->setAddressId($this->getRequest()->getParam('address_id'));

            $response = Mage::helper('settings_xpc')->getZeroAuthMethod()
                ->obtainToken(null, $customer);

            $customer->setXpBufer(false);

            if (!$response->getStatus()) {

                $error = !empty($response->getErrorMessage())
                    ? $response->getErrorMessage()
                    : 'Unable to obtain token from X-Payments';

                $block->setError($error);

            } else {

                $fields = Mage::getSingleton('admin/session')->getData('xpc_fields');

                if (!is_array($fields)) {
                    $fields = array();
                }

                $fields[$this->getCustomer()->getId()] = array(
                    'target' => 'main',
                    'action' => 'start',
                    'token'  => $response->getField('token'),
                    'allow_save_card' => 'Y',
                );

                Mage::getSingleton('admin/session')->setData('xpc_fields', $fields);
            }

            $this->_addContent($block);
    
            $this->renderLayout();

        } catch (Exception $e) {

            $this->_getSession()->addError($this->__($e->getMessage()));

            $this->_redirect(
                '*/customer/edit', 
                array(
                    'id'  => $this->getRequest()->getParam('customer_id'),
                    'tab' => 'customer_edit_tab_usercards',
                )
            );
        }
    }

    /**
     * Iframe action
     *
     * @return void
     */
    public function iframeAction()
    {
        $block = $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_usercards_iframe');

        try {

            $fields = Mage::getSingleton('admin/session')->getData('xpc_fields');
            $fields = $fields[$this->getCustomer()->getId()];

            $block->setFields($fields);

        } catch (Exception $e) {

            $block->setError($e->getMessage());
        }

        echo $block->toHtml();
        exit;
    }

    /**
     * Validate Form Key
     *
     * @return bool
     */
    protected function _validateFormKey()
    {
        $result = parent::_validateFormKey();

        if ($this->getRequest()->getParam('action') == 'return') {

            // This is necessary to allow POST request on return, which doesn't contain form key
            $result = true;
        }

        return $result;
    }

    /**
     * Return action
     *
     * @return void
     */
    public function returnAction()
    {
        // If were here, then everything is OK, the transaction was authorized by the gateway.
        // But we need to make sure that the card was saved by the store successfully.

        $isCardSaved = Mage::getModel('xpaymentsconnector/usercards')
            ->checkSavedCard(
                $this->getRequest()->getParam('customer_id'),
                $this->getRequest()->getParam('txnId')
            );

        if ($isCardSaved) {

            $this->_getSession()->addSuccess($this->__('Payment card saved'));

        } else {

            $this->_getSession()->addError($this->__(
                'Payment gateway reported about successful authorization, but the store is unable to receive the payment token. '
                . 'Please check the settings in X-Payments and in the payment gateway backend.'
            ));
        }

        $block = $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_usercards_iframe');
        $block->setReturnFlag(true);

        echo $block->toHtml();
        exit;
    }
    
    /**
     * Get current customer profile
     *
     * @return Mage_Customer_Model_Customer
     */
    private function getCustomer()
    {
        $customer = Mage::getModel('customer/customer')->load(
            $this->getRequest()->getParam('customer_id')
        );

        if (!$customer->getId()) {
            throw new Exception('Wrong customer reference.');
        }

        return $customer;
    }
}
