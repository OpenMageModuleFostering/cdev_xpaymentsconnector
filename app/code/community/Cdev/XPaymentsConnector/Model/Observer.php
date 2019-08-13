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
 * Class Cdev_XPaymentsConnector_Model_Observer
 */

class Cdev_XPaymentsConnector_Model_Observer extends Mage_CatalogInventory_Model_Observer
{
    protected $_current_customer_id = null;

    public function preDispatchCheckout($observer)
    {

        Mage::getSingleton('checkout/session')->unsetData('user_card_save');

        //unset x-payment form place settings
        $unsetParams = array('place_display');
        $xpHelper = Mage::helper('xpaymentsconnector');
        $xpHelper->unsetXpaymentPrepareOrder($unsetParams);

        //set recurring product discount
        $issetRecurrnigProduct = $xpHelper->checkIssetRecurringOrder();
        if ($issetRecurrnigProduct['isset']) {
            $xpHelper->setRecurringProductDiscount();
        }

    }

    public function paymentMethodIsActive($observer)
    {
        $event = $observer->getEvent();
        $method = $event->getMethodInstance();
        $result = $event->getResult();
        $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();
        $prepaidpayments = Mage::getModel('xpaymentsconnector/payment_prepaidpayments')->getCode();

        if (($method->getCode() == $saveCardsPaymentCode) || ($method->getCode() == $prepaidpayments)) {
            $quote = $event->getQuote();
            if ($quote) {
                $customerId = $quote->getData('customer_id');
                $isBalanceCard = Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD;
                if ($method->getCode() == $prepaidpayments) {
                    $isBalanceCard = Cdev_XPaymentsConnector_Model_Usercards::BALANCE_CARD;
                }
                if ($customerId) {
                    $cardsCount = Mage::getModel('xpaymentsconnector/usercards')
                        ->getCollection()
                        ->addFieldToFilter('user_id', $customerId)
                        ->addFieldToFilter('usage_type', $isBalanceCard)
                        ->count();
                    if ($cardsCount == 0) {
                        $result->isAvailable = false;
                    }
                } else {
                    $result->isAvailable = false;
                }
            }
        }
    }


    /**
     * Dispatch: checkout_type_onepage_save_order_after
     * @param $observer
     */

