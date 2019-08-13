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
 * @author     Valerii Demidov
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer account controller
 */

class Cdev_XPaymentsConnector_CustomerController extends Mage_Core_Controller_Front_Action
{

    /**
     * Check customer authentication
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $action = $this->getRequest()->getActionName();
        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }

    /**
     * Display downloadable links bought by customer
     *
     */
    public function usercardsAction()
    {
        $request = $this->getRequest()->getPost();
        if (!empty($request)) {
            if ($request["action"] == "remove") {
                if (count($request["card"]) > 0) {

                    $itemCollection = Mage::getModel("xpaymentsconnector/usercards")
                        ->getCollection()
                        ->addFieldToFilter('xp_card_id', array('in' => $request["card"]));
                    foreach ($itemCollection as $item) {
                        if ($item->getUsageType() == Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD) {
                            $txnId = $item->getData("txnId");
                            $recurringProfile = Mage::getModel('sales/recurring_profile')->load($txnId,"reference_id");
                            if($recurringProfile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE){
                                $errorMessage = Mage::helper("xpaymentsconnector")->__("You can't delete %s card. Because this is recurring card and this recurring is still active.",$item->getXpCardId());
                                Mage::getSingleton("customer/session")->addError($errorMessage);
                                continue;
                            }
                        }
                        $item->delete();
                    }
                    $message = $this->__('Credit cards have been removed successfully.');
                    Mage::getSingleton("customer/session")->addSuccess($message);
                } else {
                    $message = $this->__('You have not selected any credit card.');
                    Mage::getSingleton("customer/session")->addError($message);
                }
            }
        }


        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        if ($block = $this->getLayout()->getBlock('xpaymentsconnector_customer_usercards')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle(Mage::helper('xpaymentsconnector')->__('My Payment Cards'));
        }
        $this->renderLayout();
    }


    public function cardaddAction(){

        $isPostResponse = $this->getRequest()->isPost();
        if($isPostResponse){
            // Check request data
            $request = $this->getRequest()->getPost();

            if (empty($request)) {
                Mage::throwException('Request doesn\'t contain POST elements.');
            }

            // check txn id
            if (empty($request['txnId'])) {
                Mage::throwException('Missing or invalid transaction ID');
            }

            $CCPaymentModel = Mage::getModel("xpaymentsconnector/payment_cc");
            $transactionStatusLabel =  $CCPaymentModel->getTransactionStatusLabels();
            $resultMessage = "";

            list($status, $response) = $CCPaymentModel->requestPaymentInfo($request['txnId']);

            if (
                !$status
                || !in_array($response['status'], array($CCPaymentModel::AUTH_STATUS, $CCPaymentModel::CHARGED_STATUS))
            ) {
                $errorMessage = $this->__("Transaction status is '%s'. Card authorization has been cancelled.",$transactionStatusLabel[$response['status']]);
                Mage::getSingleton("customer/session")->addError($errorMessage);
                $resultMessage = "Card authorization has been cancelled.";
            }else{
                // save user card
                Mage::getSingleton("checkout/session")->setData("user_card_save",true);
                $newCardData = $CCPaymentModel->saveUserCard($request);
                Mage::getSingleton("checkout/session")->unsetData("user_card_save");
                $resultMessage = $this->__("Payment card has been added successfully!");

                Mage::getSingleton("customer/session")->addSuccess( $this->__("You created card number '%s'. Transaction status is '%s'.",$newCardData->getData("xp_card_id"),$transactionStatusLabel[$response['status']]));
            }

            $this->getResponse()->setBody(
                $this->getLayout()
                    ->createBlock('xpaymentsconnector/customer_success')->setData("result_message",$resultMessage)
                    ->toHtml()
            );
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        if ($block = $this->getLayout()->getBlock('xpaymentsconnector_customer_cardadd')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle(Mage::helper('xpaymentsconnector')->__('Add new credit card to list (X-Payments)'));
        }
        $this->renderLayout();
    }

}
