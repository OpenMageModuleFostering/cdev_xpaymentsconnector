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
 * Customer account controller for user cards
 */

class Cdev_XPaymentsConnector_CustomerController extends Mage_Core_Controller_Front_Action
{
    /**
     * Check customer authentication
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        parent::preDispatch();

        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }

        return $this;
    }

    /**
     * Display list of customer's cards
     *
     * @return void
     */
    public function usercardsAction()
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $block = $this->getLayout()->getBlock('xpaymentsconnector_customer_usercards_list');
        $block->setRefererUrl($this->_getRefererUrl());
        
        $headBlock = $this->getLayout()->getBlock('head');
        $headBlock->setTitle(Mage::helper('xpaymentsconnector')->__('My Payment Cards'));
        
        $this->renderLayout();
    }

    /**
     * Remove card action
     * 
     * @return void
     */
    public function removecardAction()
    {
        $request = $this->getRequest()->getPost();

        $result = false;

        if (!empty($request['card'])) {

            $items = Mage::getModel('xpaymentsconnector/usercards')
                ->getCollection()
                ->addFieldToFilter('xp_card_id', array('in' => $request['card']));

            foreach ($items as $item) {

                if (Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD == $item->getUsageType()) {

                    $recurringProfile = Mage::getModel('sales/recurring_profile')->load($item->getData('txnId'), 'reference_id');

                    if (Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE == $recurringProfile->getState()) {

                        $message = Mage::helper('xpaymentsconnector')->__(
                            'You can\'t delete %s card. Because this is recurring card and this recurring(s) is still active.',
                            $item->getXpCardId()
                        );

                        Mage::getSingleton('customer/session')->addError($errorMessage);
                     
                        continue;
                    }
                } 
   
                $item->delete();
                $result = true;
           }

            if ($result) {
                Mage::getSingleton('customer/session')->addSuccess('Credit cards have been removed successfully.');
            }
        }

        $this->_redirect('xpaymentsconnector/customer/usercards');
    }

    /**
     * Add new card action
     *
     * @return void
     */
    public function cardaddAction()
    {
        try {

            $customer = $this->getCustomer();

            $this->loadLayout();

            $block = $this->getLayout()->getBlock('xpaymentsconnector_customer_usercards_add');

            $response = Mage::helper('settings_xpc')->getZeroAuthMethod()
                ->obtainToken(null, $customer);

            if (!$response->getStatus()) {

                $error = !empty($response->getErrorMessage())
                    ? $response->getErrorMessage()
                    : 'Unable to obtain token from X-Payments';

                $block->setError($error);

            } else {

                $fields = array(
                    'target' => 'main',
                    'action' => 'start',
                    'token'  => $response->getField('token'),
                    'allow_save_card' => 'Y',
                );

                Mage::getSingleton('customer/session')->setData('xpc_fields', $fields);
            }

            $this->renderLayout();

        } catch (Exception $e) {

            Mage::getSingleton('customer/session')->addError($this->__($e->getMessage()));

            $this->_redirect('xpaymentsconnector/customer/usercards');
        }
    }

    /**
     * Iframe action
     *
     * @return void
     */
    public function iframeAction()
    {
        $block = $this->getLayout()->createBlock('xpaymentsconnector/customer_usercards_iframe');

        try {

            $fields = Mage::getSingleton('customer/session')->getData('xpc_fields');
            $block->setFields($fields);

        } catch (Exception $e) {

            $block->setError($e->getMessage());
        }

        echo $block->toHtml();
        exit;
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

            Mage::getSingleton('customer/session')->addSuccess($this->__('Payment card saved'));

        } else {

            Mage::getSingleton('customer/session')->addError($this->__(
                'Payment gateway reported about successful authorization, but the store is unable to receive the payment token. '
                . 'Please contact the store admnistrator.'
            ));
        }

        $block = $this->getLayout()->createBlock('xpaymentsconnector/customer_usercards_iframe');
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
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        if (!$customer->getId()) {
            throw new Exception('Wrong customer reference.');
        }

        return $customer;
    }
}