    public function updateOrder($observer)
    {
        $xpHelper = Mage::helper('xpaymentsconnector');
        $event = $observer->getEvent();
        $order = $event->getOrder();

        $checkoutSession = Mage::getSingleton('checkout/session');
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $paymentCcModel = Mage::getModel('xpaymentsconnector/payment_cc');
        $xpaymentPaymentCode = $paymentCcModel->getCode();
        $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();
        $useIframe = Mage::helper('xpaymentsconnector')->isUseIframe();
        $quote = $observer->getQuote();

        $noticeHelper = $xpHelper->getFailureCheckoutNoticeHelper();

        if ($paymentMethod == $saveCardsPaymentCode) {

            $order->setCanSendNewEmailFlag(false);
            $paymentCardNumber = NULL;
            $grandTotal = NULL;
            $cardData = NULL;
            $paymentCardNumber = $quote->getPayment()->getData('xp_payment_card');
            $cardData = Mage::getModel('xpaymentsconnector/usercards')->load($paymentCardNumber);

            $response = $paymentCcModel->sendAgainTransactionRequest($order->getId(),$paymentCardNumber,$grandTotal,$cardData);

            if ($response['success']) {
                $result = $paymentCcModel->updateOrderByXpaymentResponse($order->getId(), $response['response']['transaction_id']);
                if (!$result['success']) {
                    $checkoutSession->addError($result['error_message']);
                    $checkoutSession->addNotice($noticeHelper);
                }
            } else {
                $checkoutSession->addError($response['error_message']);
                $checkoutSession->addNotice($noticeHelper);
            }

        }


    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function orderSuccessAction($observer)
    {
        Mage::helper('xpaymentsconnector')->unsetXpaymentPrepareOrder();
    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function postdispatchAdminhtmlSalesOrderCreateSave($observer)
    {
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $incrementId = $quote->getData('reserved_order_id');
        $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $admSession = Mage::getSingleton('adminhtml/session');
        $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();
        $prepaidpayments = Mage::getModel('xpaymentsconnector/payment_prepaidpayments')->getCode();

        if ($paymentMethod == $saveCardsPaymentCode) {
            $orderId = $order->getId();
            $grandTotal = $quote->getData('grand_total');
            $this->adminhtmlSendSaveCardsPaymentTransaction($orderId,$grandTotal);
        } elseif ($paymentMethod == $prepaidpayments) {
            $xpPrepaidPaymentsCard = $admSession->getData('xp_prepaid_payments');
            $currentUserCard = Mage::getModel('xpaymentsconnector/usercards')->load($xpPrepaidPaymentsCard);
            $txnid = $currentUserCard->getData('txnId');
            $lastTransId = $txnid;

            $order->setData('xpc_txnid',$txnid);
            $order->getPayment()->setTransactionId($txnid);
            $api = Mage::getModel('xpaymentsconnector/payment_cc');
            list($status, $response) = $api->requestPaymentInfo($txnid,false,true);

            if($status){
                $currentTransaction = end($response['transactions']);
                $lastTransId = $currentTransaction['txnid'];
            }

            $order->getPayment()->setLastTransId($lastTransId);
            $order->setData('xp_card_data',serialize($currentUserCard->getData()));
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                (bool)Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage::helper('xpaymentsconnector')->__('Customer has been redirected to X-Payments.')
            );
            $order->save();
        }
        $admSession->unsetData('xp_payment_card');
        $admSession->unsetData('xp_prepaid_payments');

    }


    /**
     * Send transaction to X-Payment for 'order edit' event
     * @param Varien_Event_Observer $observer
     */
    public function postdispatchAdminhtmlSalesOrderEditSave($observer)
    {
        $adminhtmlSessionQuote = Mage::getSingleton('adminhtml/session_quote');
        $parentOrder = $adminhtmlSessionQuote->getOrder();
        $incrementId = $parentOrder->getRelationChildRealId();

        if($incrementId){
            $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
            $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
            $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();

            if ($paymentMethod == $saveCardsPaymentCode && $order) {
                $orderId = $order->getId();
                $grandTotal = $order->getGrandTotal();
                $parentOrderGrandTotal = $parentOrder->getGrandTotal();

                if($grandTotal > $parentOrderGrandTotal){
                    $checkOrderAmount = false;
                    $recalculcateGrandTotal = $grandTotal-$parentOrderGrandTotal;
                    $this->adminhtmlSendSaveCardsPaymentTransaction($orderId,$recalculcateGrandTotal,$checkOrderAmount);
                }
            }
        }
    }

    /**
     * Send transaction from the admin panel
     * @param int $orderId
     * @param float $grandTotal
     * @param bool $checkOrderAmount
     */
    protected function adminhtmlSendSaveCardsPaymentTransaction($orderId,$grandTotal,$checkOrderAmount = true)
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $xpCreditCards = $admSession->getData('xp_payment_card');

        $response = Mage::getModel('xpaymentsconnector/payment_cc')->sendAgainTransactionRequest($orderId, $xpCreditCards, $grandTotal);
        if ($response['success']) {
            Mage::getModel('xpaymentsconnector/payment_cc')->updateOrderByXpaymentResponse($orderId, $response['response']['transaction_id'],$checkOrderAmount);
        } else {
            Mage::getSingleton('adminhtml/session')->addError($response['error_message']);
        }

    }

    public function predispatchAdminhtmlSalesOrderCreateSave($observer)
    {
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $paymentMethod = $quote->getPayment()->getMethodInstance()->getCode();
        $prepaidpayments = Mage::getModel('xpaymentsconnector/payment_prepaidpayments')->getCode();
        if ($paymentMethod == $prepaidpayments) {
            $admSession = Mage::getSingleton('adminhtml/session');
            $xpPrepaidPaymentsCard = $admSession->getData('xp_prepaid_payments');
            $currentUserCard = Mage::getModel('xpaymentsconnector/usercards')->load($xpPrepaidPaymentsCard);
            $grandTotal = $quote->getData('grand_total');
            $cardAmount = $currentUserCard->getAmount();
            if ($cardAmount < $grandTotal) {
                $errorMessage = Mage::helper('xpaymentsconnector')
                    ->__("You can't make an order using card (**%s) worth over %s",
                        $currentUserCard->getData('last_4_cc_num'),
                        Mage::helper('core')->currency($cardAmount));
                $admSession->addError($errorMessage);
                /*redirect to last page*/
                Mage::app()->getResponse()->setRedirect($_SERVER['HTTP_REFERER']);
                Mage::app()->getResponse()->sendResponse();
                exit;
            } else {
                $cardBalance = $cardAmount - $grandTotal;
                if ($cardBalance == 0) {
                    $currentUserCard->delete();
                } else {
                    $currentUserCard->setAmount($cardBalance)->save();
                }

            }
        }
    }

    public function adminhtmlSavePaymentCard()
    {
        $payment = Mage::app()->getRequest()->getPost('payment');
        $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();
        $prepaidpayments = Mage::getModel('xpaymentsconnector/payment_prepaidpayments')->getCode();

        if ($payment) {
            if ($payment['method'] == $saveCardsPaymentCode) {
                if ($payment['xp_payment_card']) {
                    $admSession = Mage::getSingleton('adminhtml/session');
                    $admSession->setData('xp_payment_card', $payment['xp_payment_card']);
                }
            } elseif ($payment['method'] == $prepaidpayments) {
                if ($payment['xp_prepaid_payments']) {
                    $admSession = Mage::getSingleton('adminhtml/session');
                    $admSession->setData('xp_prepaid_payments', $payment['xp_prepaid_payments']);
                }
            }
        }
    }

    public function unsetXpaymentSelectedCard()
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $admSession->unsetData('xp_payment_card');
        $admSession->unsetData('xp_prepaid_payments');
    }

    public function orderInvoiceSaveBefore($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        if (Mage::helper('xpaymentsconnector')->isXpaymentsMethod($paymentCode)) {
            $txnid = $order->getData('xpc_txnid');
            $invoice->setTransactionId($txnid);
        }
    }

    public function invoiceVoid($observer)
    {
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        if (Mage::helper('xpaymentsconnector')->isXpaymentsMethod($paymentCode)) {
            $data = array(
                'txnId' => $order->getData('xpc_txnid'),
                'amount' => number_format($invoice->getGrandTotal(), 2, '.', ''),
            );
            Mage::getModel('xpaymentsconnector/payment_cc')->authorizedTransactionRequest('void', $data);
        }
    }

    public function createOrdersByCustomerSubscriptions($observer)
    {
        $xpaymentsHelper = Mage::helper('xpaymentsconnector');
        $recurringProfileList = Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToFilter('state', array('neq' => Mage_Sales_Model_Recurring_Profile::STATE_CANCELED));

        $userTransactionHasBeenSend = false;
        if ($recurringProfileList->getSize() > 0) {
            foreach ($recurringProfileList as $profile) {
                $customerId = $profile->getCustomerId();
                if($this->_current_customer_id != $customerId){
                    $this->_current_customer_id = $customerId;
                    $userTransactionHasBeenSend = false;
                }

                if(!$userTransactionHasBeenSend){
                    if ($profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_PENDING) {
                            $cardData = $xpaymentsHelper->getProfileOrderCardData($profile);
                            $orderAmountData = $xpaymentsHelper->preparePayDeferredOrderAmountData($profile);
                            if(!empty($orderAmountData)){
                                $xpaymentsHelper->resendPayDeferredRecurringTransaction($profile, $orderAmountData, $cardData);
                                $userTransactionHasBeenSend = true;
                                continue;
                            }else{
                                $profile->activate();
                            }
                    }

                    if ($profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE) {
                        $startDateTime = strtotime($profile->getStartDatetime());
                        $lastSuccessTransactionDate = strtotime($profile->getXpSuccessTransactionDate());
                        $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;

                        $profilePeriodValue = $xpaymentsHelper->getCurrentBillingPeriodTimeStamp($profile);
                        $newTransactionDate = $lastActionDate + $profilePeriodValue;

                        $currentDateObj = new Zend_Date(time());
                        $currentDateStamp = $currentDateObj->getTimestamp();

                        /*
                        var_dump('period_stamp ='.$profilePeriodValue / 3600 ."(h)",
                                 "current = ".date("Y-m-d H:m:s",$currentDateStamp),
                                 "start = ".date("Y-m-d H:m:s",$startDateTime),
                                 "last = ".date("Y-m-d H:m:s",$lastActionDate),
                                 "new = ".date("Y-m-d H:m:s",$newTransactionDate),
                                 "profile_id = ".$profile->getProfileId());die;
                        */

                        $timePassed = $currentDateStamp - $lastActionDate;
                        $currentSuccessCycles = $profile->getXpCountSuccessTransaction();

                        $isFirstRecurringProfileProcess = (($currentSuccessCycles == 0) && ($startDateTime < $currentDateStamp))
                            ? true : false;

                        if (($timePassed >= $profilePeriodValue) || $isFirstRecurringProfileProcess) {
                            // check by count of success transaction
                            $periodMaxCycles = $profile->getPeriodMaxCycles();

                            if (($periodMaxCycles > $currentSuccessCycles) || is_null($periodMaxCycles)) {
                                $orderItemInfo = $profile->getData('order_item_info');
                                if (!is_array($orderItemInfo)) {
                                    $orderItemInfo = unserialize($orderItemInfo);
                                }

                                $initialFeeAmount = $orderItemInfo['recurring_initial_fee'];

                                $isFirstRecurringOrder = false;

                                //calculate grand total
                                $billingAmount  =  $profile->getBillingAmount();
                                $shippingAmount =  $profile->getShippingAmount();
                                $taxAmount = $profile->getTaxAmount();

                                $initialFeeTax = ($profile->getInitiaFeePaid()) ? 0 : 123;
                                // add discount for transaction amount
                                $useDiscount = ($profile->getXpCountSuccessTransaction() > 0) ? false : true;
                                $discountAmount = ($useDiscount) ? $orderItemInfo['discount_amount'] : 0;

                                $transactionAmount = $billingAmount + $shippingAmount + $taxAmount - $discountAmount ;

                                $orderId = $xpaymentsHelper->createOrder($profile,$isFirstRecurringOrder);
                                $cardData = $xpaymentsHelper->getProfileOrderCardData($profile);

                                $response = Mage::getModel('xpaymentsconnector/payment_cc')->sendAgainTransactionRequest($orderId, NULL, $transactionAmount , $cardData);
                                $userTransactionHasBeenSend = true;
                                if ($response['success']) {
                                    $result = Mage::getModel('xpaymentsconnector/payment_cc')->updateOrderByXpaymentResponse($orderId, $response['response']['transaction_id']);
                                    $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $result['success'], $newTransactionDate);
                                    if (!$result['success']) {
                                        Mage::log($result['error_message'], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                                    }

                                } else {
                                    $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $response['success'], $newTransactionDate);
                                    Mage::log($response['error_message'], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                                }

                            } else {
                                // Subscription is completed
                                $profile->finished();

                            }
                        }
                    }
                }
            }

        }


    }

    /**
     * Add redirect for buying recurring product by xpayments method(without iframe)
     * @param $observer
     */
    public function addRedirectForXpaymentMethod($observer)
    {
        $profiles = $observer->getData('recurring_profiles');
        if (!empty($profiles)) {
            $profile = current($profiles);
            $currentPaymentMethodCode = $profile->getData('method_code');
            $xpaymentPaymentCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();
            $useIframe = Mage::helper('xpaymentsconnector')->isUseIframe();
            if (($currentPaymentMethodCode == $xpaymentPaymentCode) && !$useIframe) {
                $redirectUrl = Mage::getUrl('xpaymentsconnector/processing/redirect', array('_secure' => true));
                Mage::getSingleton('checkout/session')->setRedirectUrl($redirectUrl);
            }

        }

    }

    /**
     * Set discount for recurring product(for ajax cart item quantity update)
     * Remove X-Payments token
     * Update initial Fee for recurring product;
     * @param $observer
     */
    public function updateCartItem($observer)
    {
        $unsetParams = array('token');
        $xpHelper = Mage::helper('xpaymentsconnector');
        $xpHelper->unsetXpaymentPrepareOrder($unsetParams);

        //set recurring product discount
        Mage::helper('xpaymentsconnector')->setRecurringProductDiscount();

        // update InitAmount for recurring products
        $cart = $observer->getCart('quote');
        foreach ($cart->getAllVisibleItems() as $item){
            $product = $item->getProduct();
            if ((bool)$product->getIsRecurring()) {
                $profile = Mage::getModel('payment/recurring_profile')
                    ->setLocale(Mage::app()->getLocale())
                    ->setStore(Mage::app()->getStore())
                    ->importProduct($product);
                if($profile->getInitAmount()){
                    // duplicate as 'additional_options' to render with the product statically
                    $infoOptions = array(array(
                        'label' => $profile->getFieldLabel('start_datetime'),
                        'value' => $profile->exportStartDatetime(true),
                    ));

                    foreach ($profile->exportScheduleInfo($item) as $info) {
                        $infoOptions[] = array(
                            'label' => $info->getTitle(),
                            'value' => $info->getSchedule(),
                        );
                    }

                    $itemOptionModel = Mage::getModel('sales/quote_item_option')
                        ->getCollection()
                        ->addItemFilter($item->getId())
                        ->addFieldToFilter('code','additional_options')
                        ->addFieldToFilter('product_id',$product->getId())
                        ->getFirstItem();

                    $itemOptionModel->setValue(serialize($infoOptions));
                    $itemOptionModel->save();
                }
            }


        }
        //

    }

    /**
     * Remove X-Payments token;
     * Update initial Fee for recurring product;
     * @param $observer
     */
    public function checkoutCartAdd($observer)
    {
        $xpHelper = Mage::helper('xpaymentsconnector');
        $unsetParams = array('token');
        $xpHelper->unsetXpaymentPrepareOrder($unsetParams);
    }

    /**
     * Remove X-Payments token and prepare order number.
     * @param $observer
     */
    public function postdispatchCartDelete($observer)
    {
        $unsetParams = array('token', 'prepare_order_id');
        Mage::helper('xpaymentsconnector')->unsetXpaymentPrepareOrder($unsetParams);
    }


    /**
     * Set 'place_display' flag for feature x-payment form.
     * @param $observer
     */
    public function predispatchSaveShippingMethod()
    {
        Mage::helper('xpaymentsconnector')->setIframePlaceDisplaySettings();
    }

    /**
     * Remove X-Payments token after update shipping method
     * @param $observer
     */
    public function postdispatchSaveShippingMethod($observer)
    {
        $xpHelper = Mage::helper('xpaymentsconnector');
        $isPaymentPlaceDisplayFlag = $xpHelper->isIframePaymentPlaceDisplay();
        if (!$isPaymentPlaceDisplayFlag) {
            $unsetParams = array('token');
            $xpHelper->unsetXpaymentPrepareOrder($unsetParams);
        }
    }

    public function postDispatchSavePayment($observer)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $paymentMethodCode = $quote->getPayment()->getMethodInstance()->getCode();
        $xpaymentPaymentCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();
        $xpHelper = Mage::helper('xpaymentsconnector');

        if ($paymentMethodCode == $xpaymentPaymentCode) {
            $saveCard = Mage::app()->getRequest()->getPost('savecard');
            if ($saveCard) {
                Mage::getSingleton('checkout/session')->setData('user_card_save', $saveCard);
            }
        } else {
            Mage::getSingleton('checkout/session')->unsetData('user_card_save');
        }
    }

    /**
     * Set discount for recurring product
     * @param $observer
     */
    public function preDispatchCartIndex($observer)
    {
        $unsetXpPrepareOrder = Mage::app()->getRequest()->getParam('unset_xp_prepare_order');
        $xpHelper = Mage::helper('xpaymentsconnector');
        if (isset($unsetXpPrepareOrder)) {
            $unsetParams = array('token');
            $xpHelper->unsetXpaymentPrepareOrder($unsetParams);
        }

        //set recurring product discount
        $xpHelper->setRecurringProductDiscount();

    }


    /**
     * Send xp transaction from 'XP Order State tab'
     * @param $observer
     */
    public function adminhtmlSalesOrderView($observer){
        $xpTransactionData = Mage::app()->getRequest()->getPost();
        if(!empty($xpTransactionData)){

            if(!empty($xpTransactionData)
                && isset($xpTransactionData['xpaction'])
                && isset($xpTransactionData['xpc_txnid'])
                && isset($xpTransactionData['transaction_amount'])
            ){

                $xpaymentModel = Mage::getModel('xpaymentsconnector/payment_cc');
                $data = array(
                    'txnId' => $xpTransactionData['xpc_txnid'],
                    'amount' => number_format($xpTransactionData['transaction_amount'], 2, '.', ''),
                );
                $result = array();
                switch ($xpTransactionData['xpaction']) {
                    case 'refund':
                        $result = $xpaymentModel->authorizedTransactionRequest('refund', $data);
                        break;
                    case 'capture':
                        $result = $xpaymentModel->authorizedTransactionRequest('capture', $data);
                        break;
                    case 'void':
                        $result = $xpaymentModel->authorizedTransactionRequest('void', $data);
                        break;
                }

                if(empty($result['error_message'])){
                    $message =   Mage::helper('xpaymentsconnector')->__("Transaction '%s' to order (%s)  was successful!",
                    $xpTransactionData['xpaction'],
                    $xpTransactionData['orderid']);
                    Mage::getSingleton('adminhtml/session')->addSuccess($message);
                } else{
                    Mage::getSingleton('adminhtml/session')->addError($result['error_message']);
                }

            }
        }
    }

}
